<?php

namespace Tests\Unit;

use App\Support\IntlFallback;
use Tests\TestCase;

class IntlFallbackTest extends TestCase
{
    public function test_hungarian_formatting_without_intl(): void
    {
        $nbsp = "\u{00A0}";
        $this->assertSame('1' . $nbsp . '234' . $nbsp . '567', IntlFallback::format(1234567, locale: 'hu'));
        $this->assertSame('1,5', IntlFallback::format(1.5, locale: 'hu'));
        $this->assertSame('3,142', IntlFallback::format(3.14159, locale: 'hu'));
        $this->assertSame('3,14', IntlFallback::format(3.14159, maxPrecision: 2, locale: 'hu'));
        $this->assertSame('2,00', IntlFallback::format(2, precision: 2, locale: 'hu'));
        $this->assertSame('0', IntlFallback::format(0, locale: 'hu'));
        $this->assertSame('-12' . $nbsp . '000', IntlFallback::format(-12000, locale: 'hu'));
    }

    public function test_english_and_german_separators(): void
    {
        $this->assertSame('1,234.5', IntlFallback::format(1234.5, locale: 'en'));
        $this->assertSame('1.234,5', IntlFallback::format(1234.5, locale: 'de'));
        $this->assertSame('1,234', IntlFallback::format(1234, locale: 'en_US'));
    }

    public function test_percentage_and_currency(): void
    {
        $this->assertSame('42%', IntlFallback::percentage(42, locale: 'en'));
        $this->assertSame("42\u{00A0}%", IntlFallback::percentage(42, locale: 'hu'));
        $this->assertSame("241\u{00A0}678\u{00A0}Ft", IntlFallback::currency(241678.4, 'HUF', 'hu'));
        $this->assertSame('$1,234.50', IntlFallback::currency(1234.5, 'USD', 'en'));
    }

    public function test_laravel_number_class_is_patched_for_hosts_without_intl(): void
    {
        $source = (string) file_get_contents(base_path('vendor/laravel/framework/src/Illuminate/Support/Number.php'));
        $this->assertSame(3, substr_count($source, '/* goldentor-intl-fallback */'));
        $this->assertSame('1,234.5', \Illuminate\Support\Number::format(1234.5, locale: 'en'));
    }
}
