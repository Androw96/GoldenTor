<?php

namespace App\Support;

use App\Models\AdminUser;
use App\Models\Customer;
use Composer\InstalledVersions;
use Filament\Facades\Filament;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only health checks for the live UNAS installation: environment, legacy tables,
 * writable folders, recent log errors and an in-process render of every admin page.
 * Used by `php artisan goldentor:diagnose`, the deploy script and the "Rendszerállapot" page.
 */
class Diagnostics
{
    /** Columns the admin reads/writes in the tables shared with the public website. */
    public const TABLES = [
        'admin_users' => ['id', 'name', 'email', 'password', 'remember_token'],
        'users' => ['id', 'email', 'name', 'phone', 'password_hash', 'created_at'],
        'bookings' => ['id', 'public_token', 'created_at', 'name', 'email', 'phone', 'topic', 'meeting', 'booking_date', 'booking_time', 'message', 'status', 'active_slot', 'reminder_sent_at'],
        'contacts' => ['id', 'created_at', 'name', 'email', 'phone', 'topic', 'message', 'status'],
        'content' => ['content_key', 'language', 'title', 'payload', 'updated_at'],
        'feedback' => ['id', 'user_id', 'expert', 'rating', 'message', 'created_at'],
        'calculations' => ['id', 'user_id', 'calculator_type', 'inputs_json', 'results_json', 'created_at'],
    ];

    /** @var array<int, Throwable> */
    public static array $captured = [];

    private static bool $listening = false;

    public static function environment(): array
    {
        $result = [
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'filament' => self::packageVersion('filament/filament'),
            'livewire' => self::packageVersion('livewire/livewire'),
            'app_url' => config('app.url'),
            'locale' => config('app.locale'),
            'missing_extensions' => array_values(array_filter(
                ['intl', 'mbstring', 'pdo_mysql', 'fileinfo', 'zip', 'gd', 'openssl', 'tokenizer', 'dom'],
                fn (string $extension) => ! extension_loaded($extension)
            )),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        ];

        try {
            $connection = DB::connection();
            $result['database'] = $connection->getDriverName();
            $result['database_version'] = (string) $connection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
            if ($connection->getDriverName() === 'mysql') {
                $row = $connection->selectOne('select @@SESSION.sql_mode as sql_mode, @@SESSION.time_zone as time_zone');
                $result['sql_mode'] = $row->sql_mode ?? null;
                $result['db_time_zone'] = $row->time_zone ?? null;
            }
        } catch (Throwable $error) {
            $result['database_error'] = self::describe($error);
        }

        return $result;
    }

    /** @return array<string, array{exists: bool, rows?: int, missing_columns?: array<int, string>, error?: string}> */
    public static function tables(): array
    {
        $result = [];
        foreach (self::TABLES as $table => $columns) {
            try {
                if (! Schema::hasTable($table)) {
                    $result[$table] = ['exists' => false];
                    continue;
                }
                $present = array_map('strtolower', Schema::getColumnListing($table));
                $result[$table] = [
                    'exists' => true,
                    'rows' => DB::table($table)->count(),
                    'missing_columns' => array_values(array_diff($columns, $present)),
                ];
            } catch (Throwable $error) {
                $result[$table] = ['exists' => false, 'error' => self::describe($error)];
            }
        }

        return $result;
    }

    /** @return array<string, bool> folder (relative) => writable */
    public static function writableFolders(): array
    {
        $folders = [
            'storage/logs' => storage_path('logs'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/framework/cache/data' => storage_path('framework/cache/data'),
            'storage/app/livewire-tmp' => storage_path('app/livewire-tmp'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];
        try {
            $folders['site: assets/uploads'] = rtrim((string) config('filesystems.disks.site.root'), '/') . '/assets/uploads';
        } catch (Throwable) {
        }

        $result = [];
        foreach ($folders as $label => $path) {
            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            $result[$label] = is_dir($path) && is_writable($path);
        }

        return $result;
    }

    /**
     * Last error lines of the newest Laravel log files (first line of each entry only,
     * e-mail addresses masked, no stack traces).
     *
     * @return array<int, array{time: string, level: string, message: string}>
     */
    public static function recentErrors(int $limit = 15): array
    {
        $files = glob(storage_path('logs/laravel*.log')) ?: [];
        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        $entries = [];
        foreach (array_slice($files, 0, 3) as $file) {
            $size = (int) @filesize($file);
            $handle = @fopen($file, 'rb');
            if (! $handle) {
                continue;
            }
            if ($size > 512 * 1024) {
                fseek($handle, -512 * 1024, SEEK_END);
            }
            $content = (string) stream_get_contents($handle);
            fclose($handle);

            preg_match_all('/^\[(\d{4}-\d{2}-\d{2}[ T][\d:.+\-]+)\] [\w-]+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/m', $content, $matches, PREG_SET_ORDER);
            foreach (array_reverse($matches) as $match) {
                $entries[] = [
                    'time' => $match[1],
                    'level' => $match[2],
                    'message' => self::mask(mb_strimwidth($match[3], 0, 900, '…')),
                ];
                if (count($entries) >= $limit) {
                    break 2;
                }
            }
        }

        return $entries;
    }

    /**
     * Renders every admin page in-process as the first admin user and reports
     * HTTP status plus the exception behind any server error.
     *
     * @return array<string, array{status: int|null, error?: string}>
     */
    public static function probePages(?AdminUser $admin = null): array
    {
        $admin ??= AdminUser::query()->orderBy('id')->first();
        if (! $admin) {
            return ['_' => ['status' => null, 'error' => 'Nincs admin felhasználó – előbb az /admin/setup oldalon hozz létre egyet.']];
        }

        self::listen();
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);

        $urls = [];
        try {
            foreach ($panel->getPages() as $page) {
                $urls[class_basename($page)] = $page::getUrl();
            }
            foreach ($panel->getResources() as $resource) {
                $urls[class_basename($resource)] = $resource::getUrl('index');
            }
            $customer = Customer::query()->orderByDesc('id')->first();
            if ($customer) {
                $urls['CustomerResource:view'] = \App\Filament\Resources\CustomerResource::getUrl('view', ['record' => $customer]);
                $urls['CustomerResource:edit'] = \App\Filament\Resources\CustomerResource::getUrl('edit', ['record' => $customer]);
            }
        } catch (Throwable $error) {
            return ['_' => ['status' => null, 'error' => 'URL-ek nem állíthatók elő: ' . self::describe($error)]];
        }

        $kernel = app(HttpKernel::class);
        $results = [];
        foreach ($urls as $label => $url) {
            self::$captured = [];
            try {
                auth()->guard('web')->setUser($admin);
                $request = Request::create($url, 'GET', server: ['HTTP_ACCEPT' => 'text/html']);
                $response = $kernel->handle($request);
                $kernel->terminate($request, $response);
                $status = $response->getStatusCode();
                $results[$label] = ['status' => $status];
                if ($status >= 500) {
                    $results[$label]['error'] = self::$captured
                        ? self::describe(end(self::$captured))
                        : 'Ismeretlen hiba (nincs rögzített kivétel).';
                }
            } catch (Throwable $error) {
                $results[$label] = ['status' => null, 'error' => self::describe($error)];
            }
        }

        return $results;
    }

    public static function describe(Throwable $error): string
    {
        $file = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $error->getFile());
        $where = $file . ':' . $error->getLine();

        // Point at the first frame in our own code when the error surfaced inside a package.
        if (str_contains($file, 'vendor' . DIRECTORY_SEPARATOR)) {
            foreach ($error->getTrace() as $frame) {
                $frameFile = $frame['file'] ?? '';
                if ($frameFile !== '' && ! str_contains($frameFile, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)
                    && str_starts_with($frameFile, base_path())) {
                    $where .= ' (saját kód: ' . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $frameFile) . ':' . ($frame['line'] ?? '?') . ')';
                    break;
                }
            }
        }

        $message = get_class($error) . ': ' . mb_strimwidth($error->getMessage(), 0, 900, '…') . ' @ ' . $where;
        if ($error->getPrevious()) {
            $message .= ' ← ' . get_class($error->getPrevious()) . ': ' . mb_strimwidth($error->getPrevious()->getMessage(), 0, 400, '…');
        }

        return self::mask($message);
    }

    public static function mask(string $text): string
    {
        return (string) preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '***@***', $text);
    }

    public static function report(bool $probe = true): array
    {
        $report = [
            'environment' => self::safely(fn () => self::environment()),
            'tables' => self::safely(fn () => self::tables()),
            'writable' => self::safely(fn () => self::writableFolders()),
            'recent_errors' => self::safely(fn () => self::recentErrors()),
        ];
        if ($probe) {
            $report['pages'] = self::safely(fn () => self::probePages());
        }

        return $report;
    }

    private static function listen(): void
    {
        if (self::$listening) {
            return;
        }
        self::$listening = true;
        $handler = app(ExceptionHandler::class);
        if (method_exists($handler, 'reportable')) {
            $handler->reportable(function (Throwable $error): void {
                self::$captured[] = $error;
            });
        }
    }

    private static function safely(callable $check): mixed
    {
        try {
            return $check();
        } catch (Throwable $error) {
            return ['error' => self::describe($error)];
        }
    }

    private static function packageVersion(string $package): ?string
    {
        try {
            return class_exists(InstalledVersions::class) ? InstalledVersions::getPrettyVersion($package) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
