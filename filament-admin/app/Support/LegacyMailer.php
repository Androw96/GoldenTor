<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Sends plain-text mail through PHP mail(), exactly like the public API on the UNAS host,
 * so the admin and the website use the same (already working) delivery path.
 */
class LegacyMailer
{
    public static function send(string $to, string $subject, string $body): void
    {
        if (! config('goldentor.mail_enabled')) {
            Log::info('Golden Tor mail (not sent, mail disabled)', ['to' => $to, 'subject' => $subject]);

            return;
        }

        $from = (string) config('goldentor.mail_from');
        $headers = "From: {$from}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit";
        $encodedSubject = function_exists('mb_encode_mimeheader') ? mb_encode_mimeheader($subject, 'UTF-8') : $subject;

        if (! @mail($to, $encodedSubject, $body, $headers)) {
            Log::warning('Golden Tor mail could not be handed to mail()', ['to' => $to, 'subject' => $subject]);
        }
    }
}
