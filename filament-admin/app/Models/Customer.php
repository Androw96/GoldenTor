<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Public website account (fiok.html). Lives in the legacy `users` table.
 */
class Customer extends Model
{
    protected $table = 'users';

    public $timestamps = false;

    protected $fillable = ['name', 'email', 'phone'];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Customer $customer): void {
            $customer->created_at = $customer->created_at ?: now('UTC');
            $customer->phone = $customer->phone ?? '';
        });
    }

    public function calculations(): HasMany
    {
        return $this->hasMany(Calculation::class, 'user_id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class, 'user_id');
    }
}
