<?php

declare(strict_types=1);

namespace CometCMS\Site;

/** Bundled gallery interactions, allowed by their exact CSP hash. */
final class GalleryLightbox
{
    public static function script(): string
    {
        return (string) file_get_contents(__DIR__ . '/assets/gallery.js') . "\ninitGalleryLightbox(document);";
    }

    public static function cspSource(): string
    {
        return "'sha256-" . base64_encode(hash('sha256', self::script(), true)) . "'";
    }
}
