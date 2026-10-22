<?php

declare(strict_types=1);

namespace CometCMS\Site;

/** Only bundled interactions and installed block scripts included in the rendered page may execute. */
final class SiteScripts
{
    public static function cspDirective(string $html, ?SiteContext $ctx = null): string
    {
        $sources = [];
        foreach ([GalleryLightbox::class, Tabs::class, SiteSearch::class, ThemeToggle::class, Menus::class, ListFilters::class] as $module) {
            if (str_contains($html, '>' . $module::script() . '</script>')) {
                $sources[] = $module::cspSource();
            }
        }

        if ($ctx !== null) {
            array_push($sources, ...\CometCMS\Site\Blocks\BlockScripts::cspSources($html, $ctx));
        }

        return 'script-src ' . ($sources === [] ? "'none'" : implode(' ', $sources));
    }
}
