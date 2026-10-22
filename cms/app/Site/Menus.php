<?php

declare(strict_types=1);

namespace CometCMS\Site;

/** Bundled menu behavior that closes open dropdowns and panels, allowed by its exact CSP hash. */
final class Menus
{
    public static function script(): string
    {
        return (string) file_get_contents(__DIR__ . '/assets/menus.js') . "\ninitMenus(document);";
    }

    public static function cspSource(): string
    {
        return "'sha256-" . base64_encode(hash('sha256', self::script(), true)) . "'";
    }
}
