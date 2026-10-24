<?php

declare(strict_types=1);

namespace CometCMS\Site;

/** Bundled tab interactions, allowed by their exact CSP hash. */
final class Tabs
{
    public static function script(): string
    {
        return (string) file_get_contents(__DIR__ . '/assets/tabs.js') . "\ninitTabs(document);";
    }

    public static function cspSource(): string
    {
        return "'sha256-" . base64_encode(hash('sha256', self::script(), true)) . "'";
    }
}
