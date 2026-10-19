<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;

class CreateAdminUser extends Command
{
    protected $signature = 'goldentor:admin {email} {--name=Admin} {--password=}';

    protected $description = 'Create or update a Golden Tor Filament admin user';

    public function handle(): int
    {
        $password = $this->option('password') ?: $this->secret('Jelszó (min. 12 karakter)');
        if (! is_string($password) || strlen($password) < 12) {
            $this->error('A jelszó legalább 12 karakter legyen.');

            return self::FAILURE;
        }

        $admin = AdminUser::query()->updateOrCreate(
            ['email' => mb_strtolower((string) $this->argument('email'))],
            ['name' => (string) $this->option('name'), 'password' => $password],
        );

        $this->info("Admin kész: {$admin->email}");

        return self::SUCCESS;
    }
}
