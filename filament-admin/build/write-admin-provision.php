<?php
declare(strict_types=1);

function required_provision_value(string $name): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        throw new RuntimeException('Missing provisioning secret: ' . $name);
    }
    return $value;
}
$email = strtolower(trim(required_provision_value('UNAS_PROVISION_ADMIN_EMAIL')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException('Invalid admin email');
}
$password = required_provision_value('UNAS_PROVISION_ADMIN_PASSWORD');
if (strlen($password) < 20) {
    throw new RuntimeException('Provisioning password must contain at least 20 characters');
}
$settings = [
    'email' => $email,
    'password_hash' => password_hash($password, PASSWORD_BCRYPT),
    'key' => required_provision_value('UNAS_SETUP_KEY'),
    'expires' => time() + 900,
];
$template = file_get_contents(__DIR__ . '/../deploy/admin-provision.php.template');
file_put_contents($argv[1], str_replace('__PROVISION_SETTINGS__', base64_encode(json_encode($settings, JSON_THROW_ON_ERROR)), $template));
chmod($argv[1], 0600);
