<?php

declare(strict_types=1);

namespace CometCMS\Site;

/** Bundled internal page viewer, allowed by its exact CSP hash. */
final class LinkModal
{
    public static function script(): string
    {
        return (string) file_get_contents(__DIR__ . '/assets/link-modal.js') . "\ninitLinkModal(document);";
    }

    public static function cspSource(): string
    {
        return "'sha256-" . base64_encode(hash('sha256', self::script(), true)) . "'";
    }
}
