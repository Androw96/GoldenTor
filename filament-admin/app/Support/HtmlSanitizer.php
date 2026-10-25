<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list sanitiser for the short rich texts of the public pages
 * (headings with <br>/<em>, buttons with an icon <span>, links in paragraphs).
 */
class HtmlSanitizer
{
    /** tag => allowed attributes */
    public const ALLOWED = [
        'a' => ['href', 'target', 'rel', 'class'],
        'strong' => ['class'], 'em' => ['class'], 'b' => [], 'i' => [], 'br' => [],
        'span' => ['class', 'aria-hidden'], 'small' => ['class'], 'sup' => [], 'sub' => [],
    ];

    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'svg', 'math', 'form', 'input', 'textarea', 'select', 'button'];

    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"?><div id="gt-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('gt-root');
        if (! $root) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        self::cleanChildren($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private static function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node->nodeType === XML_COMMENT_NODE || $node->nodeType === XML_PI_NODE) {
                $parent->removeChild($node);
                continue;
            }
            if (! $node instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($node->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $parent->removeChild($node);
                continue;
            }
            self::cleanChildren($node);
            if (! array_key_exists($tag, self::ALLOWED)) {
                // Browser-made <div>/<p> on Enter become line breaks; other tags are unwrapped.
                if (in_array($tag, ['div', 'p'], true) && $node->previousSibling) {
                    $parent->insertBefore($node->ownerDocument->createElement('br'), $node);
                }
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                continue;
            }
            foreach (iterator_to_array($node->attributes) as $attribute) {
                if (! in_array(strtolower($attribute->name), self::ALLOWED[$tag], true)) {
                    $node->removeAttribute($attribute->name);
                }
            }
            if ($tag === 'a' && $node->hasAttribute('href')
                && ! preg_match('#^(https?:|mailto:|tel:|/|\#|[\w.-]+\.html)#i', trim($node->getAttribute('href')))) {
                $node->removeAttribute('href');
            }
            if ($tag === 'a' && $node->getAttribute('target') === '_blank') {
                $node->setAttribute('rel', 'noopener');
            }
        }
    }
}
