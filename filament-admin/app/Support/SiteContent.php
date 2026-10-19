<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Read/write access to the legacy `content` table (composite key content_key + language)
 * that the public website reads through /api/content/{key}?lang=xx.
 */
class SiteContent
{
    public const LANGUAGES = ['hu' => 'Magyar', 'en' => 'English', 'de' => 'Deutsch'];

    public static function get(string $key, string $language): ?array
    {
        $row = DB::table('content')->where('content_key', $key)->where('language', $language)->first();
        if (! $row) {
            return null;
        }
        $payload = json_decode((string) $row->payload, true);

        return is_array($payload) ? $payload : null;
    }

    public static function updatedAt(string $key, string $language): ?string
    {
        return DB::table('content')->where('content_key', $key)->where('language', $language)->value('updated_at');
    }

    public static function put(string $key, string $language, string $title, array $payload): void
    {
        DB::table('content')->updateOrInsert(
            ['content_key' => $key, 'language' => $language],
            [
                'title' => $title,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now('UTC')->format('Y-m-d H:i:s'),
            ]
        );
    }

    /** Defaults copied from the previous admin (admin.js), used when nothing is saved yet. */
    public static function defaults(string $key): array
    {
        static $defaults = null;
        if ($defaults === null) {
            $defaults = json_decode((string) file_get_contents(resource_path('defaults/content.json')), true) ?: [];
        }

        return $defaults[$key] ?? [];
    }
}
