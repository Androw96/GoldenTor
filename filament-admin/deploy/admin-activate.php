<?php
declare(strict_types=1);

/*
 * Golden Tor – browser trigger for the Filament admin installer.
 *
 * GitHub runners can upload over FTP but are sometimes blocked by the UNAS firewall on
 * HTTPS. In that case the workflow leaves data/admin-panel.zip + unas/admin-deploy.php on
 * the server and this page starts the installer from a normal browser instead.
 *
 * It only acts on an upload younger than 6 hours, runs at most once at a time, and the
 * installer deletes the archive after a successful activation. The full JSON result is
 * stored in data/admin-deploy-result.json (not web-accessible); the page shows a summary.
 */

$root = dirname(__DIR__);
$archive = $root . '/data/admin-panel.zip';
$installer = __DIR__ . '/admin-deploy.php';
$resultFile = $root . '/data/admin-deploy-result.json';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

function activation_page(string $title, string $body, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex"><title>' . htmlspecialchars($title) . '</title><style>'
        . 'body{margin:0;padding:32px 16px;background:#141c17;color:#f8f3ee;font:15px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif}'
        . 'main{max-width:960px;margin:0 auto}h1{font-size:1.35rem}li{margin:4px 0}'
        . 'pre{white-space:pre-wrap;word-break:break-word;background:#0d120f;border:1px solid #2f4136;border-radius:10px;padding:14px;font-size:12px;max-height:60vh;overflow:auto}'
        . 'button{padding:12px 20px;border:0;border-radius:8px;background:#b8962e;color:#141c17;font:inherit;font-weight:700;cursor:pointer}'
        . '.ok{color:#4ade80}.bad{color:#f87171}</style></head><body><main><h1>' . htmlspecialchars($title) . '</h1>' . $body . '</main></body></html>';
    exit;
}

$pending = is_file($archive) && is_file($installer) && filemtime($archive) > time() - 6 * 3600;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    if (!$pending) {
        activation_page('Golden Tor admin telepítés', '<p>Nincs telepítésre váró admin kiadás.</p>', 404);
    }
    $uploaded = date('Y-m-d H:i', (int) filemtime($archive));
    activation_page('Golden Tor admin telepítés',
        '<p>Feltöltött, aktiválásra váró admin kiadás: <strong>' . htmlspecialchars($uploaded) . '</strong> (szerveridő).</p>'
        . '<p>A telepítés 1–3 percig tart. Ne zárd be az oldalt.</p>'
        . '<form method="post"><button type="submit">Telepítés indítása</button></form>');
}

if (!$pending) {
    activation_page('Golden Tor admin telepítés', '<p class="bad">Nincs telepítésre váró admin kiadás.</p>', 409);
}

$lock = fopen($root . '/data/admin-deploy.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    activation_page('Golden Tor admin telepítés', '<p class="bad">Egy telepítés már folyamatban van. Várj pár percet, majd frissítsd az oldalt.</p>', 409);
}

if (!preg_match("/const SETUP_KEY = '([^']+)';/", (string) file_get_contents($installer), $match) || str_starts_with($match[1], '__GOLDENTOR')) {
    activation_page('Golden Tor admin telepítés', '<p class="bad">A telepítő nincs megfelelően feltöltve.</p>', 500);
}

// Run the regular installer exactly as the workflow would, capturing its JSON answer.
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_GOLDENTOR_SETUP_KEY'] = $match[1];
unset($_SERVER['HTTP_X_GOLDENTOR_DEPLOY_ACTION']);

ob_start();
register_shutdown_function(function () use ($resultFile, $lock): void {
    $json = (string) ob_get_clean();
    @file_put_contents($resultFile, $json);
    if ($lock) {
        flock($lock, LOCK_UN);
    }
    $data = json_decode($json, true);
    if (!is_array($data)) {
        $fatal = error_get_last();
        activation_page('Telepítés: ismeretlen eredmény',
            '<p class="bad">A telepítő nem adott értelmezhető választ.</p><pre>'
            . htmlspecialchars(mb_substr($json, 0, 4000) . ($fatal ? "\n" . $fatal['message'] . ' @ ' . $fatal['file'] . ':' . $fatal['line'] : '')) . '</pre>', 500);
    }
    $ok = ($data['ok'] ?? false) === true;
    $problems = $data['problems'] ?? ($data['diagnostics']['problems'] ?? []);
    $list = '';
    foreach ((array) $problems as $problem) {
        $list .= '<li>' . htmlspecialchars((string) $problem) . '</li>';
    }
    activation_page($ok ? 'Telepítés kész' : 'Telepítés sikertelen',
        '<p class="' . ($ok ? 'ok' : 'bad') . '">' . ($ok ? 'Az új admin élesítve.' : htmlspecialchars((string) ($data['error'] ?? 'Hiba történt.'))) . '</p>'
        . ($list !== '' ? '<h2>Diagnosztika</h2><ul>' . $list . '</ul>' : '')
        . '<details><summary>Teljes válasz</summary><pre>' . htmlspecialchars($json) . '</pre></details>'
        . '<p><a style="color:#d6b85a" href="/admin">Tovább az adminba</a></p>', $ok ? 200 : 500);
});

require $installer;
