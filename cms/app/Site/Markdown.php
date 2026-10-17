<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Core\HtmlSanitizer;

/**
 * Markdown to safe HTML. Parsedown is bundled (no Composer on shared hosts).
 * Inline HTML is allowed like in any Markdown editor; the shared HTML
 * allow-list is the security boundary and removes anything active.
 */
final class Markdown
{
    private static ?\Parsedown $parser = null;

    public static function toHtml(string $markdown): string
    {
        if (trim($markdown) === '') {
            return '';
        }

        return HtmlSanitizer::sanitize(self::parser()->text($markdown));
    }

    /** Plain text for excerpts and meta descriptions. */
    public static function toText(string $markdown): string
    {
        return trim(html_entity_decode(strip_tags(self::toHtml($markdown)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function parser(): \Parsedown
    {
        if (self::$parser === null) {
            require_once COMET_APP . '/Vendor/Parsedown/Parsedown.php';
            $parser = new \Parsedown();
            $parser->setBreaksEnabled(true);
            self::$parser = $parser;
        }

        return self::$parser;
    }
}
