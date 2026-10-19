<?php
/*
 * Adapts a fresh `composer create-project laravel/laravel:^10` skeleton for the Golden Tor
 * admin. Run from the skeleton root *before* copying the overlay files over it.
 * Every replacement must hit exactly once, otherwise the build stops.
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

patch('config/app.php', "App\\Providers\\RouteServiceProvider::class,", "App\\Providers\\RouteServiceProvider::class,\n        App\\Providers\\Filament\\AdminPanelProvider::class,");
patch('config/app.php', "'locale' => 'en',", "'locale' => 'hu',");
patch('config/database.php', "'migrations' => 'migrations',", "'migrations' => 'admin_migrations',");

// The default migrations would try to create a second `users` table in the shared database.
foreach (glob('database/migrations/*.php') as $migration) {
    unlink($migration);
}
foreach (['app/Models/User.php', 'database/factories/UserFactory.php', 'tests/Feature/ExampleTest.php', 'config/sanctum.php'] as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}

// Seeder in the skeleton references the removed User model in a comment only; replace it anyway.
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
