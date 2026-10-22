<?php
declare(strict_types=1);

// Compare the installed framework's actual routes against the Apache rules.
require getcwd() . '/vendor/autoload.php';
$app = require getcwd() . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$rules = file_get_contents($argv[1]);
if (!preg_match('~^RewriteRule (\S+) admin-panel/public/index\.php~m', $rules, $match)) {
    throw new RuntimeException('Missing Laravel rewrite rule');
}
$rewrite = '~' . $match[1] . '~';
if (!preg_match('/<If "%\{REQUEST_URI\} =~ m#([^#]+)#">/', $rules, $match)) {
    throw new RuntimeException('Missing admin security-header scope');
}
$headerScope = '~' . $match[1] . '~';
$checked = 0;
foreach (Illuminate\Support\Facades\Route::getRoutes() as $route) {
    $uri = $route->uri();
    if (!str_starts_with($uri, 'livewire') && !preg_match('~^admin(?:/|$)~', $uri)) {
        continue;
    }
    if (!preg_match($rewrite, $uri) || !preg_match($headerScope, '/' . $uri)) {
        throw new RuntimeException('Apache does not route/protect framework endpoint: ' . $uri);
    }
    $checked++;
}
if ($checked === 0) {
    throw new RuntimeException('No framework routes checked');
}
foreach (['admin-panel/.env', 'admin.html', 'api/health', 'livewire-invalid/update'] as $uri) {
    if (preg_match($rewrite, $uri)) {
        throw new RuntimeException('Admin rewrite intercepts unrelated path: ' . $uri);
    }
}
echo "PASS: {$checked} installed admin/Livewire routes match Apache routing and security headers.\n";
