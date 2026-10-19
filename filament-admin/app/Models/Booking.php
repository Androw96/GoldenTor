<?php

namespace App\Models;

use App\Support\LegacyMailer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $table = 'bookings';

    public $timestamps = false;

    protected $fillable = [
        'name', 'email', 'phone', 'topic', 'meeting', 'booking_date', 'booking_time', 'message', 'status',
    ];

    protected $casts = [
        'booking_date' => 'date:Y-m-d',
        'created_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public const STATUSES = [
        'requested' => 'Igényelt',
        'confirmed' => 'Visszaigazolt',
        'completed' => 'Lezárt',
        'cancelled' => 'Lemondott',
    ];

    public const STATUS_COLORS = [
        'requested' => 'warning',
        'confirmed' => 'success',
        'completed' => 'gray',
        'cancelled' => 'danger',
    ];

    public const STATUS_ICONS = [
        'requested' => 'heroicon-m-clock',
        'confirmed' => 'heroicon-m-check-circle',
        'completed' => 'heroicon-m-archive-box',
        'cancelled' => 'heroicon-m-x-circle',
    ];

    /** Same mail wording as the public API (unas/api.php). */
    private const STATUS_MAIL_LABELS = [
        'confirmed' => 'visszaigazoltuk',
        'completed' => 'lezártnak jelöltük',
        'cancelled' => 'lemondottnak jelöltük',
    ];

    protected static function booted(): void
    {
        static::creating(function (Booking $booking): void {
            $booking->public_token = $booking->public_token ?: bin2hex(random_bytes(24));
            $booking->created_at = $booking->created_at ?: now('UTC');
            $booking->status = $booking->status ?: 'requested';
            $booking->phone = $booking->phone ?? '';
            $booking->message = $booking->message ?? '';
        });

        // Keep the unique "active_slot" column in sync exactly like the public API does.
        static::saving(function (Booking $booking): void {
            $booking->active_slot = $booking->status === 'cancelled' || ! $booking->booking_date || ! $booking->booking_time
                ? null
                : $booking->booking_date->format('Y-m-d') . ' ' . $booking->booking_time;
        });

        static::updated(function (Booking $booking): void {
            if ($booking->wasChanged('status') && isset(self::STATUS_MAIL_LABELS[$booking->status])) {
                $booking->notifyCustomerAboutStatus();
            }
        });
    }

    /** booking_time is a MySQL TIME ("14:00:00"); the panel works with "14:00". */
    protected function bookingTime(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? substr($value, 0, 5) : null,
            set: fn (?string $value) => $value ? substr($value, 0, 5) . ':00' : null,
        );
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query
            ->whereDate('booking_date', '>=', now('Europe/Budapest')->toDateString())
            ->whereIn('status', ['requested', 'confirmed']);
    }

    public function portalUrl(): string
    {
        return rtrim((string) config('goldentor.public_url'), '/') . '/ugyfelportal.html?token=' . rawurlencode((string) $this->public_token);
    }

    public function slotLabel(): string
    {
        return ($this->booking_date?->format('Y.m.d.') ?? '-') . ' ' . ($this->booking_time ?? '');
    }

    public function notifyCustomerAboutStatus(): void
    {
        $label = self::STATUS_MAIL_LABELS[$this->status] ?? null;
        if (! $label || ! $this->email) {
            return;
        }
        LegacyMailer::send(
            $this->email,
            'Golden Tor - időpont frissítve',
            "Kedves {$this->name}!\n\nA {$this->booking_date?->format('Y-m-d')} {$this->booking_time} időpontra szóló konzultációját {$label}.\n\nRészletek: {$this->portalUrl()}"
        );
    }
}
