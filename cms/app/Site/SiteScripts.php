<?php

declare(strict_types=1);

namespace CometCMS\Site;

/** Bundled interactions and the CAPTCHA providers used by the rendered page may execute. */
final class SiteScripts
{
    public static function cspDirective(string $html): string
    {
        $sources = [];
        foreach ([GalleryLightbox::class, LinkModal::class, Tabs::class, SiteSearch::class, ThemeToggle::class, Menus::class, ListFilters::class, FormCaptcha::class] as $module) {
            if (str_contains($html, '>' . $module::script() . '</script>')) {
                $sources[] = $module::cspSource();
            }
        }
        $sources = [...$sources, ...FormCaptcha::cspSources($html)['script-src']];

        return 'script-src ' . ($sources === [] ? "'none'" : implode(' ', $sources));
    }
}
