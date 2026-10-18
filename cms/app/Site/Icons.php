<?php

declare(strict_types=1);

namespace CometCMS\Site;

/** Trusted, locally bundled Material Design icons shared with the builder. */
final class Icons
{
    private static ?array $catalog = null;

    public static function catalog(): array
    {
        return self::$catalog ??= json_decode((string) file_get_contents(__DIR__ . '/assets/icons.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function names(): array
    {
        return array_keys(self::catalog());
    }
}
