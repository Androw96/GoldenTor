<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $table = 'contacts';

    public $timestamps = false;

    protected $fillable = ['name', 'email', 'phone', 'topic', 'message', 'status'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public const STATUSES = [
        'new' => 'Új',
        'in_progress' => 'Folyamatban',
        'done' => 'Lezárva',
        'archived' => 'Archivált',
    ];

    public const STATUS_COLORS = [
        'new' => 'warning',
        'in_progress' => 'info',
        'done' => 'success',
        'archived' => 'gray',
    ];

    protected static function booted(): void
    {
        static::creating(function (Contact $contact): void {
            $contact->created_at = $contact->created_at ?: now('UTC');
            $contact->status = $contact->status ?: 'new';
            $contact->phone = $contact->phone ?? '';
        });
    }
}
