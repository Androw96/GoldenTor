<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Saved page structure of the public website (content key "page_layout", language "hu").
 *
 * Payload: { "<prefix>": {
 *   "order":  { "<page>.main" | "<list id>": ["<section/item id>", ...] },
 *   "hidden": ["<section/item/text/link id>", ...],
 *   "clones": { "<prefix>.c<n>": { "source": "<item id>", "list": "<list id>" } },
 *   "images": { "<image id>": { "src": "/assets/...", "alt": "..." } },
 *   "links":  { "<link id>": { "href": "..." } }
 * } }
 * Prefix = page name, or "header"/"footer" for the parts shared by every page.
 * Ids are written into the HTML by scripts/tag-editable-text.py; copies get "@c<n>" suffixes.
 */
class PageLayouts
{
    public const CONTENT_KEY = 'page_layout';

    public const LANGUAGE = 'hu';

    private const PREFIX = '/^[a-z0-9-]{1,40}$/';

    private const SUFFIX = '(?:@c\d{1,5})?';

    public static function all(): array
    {
        $payload = SiteContent::get(self::CONTENT_KEY, self::LANGUAGE);

        return is_array($payload) ? $payload : [];
    }

    public static function forPrefix(string $prefix): array
    {
        return self::all()[$prefix] ?? [];
    }

    /** @param  array<string, array>  $layouts  prefix => layout (already validated) */
    public static function store(array $layouts): void
    {
        $all = self::all();
        foreach ($layouts as $prefix => $layout) {
            if (self::isEmpty($layout)) {
                unset($all[$prefix]);
            } else {
                $all[$prefix] = $layout;
            }
        }
        ksort($all);
        if (strlen((string) json_encode($all)) > 512 * 1024) {
            throw new InvalidArgumentException('Túl sok szerkezeti módosítás egyszerre.');
        }
        SiteContent::put(self::CONTENT_KEY, self::LANGUAGE, 'Weboldal szerkezete (oldalszerkesztő)', $all);
    }

    public static function reset(string $prefix): void
    {
        self::store([$prefix => []]);
    }

    public static function isEmpty(array $layout): bool
    {
        foreach (['order', 'hidden', 'clones', 'images', 'links'] as $part) {
            if (! empty($layout[$part])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validates one prefix's layout and returns a clean copy.
     *
     * @throws InvalidArgumentException with a user-readable (Hungarian) message
     */
    public static function clean(string $prefix, mixed $layout): array
    {
        if (! preg_match(self::PREFIX, $prefix)) {
            throw new InvalidArgumentException("Érvénytelen oldalazonosító: {$prefix}");
        }
        if (! is_array($layout)) {
            throw new InvalidArgumentException('Hibás szerkezeti adat.');
        }
        $p = preg_quote($prefix, '/');
        $struct = fn (string $letters) => '/^' . $p . '\.(?:[' . $letters . ']\d{1,5}|c\d{1,5})' . self::SUFFIX . '$/';
        $textId = '/^' . $p . '\.\d{1,5}' . self::SUFFIX . '$/';
        $clean = ['order' => [], 'hidden' => [], 'clones' => [], 'images' => [], 'links' => []];

        foreach (self::mapOf($layout, 'order', 100) as $container => $ids) {
            $isMain = $container === $prefix . '.main';
            if (! $isMain && ! preg_match('/^' . $p . '\.l\d{1,5}' . self::SUFFIX . '$/', (string) $container)) {
                throw new InvalidArgumentException("Érvénytelen tároló: {$container}");
            }
            $clean['order'][$container] = self::idList($ids, $isMain ? '/^' . $p . '\.s\d{1,5}$/' : $struct('i'), 300);
        }

        $hidden = [];
        foreach (self::listOf($layout, 'hidden', 500) as $id) {
            $id = (string) $id;
            if (! preg_match($struct('sikl'), $id) && ! preg_match($textId, $id)) {
                throw new InvalidArgumentException("Érvénytelen elrejtett elem: {$id}");
            }
            $hidden[$id] = true;
        }
        $clean['hidden'] = array_keys($hidden);

        foreach (self::mapOf($layout, 'clones', 200) as $cloneId => $clone) {
            if (! preg_match('/^' . $p . '\.c\d{1,5}$/', (string) $cloneId) || ! is_array($clone)) {
                throw new InvalidArgumentException("Érvénytelen másolat: {$cloneId}");
            }
            $source = (string) ($clone['source'] ?? '');
            $list = (string) ($clone['list'] ?? '');
            if (! preg_match($struct('i'), $source) || ! preg_match('/^' . $p . '\.l\d{1,5}' . self::SUFFIX . '$/', $list)) {
                throw new InvalidArgumentException("Érvénytelen másolat forrása: {$cloneId}");
            }
            $clean['clones'][$cloneId] = ['source' => $source, 'list' => $list];
        }

        foreach (self::mapOf($layout, 'images', 100) as $imageId => $image) {
            if (! preg_match('/^' . $p . '\.m\d{1,5}' . self::SUFFIX . '$/', (string) $imageId) || ! is_array($image)) {
                throw new InvalidArgumentException("Érvénytelen kép: {$imageId}");
            }
            $src = self::safeUrl((string) ($image['src'] ?? ''), true);
            if ($src === null) {
                throw new InvalidArgumentException('Érvénytelen képcím. Csak feltöltött kép vagy https:// cím használható.');
            }
            $clean['images'][$imageId] = ['src' => $src, 'alt' => mb_substr(trim(strip_tags((string) ($image['alt'] ?? ''))), 0, 300)];
        }

        foreach (self::mapOf($layout, 'links', 300) as $linkId => $link) {
            if (! preg_match('/^' . $p . '\.k\d{1,5}' . self::SUFFIX . '$/', (string) $linkId) || ! is_array($link)) {
                throw new InvalidArgumentException("Érvénytelen link: {$linkId}");
            }
            $href = self::safeUrl((string) ($link['href'] ?? ''));
            if ($href === null) {
                throw new InvalidArgumentException('Érvénytelen linkcím: ' . mb_substr((string) ($link['href'] ?? ''), 0, 80));
            }
            $clean['links'][$linkId] = ['href' => $href];
        }

        return $clean;
    }

    /** Allowed: https/http URLs, mailto:, tel:, #anchors, /paths, page.html links, assets/ paths. */
    public static function safeUrl(string $url, bool $image = false): ?string
    {
        $url = trim($url);
        if (! $image && preg_match('/^tel:/i', $url)) {
            $url = (string) preg_replace('/\s+/', '', $url);
        }
        if ($url === '' || mb_strlen($url) > 500 || preg_match('/[\s"\'<>\\\\]/', $url) || str_contains($url, '..')) {
            return null;
        }
        if (preg_match('#^https?://#i', $url)) {
            return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
        }
        if (preg_match('#^/(?!/)#', $url) || preg_match('#^assets/#', $url)) {
            return $url;
        }
        if ($image) {
            return null;
        }
        if (preg_match('/^mailto:[^@\s]+@[^@\s]+$/i', $url) || preg_match('/^tel:\+?[0-9 ()\-]{3,30}$/i', $url)
            || preg_match('/^#[\w\-]{0,80}$/', $url) || preg_match('/^[\w.\-]+\.html(?:[?#][\w=&%.\-]*)?$/', $url)) {
            return $url;
        }

        return null;
    }

    private static function mapOf(array $layout, string $part, int $max): array
    {
        $value = $layout[$part] ?? [];
        if (! is_array($value) || count($value) > $max) {
            throw new InvalidArgumentException("Hibás vagy túl nagy szerkezeti rész: {$part}");
        }

        return $value;
    }

    private static function listOf(array $layout, string $part, int $max): array
    {
        $value = $layout[$part] ?? [];
        if (! is_array($value) || ! array_is_list($value) || count($value) > $max) {
            throw new InvalidArgumentException("Hibás vagy túl nagy szerkezeti rész: {$part}");
        }

        return $value;
    }

    private static function idList(mixed $ids, string $pattern, int $max): array
    {
        if (! is_array($ids) || ! array_is_list($ids) || count($ids) > $max) {
            throw new InvalidArgumentException('Hibás sorrend.');
        }
        $clean = [];
        foreach ($ids as $id) {
            if (! is_string($id) || ! preg_match($pattern, $id)) {
                throw new InvalidArgumentException('Érvénytelen elem a sorrendben: ' . (is_string($id) ? $id : gettype($id)));
            }
            $clean[$id] = true;
        }

        return array_keys($clean);
    }
}
