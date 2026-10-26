<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * The admin side of the booking calendar. All rules live in unas/calendar.php, which the
 * public website uses as well, so the admin and the booking form can never disagree.
 * On the server the admin is installed in <site root>/admin-panel, next to unas/.
 */
class BookingCalendar
{
    public static function load(): void
    {
        if (class_exists(\GoldenTorCalendar::class, false)) {
            return;
        }
        $path = rtrim((string) config('goldentor.site_root'), '/\\') . '/unas/calendar.php';
        if (! is_file($path)) {
            throw new RuntimeException('A naptár modul nem található (' . $path . '). Futtasd a „Deploy Golden Tor to UNAS” telepítést is.');
        }
        require_once $path;
    }

    public static function pdo(): PDO
    {
        self::load();

        return DB::connection()->getPdo();
    }

    public static function rules(): array
    {
        return \GoldenTorCalendar::rules(self::pdo());
    }

    /** @return array<string, string> slot => slot, for select fields */
    public static function slotOptions(): array
    {
        try {
            $slots = self::rules()['slots'];
        } catch (\Throwable) {
            $slots = (array) config('goldentor.slots', []);
        }

        return array_combine($slots, $slots) ?: [];
    }

    public static function saveRules(array $input): array
    {
        return \GoldenTorCalendar::saveRules(self::pdo(), $input);
    }

    public static function google(): array
    {
        return \GoldenTorCalendar::google(self::pdo());
    }

    public static function saveGoogle(array $urls, bool $allDayBlocks): array
    {
        return \GoldenTorCalendar::saveGoogle(self::pdo(), $urls, $allDayBlocks);
    }

    public static function sync(): array
    {
        return \GoldenTorCalendar::syncGoogle(self::pdo());
    }

    public static function importUpload(string $ics, string $fileName): int
    {
        return \GoldenTorCalendar::importUpload(self::pdo(), $ics, $fileName);
    }

    public static function clearUpload(): void
    {
        \GoldenTorCalendar::clearUpload(self::pdo());
    }

    public static function occupancy(string $from, string $to): array
    {
        return \GoldenTorCalendar::occupancy(self::pdo(), $from, $to);
    }

    public static function addBlock(string $start, string $end, bool $allDay, string $title): int
    {
        return \GoldenTorCalendar::addBlock(self::pdo(), $start, $end, $allDay, $title);
    }

    public static function deleteBlock(int $id): bool
    {
        return \GoldenTorCalendar::deleteBlock(self::pdo(), $id);
    }

    public static function manualBlocks(): array
    {
        return \GoldenTorCalendar::manualBlocks(self::pdo());
    }

    /** "2026-10-27 09:00:00" (UTC, as stored) → "2026. 10. 27. 10:00" Budapest time. */
    public static function utcLabel(?string $utc): ?string
    {
        if (! $utc) {
            return null;
        }

        return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('Europe/Budapest'))
            ->format('Y. m. d. H:i');
    }
}
