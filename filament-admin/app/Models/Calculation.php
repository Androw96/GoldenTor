<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Calculation extends Model
{
    protected $table = 'calculations';

    public $timestamps = false;

    protected $casts = [
        'inputs_json' => 'array',
        'results_json' => 'array',
        'created_at' => 'datetime',
    ];

    public const TYPES = [
        'investment' => 'Vagyonépítés',
        'loan' => 'Hiteltervezés',
        'reserve' => 'Védelmi tartalék',
    ];

    public const TYPE_COLORS = [
        'investment' => 'success',
        'loan' => 'warning',
        'reserve' => 'info',
    ];

    /** Human labels for every stored input/result key (see calculator.js). */
    public const FIELD_LABELS = [
        'capital' => 'Kezdőtőke', 'monthly' => 'Havi megtakarítás', 'rate' => 'Éves kamat / hozam (%)', 'years' => 'Időtáv (év)',
        'total' => 'Végösszeg', 'paid' => 'Befizetés', 'growth' => 'Növekmény',
        'amount' => 'Hitelösszeg', 'payment' => 'Havi törlesztő', 'cost' => 'Kamatteher',
        'expense' => 'Havi alapkiadás', 'dependants' => 'Eltartottak', 'months' => 'Biztonsági időszak (hó)', 'current' => 'Jelenlegi tartalék',
        'target' => 'Javasolt tartalék', 'coverage' => 'Jelenlegi fedezet (hó)', 'gap' => 'Még felépítendő',
    ];

    /** Fields that are not forint amounts. */
    private const PLAIN_FIELDS = ['rate', 'years', 'dependants', 'months', 'coverage'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'user_id');
    }

    /** The headline number the customer saw (same choice as account.js). */
    public function headlineValue(): float
    {
        $results = $this->results_json ?? [];
        $key = match ($this->calculator_type) {
            'loan' => 'payment',
            'reserve' => 'target',
            default => 'total',
        };

        return (float) ($results[$key] ?? 0);
    }

    /** @return array<string, string> label => formatted value */
    public static function formatFields(?array $values): array
    {
        $formatted = [];
        foreach ($values ?? [] as $key => $value) {
            $label = self::FIELD_LABELS[$key] ?? $key;
            $formatted[$label] = in_array($key, self::PLAIN_FIELDS, true)
                ? rtrim(rtrim(number_format((float) $value, 2, ',', ' '), '0'), ',')
                : number_format((float) $value, 0, ',', ' ') . ' Ft';
        }

        return $formatted;
    }
}
