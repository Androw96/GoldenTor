<?php
declare(strict_types=1);

/**
 * Golden Tor booking calendar.
 *
 * One place that decides which consultation slots a customer may pick:
 *   - booking rules (weekdays, slot times, length, how far ahead) set in the admin,
 *   - existing bookings,
 *   - blocks added by an admin ("manual"),
 *   - events imported from Google Calendar through its secret iCal address ("google")
 *     or from an uploaded .ics file ("upload").
 *
 * Framework-free on purpose: the public API (unas/api.php) and the Filament admin
 * (app/Support/BookingCalendar.php) both use this file with a plain PDO connection.
 * The SQL is portable between MySQL (live) and SQLite (CI tests).
 * Times in the database are Europe/Budapest wall-clock times ("Y-m-d H:i:s").
 */
final class GoldenTorCalendar
{
    public const TZ = 'Europe/Budapest';

    /** Seconds between automatic Google Calendar refreshes. */
    public const SYNC_INTERVAL = 600;

    public const MAX_ICS_BYTES = 10 * 1024 * 1024;

    public const MAX_URLS = 5;

    /** Imported events are kept for this window around today. */
    public const IMPORT_DAYS_BACK = 7;

    public const IMPORT_DAYS_AHEAD = 200;

    public const WEEKDAYS = [1 => 'Hétfő', 2 => 'Kedd', 3 => 'Szerda', 4 => 'Csütörtök', 5 => 'Péntek', 6 => 'Szombat', 7 => 'Vasárnap'];

    public const SOURCES = ['manual' => 'Admin zárolás', 'google' => 'Google Naptár', 'upload' => 'Feltöltött naptár'];

    private const MAX_EVENTS = 20000;

    private const WINDOWS_ZONES = [
        'central europe standard time' => 'Europe/Budapest',
        'central european standard time' => 'Europe/Warsaw',
        'w. europe standard time' => 'Europe/Berlin',
        'romance standard time' => 'Europe/Paris',
        'gmt standard time' => 'Europe/London',
        'greenwich standard time' => 'UTC',
        'e. europe standard time' => 'Europe/Bucharest',
        'fle standard time' => 'Europe/Kiev',
        'gtb standard time' => 'Europe/Athens',
        'eastern standard time' => 'America/New_York',
        'pacific standard time' => 'America/Los_Angeles',
        'utc' => 'UTC',
    ];

    private const DAY_CODES = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7];

    // ------------------------------------------------------------------ settings

    public static function defaultRules(): array
    {
        return [
            'weekdays' => [1, 2, 3, 4, 5],
            'slots' => ['09:00', '11:00', '14:00', '16:00'],
            'duration' => 45,
            'days_ahead' => 21,
            'min_notice_days' => 1,
        ];
    }

    public static function rules(PDO $db): array
    {
        $stored = self::readSetting($db, 'rules');
        if (!$stored) {
            return self::defaultRules();
        }
        try {
            return self::normalizeRules($stored + self::defaultRules());
        } catch (InvalidArgumentException $error) {
            return self::defaultRules();
        }
    }

    /** @throws InvalidArgumentException with a Hungarian message */
    public static function normalizeRules(array $input): array
    {
        $weekdays = [];
        foreach ((array) ($input['weekdays'] ?? []) as $day) {
            $day = filter_var($day, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 7]]);
            if ($day !== false) {
                $weekdays[$day] = $day;
            }
        }
        ksort($weekdays);

        $slots = [];
        foreach ((array) ($input['slots'] ?? []) as $slot) {
            $slot = trim((string) $slot);
            if (!preg_match('/^([01]?\d|2[0-3])[:.]([0-5]\d)$/', $slot, $match)) {
                throw new InvalidArgumentException("Érvénytelen időpont: „{$slot}”. Formátum: ÓÓ:PP, például 09:30.");
            }
            $slots[sprintf('%02d:%s', (int) $match[1], $match[2])] = true;
        }
        $slots = array_keys($slots);
        sort($slots);
        if (count($slots) > 24) {
            throw new InvalidArgumentException('Legfeljebb 24 időpont adható meg naponta.');
        }

        $duration = filter_var($input['duration'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 10, 'max_range' => 480]]);
        if ($duration === false) {
            throw new InvalidArgumentException('A konzultáció hossza 10 és 480 perc között lehet.');
        }
        $daysAhead = filter_var($input['days_ahead'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 180]]);
        if ($daysAhead === false) {
            throw new InvalidArgumentException('Az előre foglalható napok száma 1 és 180 között lehet.');
        }
        $notice = filter_var($input['min_notice_days'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 60]]);
        if ($notice === false) {
            throw new InvalidArgumentException('A minimális előzetes foglalás 0 és 60 nap között lehet.');
        }

        return [
            'weekdays' => array_values($weekdays),
            'slots' => $slots,
            'duration' => $duration,
            'days_ahead' => $daysAhead,
            'min_notice_days' => $notice,
        ];
    }

    public static function saveRules(PDO $db, array $input): array
    {
        $rules = self::normalizeRules($input);
        self::writeSetting($db, 'rules', $rules);

        return $rules;
    }

    /** Google / uploaded calendar settings and the state of the last import. */
    public static function google(PDO $db): array
    {
        $stored = self::readSetting($db, 'google') ?? [];
        $sync = self::readSetting($db, 'sync') ?? [];
        $upload = self::readSetting($db, 'upload') ?? [];

        return [
            'urls' => array_values(array_filter((array) ($stored['urls'] ?? []), 'is_string')),
            'allday_blocks' => (bool) ($stored['allday_blocks'] ?? true),
            'sync' => $sync,
            'upload' => $upload,
        ];
    }

    /**
     * @param string[] $urls
     * @throws InvalidArgumentException
     */
    public static function saveGoogle(PDO $db, array $urls, bool $allDayBlocks): array
    {
        $clean = [];
        foreach ($urls as $url) {
            $url = trim((string) $url);
            if ($url !== '') {
                $clean[self::normalizeUrl($url)] = true;
            }
        }
        if (count($clean) > self::MAX_URLS) {
            throw new InvalidArgumentException('Legfeljebb ' . self::MAX_URLS . ' naptárcím adható meg.');
        }
        self::writeSetting($db, 'google', ['urls' => array_keys($clean), 'allday_blocks' => $allDayBlocks]);
        if (!$clean) {
            self::replaceSource($db, 'google', []);
            self::writeSetting($db, 'sync', []);
        }

        return self::google($db);
    }

    /** @throws InvalidArgumentException */
    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if (stripos($url, 'webcal://') === 0) {
            $url = 'https://' . substr($url, 9);
        }
        if (strlen($url) > 2000 || preg_match('/\s/', $url) || stripos($url, 'https://') !== 0 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('A naptár címe nem érvényes. A Google Naptárban a „Titkos cím iCal formátumban” mezőből másold ki (https://calendar.google.com/… .ics).');
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || $host === 'localhost' || substr($host, -6) === '.local' || filter_var(trim($host, '[]'), FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('A naptár címe nem érvényes: csak nyilvános https:// cím adható meg.');
        }

        return $url;
    }

    // ------------------------------------------------------------------ importing

    /**
     * Downloads every configured calendar and replaces the imported Google events.
     * Never throws: the result (and any error) is stored and returned.
     */
    public static function syncGoogle(PDO $db): array
    {
        $settings = self::google($db);
        $state = ['at' => gmdate('Y-m-d H:i:s'), 'ok' => true, 'error' => null, 'count' => 0, 'calendars' => count($settings['urls'])];
        if (!$settings['urls']) {
            self::writeSetting($db, 'sync', []);

            return [];
        }
        try {
            [$from, $to] = self::importWindow();
            $events = [];
            foreach ($settings['urls'] as $index => $url) {
                try {
                    $ics = self::download($url);
                    $events = array_merge($events, self::parseIcs($ics, $from, $to));
                } catch (Throwable $error) {
                    throw new RuntimeException(($index + 1) . '. naptár: ' . $error->getMessage(), 0, $error);
                }
            }
            $state['count'] = self::replaceSource($db, 'google', $events);
        } catch (Throwable $error) {
            // Keep the previously imported events: a temporary Google outage must not free busy slots.
            $state['ok'] = false;
            $state['error'] = mb_substr($error->getMessage(), 0, 500);
            $previous = self::readSetting($db, 'sync') ?? [];
            $state['count'] = (int) ($previous['count'] ?? 0);
            $state['last_ok_at'] = $previous['ok'] ?? false ? ($previous['at'] ?? null) : ($previous['last_ok_at'] ?? null);
        }
        if ($state['ok']) {
            $state['last_ok_at'] = $state['at'];
        }
        self::writeSetting($db, 'sync', $state);

        return $state;
    }

    /** Refreshes the Google events when the last attempt is older than SYNC_INTERVAL. Never throws. */
    public static function autoSync(PDO $db): void
    {
        try {
            if (!self::google($db)['urls']) {
                return;
            }
            if (self::claimSync($db, self::SYNC_INTERVAL)) {
                self::syncGoogle($db);
            }
        } catch (Throwable $error) {
            error_log('Golden Tor calendar sync: ' . $error->getMessage());
        }
    }

    /** Replaces the events of the previously uploaded .ics file. Returns the number of stored events. */
    public static function importUpload(PDO $db, string $ics, string $fileName): int
    {
        if (strlen($ics) > self::MAX_ICS_BYTES) {
            throw new InvalidArgumentException('A naptárfájl túl nagy (legfeljebb 10 MB).');
        }
        [$from, $to] = self::importWindow();
        $count = self::replaceSource($db, 'upload', self::parseIcs($ics, $from, $to));
        self::writeSetting($db, 'upload', ['at' => gmdate('Y-m-d H:i:s'), 'file' => mb_substr($fileName, 0, 120), 'count' => $count]);

        return $count;
    }

    public static function clearUpload(PDO $db): void
    {
        self::replaceSource($db, 'upload', []);
        self::writeSetting($db, 'upload', []);
    }

    /** @return array{0: DateTimeImmutable, 1: DateTimeImmutable} */
    private static function importWindow(): array
    {
        $today = new DateTimeImmutable('today', new DateTimeZone(self::TZ));

        return [$today->modify('-' . self::IMPORT_DAYS_BACK . ' days'), $today->modify('+' . self::IMPORT_DAYS_AHEAD . ' days')];
    }

    private static function download(string $url): string
    {
        $url = self::normalizeUrl($url);
        if (function_exists('curl_init')) {
            $body = '';
            $tooLarge = false;
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_USERAGENT => 'GoldenTor-Calendar/1.0',
                CURLOPT_HTTPHEADER => ['Accept: text/calendar, */*'],
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge): int {
                    $body .= $chunk;
                    if (strlen($body) > self::MAX_ICS_BYTES) {
                        $tooLarge = true;

                        return 0;
                    }

                    return strlen($chunk);
                },
            ]);
            curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $error = curl_error($handle);
            curl_close($handle);
            if ($tooLarge) {
                throw new RuntimeException('a naptár túl nagy (10 MB felett).');
            }
            if ($status === 0) {
                throw new RuntimeException('a szerver nem érte el a naptárat (' . ($error ?: 'nincs válasz') . ').');
            }
        } else {
            $context = stream_context_create(['http' => ['timeout' => 15, 'user_agent' => 'GoldenTor-Calendar/1.0', 'ignore_errors' => true, 'follow_location' => 1, 'max_redirects' => 3]]);
            $body = @file_get_contents($url, false, $context, 0, self::MAX_ICS_BYTES + 1);
            $status = 0;
            foreach ($http_response_header ?? [] as $header) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match)) {
                    $status = (int) $match[1];
                }
            }
            if ($body === false || $status === 0) {
                throw new RuntimeException('a szerver nem érte el a naptárat.');
            }
            if (strlen($body) > self::MAX_ICS_BYTES) {
                throw new RuntimeException('a naptár túl nagy (10 MB felett).');
            }
        }
        if ($status === 404 || $status === 403 || $status === 401) {
            throw new RuntimeException("a Google nem adta ki a naptárat (HTTP {$status}). Ellenőrizd, hogy a teljes titkos címet másoltad-e be, és hogy nem lett-e azóta visszaállítva.");
        }
        if ($status !== 200) {
            throw new RuntimeException("a naptár letöltése nem sikerült (HTTP {$status}).");
        }
        if (stripos($body, 'BEGIN:VCALENDAR') === false) {
            throw new RuntimeException('a cím nem naptárfájlt adott vissza. A „Titkos cím iCal formátumban” címet kell megadni, nem a naptár weboldalát.');
        }

        return $body;
    }

    // ------------------------------------------------------------------ iCalendar parsing

    /**
     * Parses an iCalendar file and expands recurring events inside [$from, $to).
     *
     * @return list<array{start: string, end: string, all_day: bool, busy: bool, title: string, uid: string}>
     * @throws InvalidArgumentException when the text is not an iCalendar file
     */
    public static function parseIcs(string $ics, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        if (stripos($ics, 'BEGIN:VCALENDAR') === false) {
            throw new InvalidArgumentException('A fájl nem naptár (.ics) formátumú.');
        }
        if (strncmp($ics, "\xEF\xBB\xBF", 3) === 0) {
            $ics = substr($ics, 3);
        }
        $ics = str_replace(["\r\n", "\r"], "\n", $ics);
        $ics = preg_replace("/\n[ \t]/", '', $ics);

        $calendarZone = null;
        $components = [];
        $current = null;
        $nested = 0;
        foreach (explode("\n", $ics) as $line) {
            if ($line === '') {
                continue;
            }
            if ($current === null) {
                if (strcasecmp($line, 'BEGIN:VEVENT') === 0) {
                    $current = [];
                    $nested = 0;
                } elseif (stripos($line, 'X-WR-TIMEZONE:') === 0) {
                    $calendarZone = self::zone(trim(substr($line, 14)));
                }
                continue;
            }
            if (stripos($line, 'BEGIN:') === 0) {
                $nested++;
                continue;
            }
            if (stripos($line, 'END:') === 0) {
                if ($nested === 0) {
                    $components[] = $current;
                    $current = null;
                } else {
                    $nested--;
                }
                continue;
            }
            if ($nested > 0) {
                continue;
            }
            $property = self::parseProperty($line);
            if ($property) {
                $current[$property['name']][] = $property;
            }
        }

        $defaultZone = $calendarZone ?? new DateTimeZone(self::TZ);
        $local = new DateTimeZone(self::TZ);

        // RECURRENCE-ID overrides replace single instances of a recurring series.
        $overridden = [];
        foreach ($components as $component) {
            if (isset($component['RECURRENCE-ID'][0], $component['UID'][0])) {
                $instance = self::parseDate($component['RECURRENCE-ID'][0], $defaultZone);
                if ($instance) {
                    $overridden[$component['UID'][0]['value']][self::instanceKey($instance[0], $instance[1])] = true;
                }
            }
        }

        $events = [];
        foreach ($components as $component) {
            $status = strtoupper($component['STATUS'][0]['value'] ?? '');
            if ($status === 'CANCELLED' || !isset($component['DTSTART'][0])) {
                continue;
            }
            $start = self::parseDate($component['DTSTART'][0], $defaultZone);
            if (!$start) {
                continue;
            }
            [$startAt, $allDay] = $start;

            $endAt = null;
            if (isset($component['DTEND'][0])) {
                $end = self::parseDate($component['DTEND'][0], $startAt->getTimezone());
                $endAt = $end ? $end[0] : null;
            } elseif (isset($component['DURATION'][0])) {
                $endAt = self::addDuration($startAt, $component['DURATION'][0]['value']);
            }
            if (!$endAt || $endAt < $startAt) {
                $endAt = $allDay ? $startAt->modify('+1 day') : $startAt;
            }
            $days = $allDay ? max(1, (int) round(($endAt->getTimestamp() - $startAt->getTimestamp()) / 86400)) : 0;
            $seconds = $endAt->getTimestamp() - $startAt->getTimestamp();

            $uid = (string) ($component['UID'][0]['value'] ?? '');
            $isOverride = isset($component['RECURRENCE-ID'][0]);
            $transparent = strtoupper($component['TRANSP'][0]['value'] ?? '') === 'TRANSPARENT';
            $title = self::unescape((string) ($component['SUMMARY'][0]['value'] ?? ''));

            $starts = [$startAt];
            if (!$isOverride && isset($component['RRULE'][0])) {
                $starts = self::expandRule($startAt, $allDay, $component['RRULE'][0]['value'], $to);
            }
            foreach ($component['RDATE'] ?? [] as $rdate) {
                foreach (explode(',', $rdate['value']) as $value) {
                    $extra = self::parseDate(['name' => 'RDATE', 'params' => $rdate['params'], 'value' => $value], $startAt->getTimezone());
                    if ($extra && $extra[0]->format('His') === '000000' && !$allDay && strlen($value) === 8) {
                        $extra[0] = $extra[0]->setTime((int) $startAt->format('H'), (int) $startAt->format('i'));
                    }
                    if ($extra) {
                        $starts[] = $extra[0];
                    }
                }
            }
            $excluded = [];
            foreach ($component['EXDATE'] ?? [] as $exdate) {
                foreach (explode(',', $exdate['value']) as $value) {
                    $parsed = self::parseDate(['name' => 'EXDATE', 'params' => $exdate['params'], 'value' => $value], $startAt->getTimezone());
                    if ($parsed) {
                        $excluded[self::instanceKey($parsed[0], $allDay || $parsed[1])] = true;
                    }
                }
            }
            $skip = $isOverride ? [] : ($overridden[$uid] ?? []);

            $seen = [];
            foreach ($starts as $occurrence) {
                $key = self::instanceKey($occurrence, $allDay);
                if (isset($seen[$key]) || isset($excluded[$key]) || isset($skip[$key])) {
                    continue;
                }
                $seen[$key] = true;
                if ($allDay) {
                    $date = $occurrence->format('Y-m-d');
                    $s = new DateTimeImmutable($date . ' 00:00:00', $local);
                    $e = $s->modify("+{$days} days");
                } else {
                    $s = $occurrence->setTimezone($local);
                    $e = $occurrence->modify("+{$seconds} seconds")->setTimezone($local);
                }
                if ($e <= $from || $s >= $to || ($e == $s && ($s < $from || $s >= $to))) {
                    continue;
                }
                $events[] = [
                    'start' => $s->format('Y-m-d H:i:s'),
                    'end' => $e->format('Y-m-d H:i:s'),
                    'all_day' => $allDay,
                    'busy' => !$transparent,
                    'title' => mb_substr($title, 0, 190),
                    'uid' => mb_substr($uid, 0, 190),
                ];
                if (count($events) >= self::MAX_EVENTS) {
                    break 2;
                }
            }
        }
        usort($events, static fn (array $a, array $b): int => strcmp($a['start'], $b['start']));

        return $events;
    }

    /** @return array{name: string, params: array<string, string>, value: string}|null */
    private static function parseProperty(string $line): ?array
    {
        $length = strlen($line);
        $quoted = false;
        $colon = null;
        for ($i = 0; $i < $length; $i++) {
            $char = $line[$i];
            if ($char === '"') {
                $quoted = !$quoted;
            } elseif ($char === ':' && !$quoted) {
                $colon = $i;
                break;
            }
        }
        if ($colon === null) {
            return null;
        }
        $head = substr($line, 0, $colon);
        $parts = preg_split('/;(?=(?:[^"]*"[^"]*")*[^"]*$)/', $head) ?: [$head];
        $name = strtoupper(array_shift($parts));
        $params = [];
        foreach ($parts as $part) {
            $pair = explode('=', $part, 2);
            if (count($pair) === 2) {
                $params[strtoupper($pair[0])] = trim($pair[1], '"');
            }
        }

        return ['name' => $name, 'params' => $params, 'value' => substr($line, $colon + 1)];
    }

    /** @return array{0: DateTimeImmutable, 1: bool}|null  [moment, is all-day] */
    private static function parseDate(array $property, DateTimeZone $fallback): ?array
    {
        $value = trim($property['value']);
        $params = $property['params'] ?? [];
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $m) || strtoupper($params['VALUE'] ?? '') === 'DATE' && preg_match('/^(\d{4})(\d{2})(\d{2})/', $value, $m)) {
            if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return null;
            }

            return [new DateTimeImmutable("{$m[1]}-{$m[2]}-{$m[3]} 00:00:00", new DateTimeZone(self::TZ)), true];
        }
        if (!preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})?(Z?)$/i', $value, $m)) {
            return null;
        }
        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || (int) $m[4] > 24 || (int) $m[5] > 59) {
            return null;
        }
        $zone = $m[7] !== '' ? new DateTimeZone('UTC') : (isset($params['TZID']) ? (self::zone($params['TZID']) ?? $fallback) : $fallback);
        $seconds = $m[6] !== '' ? $m[6] : '00';

        try {
            return [new DateTimeImmutable("{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}:{$m[5]}:{$seconds}", $zone), false];
        } catch (Exception $error) {
            return null;
        }
    }

    private static function zone(string $id): ?DateTimeZone
    {
        $id = trim($id, " \"");
        if ($id === '') {
            return null;
        }
        $mapped = self::WINDOWS_ZONES[strtolower($id)] ?? null;
        $candidates = [$mapped ?? $id];
        // "/freeassociation.sourceforge.net/Tzfile/Europe/Budapest" and similar prefixed ids.
        $segments = array_values(array_filter(explode('/', $id), 'strlen'));
        for ($i = 1; $i < count($segments); $i++) {
            $candidates[] = implode('/', array_slice($segments, $i));
        }
        foreach ($candidates as $candidate) {
            try {
                return new DateTimeZone($candidate);
            } catch (Exception $error) {
                continue;
            }
        }

        return null;
    }

    private static function addDuration(DateTimeImmutable $start, string $duration): ?DateTimeImmutable
    {
        if (!preg_match('/^([+-])?P(?:(\d+)W)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/i', trim($duration), $m) || ($m[1] ?? '') === '-') {
            return null;
        }
        $days = (int) ($m[2] ?? 0) * 7 + (int) ($m[3] ?? 0);
        $seconds = (int) ($m[4] ?? 0) * 3600 + (int) ($m[5] ?? 0) * 60 + (int) ($m[6] ?? 0);

        return $start->modify("+{$days} days")->modify("+{$seconds} seconds");
    }

    private static function instanceKey(DateTimeImmutable $moment, bool $allDay): string
    {
        return $allDay ? $moment->format('Y-m-d') : (string) $moment->getTimestamp();
    }

    private static function unescape(string $value): string
    {
        $value = strtr($value, ['\\n' => ' ', '\\N' => ' ', '\\,' => ',', '\\;' => ';', '\\\\' => '\\']);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * RRULE expansion (FREQ DAILY/WEEKLY/MONTHLY/YEARLY with INTERVAL, COUNT, UNTIL,
     * BYDAY, BYMONTHDAY, BYMONTH and BYSETPOS) – everything Google and Outlook write.
     *
     * @return list<DateTimeImmutable>
     */
    private static function expandRule(DateTimeImmutable $start, bool $allDay, string $rule, DateTimeImmutable $until): array
    {
        $parts = [];
        foreach (explode(';', strtoupper(trim($rule))) as $part) {
            $pair = explode('=', $part, 2);
            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }
        $frequency = $parts['FREQ'] ?? '';
        if (!in_array($frequency, ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true)) {
            return [$start];
        }
        $interval = max(1, (int) ($parts['INTERVAL'] ?? 1));
        $count = isset($parts['COUNT']) ? max(1, (int) $parts['COUNT']) : null;
        $limit = $until;
        if (isset($parts['UNTIL'])) {
            $parsed = self::parseDate(['name' => 'UNTIL', 'params' => [], 'value' => $parts['UNTIL']], $start->getTimezone());
            if ($parsed) {
                $ruleEnd = $parsed[1] ? $parsed[0]->setTimezone($start->getTimezone())->setTime(23, 59, 59) : $parsed[0];
                if ($ruleEnd < $limit) {
                    $limit = $ruleEnd;
                }
            }
        }
        $byDay = [];
        foreach (array_filter(explode(',', $parts['BYDAY'] ?? '')) as $entry) {
            if (preg_match('/^([+-]?\d{1,2})?(MO|TU|WE|TH|FR|SA|SU)$/', $entry, $m)) {
                $byDay[] = [(int) ($m[1] ?? 0), self::DAY_CODES[$m[2]]];
            }
        }
        $byMonthDay = array_map('intval', array_filter(explode(',', $parts['BYMONTHDAY'] ?? ''), 'strlen'));
        $byMonth = array_map('intval', array_filter(explode(',', $parts['BYMONTH'] ?? ''), 'strlen'));
        $bySetPos = array_map('intval', array_filter(explode(',', $parts['BYSETPOS'] ?? ''), 'strlen'));

        $zone = $start->getTimezone();
        $time = $start->format('H:i:s');
        $first = new DateTimeImmutable($start->format('Y-m-d') . ' 00:00:00', $zone);
        $results = [$start];
        $produced = 1;

        for ($period = 0; $period < 50000; $period++) {
            $dates = [];
            switch ($frequency) {
                case 'DAILY':
                    $day = $first->modify('+' . ($period * $interval) . ' days');
                    $periodStart = $day;
                    if ((!$byDay || in_array((int) $day->format('N'), array_column($byDay, 1), true))
                        && (!$byMonth || in_array((int) $day->format('n'), $byMonth, true))
                        && (!$byMonthDay || in_array((int) $day->format('j'), $byMonthDay, true))) {
                        $dates[] = $day;
                    }
                    break;
                case 'WEEKLY':
                    $monday = $first->modify('-' . ((int) $first->format('N') - 1) . ' days')->modify('+' . ($period * $interval * 7) . ' days');
                    $periodStart = $monday;
                    $weekdays = $byDay ? array_unique(array_column($byDay, 1)) : [(int) $start->format('N')];
                    foreach ($weekdays as $weekday) {
                        $day = $monday->modify('+' . ($weekday - 1) . ' days');
                        if (!$byMonth || in_array((int) $day->format('n'), $byMonth, true)) {
                            $dates[] = $day;
                        }
                    }
                    break;
                case 'MONTHLY':
                    $month = $first->modify('first day of this month')->modify('+' . ($period * $interval) . ' months');
                    $periodStart = $month;
                    if (!$byMonth || in_array((int) $month->format('n'), $byMonth, true)) {
                        $dates = self::daysInMonth($month, $byDay, $byMonthDay, (int) $start->format('j'));
                    }
                    break;
                default: // YEARLY
                    $year = (int) $first->format('Y') + $period * $interval;
                    $periodStart = new DateTimeImmutable("{$year}-01-01 00:00:00", $zone);
                    foreach ($byMonth ?: [(int) $start->format('n')] as $monthNumber) {
                        if ($monthNumber < 1 || $monthNumber > 12) {
                            continue;
                        }
                        $month = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $monthNumber), $zone);
                        $dates = array_merge($dates, self::daysInMonth($month, $byDay, $byMonthDay, (int) $start->format('j')));
                    }
            }
            if ($periodStart > $limit) {
                break;
            }
            usort($dates, static fn ($a, $b) => $a <=> $b);
            if ($bySetPos && $dates) {
                $picked = [];
                foreach ($bySetPos as $position) {
                    $index = $position > 0 ? $position - 1 : count($dates) + $position;
                    if (isset($dates[$index])) {
                        $picked[] = $dates[$index];
                    }
                }
                $dates = $picked;
                usort($dates, static fn ($a, $b) => $a <=> $b);
            }
            foreach ($dates as $date) {
                $occurrence = $allDay ? $date : new DateTimeImmutable($date->format('Y-m-d') . ' ' . $time, $zone);
                if ($occurrence <= $start) {
                    continue;
                }
                if ($occurrence > $limit) {
                    return $results;
                }
                if ($count !== null && $produced >= $count) {
                    return $results;
                }
                $results[] = $occurrence;
                $produced++;
            }
        }

        return $results;
    }

    /** @return list<DateTimeImmutable> */
    private static function daysInMonth(DateTimeImmutable $month, array $byDay, array $byMonthDay, int $defaultDay): array
    {
        $length = (int) $month->format('t');
        $dates = [];
        if ($byDay) {
            foreach ($byDay as [$ordinal, $weekday]) {
                $matches = [];
                for ($d = 1; $d <= $length; $d++) {
                    $day = $month->setDate((int) $month->format('Y'), (int) $month->format('n'), $d);
                    if ((int) $day->format('N') === $weekday) {
                        $matches[] = $day;
                    }
                }
                if ($ordinal === 0) {
                    $dates = array_merge($dates, $matches);
                } else {
                    $index = $ordinal > 0 ? $ordinal - 1 : count($matches) + $ordinal;
                    if (isset($matches[$index])) {
                        $dates[] = $matches[$index];
                    }
                }
            }
            if ($byMonthDay) {
                $dates = array_values(array_filter($dates, static function (DateTimeImmutable $day) use ($byMonthDay, $length): bool {
                    $d = (int) $day->format('j');

                    return in_array($d, $byMonthDay, true) || in_array($d - $length - 1, $byMonthDay, true);
                }));
            }

            return $dates;
        }
        foreach ($byMonthDay ?: [$defaultDay] as $d) {
            $d = $d < 0 ? $length + $d + 1 : $d;
            if ($d >= 1 && $d <= $length) {
                $dates[] = $month->setDate((int) $month->format('Y'), (int) $month->format('n'), $d);
            }
        }

        return $dates;
    }

    // ------------------------------------------------------------------ availability

    /**
     * Every slot of every day in [$fromDate, $toDate] with its state:
     *   free     – the customer may book it
     *   booked   – an active booking overlaps it
     *   blocked  – an admin block overlaps it
     *   busy     – a Google / uploaded calendar event overlaps it
     *   closed   – not a booking day
     *   past     – too early (minimum notice) or already over
     *   later    – beyond the booking horizon
     */
    public static function occupancy(PDO $db, string $fromDate, string $toDate, ?array $rules = null): array
    {
        $rules ??= self::rules($db);
        $local = new DateTimeZone(self::TZ);
        $from = self::date($fromDate);
        $to = self::date($toDate);
        if ($to < $from || $from->diff($to)->days > 400) {
            throw new InvalidArgumentException('Érvénytelen dátumtartomány.');
        }
        $now = new DateTimeImmutable('now', $local);
        $today = new DateTimeImmutable('today', $local);
        $earliest = $rules['min_notice_days'] > 0 ? $today->modify('+' . $rules['min_notice_days'] . ' days') : $now->modify('+60 minutes');
        $horizon = $today->modify('+' . $rules['days_ahead'] . ' days');
        $allDayBlocks = self::google($db)['allday_blocks'];
        $duration = $rules['duration'];

        $rangeStart = $from->format('Y-m-d 00:00:00');
        $rangeEnd = $to->modify('+1 day')->format('Y-m-d 00:00:00');

        $blocks = [];
        try {
            $statement = $db->prepare('SELECT id,source,starts_at,ends_at,all_day,busy,title FROM calendar_blocks WHERE starts_at < ? AND ends_at > ? ORDER BY starts_at');
            $statement->execute([$rangeEnd, $rangeStart]);
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $row['all_day'] = (bool) $row['all_day'];
                $row['busy'] = (bool) $row['busy'];
                $row['counts'] = $row['source'] === 'manual' || $row['busy'] || ($row['all_day'] && $allDayBlocks);
                $row['start_ts'] = (new DateTimeImmutable($row['starts_at'], $local))->getTimestamp();
                $row['end_ts'] = (new DateTimeImmutable($row['ends_at'], $local))->getTimestamp();
                $blocks[] = $row;
            }
        } catch (PDOException $error) {
            // Calendar tables not created yet (admin not deployed): no blocks.
        }

        $bookings = [];
        $statement = $db->prepare("SELECT id,name,topic,status,booking_date,booking_time FROM bookings WHERE booking_date >= ? AND booking_date <= ? AND status <> 'cancelled' ORDER BY booking_date,booking_time");
        $statement->execute([$from->modify('-1 day')->format('Y-m-d'), $to->format('Y-m-d')]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $time = substr((string) $row['booking_time'], 0, 5);
            $start = new DateTimeImmutable(substr((string) $row['booking_date'], 0, 10) . ' ' . $time . ':00', $local);
            $row['booking_time'] = $time;
            $row['booking_date'] = substr((string) $row['booking_date'], 0, 10);
            $row['start_ts'] = $start->getTimestamp();
            $row['end_ts'] = $start->getTimestamp() + $duration * 60;
            $bookings[] = $row;
        }

        $days = [];
        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            $weekday = (int) $day->format('N');
            $workday = in_array($weekday, $rules['weekdays'], true);
            $dayStart = $day->getTimestamp();
            $dayEnd = $day->modify('+1 day')->getTimestamp();

            $dayBlocks = array_values(array_filter($blocks, static fn ($b) => $b['start_ts'] < $dayEnd && $b['end_ts'] > $dayStart));
            $dayBookings = array_values(array_filter($bookings, static fn ($b) => $b['start_ts'] < $dayEnd && $b['end_ts'] > $dayStart));

            $slots = [];
            foreach ($rules['slots'] as $time) {
                $start = new DateTimeImmutable("{$date} {$time}:00", $local);
                $startTs = $start->getTimestamp();
                $endTs = $startTs + $duration * 60;
                $booking = null;
                foreach ($dayBookings as $candidate) {
                    if ($candidate['start_ts'] < $endTs && $candidate['end_ts'] > $startTs) {
                        $booking = $candidate;
                        break;
                    }
                }
                $manual = null;
                $busy = null;
                foreach ($dayBlocks as $block) {
                    if (!$block['counts'] || !($block['start_ts'] < $endTs && $block['end_ts'] > $startTs)) {
                        continue;
                    }
                    if ($block['source'] === 'manual') {
                        $manual ??= $block;
                    } else {
                        $busy ??= $block;
                    }
                }
                $state = match (true) {
                    $booking !== null => 'booked',
                    $manual !== null => 'blocked',
                    $busy !== null => 'busy',
                    !$workday => 'closed',
                    $start < $earliest || $start <= $now => 'past',
                    $day > $horizon => 'later',
                    default => 'free',
                };
                $slots[] = [
                    'time' => $time,
                    'state' => $state,
                    'available' => $state === 'free',
                    'booking' => $booking ? self::publicRow($booking, ['id', 'name', 'topic', 'status', 'booking_time']) : null,
                    'block' => ($manual ?? $busy) ? self::publicRow($manual ?? $busy, ['id', 'source', 'title', 'starts_at', 'ends_at', 'all_day']) : null,
                ];
            }

            $days[] = [
                'date' => $date,
                'weekday' => $weekday,
                'workday' => $workday,
                'in_window' => $day >= $today && $day <= $horizon,
                'slots' => $slots,
                'blocks' => array_map(static fn ($b) => self::publicRow($b, ['id', 'source', 'title', 'starts_at', 'ends_at', 'all_day', 'busy', 'counts']), $dayBlocks),
                'bookings' => array_map(static fn ($b) => self::publicRow($b, ['id', 'name', 'topic', 'status', 'booking_date', 'booking_time']), array_values(array_filter($dayBookings, static fn ($b) => $b['booking_date'] === $date))),
            ];
        }

        return $days;
    }

    /**
     * What the public booking form needs: every bookable day in the window with its slots
     * (true/false only – nothing about the reason).
     */
    public static function bookableDays(PDO $db): array
    {
        $rules = self::rules($db);
        $today = new DateTimeImmutable('today', new DateTimeZone(self::TZ));
        $days = [];
        foreach (self::occupancy($db, $today->format('Y-m-d'), $today->modify('+' . $rules['days_ahead'] . ' days')->format('Y-m-d'), $rules) as $day) {
            if (!$day['workday'] || !$day['slots']) {
                continue;
            }
            $open = array_filter($day['slots'], static fn ($slot) => !in_array($slot['state'], ['past', 'closed', 'later'], true));
            if (!$open) {
                continue;
            }
            $days[] = [
                'date' => $day['date'],
                'slots' => array_values(array_map(static fn ($slot) => ['time' => $slot['time'], 'available' => $slot['available']], $open)),
            ];
        }

        return ['duration' => $rules['duration'], 'days' => $days];
    }

    /** Null when the slot can be booked, otherwise the reason for the customer. */
    public static function slotProblem(PDO $db, string $date, string $time): ?string
    {
        try {
            $day = self::date($date);
        } catch (InvalidArgumentException $error) {
            return 'Érvénytelen dátum.';
        }
        foreach (self::occupancy($db, $day->format('Y-m-d'), $day->format('Y-m-d'))[0]['slots'] as $slot) {
            if ($slot['time'] !== $time) {
                continue;
            }
            if ($slot['state'] === 'free') {
                return null;
            }
            if (in_array($slot['state'], ['booked', 'blocked', 'busy'], true)) {
                return 'Ez az időpont időközben foglalttá vált. Kérjük, válasszon másikat.';
            }
            break;
        }

        return 'Erre az időpontra nem lehet foglalni. Kérjük, válasszon a felkínált napok és időpontok közül.';
    }

    // ------------------------------------------------------------------ admin blocks

    public static function addBlock(PDO $db, string $start, string $end, bool $allDay, string $title): int
    {
        $s = self::dateTime($start);
        $e = self::dateTime($end);
        if ($e <= $s) {
            throw new InvalidArgumentException('A zárolás vége a kezdete után kell legyen.');
        }
        if ($s->diff($e)->days > 366) {
            throw new InvalidArgumentException('Egy zárolás legfeljebb egy évig tarthat.');
        }
        $now = gmdate('Y-m-d H:i:s');
        $statement = $db->prepare('INSERT INTO calendar_blocks(source,starts_at,ends_at,all_day,busy,title,uid,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)');
        $statement->execute(['manual', $s->format('Y-m-d H:i:s'), $e->format('Y-m-d H:i:s'), $allDay ? 1 : 0, 1, mb_substr(trim($title), 0, 190), '', $now, $now]);

        return (int) $db->lastInsertId();
    }

    public static function deleteBlock(PDO $db, int $id): bool
    {
        $statement = $db->prepare("DELETE FROM calendar_blocks WHERE id=? AND source='manual'");
        $statement->execute([$id]);

        return $statement->rowCount() === 1;
    }

    /** Upcoming admin blocks (for the list in the admin). */
    public static function manualBlocks(PDO $db, int $limit = 100): array
    {
        try {
            $statement = $db->prepare("SELECT id,starts_at,ends_at,all_day,title FROM calendar_blocks WHERE source='manual' AND ends_at > ? ORDER BY starts_at LIMIT " . max(1, min(500, $limit)));
            $statement->execute([(new DateTimeImmutable('now', new DateTimeZone(self::TZ)))->format('Y-m-d H:i:s')]);

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $error) {
            return [];
        }
    }

    // ------------------------------------------------------------------ storage helpers

    private static function replaceSource(PDO $db, string $source, array $events): int
    {
        $own = !$db->inTransaction();
        if ($own) {
            $db->beginTransaction();
        }
        try {
            $db->prepare('DELETE FROM calendar_blocks WHERE source=?')->execute([$source]);
            $insert = $db->prepare('INSERT INTO calendar_blocks(source,starts_at,ends_at,all_day,busy,title,uid,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)');
            $now = gmdate('Y-m-d H:i:s');
            foreach ($events as $event) {
                $insert->execute([$source, $event['start'], $event['end'], $event['all_day'] ? 1 : 0, $event['busy'] ? 1 : 0, $event['title'], $event['uid'], $now, $now]);
            }
            if ($own) {
                $db->commit();
            }
        } catch (Throwable $error) {
            if ($own && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }

        return count($events);
    }

    private static function readSetting(PDO $db, string $key): ?array
    {
        try {
            $statement = $db->prepare('SELECT setting_value FROM calendar_settings WHERE setting_key=?');
            $statement->execute([$key]);
            $value = $statement->fetchColumn();
        } catch (PDOException $error) {
            return null;
        }
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : null;
    }

    private static function writeSetting(PDO $db, string $key, array $value): void
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $now = gmdate('Y-m-d H:i:s');
        $update = $db->prepare('UPDATE calendar_settings SET setting_value=?, updated_at=? WHERE setting_key=?');
        $update->execute([$json, $now, $key]);
        if ($update->rowCount() > 0) {
            return;
        }
        try {
            $db->prepare('INSERT INTO calendar_settings(setting_key,setting_value,updated_at) VALUES(?,?,?)')->execute([$key, $json, $now]);
        } catch (PDOException $error) {
            $update->execute([$json, $now, $key]);
        }
    }

    /** Lets exactly one request refresh the calendar per interval. */
    private static function claimSync(PDO $db, int $interval): bool
    {
        $now = gmdate('Y-m-d H:i:s');
        $threshold = gmdate('Y-m-d H:i:s', time() - $interval);
        $statement = $db->prepare("UPDATE calendar_settings SET updated_at=? WHERE setting_key='sync_lock' AND updated_at < ?");
        $statement->execute([$now, $threshold]);
        if ($statement->rowCount() === 1) {
            return true;
        }
        $exists = $db->prepare("SELECT COUNT(*) FROM calendar_settings WHERE setting_key='sync_lock'");
        $exists->execute();
        if ((int) $exists->fetchColumn() > 0) {
            return false;
        }
        try {
            $db->prepare("INSERT INTO calendar_settings(setting_key,setting_value,updated_at) VALUES('sync_lock','{}',?)")->execute([$now]);

            return true;
        } catch (PDOException $error) {
            return false;
        }
    }

    private static function publicRow(array $row, array $keys): array
    {
        return array_intersect_key($row, array_flip($keys));
    }

    private static function date(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone(self::TZ));
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Érvénytelen dátum.');
        }

        return $date;
    }

    private static function dateTime(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', strlen($value) === 16 ? $value . ':00' : $value, new DateTimeZone(self::TZ));
        if (!$date) {
            throw new InvalidArgumentException('Érvénytelen időpont.');
        }

        return $date;
    }
}
