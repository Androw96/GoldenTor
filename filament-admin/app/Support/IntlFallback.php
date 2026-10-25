<?php

namespace App\Support;

/**
 * Number formatting without the PHP "intl" extension.
 *
 * The UNAS host runs PHP without intl, and Laravel's Number::format() (used by every
 * Filament table for pagination and counts) throws without it. The build patches
 * Number::format/percentage/currency to call this class only when intl is missing;
 * with intl installed Laravel's own implementation runs unchanged.
 */
class IntlFallback
{
    public static function format(int|float $number, ?int $precision = null, ?int $maxPrecision = null, ?string $locale = null): string
    {
        $natural = self::decimalsIn($number);
        if ($precision !== null) {
            $decimals = max(0, $precision);
        } elseif ($maxPrecision !== null) {
            $decimals = min(max(0, $maxPrecision), $natural);
        } else {
            // NumberFormatter::DECIMAL shows at most three fraction digits by default.
            $decimals = min(3, $natural);
        }

        [$decimalSeparator, $thousandsSeparator] = self::separators($locale);
        $formatted = number_format((float) $number, $decimals, $decimalSeparator, $thousandsSeparator);

        if ($precision === null && $decimals > 0) {
            // Rounding may leave trailing zeros (e.g. 1.2999 -> 1.300); drop them like intl does.
            $formatted = rtrim(rtrim($formatted, '0'), $decimalSeparator);
        }

        return $formatted;
    }

    public static function percentage(int|float $number, int $precision = 0, ?int $maxPrecision = null, ?string $locale = null): string
    {
        $value = $maxPrecision !== null
            ? self::format($number, null, $maxPrecision, $locale)
            : self::format($number, $precision, null, $locale);

        return $value . (self::language($locale) === 'en' ? '%' : "\u{00A0}%");
    }

    public static function currency(int|float $amount, string $in = '', ?string $locale = null, ?int $precision = null): string
    {
        $currency = strtoupper($in !== '' ? $in : 'HUF');
        $symbols = ['HUF' => 'Ft', 'EUR' => '€', 'USD' => '$', 'GBP' => '£'];
        $value = self::format($amount, $precision ?? ($currency === 'HUF' ? 0 : 2), null, $locale);
        $symbol = $symbols[$currency] ?? $currency;

        return in_array($currency, ['USD', 'GBP'], true) && self::language($locale) === 'en'
            ? $symbol . $value
            : $value . "\u{00A0}" . $symbol;
    }

    /** @return array{0: string, 1: string} decimal separator, thousands separator */
    public static function separators(?string $locale): array
    {
        return match (self::language($locale)) {
            'hu', 'fr', 'cs', 'sk', 'pl', 'ru', 'uk', 'sv', 'nb', 'no', 'fi', 'lt', 'lv', 'et', 'bg' => [',', "\u{00A0}"],
            'de', 'it', 'es', 'nl', 'pt', 'da', 'tr', 'id', 'ro', 'hr', 'sl', 'sr', 'el' => [',', '.'],
            default => ['.', ','],
        };
    }

    private static function language(?string $locale): string
    {
        $locale = strtolower((string) ($locale ?? app()->getLocale()));

        return substr(str_replace('-', '_', $locale), 0, 2) ?: 'en';
    }

    private static function decimalsIn(int|float $number): int
    {
        if (is_int($number) || floor($number) == $number) {
            return 0;
        }
        $text = rtrim(rtrim(sprintf('%.10F', abs($number)), '0'), '.');
        $dot = strpos($text, '.');

        return $dot === false ? 0 : strlen($text) - $dot - 1;
    }
}
