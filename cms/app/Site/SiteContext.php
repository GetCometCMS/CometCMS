<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Content\ContentRepository;
use CometCMS\Media\MediaRepository;
use CometCMS\Localization\LocalizedValue;

/**
 * Everything one website render needs: configuration, content access, URL
 * building and the current locale. Builder previews pass unsaved settings,
 * theme, menus and layouts as overrides so they render exactly like the
 * live site will after saving.
 *
 * Content is read the way the public API reads it: only public content types
 * and only published (or due scheduled) entries, unless a preview explicitly
 * allows the one entry being previewed.
 */
final class SiteContext
{
    public string $locale = '';

    /** Builder previews mark nodes with data attributes and show placeholders. */
    public bool $annotate = false;

    /** Draft previews and builder previews are never indexed or cached. */
    public bool $preview = false;

    /** Draft previews of an entry carry a notice that the page is not live yet. */
    public bool $previewBanner = false;

    private array $overrides = [];
    private array $mediaItems = [];
    private array $entries = [];
    private array $components = [];
    private array $cardTemplates = [];
    private ?FormRepository $forms = null;
    private array $formCache = [];

    public function __construct(
        public readonly SiteRepository $site,
        public readonly ContentRepository $content,
        public readonly MediaRepository $media,
        public readonly string $basePath = '',
        public readonly string $origin = '',
    ) {}

    public static function make(SiteRepository $site, string $basePath = '', string $origin = ''): self
    {
        $workspace = $site->workspace();

        return new self($site, ContentRepository::make($workspace), new MediaRepository($workspace), rtrim($basePath, '/'), rtrim($origin, '/'));
    }

    // ─── Configuration (with preview overrides) ──────────────────────────────

    public function override(string $key, mixed $value): void
    {
        $this->overrides[$key] = $value;
    }

    public function settings(): array
    {
        $raw = $this->overrides['settings'] ?? $this->site->settings();
        $resolved = LocalizedValue::tree($raw, $this->locale ?: $this->defaultLocale(), $this->defaultLocale());
        foreach ($raw as $key => $value) if (LocalizedValue::is($value)) $resolved['_localized'][$key] = true;
        return $resolved;
    }

    public function theme(): array
    {
        return $this->overrides['theme'] ?? $this->site->theme();
    }

    public function menus(): array
    {
        return LocalizedValue::tree($this->overrides['menus'] ?? $this->site->menus(), $this->htmlLang(), $this->defaultLocale());
    }

    public function menu(string $name): ?array
    {
        foreach ($this->menus() as $menu) {
            if (($menu['name'] ?? '') === $name) {
                return $menu;
            }
        }

        return null;
    }

    public function region(string $region): array
    {
        return $this->overrides['region:' . $region] ?? $this->site->region($region);
    }

    public function layout(string $type, string $variant): array
    {
        return $this->overrides['layout:' . $type . ':' . $variant] ?? $this->site->layout($type, $variant);
    }

    /** The standard card of a content type (see SiteRepository::cardTemplate()). */
    public function cardTemplate(string $type): array
    {
        return $this->cardTemplates[$type] ??= $this->site->cardTemplate($type);
    }

    /** A reusable component; the builder overrides the one being edited. */
    public function component(string $id): ?array
    {
        if (array_key_exists('component:' . $id, $this->overrides)) {
            return $this->overrides['component:' . $id];
        }
        if (!array_key_exists($id, $this->components)) {
            $this->components[$id] = $this->site->component($id);
        }

        return $this->components[$id];
    }

    /** A website form; null when it does not exist (any more). */
    public function form(string $id): ?array
    {
        if ($id === '') {
            return null;
        }
        if (!array_key_exists($id, $this->formCache)) {
            $this->formCache[$id] = $this->forms()->find($id);
        }

        $form = LocalizedValue::tree($this->formCache[$id], $this->htmlLang(), $this->defaultLocale());
        if ($form !== null) foreach ($this->formCache[$id] as $key => $value) if (LocalizedValue::is($value)) $form['_localized'][$key] = true;
        return $form;
    }

    public function forms(): FormRepository
    {
        return $this->forms ??= new FormRepository($this->site);
    }

    /** Where a form posts to (see SiteController::submitForm()). */
    public function formAction(string $id): string
    {
        return $this->basePath . '/_site/forms/' . rawurlencode($this->site->workspace()->slug()) . '/' . rawurlencode($id);
    }

    /** Where search elements look up their hits (see SiteController::search()). */
    public function searchUrl(): string
    {
        return $this->basePath . '/_site/search/' . rawurlencode($this->site->workspace()->slug());
    }

    public function siteTitle(): string
    {
        $title = (string) ($this->settings()['title'] ?? '');

        return $title !== '' ? $title : $this->site->workspaceLabel();
    }

    public function type(string $name): ?array
    {
        return LocalizedValue::tree($this->site->types()[$name] ?? null, $this->htmlLang(), $this->defaultLocale());
    }

    /** Public content types can be shown; private ones never leave the API. */
    public function isPublicType(string $name): bool
    {
        $type = $this->type($name);

        return $type !== null && ($type['visibility'] ?? 'public') !== 'private';
    }

    public function route(string $type): ?array
    {
        $route = $this->settings()['routes'][$type] ?? null;

        return is_array($route) && $route['enabled'] && $this->isPublicType($type) && in_array($this->locale ?: $this->defaultLocale(), $this->locales(), true) ? $route : null;
    }

    public function defaultLocale(): string
    {
        return (string) (($this->overrides['settings'] ?? $this->site->settings())['default_locale'] ?? 'en');
    }

    /** @return list<string> */
    public function locales(): array
    {
        return array_values((array) ($this->settings()['locales'] ?? []));
    }

    public function htmlLang(): string
    {
        $locale = $this->locale !== '' ? $this->locale : $this->defaultLocale();

        return $locale !== '' ? $locale : 'en';
    }

    // ─── URLs ────────────────────────────────────────────────────────────────

    /** Site-relative path (without locale prefix) to a full URL path. */
    public function path(string $path, ?string $locale = null): string
    {
        $locale ??= $this->locale;
        $prefix = $locale !== '' && $locale !== $this->defaultLocale() && in_array($locale, $this->locales(), true) ? '/' . $locale : '';
        $path = '/' . ltrim($path, '/');

        if ($prefix !== '' && $path === '/') {
            return $this->basePath . $prefix;
        }

        return $this->basePath . $prefix . ($path === '/' && $this->basePath !== '' ? '/' : $path);
    }

    public function absolute(string $url): string
    {
        return str_starts_with($url, '/') && !str_starts_with($url, '//') ? $this->origin . $url : $url;
    }

    public function homeUrl(?string $locale = null): string
    {
        return $this->path('/', $locale);
    }

    public function entryPath(string $type, array $entry): ?string
    {
        $home = $this->settings()['homepage'] ?? [];
        $slug = (string) ($entry['slug'] ?? $entry['id'] ?? '');
        $schema = $this->type($type);

        if ($schema === null || !$this->isPublicType($type)) {
            return null;
        }

        if (($home['type'] ?? '') === $type && ($home['entry'] ?? '') === ($entry['uid'] ?? $entry['id'])) {
            return '/';
        }

        $route = $this->route($type);
        if ($route === null || $slug === '') {
            return null;
        }

        return str_replace('{slug}', rawurlencode($slug), $route['pattern']);
    }

    public function entryUrl(string $type, array $entry, ?string $locale = null): ?string
    {
        $before = $this->locale;
        $this->locale = $locale ?? ($this->locale ?: $this->defaultLocale());
        try {
            if (!in_array($this->locale, $this->locales(), true)) return null;
            $entry = $this->content->resolveLocaleFields($entry, $this->locale, !$this->preview);
            $path = $this->entryPath($type, $entry);
            return $path === null ? null : $this->path($path, $this->locale);
        } finally { $this->locale = $before; }
    }

    public function indexPath(string $type): ?string
    {
        $home = $this->settings()['homepage'] ?? [];
        $schema = $this->type($type);

        if ($schema === null || !$this->isPublicType($type)) {
            return null;
        }

        if (($home['type'] ?? '') === $type && ($home['entry'] ?? '') === '') {
            return '/';
        }

        $route = $this->route($type);

        return $route !== null && $route['index_path'] !== '' ? $route['index_path'] : null;
    }

    public function indexUrl(string $type, ?string $locale = null): ?string
    {
        $before = $this->locale;
        $this->locale = $locale ?? ($this->locale ?: $this->defaultLocale());
        try {
            if (!in_array($this->locale, $this->locales(), true)) return null;
            $path = $this->indexPath($type);
            return $path === null ? null : $this->path($path, $this->locale);
        } finally { $this->locale = $before; }
    }

    public function mediaUrl(string $file, array $query = []): string
    {
        $url = $this->basePath . '/media/' . rawurlencode($this->site->workspace()->slug()) . '/' . rawurlencode($file);

        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }

    /** The flag image of a language, or null when there is no flag for it. */
    public function flagUrl(string $locale): ?string
    {
        $country = Flags::country($locale);

        return $country !== '' ? $this->basePath . '/_site/flags/' . $country . '.svg' : null;
    }

    public function stylesheetUrl(): string
    {
        return $this->basePath . '/_site/' . rawurlencode($this->site->workspace()->slug()) . '/style.css?v=' . $this->site->stylesheetVersion();
    }

    public function customStylesheetUrl(): string
    {
        return $this->basePath . '/_site/' . rawurlencode($this->site->workspace()->slug()) . '/theme/style.css?v=' . $this->site->customStylesheetVersion();
    }

    // ─── Content ─────────────────────────────────────────────────────────────

    /** A visible entry by slug or id, with the current locale applied. */
    public function findEntry(string $type, string $identifier, bool $allowHidden = false): ?array
    {
        if (!$this->isPublicType($type) || !preg_match('/^[A-Za-z0-9_-]+$/', $identifier)) {
            return null;
        }

        if (!in_array($this->locale ?: $this->defaultLocale(), $this->locales(), true)) return null;
        $key = $type . '|' . $identifier . '|' . ($allowHidden ? '1' : '0') . '|' . $this->locale;
        if (array_key_exists($key, $this->entries)) {
            return $this->entries[$key];
        }

        $entry = $this->content->findByIdentifier($type, $identifier, false, $this->locale);
        if ($entry !== null && !$allowHidden && !$this->content->isPubliclyVisible($entry, $this->locale ?: $this->defaultLocale())) {
            $entry = null;
        }

        return $this->entries[$key] = $entry === null ? null : $this->localize($type, $entry);
    }

    /**
     * Published entries of a public type, localized, filtered and sorted.
     *
     * @return array{data: list<array>, total: int}
     */
    public function query(string $type, array $params): array
    {
        if (!$this->isPublicType($type)) {
            return ['data' => [], 'total' => 0];
        }

        if (!in_array($this->locale ?: $this->defaultLocale(), $this->locales(), true)) return ['data' => [], 'total' => 0];
        if ($this->locale !== '') {
            $params['locale'] = $this->locale;
        }

        $result = $this->content->query($type, $params, false);

        return ['data' => $result['data'], 'total' => (int) $result['meta']['total']];
    }

    public function localize(string $type, array $entry): array
    {
        if ($this->locale !== '' && in_array($this->locale, $this->locales(), true)) {
            return $this->content->resolveLocaleFields($entry, $this->locale, !$this->preview);
        }

        return $this->content->resolveLocaleFields($entry, $this->defaultLocale(), !$this->preview);
    }

    /** Public media metadata, or null for missing and private files. */
    public function mediaItem(string $file): ?array
    {
        $file = basename($file);

        if ($file === '') {
            return null;
        }

        if (!array_key_exists($file, $this->mediaItems)) {
            $item = $this->media->find($file);
            $this->mediaItems[$file] = $item !== null && ($item['visibility'] ?? 'public') !== 'private' ? $item : null;
        }

        return LocalizedValue::tree($this->mediaItems[$file], $this->htmlLang(), $this->defaultLocale());
    }

    public function variantDescriptor(string $file): ?array
    {
        return $this->media->variantDescriptor($file);
    }
}
