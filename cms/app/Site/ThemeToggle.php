<?php

declare(strict_types=1);

namespace CometCMS\Site;

/**
 * The bundled light/dark switch, allowed by its exact CSP hash. It is a
 * classic script in the head, so a saved choice applies before first paint.
 */
final class ThemeToggle
{
    public static function script(): string
    {
        return (string) file_get_contents(__DIR__ . '/assets/theme-toggle.js');
    }

    public static function cspSource(): string
    {
        return "'sha256-" . base64_encode(hash('sha256', self::script(), true)) . "'";
    }
}
