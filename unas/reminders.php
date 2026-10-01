<?php
declare(strict_types=1);

// Invoke through the UNAS PHP cron runner every five minutes. Web requests are
// denied in .htaccess so booking data cannot be read through this file.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/bootstrap.php';

$now = new DateTimeImmutable('now', new DateTimeZone('Europe/Budapest'));
$start = $now->modify('+23 hours 30 minutes');
$end = $now->modify('+24 hours 30 minutes');
$statement = db()->query("SELECT * FROM bookings WHERE status='confirmed' AND reminder_sent_at IS NULL");
$update = db()->prepare('UPDATE bookings SET reminder_sent_at=? WHERE id=? AND reminder_sent_at IS NULL');

foreach ($statement->fetchAll() as $booking) {
    $appointment = new DateTimeImmutable("{$booking['booking_date']} {$booking['booking_time']}", new DateTimeZone('Europe/Budapest'));
    if ($appointment < $start || $appointment > $end) {
        continue;
    }
    send_mail('Golden Tor - emlékeztető a holnapi konzultációra', "Kedves {$booking['name']}!\n\nEmlékeztetjük, hogy konzultációja holnap, {$booking['booking_date']} {$booking['booking_time']} időpontban lesz.\nMód: {$booking['meeting']}\nTéma: {$booking['topic']}", $booking['email']);
    $update->execute([now(), $booking['id']]);
}
