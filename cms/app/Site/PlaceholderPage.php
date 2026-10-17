<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Core\Security;

/** A self-contained landing page for installations without a homepage. */
final class PlaceholderPage
{
    public static function render(string $title = 'CometCMS', string $lang = 'en'): string
    {
        $title = Security::e($title);
        $lang = Security::e($lang);
        // Embed the existing brand asset so this also works before the admin
        // is built, offline and in subdirectory installations.
        $logo = base64_encode((string) file_get_contents(__DIR__ . '/assets/cometcms-white.png'));

        ob_start();
        require __DIR__ . '/views/placeholder.php';

        return (string) ob_get_clean();
    }
}
