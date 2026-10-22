<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Core\Security;
use CometCMS\Localization\Messages;

/**
 * Builds complete HTML documents: header region, the layout of the page,
 * footer region, and the <head> with SEO metadata.
 */
final class PageRenderer
{
    private NodeRenderer $nodes;
    private ?\Closure $savedRender = null;

    public function __construct(private readonly SiteContext $ctx)
    {
        $this->nodes = new NodeRenderer($ctx);
    }

    public function entryPage(array $type, array $entry, int $page = 1): string
    {
        $this->savedRender = function (SiteContext $saved) use ($type, $entry, $page): ?string {
            $original = $saved->findEntry($type['name'], (string) ($entry['uid'] ?? $entry['id']), true);
            if ($original === null || ($original['status'] ?? '') !== ($entry['status'] ?? '')
                || ($original['published_at'] ?? null) !== ($entry['published_at'] ?? null)) {
                return null;
            }
            return (new self($saved))->entryPage($type, $original, $page);
        };
        $name = (string) $type['name'];
        $url = $this->ctx->entryUrl($name, $entry) ?? $this->ctx->homeUrl();
        $isHome = $this->ctx->entryPath($name, $entry) === '/';
        $state = $this->state($entry, $type, $url, $page, $this->entryAlternates($type, $entry));
        $main = $this->nodes->render($this->ctx->layout($name, 'detail'), $state);
        $title = (string) ($entry['title'] ?? '');

        return $this->document([
            'title' => $isHome ? $this->homeTitle() : $this->pageTitle($title),
            'description' => $this->entryDescription($type, $entry),
            'image' => $this->entryImage($type, $entry),
            'canonical' => $url,
            'og_type' => $isHome ? 'website' : 'article',
            'body_class' => 'c-type-' . $name . ' c-view-detail' . ($isHome ? ' c-view-home' : ''),
            'published_at' => (string) ($entry['published_at'] ?? ''),
        ], $main, $state);
    }

    public function indexPage(array $type, int $page = 1): string
    {
        $this->savedRender = fn(SiteContext $saved): string => (new self($saved))->indexPage($type, $page);
        $name = (string) $type['name'];
        $url = $this->ctx->indexUrl($name) ?? $this->ctx->homeUrl();
        $isHome = $this->ctx->indexPath($name) === '/';
        $alternates = [];
        foreach ($this->ctx->locales() as $locale) {
            $alternates[$locale] = $this->ctx->indexUrl($name, $locale) ?? $this->ctx->homeUrl($locale);
        }

        $state = $this->state(null, $type, $url, $page, $alternates);
        $main = $this->nodes->render($this->ctx->layout($name, 'index'), $state);
        $route = $this->ctx->route($name);
        $title = (string) (($route['index_title'] ?? '') ?: $type['label']);

        return $this->document([
            'title' => $isHome ? $this->homeTitle() : $this->pageTitle($title . ($page > 1 ? ' – ' . $page : '')),
            'description' => (string) ($this->ctx->settings()['description'] ?? ''),
            'canonical' => $url . ($page > 1 ? (str_contains($url, '?') ? '&' : '?') . 'page=' . $page : ''),
            'og_type' => 'website',
            'body_class' => 'c-type-' . $name . ' c-view-index' . ($isHome ? ' c-view-home' : ''),
        ], $main, $state);
    }

    /** Branded blank page shown until a public homepage is available. */
    public function autoHome(): string
    {
        $this->savedRender = null;
        if ($this->ctx->previewBanner) {
            return $this->document(['title' => $this->homeTitle(), 'noindex' => true],
                '<section class="c-section"><div class="c-container"><h1>' . Security::e($this->homeTitle()) . '</h1></div></section>',
                $this->state(null, null, $this->ctx->homeUrl(), 1, []));
        }
        return PlaceholderPage::render($this->homeTitle(), $this->ctx->htmlLang());
    }

    public function previewExpired(): string
    {
        $this->savedRender = null;
        $de = str_starts_with((string) ($this->ctx->adminUser['language'] ?? 'en'), 'de');
        $title = $de ? 'Diese Vorschau ist nicht mehr verfügbar' : 'This preview is no longer available';
        $message = $de ? 'Öffne eine neue Vorschau im Editor, um deine aktuellen Änderungen zu sehen.' : 'Open a new preview from the editor to see your current changes.';
        return $this->document(['title' => $title, 'noindex' => true],
            '<section class="c-section c-py-2xl"><div class="c-container c-container-narrow"><h1>' . Security::e($title) . '</h1><p>' . Security::e($message) . '</p></div></section>',
            $this->state(null, null, $this->ctx->homeUrl(), 1, []));
    }

    public function notFound(string $path): string
    {
        $this->savedRender = fn(SiteContext $saved): string => (new self($saved))->notFound($path);
        $settings = $this->ctx->settings();
        foreach (['not_found_title', 'not_found_message'] as $key) if ($settings[$key] === '' && empty($settings['_localized'][$key])) $settings[$key] = Messages::get($key, $this->ctx->htmlLang());
        $state = $this->state(null, null, $path, 1, []);
        $main = '<section class="c-section c-section-narrow c-py-2xl"><div class="c-container c-container-narrow">'
            . '<div class="c-el"><h1 class="c-heading">' . Security::e($settings['not_found_title']) . '</h1></div>'
            . '<div class="c-el"><p>' . Security::e($settings['not_found_message']) . '</p></div>'
            . '<div class="c-el"><a class="c-button c-button-primary c-button-md" href="' . Security::e($this->ctx->homeUrl()) . '">' . Security::e($this->homeLabel()) . '</a></div>'
            . '</div></section>';

        return $this->document([
            'title' => $this->pageTitle($settings['not_found_title']),
            'description' => '',
            'canonical' => '',
            'noindex' => true,
            'body_class' => 'c-view-404',
        ], $main, $state);
    }

    /**
     * Builder preview of one layout. Only the edited layout carries node
     * annotations; header and footer are shown for context.
     */
    public function layoutPreview(array $type, string $variant, ?array $entry): string
    {
        $this->savedRender = function (SiteContext $saved) use ($type, $variant, $entry): string {
            $original = $entry !== null ? $saved->findEntry($type['name'], (string) ($entry['uid'] ?? $entry['id']), true) : null;
            return (new self($saved))->layoutPreview($type, $variant, $original);
        };
        $name = (string) $type['name'];
        $entry ??= $this->sampleEntry($type);
        $layout = $this->ctx->layout($name, $variant);

        if ($variant === 'index') {
            $url = $this->ctx->indexUrl($name) ?? $this->ctx->homeUrl();
            $alternates = [];
            foreach ($this->ctx->locales() as $locale) {
                $alternate = $this->ctx->indexUrl($name, $locale);
                if ($alternate !== null) $alternates[$locale] = $alternate;
            }
            $state = $this->state(null, $type, $url, 1, $alternates);
        } else {
            $url = $this->ctx->entryUrl($name, $entry) ?? $this->ctx->homeUrl();
            $state = $this->state($entry, $type, $url, 1, $this->entryAlternates($type, $entry));
        }

        $main = $this->nodes->render($layout, $state);

        return $this->document([
            'title' => $this->pageTitle((string) ($variant === 'index' ? $type['label'] : ($entry['title'] ?? ''))),
            'noindex' => true,
            'body_class' => 'c-type-' . $name . ' c-view-' . $variant,
        ], $main, $state, true, true);
    }

    /**
     * Builder preview of one entry's page content (see SiteContext::$pageDraft)
     * in its type's detail layout. Only the page content is annotated; the
     * rest of the page is shown for context. A layout without a place for the
     * page field shows the content below it, so it can still be edited.
     */
    public function pagePreview(array $type, array $entry, string $field): string
    {
        $name = (string) $type['name'];
        $layout = $this->ctx->layout($name, 'detail');
        if (!isset(LayoutSchema::pagePlaces($layout)[$field])) {
            $layout[] = LayoutSchema::node(LayoutSchema::PAGE_CONTENT, ['field' => $field]);
        }
        $url = $this->ctx->entryUrl($name, $entry) ?? $this->ctx->homeUrl();
        $state = $this->state($entry, $type, $url, 1, $this->entryAlternates($type, $entry));
        $main = $this->nodes->renderPlain($layout, $state);

        return $this->document([
            'title' => $this->pageTitle((string) ($entry['title'] ?? '')),
            'noindex' => true,
            'body_class' => 'c-type-' . $name . ' c-view-detail c-view-page-edit',
        ], $main, $state, true, true, 'page');
    }

    public function regionPreview(string $region): string
    {
        $this->savedRender = fn(SiteContext $saved): string => (new self($saved))->regionPreview($region);
        $state = $this->state(null, null, $this->ctx->homeUrl(), 1, []);
        $main = '<section class="c-section c-section-contained c-py-2xl"><div class="c-container c-container-contained">'
            . '<div class="c-preview-main" data-placeholder="regionMain">Page content</div></div></section>';

        return $this->document(['title' => $this->ctx->siteTitle(), 'noindex' => true, 'body_class' => 'c-view-region-preview'], $main, $state, true, false, $region);
    }

    /**
     * Builder preview of one component, inside the plain section and row it
     * needs to lay out like on a page. A component of a content type shows
     * the given (or a sample) entry of that type.
     */
    public function componentPreview(array $component, ?array $type, ?array $entry): string
    {
        $this->savedRender = function (SiteContext $saved) use ($component, $type, $entry): ?string {
            $original = $saved->component($component['id']);
            if ($original === null) return null;
            $savedEntry = $type !== null && $entry !== null ? $saved->findEntry($type['name'], (string) ($entry['uid'] ?? $entry['id']), true) : null;
            return (new self($saved))->componentPreview($original, $type, $savedEntry);
        };
        if ($type !== null) {
            $entry ??= $this->sampleEntry($type);
            $url = $this->ctx->entryUrl((string) $type['name'], $entry) ?? $this->ctx->homeUrl();
            $state = $this->state($entry, $type, $url, 1, $this->entryAlternates($type, $entry));
        } else {
            $state = $this->state(null, null, $this->ctx->homeUrl(), 1, []);
        }

        $html = $this->nodes->render($component['nodes'], $state);
        if ($component['root'] === 'column') {
            $html = '<div class="c-row c-gap-md c-items-stretch c-justify-start">' . $html . '</div>';
        }
        if ($component['root'] !== 'section') {
            $html = '<section class="c-section c-section-contained c-py-xl"><div class="c-container c-container-contained">' . $html . '</div></section>';
        }

        return $this->document([
            'title' => $this->pageTitle((string) $component['name']),
            'noindex' => true,
            'body_class' => 'c-view-component-preview' . ($type !== null ? ' c-type-' . $type['name'] : ''),
        ], '<div data-drop-root>' . $html . '</div>', $state, false);
    }

    public function sampleEntry(array $type): array
    {
        return [
            'id' => 'sample',
            'slug' => 'sample',
            'collection' => (string) $type['name'],
            'title' => 'Sample ' . strtolower((string) $type['label']),
            'status' => 'published',
            'published_at' => $this->ctx->previewTime ??= Security::now(),
            'created_at' => $this->ctx->previewTime ??= Security::now(),
            'updated_at' => $this->ctx->previewTime ??= Security::now(),
        ];
    }

    // ─── Document ────────────────────────────────────────────────────────────

    /**
     * @param bool $withRegions  include header and footer
     * @param bool $plainRegions render header/footer without builder annotations
     * @param string $annotatedRegion the region being edited in the builder
     */
    private function document(array $meta, string $main, array $state, bool $withRegions = true, bool $plainRegions = false, string $annotatedRegion = ''): string
    {
        $settings = $this->ctx->settings();
        $header = $footer = '';

        if ($withRegions) {
            $regionState = $state;
            $render = function (string $region) use ($regionState, $plainRegions, $annotatedRegion): string {
                $nodes = $this->ctx->region($region);
                $plain = $plainRegions || ($annotatedRegion !== '' && $annotatedRegion !== $region);

                return $plain ? $this->nodes->renderPlain($nodes, $regionState) : $this->nodes->render($nodes, $regionState);
            };
            $dropRoot = fn(string $region): string => $this->ctx->annotate && $annotatedRegion === $region ? ' data-drop-root' : '';
            $header = '<header class="c-region c-header"' . $dropRoot('header') . '>' . $render('header') . '</header>';
            $footer = '<footer class="c-region c-footer"' . $dropRoot('footer') . '>' . $render('footer') . '</footer>';
        }

        $title = Security::e((string) ($meta['title'] ?? $this->ctx->siteTitle()));
        $description = trim((string) ($meta['description'] ?? ''));
        $canonical = (string) ($meta['canonical'] ?? '');
        $noindex = ($meta['noindex'] ?? false) || $this->ctx->preview || !empty($settings['noindex']);
        $head = '<meta charset="utf-8">'
            // Previews are shown in the admin (in an iframe, or as a page); they
            // never run scripts even though the admin itself does.
            . ($this->ctx->preview ? '<meta http-equiv="Content-Security-Policy" content="script-src \'none\'; object-src \'none\'; base-uri \'none\'; form-action \'none\'">' : '')
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . $title . '</title>'
            . '<meta name="generator" content="CometCMS">';

        if ($description !== '') {
            $head .= '<meta name="description" content="' . Security::e($description) . '">';
        }

        if ($noindex) {
            $head .= '<meta name="robots" content="noindex,nofollow">';
        }

        if ($canonical !== '' && !$this->ctx->preview) {
            $head .= '<link rel="canonical" href="' . Security::e($this->ctx->absolute($canonical)) . '">';
            $head .= '<meta property="og:url" content="' . Security::e($this->ctx->absolute($canonical)) . '">';
        }

        $head .= '<meta property="og:title" content="' . $title . '">'
            . '<meta property="og:site_name" content="' . Security::e($this->ctx->siteTitle()) . '">'
            . '<meta property="og:type" content="' . Security::e((string) ($meta['og_type'] ?? 'website')) . '">';

        if ($description !== '') {
            $head .= '<meta property="og:description" content="' . Security::e($description) . '">';
        }

        $image = $meta['image'] ?? null;
        if ($image === null && ($settings['og_image'] ?? '') !== '') {
            $image = $this->ctx->mediaItem($settings['og_image']);
        }
        if (is_array($image) && str_starts_with((string) $image['mime'], 'image/')) {
            $head .= '<meta property="og:image" content="' . Security::e($this->ctx->absolute($this->ctx->mediaUrl($image['name'], $this->ogImageQuery($image)))) . '">'
                . '<meta name="twitter:card" content="summary_large_image">';
            if (($image['alt'] ?? '') !== '') {
                $head .= '<meta property="og:image:alt" content="' . Security::e((string) $image['alt']) . '">';
            }
        }

        if (($meta['published_at'] ?? '') !== '') {
            $head .= '<meta property="article:published_time" content="' . Security::e((string) $meta['published_at']) . '">';
        }

        $alternates = is_array($state['alternates'] ?? null) ? $state['alternates'] : [];
        if (count($alternates) > 1 && !$this->ctx->preview) {
            foreach ($alternates as $locale => $url) {
                $head .= '<link rel="alternate" hreflang="' . Security::e((string) $locale) . '" href="' . Security::e($this->ctx->absolute($url)) . '">';
            }
            $default = $alternates[$this->ctx->defaultLocale()] ?? null;
            if ($default !== null) {
                $head .= '<link rel="alternate" hreflang="x-default" href="' . Security::e($this->ctx->absolute($default)) . '">';
            }
        }

        $favicon = ($settings['favicon'] ?? '') !== '' ? $this->ctx->mediaItem($settings['favicon']) : null;
        if ($favicon !== null) {
            $head .= '<link rel="icon" href="' . Security::e($this->ctx->mediaUrl($favicon['name'])) . '">';
        }

        // Previews render unsaved themes, so they inline the stylesheet.
        $head .= $this->ctx->preview
            ? '<style>' . Theme::stylesheet($this->ctx->theme(), new FontRegistry($this->ctx->site->workspace()), $this->ctx->basePath) . '</style>'
            : '<link rel="stylesheet" href="' . Security::e($this->ctx->stylesheetUrl()) . '">'
                . '<link rel="stylesheet" href="' . Security::e($this->ctx->customStylesheetUrl()) . '">';

        // The theme toggle's script runs before the body, so a saved choice never flashes.
        if (!$this->ctx->preview && str_contains($header . $main . $footer, 'data-theme-toggle')) {
            $head .= '<script>' . ThemeToggle::script() . '</script>';
        }

        $opening = '<!doctype html><html lang="' . Security::e($this->ctx->htmlLang()) . '"><head>' . $head . '</head>'
            . '<body class="c-site ' . Security::e((string) ($meta['body_class'] ?? '')) . ($this->ctx->annotate ? ' c-builder' : '') . '">';
        $content = '<a class="c-skip-link" href="#main">' . Security::e(Messages::get('skip', $this->ctx->htmlLang())) . '</a>'
            . $header
            . '<main id="main" class="c-main"' . ($this->ctx->annotate && $annotatedRegion === '' && $withRegions ? ' data-drop-root' : '') . '>' . $main . '</main>'
            . $footer
            . (!$this->ctx->preview && str_contains($header . $main . $footer, 'data-gallery-lightbox')
                ? '<script type="module">' . GalleryLightbox::script() . '</script>' : '')
            . (!$this->ctx->preview && str_contains($header . $main . $footer, 'data-tabs=')
                ? '<script type="module">' . Tabs::script() . '</script>' : '')
            . (!$this->ctx->preview && str_contains($header . $main . $footer, 'data-search=')
                ? '<script type="module">' . SiteSearch::script() . '</script>' : '')
            . (!$this->ctx->preview && str_contains($header . $main . $footer, ' data-menu ')
                ? '<script type="module">' . Menus::script() . '</script>' : '')
            . '</body></html>';
        $banner = '';
        if ($this->ctx->previewBanner) {
            // Compare the page that was actually rendered, including shared regions,
            // embedded entries, locale and theme. A token alone does not imply changes.
            $saved = $this->ctx->previewToken !== '' && $this->savedRender !== null
                ? ($this->savedRender)($this->ctx->withoutOverrides()) : null;
            $unsaved = $this->ctx->previewToken !== '' && ($saved === null
                || $this->comparisonHtml($saved) !== $this->comparisonHtml($opening . $content));
            $banner = (new PreviewAdminBar($this->ctx))->render($state, $unsaved);
        }
        return $opening . $banner . $content;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function comparisonHtml(string $html): string
    {
        // Default layouts can generate fresh node IDs. Menu and form control IDs
        // do not change the displayed content; preserve their relationships and order.
        preg_match_all('/<input type="checkbox" id="([^"]+)" class="c-menu-toggle"|<input type="hidden" name="_anchor" value="([^"]+)"/', $html, $matches, PREG_SET_ORDER);
        $ids = [];
        foreach ($matches as $match) {
            $id = ($match[1] ?? '') !== '' ? $match[1] : $match[2];
            $ids[$id] ??= '__preview_control_' . count($ids);
        }
        return strtr($html, $ids);
    }

    private function state(?array $entry, ?array $type, string $path, int $page, array $alternates): array
    {
        // A previewed draft shows the date it gets once published instead of an unresolved reference.
        if ($this->ctx->preview && $entry !== null && empty($entry['published_at'])) {
            $entry['published_at'] = $this->ctx->previewTime ??= Security::now();
        }
        return [
            'entry' => $entry,
            'type' => $type,
            'path' => $this->ctx->browsePreview ? $path : (strtok($path, '?') ?: $path),
            'page' => $page,
            'alternates' => $alternates,
            'depth' => 0,
        ];
    }

    private function entryAlternates(array $type, array $entry): array
    {
        $alternates = [];

        foreach ($this->ctx->locales() as $locale) {
            if ($this->ctx->content->isPubliclyVisible($entry, $locale)) {
                $url = $this->ctx->entryUrl((string) $type['name'], $entry, $locale);
                if ($url !== null) {
                    $alternates[$locale] = $url;
                }
            }
        }

        return $alternates;
    }

    private function pageTitle(string $title): string
    {
        $site = $this->ctx->siteTitle();
        $template = (string) ($this->ctx->settings()['title_template'] ?? '{title} · {site}');

        return $title === '' ? $site : strtr($template, ['{title}' => $title, '{site}' => $site]);
    }

    private function homeTitle(): string
    {
        $tagline = (string) ($this->ctx->settings()['tagline'] ?? '');

        return $this->ctx->siteTitle() . ($tagline !== '' ? ' · ' . $tagline : '');
    }

    private function homeLabel(): string
    {
        return Messages::get('back_home', $this->ctx->htmlLang());
    }

    private function entryDescription(array $type, array $entry): string
    {
        $fields = is_array($type['fields'] ?? null) ? $type['fields'] : [];
        $route = $this->ctx->route((string) $type['name']) ?? [];
        $field = (string) ($route['seo_description_field'] ?? '');

        if ($field === '' || !isset($fields[$field])) {
            $field = '';
            // Text fields first; page content describes an entry that has no such text.
            foreach ([['textarea', 'markdown', 'html'], ['page']] as $types) {
                foreach ($fields as $name => $config) {
                    if (is_array($config) && in_array($config['type'] ?? '', $types, true) && !empty($entry[$name])) {
                        $field = (string) $name;
                        break 2;
                    }
                }
            }
        }

        if ($field !== '' && ($fields[$field]['type'] ?? '') === 'page') {
            $text = LayoutSchema::text(LayoutSchema::pageNodes($entry[$field] ?? null), $this->ctx->htmlLang(), $this->ctx->defaultLocale());
            if ($text !== '') {
                return Text::summary($text);
            }
        } elseif ($field !== '' && is_scalar($entry[$field] ?? null) && (string) $entry[$field] !== '') {
            $type = (string) ($fields[$field]['type'] ?? 'text');
            $value = (string) $entry[$field];
            $text = match ($type) {
                'markdown' => Markdown::toText($value),
                'html' => html_entity_decode(strip_tags(str_replace(['</p>', '<br>'], ' ', $value)), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                default => $value,
            };

            return Text::summary($text);
        }

        return (string) ($this->ctx->settings()['description'] ?? '');
    }

    private function entryImage(array $type, array $entry): ?array
    {
        $fields = is_array($type['fields'] ?? null) ? $type['fields'] : [];
        $route = $this->ctx->route((string) $type['name']) ?? [];
        $candidates = [];
        $preferred = (string) ($route['seo_image_field'] ?? '');

        if ($preferred !== '' && ($fields[$preferred]['type'] ?? '') === 'media') {
            $candidates[] = $preferred;
        }

        foreach ($fields as $name => $config) {
            if (is_array($config) && ($config['type'] ?? '') === 'media') {
                $candidates[] = (string) $name;
            }
        }

        foreach (array_unique($candidates) as $field) {
            foreach ((array) ($entry[$field] ?? []) as $file) {
                $item = is_scalar($file) ? $this->ctx->mediaItem((string) $file) : null;
                if ($item !== null && str_starts_with((string) $item['mime'], 'image/')) {
                    return $item;
                }
            }
        }

        return null;
    }

    private function ogImageQuery(array $image): array
    {
        $descriptor = $this->ctx->variantDescriptor($image['name']);

        if ($descriptor === null || $descriptor['widths'] === []) {
            return [];
        }

        $width = null;
        foreach ($descriptor['widths'] as $candidate) {
            if ($candidate <= 1280) {
                $width = $candidate;
            }
        }

        // Social networks do not all accept WebP; JPEG is the safe choice.
        return $width === null ? [] : ['w' => $width, 'format' => in_array('jpeg', $descriptor['formats'], true) ? 'jpeg' : $descriptor['default_format']];
    }

}
