<?php
declare(strict_types=1);

const GOLDENTOR_ROOT = __DIR__ . '/..';

function config(): array {
    static $config;
    if ($config !== null) {
        return $config;
    }
    $path = __DIR__ . '/config.php';
    if (!is_file($path)) {
        http_response_code(503);
        exit('A szolgáltatás még nincs konfigurálva.');
    }
    $config = require $path;
    return $config;
}

function db(): PDO {
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }
    $database = config()['database'];
    $connection = new PDO(
        "mysql:host={$database['host']};dbname={$database['name']};charset=utf8mb4",
        $database['user'],
        $database['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    return $connection;
}

function start_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('goldentor_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => (bool) preg_match('#^https://#', config()['public_url']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function now(): string {
    return gmdate('Y-m-d H:i:s');
}

function json_response(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_json(): array {
    $payload = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Érvénytelen JSON kérés.');
    }
    return $payload;
}

function clean_text(mixed $value, int $limit, bool $required = true): string {
    $value = preg_replace('/\s+/u', ' ', trim((string) ($value ?? '')));
    if ($required && $value === '') {
        throw new InvalidArgumentException('Kötelező mező hiányzik.');
    }
    if (mb_strlen($value) > $limit) {
        throw new InvalidArgumentException('Az egyik mező túl hosszú.');
    }
    return $value;
}

function current_user(): ?array {
    start_session();
    if ((int) ($_SESSION['expires_at'] ?? 0) < time()) {
        $_SESSION = [];
        session_destroy();
        return null;
    }
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id) && !ctype_digit((string) $id)) {
        return null;
    }
    $statement = db()->prepare('SELECT id,name,email,phone FROM users WHERE id=?');
    $statement->execute([(int) $id]);
    return $statement->fetch() ?: null;
}

function require_user(): array {
    $user = current_user();
    if (!$user) {
        json_response(['error' => 'A folytatáshoz jelentkezzen be.'], 401);
    }
    return $user;
}

function require_same_origin(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $expected = rtrim(config()['public_url'], '/');
    if (($_SERVER['HTTP_X_GOLDENTOR_REQUEST'] ?? '') !== '1' || ($origin !== '' && !hash_equals($expected, $origin))) {
        json_response(['error' => 'Érvénytelen kérés eredete.'], 403);
    }
}

function rate_limited(string $scope, int $maximum = 12): bool {
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $key = hash('sha256', $scope . '|' . $ip);
    $statement = db()->prepare(
        'INSERT INTO rate_limits(rate_key,window_started,request_count) VALUES(?,UTC_TIMESTAMP(),1) '
        . 'ON DUPLICATE KEY UPDATE request_count=IF(window_started < UTC_TIMESTAMP() - INTERVAL 10 MINUTE,1,request_count+1), '
        . 'window_started=IF(window_started < UTC_TIMESTAMP() - INTERVAL 10 MINUTE,UTC_TIMESTAMP(),window_started)'
    );
    $statement->execute([$key]);
    $statement = db()->prepare('SELECT request_count FROM rate_limits WHERE rate_key=?');
    $statement->execute([$key]);
    return (int) $statement->fetchColumn() > $maximum;
}

function admin_authorized(): bool {
    $supplied = (string) ($_SERVER['HTTP_X_ADMIN_KEY'] ?? '');
    return $supplied !== '' && hash_equals((string) config()['admin_key'], $supplied);
}

function require_admin(): void {
    if (!admin_authorized()) {
        header('WWW-Authenticate: Basic realm="Golden Tor Admin"');
        json_response(['error' => 'Admin jogosultság szükséges.'], 401);
    }
}

function send_mail(string $subject, string $body, string $recipient): void {
    $from = (string) config()['mail_from'];
    @mail($recipient, $subject, $body, "From: {$from}\r\nContent-Type: text/plain; charset=UTF-8");
}
