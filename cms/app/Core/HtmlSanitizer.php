<?php

declare(strict_types=1);

namespace CometCMS\Core;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list HTML cleaner shared by HTML fields, rendered Markdown and the
 * website's rich-text elements. Unknown tags are unwrapped (their text is
 * kept), active content is dropped together with its contents.
 */
final class HtmlSanitizer
{
    /** @var array<string, list<string>> */
    public const ALLOWED_TAGS = [
        'a' => ['href', 'title', 'target', 'rel'],
        'blockquote' => [],
        'br' => [],
        'code' => [],
        'div' => ['class'],
        'em' => [],
        'h1' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'h5' => [],
        'h6' => [],
        'hr' => [],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'li' => [],
        'mark' => [],
        'ol' => [],
        'p' => [],
        'pre' => [],
        's' => [],
        'span' => ['class'],
        'strong' => [],
        'sub' => [],
        'sup' => [],
        'table' => [],
        'tbody' => [],
        'td' => [],
        'th' => [],
        'thead' => [],
        'tr' => [],
        'u' => [],
        'ul' => [],
    ];

    private const DROP_WITH_CONTENTS = ['iframe', 'object', 'script', 'style'];

    public static function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="__cometcms_html_root__">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('__cometcms_html_root__');

        if (!$root instanceof DOMElement) {
            return '';
        }

        self::sanitizeChildren($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    public static function isSafeUrl(string $url): bool
    {
        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/') || str_starts_with($url, './') || str_starts_with($url, '../')) {
            return true;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return $scheme === null || in_array(strtolower((string) $scheme), ['http', 'https', 'mailto', 'tel'], true);
    }

    private static function sanitizeChildren(DOMNode $node): void
    {
        for ($child = $node->firstChild; $child !== null;) {
            $next = $child->nextSibling;
            self::sanitizeNode($child);
            $child = $next;
        }
    }

    private static function sanitizeNode(DOMNode $node): void
    {
        if (!$node instanceof DOMElement) {
            return;
        }

        $tag = strtolower($node->tagName);

        if (in_array($tag, self::DROP_WITH_CONTENTS, true)) {
            $node->parentNode?->removeChild($node);
            return;
        }

        self::sanitizeChildren($node);

        if (!array_key_exists($tag, self::ALLOWED_TAGS)) {
            self::unwrapNode($node);
            return;
        }

        $allowedAttributes = self::ALLOWED_TAGS[$tag];

        foreach (iterator_to_array($node->attributes ?? []) as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = trim($attribute->nodeValue ?? '');

            if (!in_array($name, $allowedAttributes, true) || !self::isSafeAttributeValue($tag, $name, $value)) {
                $node->removeAttribute($attribute->nodeName);
            }
        }

        if ($tag === 'a' && strtolower($node->getAttribute('target')) === '_blank') {
            $node->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function unwrapNode(DOMElement $node): void
    {
        $parent = $node->parentNode;

        if ($parent === null) {
            return;
        }

        while ($node->firstChild !== null) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }

    private static function isSafeAttributeValue(string $tag, string $name, string $value): bool
    {
        if ($name === 'href' || $name === 'src') {
            return self::isSafeUrl($value);
        }

        if ($name === 'target') {
            return in_array($value, ['_blank', '_self', '_parent', '_top'], true);
        }

        if (($name === 'width' || $name === 'height') && $tag === 'img') {
            return preg_match('/^\d{1,4}$/', $value) === 1;
        }

        return true;
    }
}
