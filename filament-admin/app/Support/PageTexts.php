<?php

namespace App\Support;

/**
 * Full-page text overrides of the public website (content key "page_text", language "hu").
 * Payload: { "<page>.<n>": { "v": value, "rich": bool, "orig": original, "page": path, "at": time, "by": admin } }
 * The ids and original texts come from scripts/tag-editable-text.py (resources/defaults/page-text.json).
 */
class PageTexts
{
    public const CONTENT_KEY = 'page_text';

    public const LANGUAGE = 'hu';

    public const KEY_PATTERN = '/^[a-z0-9-]{1,40}\.\d{1,5}(@c\d{1,5})?$/';

    public const MAX_LENGTH = 5000;

    /** @return array<string, array{page: string, pages: array<int, string>, tag: string, rich: bool, text: string}> */
    public static function manifest(): array
    {
        static $manifest = null;
        if ($manifest === null) {
            $path = resource_path('defaults/page-text.json');
            $manifest = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
        }

        return $manifest;
    }

    public static function saved(): array
    {
        return SiteContent::get(self::CONTENT_KEY, self::LANGUAGE) ?? [];
    }

    public static function normalisePlain(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));
    }

    /**
     * Applies changes and stores them.
     *
     * @param  array<string, array{value?: string, rich?: bool, original?: string|null, reset?: bool}>  $changes
     * @return array{saved: array<int, string>, removed: array<int, string>}
     */
    public static function apply(array $changes, string $page, ?string $editor): array
    {
        $payload = self::saved();
        $manifest = self::manifest();
        $result = ['saved' => [], 'removed' => []];

        foreach ($changes as $key => $change) {
            if (! preg_match(self::KEY_PATTERN, (string) $key)) {
                continue;
            }
            if (! empty($change['reset'])) {
                unset($payload[$key]);
                $result['removed'][] = $key;
                continue;
            }
            // Copies of items ("index.12@c3") share the original of their source text.
            $base = explode('@', (string) $key)[0];
            $rich = $manifest[$base]['rich'] ?? (bool) ($change['rich'] ?? false);
            $value = $rich ? HtmlSanitizer::clean((string) ($change['value'] ?? '')) : self::normalisePlain((string) ($change['value'] ?? ''));
            $original = $manifest[$base]['text'] ?? (string) ($change['original'] ?? '');
            $originalComparable = $rich ? HtmlSanitizer::clean($original) : self::normalisePlain($original);

            if ($value === $originalComparable) {
                unset($payload[$key]);
                $result['removed'][] = $key;
                continue;
            }
            $payload[$key] = [
                'v' => mb_substr($value, 0, self::MAX_LENGTH),
                'rich' => $rich,
                'orig' => mb_substr($original, 0, 2000),
                'page' => mb_substr($page, 0, 120),
                'at' => now('UTC')->format('Y-m-d H:i:s'),
                'by' => $editor,
            ];
            $result['saved'][] = $key;
        }

        ksort($payload, SORT_NATURAL);
        SiteContent::put(self::CONTENT_KEY, self::LANGUAGE, 'Weboldal szövegei (teljes szerkesztő)', $payload);

        return $result;
    }
}
