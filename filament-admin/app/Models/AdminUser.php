<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Golden Tor staff account for the Filament admin panel.
 * Deliberately separate from the public customer accounts in the `users` table.
 */
class AdminUser extends Authenticatable implements FilamentUser
{
    use Notifiable;

    protected $table = 'admin_users';

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
