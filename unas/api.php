<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const GOLDENTOR_SLOTS = ['09:00', '11:00', '14:00', '16:00'];

function route(): string {
    return trim((string) ($_GET['route'] ?? ''), '/');
}

function query_value(string $key): string {
    return clean_text($_GET[$key] ?? '', 254);
}

function valid_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && mb_strlen($email) <= 254;
}

function public_url(string $path = ''): string {
    return rtrim(config()['public_url'], '/') . $path;
}

function calculation_payload(array $payload): array {
    $type = $payload['calculator_type'] ?? '';
    $fields = [
        'investment' => ['capital', 'monthly', 'rate', 'years', 'total', 'paid', 'growth'],
        'loan' => ['amount', 'rate', 'years', 'payment', 'total', 'cost'],
        'reserve' => ['expense', 'dependants', 'months', 'current', 'target', 'coverage', 'gap'],
    ];
    if (!isset($fields[$type]) || !is_array($payload['inputs'] ?? null) || !is_array($payload['results'] ?? null)) {
        throw new InvalidArgumentException('A kalkuláció adatai hiányosak.');
    }
    $combined = array_merge($payload['inputs'], $payload['results']);
    if (array_diff(array_keys($combined), $fields[$type]) || array_diff($fields[$type], array_keys($combined))) {
        throw new InvalidArgumentException('A kalkuláció adatai hiányosak.');
    }
    foreach ($combined as $value) {
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
            throw new InvalidArgumentException('Érvénytelen kalkulációs adat.');
        }
    }
    return [$type, $payload['inputs'], $payload['results']];
}

function create_contact(array $payload): void {
    $name = clean_text($payload['name'] ?? '', 120);
    $email = mb_strtolower(clean_text($payload['email'] ?? '', 254));
    if (!valid_email($email)) {
        throw new InvalidArgumentException('Érvénytelen e-mail-cím.');
    }
    $phone = clean_text($payload['phone'] ?? '', 60, false);
    $topic = clean_text($payload['topic'] ?? '', 120);
    $message = clean_text($payload['message'] ?? '', 2000);
    $statement = db()->prepare('INSERT INTO contacts(created_at,name,email,phone,topic,message) VALUES(?,?,?,?,?,?)');
    $statement->execute([now(), $name, $email, $phone, $topic, $message]);
    send_mail('Golden Tor - új kapcsolatfelvétel', "Név: {$name}\nE-mail: {$email}\nTelefon: " . ($phone ?: '-') . "\nTéma: {$topic}\n\n{$message}", config()['contact_recipient']);
    json_response(['ok' => true, 'id' => (int) db()->lastInsertId()], 201);
}

function create_booking(array $payload): void {
    $name = clean_text($payload['name'] ?? '', 120);
    $email = mb_strtolower(clean_text($payload['email'] ?? '', 254));
    $phone = clean_text($payload['phone'] ?? '', 60, false);
    $topic = clean_text($payload['topic'] ?? '', 120);
    $meeting = clean_text($payload['meeting'] ?? '', 120);
    $date = clean_text($payload['date'] ?? '', 10);
    $time = clean_text($payload['time'] ?? '', 5);
    $message = clean_text($payload['message'] ?? '', 2000, false);
    $selected = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Europe/Budapest'));
    if (!valid_email($email)) {
        throw new InvalidArgumentException('Érvénytelen e-mail-cím.');
    }
    if (!$selected || $selected->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Érvénytelen dátum.');
    }
    $today = new DateTimeImmutable('today', new DateTimeZone('Europe/Budapest'));
    if ($selected <= $today || (int) $selected->format('N') > 5) {
        throw new InvalidArgumentException('Kérjük, válasszon egy jövőbeli munkanapot.');
    }
    if (!in_array($time, GOLDENTOR_SLOTS, true)) {
        throw new InvalidArgumentException('Érvénytelen időpont.');
    }
    $token = bin2hex(random_bytes(24));
    $statement = db()->prepare('INSERT INTO bookings(public_token,created_at,name,email,phone,topic,meeting,booking_date,booking_time,message,active_slot) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
    $statement->execute([$token, now(), $name, $email, $phone, $topic, $meeting, $date, $time . ':00', $message, $date . ' ' . $time]);
    $portal = public_url('/ugyfelportal.html?token=' . rawurlencode($token));
    send_mail('Golden Tor - új időpontkérés', "Név: {$name}\nE-mail: {$email}\nIdőpont: {$date} {$time}\n\nÜgyfélportál: {$portal}", config()['contact_recipient']);
    send_mail('Golden Tor - időpontkérés fogadva', "Kedves {$name}!\n\nFogadtuk időpontkérését: {$date} {$time}.\n\nÁllapot és naptár: {$portal}", $email);
    json_response(['ok' => true, 'id' => (int) db()->lastInsertId(), 'token' => $token, 'portalUrl' => $portal, 'calendarUrl' => '/api/calendar.ics?token=' . rawurlencode($token)], 201);
}

function account_post(string $path, array $payload): void {
    require_same_origin();
    if (rate_limited('account')) {
        json_response(['error' => 'Túl sok próbálkozás. Kérjük, próbálja meg 10 perc múlva.'], 429);
    }
    if ($path === 'auth/register' || $path === 'auth/login') {
        $email = mb_strtolower(clean_text($payload['email'] ?? '', 254));
        $password = $payload['password'] ?? '';
        if (!valid_email($email) || !is_string($password) || strlen($password) < 10 || strlen($password) > 128) {
            throw new InvalidArgumentException('Érvényes e-mail-cím és 10–128 karakteres jelszó szükséges.');
        }
        start_session();
        if ($path === 'auth/register') {
            $name = clean_text($payload['name'] ?? '', 120);
            if (($payload['privacy'] ?? false) !== true) {
                throw new InvalidArgumentException('Kérjük, olvassa el az adatkezelési tájékoztatót.');
            }
            $statement = db()->prepare('INSERT INTO users(email,name,password_hash,created_at) VALUES(?,?,?,?)');
            $statement->execute([$email, $name, password_hash($password, PASSWORD_DEFAULT), now()]);
            $id = (int) db()->lastInsertId();
        } else {
            $statement = db()->prepare('SELECT id,password_hash FROM users WHERE email=?');
            $statement->execute([$email]);
            $row = $statement->fetch();
            if (!$row || !password_verify($password, $row['password_hash'])) {
                json_response(['error' => 'Hibás e-mail-cím vagy jelszó.'], 401);
            }
            $id = (int) $row['id'];
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $id;
        $_SESSION['expires_at'] = time() + 8 * 60 * 60;
        json_response(['ok' => true], $path === 'auth/register' ? 201 : 200);
    }
    if ($path === 'auth/logout') {
        start_session();
        $_SESSION = [];
        setcookie(session_name(), '', time() - 3600, '/');
        session_destroy();
        json_response(['ok' => true]);
    }
    $user = require_user();
    if ($path === 'profile') {
        $name = clean_text($payload['name'] ?? '', 120);
        $phone = clean_text($payload['phone'] ?? '', 40, false);
        db()->prepare('UPDATE users SET name=?,phone=? WHERE id=?')->execute([$name, $phone, $user['id']]);
        json_response(['ok' => true]);
    }
    if ($path === 'feedback') {
        $expert = clean_text($payload['expert'] ?? '', 120);
        $rating = $payload['rating'] ?? 0;
        if (!is_int($rating) || $rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Válasszon 1 és 5 közötti értékelést.');
        }
        $message = clean_text($payload['message'] ?? '', 3000);
        db()->prepare('INSERT INTO feedback(user_id,expert,rating,message,created_at) VALUES(?,?,?,?,?)')->execute([$user['id'], $expert, $rating, $message, now()]);
        json_response(['ok' => true], 201);
    }
    if ($path === 'calculations') {
        [$type, $inputs, $results] = calculation_payload($payload);
        db()->prepare('INSERT INTO calculations(user_id,calculator_type,inputs_json,results_json,created_at) VALUES(?,?,?,?,?)')->execute([$user['id'], $type, json_encode($inputs), json_encode($results), now()]);
        db()->prepare('DELETE FROM calculations WHERE user_id=? AND id NOT IN (SELECT id FROM (SELECT id FROM calculations WHERE user_id=? ORDER BY id DESC LIMIT 100) AS recent)')->execute([$user['id'], $user['id']]);
        json_response(['ok' => true, 'id' => (int) db()->lastInsertId()], 201);
    }
    json_response(['error' => 'Ismeretlen végpont.'], 404);
}

function get_availability(): void {
    $date = query_value('date');
    $selected = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Europe/Budapest'));
    if (!$selected || $selected->format('Y-m-d') !== $date) {
        json_response(['error' => 'Érvénytelen dátum.'], 400);
    }
    $today = new DateTimeImmutable('today', new DateTimeZone('Europe/Budapest'));
    if ($selected <= $today || (int) $selected->format('N') > 5) {
        json_response(['slots' => []]);
    }
    $statement = db()->prepare("SELECT TIME_FORMAT(booking_time, '%H:%i') AS booking_time FROM bookings WHERE booking_date=? AND status!='cancelled'");
    $statement->execute([$date]);
    $busy = array_column($statement->fetchAll(), 'booking_time');
    $slots = array_map(static fn(string $time): array => ['time' => $time, 'available' => !in_array($time, $busy, true)], GOLDENTOR_SLOTS);
    json_response(['date' => $date, 'slots' => $slots]);
}

function get_booking(): void {
    $token = query_value('token');
    $statement = db()->prepare("SELECT id,created_at,name,topic,meeting,booking_date,TIME_FORMAT(booking_time, '%H:%i') AS booking_time,status FROM bookings WHERE public_token=?");
    $statement->execute([$token]);
    $row = $statement->fetch();
    if (!$row) {
        json_response(['error' => 'A foglalás nem található.'], 404);
    }
    json_response($row);
}

function cancel_booking(array $payload): void {
    $token = clean_text($payload['token'] ?? '', 100);
    $statement = db()->prepare("UPDATE bookings SET status='cancelled',active_slot=NULL WHERE public_token=? AND status IN ('requested','confirmed')");
    $statement->execute([$token]);
    if ($statement->rowCount() !== 1) {
        json_response(['error' => 'A foglalás nem található vagy már nem mondható le.'], 404);
    }
    json_response(['ok' => true, 'status' => 'cancelled']);
}

function get_calendar(): void {
    $token = query_value('token');
    $statement = db()->prepare('SELECT * FROM bookings WHERE public_token=?');
    $statement->execute([$token]);
    $row = $statement->fetch();
    if (!$row) {
        json_response(['error' => 'A foglalás nem található.'], 404);
    }
    $start = new DateTimeImmutable("{$row['booking_date']} {$row['booking_time']}", new DateTimeZone('Europe/Budapest'));
    $end = $start->modify('+45 minutes');
    $escape = static fn(string $value): string => str_replace(["\\", ";", ",", "\r", "\n"], ["\\\\", "\\;", "\\,", '', "\\n"], $value);
    $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Golden Tor Consulting//HU\r\nBEGIN:VEVENT\r\n"
        . 'UID:' . substr(hash('sha256', $token), 0, 24) . "@goldentor.hu\r\n"
        . 'DTSTAMP:' . gmdate('Ymd\\THis\\Z') . "\r\n"
        . 'DTSTART:' . $start->format('Ymd\\THis') . "\r\n"
        . 'DTEND:' . $end->format('Ymd\\THis') . "\r\n"
        . 'SUMMARY:' . $escape('Golden Tor konzultáció - ' . $row['topic']) . "\r\n"
        . 'DESCRIPTION:' . $escape($row['meeting']) . "\r\nLOCATION:Golden Tor Consulting / egyeztetés szerint\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="golden-tor-konzultacio.ics"');
    echo $ics;
    exit;
}

function get_calculations(): void {
    $user = require_user();
    $statement = db()->prepare('SELECT id,calculator_type,inputs_json,results_json,created_at FROM calculations WHERE user_id=? ORDER BY id DESC LIMIT 100');
    $statement->execute([$user['id']]);
    $items = array_map(static fn(array $row): array => [
        'id' => (int) $row['id'],
        'calculator_type' => $row['calculator_type'],
        'created_at' => $row['created_at'],
        'inputs' => json_decode($row['inputs_json'], true),
        'results' => json_decode($row['results_json'], true),
    ], $statement->fetchAll());
    json_response(['items' => $items]);
}

function get_content(string $key): void {
    $language = clean_text($_GET['lang'] ?? 'hu', 10);
    if (!preg_match('/^[a-z_]{1,60}$/', $key) || !preg_match('/^[a-z]{2,10}$/', $language)) {
        json_response(['error' => 'Érvénytelen tartalmi útvonal.'], 400);
    }
    $statement = db()->prepare('SELECT content_key,language,title,payload,updated_at FROM content WHERE content_key=? AND language=?');
    $statement->execute([$key, $language]);
    $row = $statement->fetch();
    if (!$row) {
        json_response(['error' => 'A tartalom nem található.'], 404);
    }
    $row['payload'] = json_decode($row['payload'], true);
    json_response($row);
}

function admin_rows(string $table): void {
    require_admin();
    $allowed = ['bookings', 'contacts'];
    if (!in_array($table, $allowed, true)) {
        json_response(['error' => 'Ismeretlen végpont.'], 404);
    }
    $rows = db()->query("SELECT * FROM {$table} ORDER BY id DESC LIMIT 250")->fetchAll();
    json_response(['items' => $rows]);
}

function admin_stats(): void {
    require_admin();
    $database = db();
    $bookings = $database->query("SELECT COUNT(*) total,SUM(status='confirmed') confirmed,SUM(status='completed') completed FROM bookings")->fetch();
    $contacts = (int) $database->query('SELECT COUNT(*) FROM contacts')->fetchColumn();
    $upcoming = $database->query("SELECT COUNT(*) FROM bookings WHERE booking_date>=CURDATE() AND status IN ('requested','confirmed')")->fetchColumn();
    $monthly = $database->query("SELECT COUNT(*) FROM bookings WHERE created_at>=DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-01')")->fetchColumn();
    $topics = $database->query('SELECT topic,COUNT(*) count FROM bookings GROUP BY topic ORDER BY count DESC LIMIT 5')->fetchAll();
    $total = (int) ($bookings['total'] ?? 0);
    $confirmed = (int) ($bookings['confirmed'] ?? 0) + (int) ($bookings['completed'] ?? 0);
    json_response(['bookings' => $total, 'contacts' => $contacts, 'upcoming' => (int) $upcoming, 'monthly' => (int) $monthly, 'conversion' => $total ? (int) round($confirmed / $total * 100) : 0, 'topics' => $topics]);
}

function admin_feedback(): void {
    require_admin();
    $rows = db()->query('SELECT f.*,u.name,u.email FROM feedback f JOIN users u ON u.id=f.user_id ORDER BY f.id DESC LIMIT 250')->fetchAll();
    json_response(['items' => $rows]);
}

function admin_customers(): void {
    require_admin();
    $rows = db()->query('SELECT u.id,u.name,u.email,u.phone,u.created_at,COUNT(c.id) AS calculation_count FROM users u LEFT JOIN calculations c ON c.user_id=u.id GROUP BY u.id ORDER BY u.id DESC LIMIT 250')->fetchAll();
    json_response(['items' => $rows]);
}

function update_admin_status(string $route, array $payload): void {
    require_admin();
    $parts = explode('/', $route);
    $table = $parts[1] ?? '';
    $id = filter_var($parts[2] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!in_array($table, ['bookings', 'contacts'], true) || !$id) {
        json_response(['error' => 'Érvénytelen kérés.'], 400);
    }
    $status = clean_text($payload['status'] ?? '', 24);
    if ($table === 'contacts') {
        if (!in_array($status, ['new', 'in_progress', 'done', 'archived'], true)) {
            throw new InvalidArgumentException('Érvénytelen státusz.');
        }
        $statement = db()->prepare('UPDATE contacts SET status=? WHERE id=?');
        $statement->execute([$status, $id]);
        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException('A megkeresés nem található.');
        }
        json_response(['ok' => true]);
    }
    if (!in_array($status, ['requested', 'confirmed', 'completed', 'cancelled'], true)) {
        throw new InvalidArgumentException('Érvénytelen státusz.');
    }
    $database = db();
    $statement = $database->prepare('SELECT * FROM bookings WHERE id=?');
    $statement->execute([$id]);
    $booking = $statement->fetch();
    if (!$booking) {
        throw new InvalidArgumentException('A foglalás nem található.');
    }
    $active = $status === 'cancelled' ? null : $booking['booking_date'] . ' ' . substr($booking['booking_time'], 0, 5);
    $statement = $database->prepare('UPDATE bookings SET status=?,active_slot=? WHERE id=?');
    $statement->execute([$status, $active, $id]);
    if ($status !== $booking['status'] && in_array($status, ['confirmed', 'completed', 'cancelled'], true)) {
        $labels = ['confirmed' => 'visszaigazoltuk', 'completed' => 'lezártnak jelöltük', 'cancelled' => 'lemondottnak jelöltük'];
        send_mail('Golden Tor - időpont frissítve', "Kedves {$booking['name']}!\n\nA {$booking['booking_date']} {$booking['booking_time']} időpontra szóló konzultációját {$labels[$status]}.\n\nRészletek: " . public_url('/ugyfelportal.html?token=' . $booking['public_token']), $booking['email']);
    }
    json_response(['ok' => true]);
}

function put_content(string $route, array $payload): void {
    require_admin();
    $parts = explode('/', $route);
    $key = $parts[2] ?? '';
    $language = $parts[3] ?? '';
    if (!preg_match('/^[a-z_]{1,60}$/', $key) || !preg_match('/^[a-z]{2,10}$/', $language)) {
        throw new InvalidArgumentException('Érvénytelen tartalmi útvonal.');
    }
    $title = clean_text($payload['title'] ?? '', 180);
    $content = $payload['payload'] ?? null;
    if (!is_array($content)) {
        throw new InvalidArgumentException('A tartalom formátuma hibás.');
    }
    $statement = db()->prepare('INSERT INTO content(content_key,language,title,payload,updated_at) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),payload=VALUES(payload),updated_at=VALUES(updated_at)');
    $statement->execute([$key, $language, $title, json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), now()]);
    json_response(['ok' => true]);
}

try {
    $path = route();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        if ($path === 'health') {
            json_response(['ok' => true, 'time' => gmdate('c')]);
        }
        if ($path === 'auth/me') {
            json_response(['user' => current_user()]);
        }
        if ($path === 'calculations') {
            get_calculations();
        }
        if ($path === 'member/valiora') {
            require_user();
            json_response(['html' => file_get_contents(GOLDENTOR_ROOT . '/private/valiora.html')]);
        }
        if ($path === 'availability') {
            get_availability();
        }
        if ($path === 'booking') {
            get_booking();
        }
        if ($path === 'calendar.ics') {
            get_calendar();
        }
        if (str_starts_with($path, 'content/')) {
            get_content(substr($path, strlen('content/')));
        }
        if ($path === 'admin/stats') {
            admin_stats();
        }
        if ($path === 'admin/bookings') {
            admin_rows('bookings');
        }
        if ($path === 'admin/contacts') {
            admin_rows('contacts');
        }
        if ($path === 'admin/feedback') {
            admin_feedback();
        }
        if ($path === 'admin/customers') {
            admin_customers();
        }
    }

    if ($method === 'POST') {
        $payload = request_json();
        if (str_starts_with($path, 'auth/') || in_array($path, ['profile', 'feedback', 'calculations'], true)) {
            account_post($path, $payload);
        }
        if (rate_limited('public')) {
            json_response(['error' => 'Túl sok kérés. Próbálja később.'], 429);
        }
        if (($payload['website'] ?? '') !== '') {
            json_response(['ok' => true]);
        }
        if ($path === 'contact') {
            create_contact($payload);
        }
        if ($path === 'bookings') {
            create_booking($payload);
        }
        if ($path === 'booking/cancel') {
            cancel_booking($payload);
        }
    }

    if ($method === 'PATCH' && str_starts_with($path, 'admin/')) {
        update_admin_status($path, request_json());
    }
    if ($method === 'PUT' && str_starts_with($path, 'admin/content/')) {
        put_content($path, request_json());
    }
    json_response(['error' => 'Ismeretlen végpont.'], 404);
} catch (InvalidArgumentException $error) {
    json_response(['error' => $error->getMessage()], 400);
} catch (PDOException $error) {
    if ($error->getCode() === '23000') {
        json_response(['error' => 'Ez az időpont időközben foglalttá vált. Kérjük, válasszon másikat.'], 409);
    }
    error_log('Golden Tor database error: ' . $error->getMessage());
    json_response(['error' => 'Átmeneti szerverhiba. Kérjük, próbálja később.'], 500);
}
