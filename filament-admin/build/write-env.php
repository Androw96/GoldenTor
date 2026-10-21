<?php
/*
 * Writes the production .env of the admin from GitHub Actions secrets.
 * Values are quoted and escaped so special characters in passwords are safe.
 */

function required(string $name): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        fwrite(STDERR, "missing secret/env: {$name}" . PHP_EOL);
        exit(1);
    }

    return $value;
}

function quote(string $value): string
{
    if (preg_match('/[\r\n]/', $value)) {
        fwrite(STDERR, 'secret values must be single-line' . PHP_EOL);
        exit(1);
    }
    // phpdotenv treats single-quoted values literally (no ${} interpolation, no escapes).
    if (strpos($value, "'") === false) {
        return "'" . $value . "'";
    }

    return '"' . str_replace('${', '\${', addcslashes($value, "\"\\")) . '"';
}

$adminKey = required('UNAS_ADMIN_KEY');
$publicUrl = getenv('GOLDENTOR_PUBLIC_URL') ?: 'https://goldentor.hu';

$values = [
    'APP_NAME' => 'Golden Tor Admin',
    'APP_ENV' => 'production',
    // Stable 32-byte key derived from the existing admin secret, so no extra secret is needed.
    'APP_KEY' => 'base64:' . base64_encode(hash('sha256', $adminKey . '|goldentor-filament-app-key', true)),
    'APP_DEBUG' => 'false',
    'APP_URL' => $publicUrl,
    'APP_LOCALE' => 'hu',
    'APP_FALLBACK_LOCALE' => 'hu',
    'ASSET_URL' => rtrim($publicUrl, '/') . '/admin-panel/public',
    'LOG_CHANNEL' => 'daily',
    'LOG_LEVEL' => 'warning',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => getenv('UNAS_DB_HOST') ?: 'localhost',
    'DB_PORT' => '3306',
    'DB_DATABASE' => required('UNAS_DB_NAME'),
    'DB_USERNAME' => required('UNAS_DB_USER'),
    'DB_PASSWORD' => required('UNAS_DB_PASSWORD'),
    'CACHE_STORE' => 'file',
    'SESSION_DRIVER' => 'file',
    'SESSION_LIFETIME' => '240',
    'SESSION_COOKIE' => 'goldentor_admin_session',
    'SESSION_SECURE_COOKIE' => str_starts_with($publicUrl, 'https://') ? 'true' : 'false',
    'QUEUE_CONNECTION' => 'sync',
    'FILESYSTEM_DISK' => 'local',
    'GOLDENTOR_ADMIN_KEY' => $adminKey,
    // A separately rotatable first-account code; the existing app encryption key stays stable.
    'GOLDENTOR_ADMIN_SETUP_KEY' => getenv('UNAS_ADMIN_SETUP_KEY') ?: $adminKey,
    'GOLDENTOR_PUBLIC_URL' => $publicUrl,
    'GOLDENTOR_MAIL_FROM' => getenv('GOLDENTOR_MAIL_FROM') ?: 'info@goldentor.hu',
    'GOLDENTOR_CONTACT_RECIPIENT' => getenv('GOLDENTOR_CONTACT_RECIPIENT') ?: 'info@goldentor.hu',
    'GOLDENTOR_MAIL_ENABLED' => 'true',
];

$lines = [];
foreach ($values as $key => $value) {
    $lines[] = $key . '=' . quote((string) $value);
}
file_put_contents($argv[1] ?? '.env', implode("\n", $lines) . "\n");
echo 'wrote ' . ($argv[1] ?? '.env') . PHP_EOL;
