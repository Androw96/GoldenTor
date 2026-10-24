<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * With APP_DEBUG=false Laravel only shows "Server Error". For a logged-in admin we show
 * the real reason instead (class, message, location) so problems on the live host can be
 * reported and fixed without FTP access to the log files. Guests still get the plain page.
 */
class AdminErrorPage
{
    public static function render(Throwable $error, Request $request): ?Response
    {
        try {
            if (config('app.debug')) {
                return null;
            }
            if ($error instanceof ValidationException || $error instanceof AuthenticationException
                || $error instanceof AuthorizationException || $error instanceof TokenMismatchException
                || $error instanceof ModelNotFoundException
                || ($error instanceof HttpExceptionInterface && $error->getStatusCode() < 500)) {
                return null;
            }
            $path = ltrim($request->path(), '/');
            if (! preg_match('#^(admin|livewire)#', $path)) {
                return null;
            }
            if (! auth()->guard('web')->check()) {
                return null;
            }

            $reference = substr(sha1(microtime() . $error->getMessage()), 0, 8);
            $details = Diagnostics::describe($error);
            $trace = [];
            foreach (array_slice($error->getTrace(), 0, 12) as $frame) {
                if (! isset($frame['file'])) {
                    continue;
                }
                $trace[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $frame['file']) . ':' . ($frame['line'] ?? '?')
                    . (isset($frame['class']) ? '  ' . $frame['class'] . ($frame['type'] ?? '::') . ($frame['function'] ?? '') : '');
            }

            $e = fn (string $text) => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html = '<!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
                . '<meta name="robots" content="noindex"><title>Hiba – Golden Tor Admin</title><style>'
                . 'body{margin:0;padding:32px 16px;background:#141c17;color:#f8f3ee;font:15px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif}'
                . 'main{max-width:960px;margin:0 auto}h1{font-size:1.3rem;margin:0 0 8px}p{color:#b9c4bc;margin:0 0 18px}'
                . 'pre{white-space:pre-wrap;word-break:break-word;background:#0d120f;border:1px solid #2f4136;border-radius:10px;padding:16px;font-size:13px;color:#fde68a}'
                . 'code{color:#b8962e}a{color:#d6b85a}</style></head><body><main>'
                . '<h1>Szerverhiba (' . $e($reference) . ')</h1>'
                . '<p>Ezt az üzenetet csak bejelentkezett admin látja. Másold ki a teljes szöveget, és küldd el annak, aki az admint javítja. '
                . 'Időpont: <code>' . $e(now('Europe/Budapest')->format('Y-m-d H:i:s')) . '</code>, cím: <code>' . $e('/' . $path) . '</code></p>'
                . '<pre>' . $e($details) . "\n\n" . $e(implode("\n", $trace)) . '</pre>'
                . '<p><a href="' . $e(url('/admin')) . '">Vissza az adminba</a></p></main></body></html>';

            return response($html, 500)->header('Cache-Control', 'no-store');
        } catch (Throwable) {
            return null;
        }
    }
}
