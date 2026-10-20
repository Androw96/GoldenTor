<?php
/*
 * Adapts a fresh Laravel 12 skeleton for the Golden Tor Filament admin.
 * It runs before the project overlay is copied into the skeleton.
 */

function patch(string $file, string $search, string $replace): void
{
    $content = file_get_contents($file);
    if ($content === false || substr_count($content, $search) !== 1) {
        fwrite(STDERR, "patch failed: {$file}: " . json_encode($search) . PHP_EOL);
        exit(1);
    }

    file_put_contents($file, str_replace($search, $replace, $content));
    echo "patched {$file}" . PHP_EOL;
}

$providers = file_get_contents('bootstrap/providers.php');
if ($providers === false) {
    fwrite(STDERR, "cannot read bootstrap/providers.php" . PHP_EOL);
    exit(1);
}
if (! str_contains($providers, 'AdminPanelProvider::class')) {
    $needle = 'AppServiceProvider::class,';
    if (substr_count($providers, $needle) !== 1) {
        fwrite(STDERR, "patch failed: bootstrap/providers.php" . PHP_EOL);
        exit(1);
    }
    file_put_contents('bootstrap/providers.php', str_replace(
        $needle,
        $needle . "\n    App\\Providers\\Filament\\AdminPanelProvider::class,",
        $providers
    ));
    echo "patched bootstrap/providers.php" . PHP_EOL;
}
patch('config/app.php', "'locale' => env('APP_LOCALE', 'en'),", "'locale' => env('APP_LOCALE', 'hu'),");
patch('config/database.php', "'table' => 'migrations',", "'table' => 'admin_migrations',");

// The public website already owns `users` and its migrations. This admin keeps
// its own migration history and only creates the admin-specific tables.
foreach (glob('database/migrations/*.php') ?: [] as $migration) {
    unlink($migration);
}
foreach (['app/Models/User.php', 'database/factories/UserFactory.php', 'tests/Feature/ExampleTest.php'] as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}

file_put_contents('database/seeders/DatabaseSeeder.php', <<<'PHP'
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        //
    }
}
PHP);

echo "skeleton ready" . PHP_EOL;
