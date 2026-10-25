<?php
/*
 * Makes Laravel's Illuminate\Support\Number usable without the "intl" extension (the UNAS
 * host has none). Inserts an "if intl is missing, use App\Support\IntlFallback" guard at
 * the top of format(), percentage() and currency(). With intl loaded nothing changes.
 * Idempotent; fails the build if the framework source no longer looks as expected.
 * Run from the Laravel project root after every composer install/update.
 */

const MARKER = '/* goldentor-intl-fallback */';
$file = 'vendor/laravel/framework/src/Illuminate/Support/Number.php';
$source = file_get_contents($file);
if ($source === false) {
    fwrite(STDERR, "cannot read {$file}\n");
    exit(1);
}
if (str_contains($source, MARKER)) {
    echo "Number.php already patched\n";
    exit(0);
}

$methods = [
    'format' => ['number', 'precision', 'maxPrecision', 'locale'],
    'percentage' => ['number', 'precision', 'maxPrecision', 'locale'],
    'currency' => ['amount', 'in', 'locale', 'precision'],
];

foreach ($methods as $method => $parameters) {
    $pattern = '/(public static function ' . $method . '\(([^)]*)\)[^{]*\{)/';
    if (!preg_match($pattern, $source, $match)) {
        fwrite(STDERR, "patch-number-intl: method {$method}() not found\n");
        exit(1);
    }
    foreach ($parameters as $parameter) {
        if (!preg_match('/\$' . $parameter . '\b/', $match[2])) {
            fwrite(STDERR, "patch-number-intl: {$method}() has no \${$parameter} parameter: {$match[2]}\n");
            exit(1);
        }
    }
    $arguments = $method === 'currency'
        ? '$amount, $in !== \'\' ? $in : static::$currency, $locale ?? static::$locale, $precision'
        : '$number, $precision, $maxPrecision, $locale ?? static::$locale';
    $guard = "\n        " . MARKER . "\n        if (! extension_loaded('intl')) {\n"
        . "            return \\App\\Support\\IntlFallback::{$method}({$arguments});\n        }\n";
    $source = preg_replace_callback($pattern, fn (array $found) => $found[1] . $guard, $source, 1, $count);
    if ($count !== 1) {
        fwrite(STDERR, "patch-number-intl: could not patch {$method}()\n");
        exit(1);
    }
}

file_put_contents($file, $source);
exec('php -l ' . escapeshellarg($file), $output, $status);
if ($status !== 0) {
    fwrite(STDERR, "patch-number-intl: patched file has syntax errors\n" . implode("\n", $output) . "\n");
    exit(1);
}
echo "patched {$file} (intl fallback)\n";
