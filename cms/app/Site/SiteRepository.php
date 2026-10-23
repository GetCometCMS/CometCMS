<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Cache\ApiCache;
use CometCMS\Content\ContentTypeRepository;
use CometCMS\Core\Security;
use CometCMS\Core\ValidationException;
use CometCMS\Localization\LocalizedValue;
use CometCMS\Localization\LanguageSettings;
use CometCMS\Storage\JsonStore;
use CometCMS\Workspaces\WorkspaceContext;
use CometCMS\Workspaces\WorkspaceRepository;

/**
 * Website configuration of one workspace, stored under
 * storage/workspaces/{workspace}/site/:
 *
 *   settings.json               homepage, URL patterns, SEO defaults
 *   menus.json                  navigation menus
 *   forms/{id}.json             website forms (see FormRepository)
 *   theme/                      the design, kept apart so it can live in git:
 *     theme.json                theme settings
 *     style.css                 custom CSS, loaded after the generated theme
 *     regions/{header,footer}.json
 *     layouts/{type}/{variant}.json
 *     components/{id}.json      reusable blocks placed in layouts and regions
 *
 * Theme files hold no save timestamps, so saving unchanged content leaves
 * them unchanged; "updated_at" comes from the file modification time.
 * Revisions live outside, in revisions/site/.
 *
 * Layouts and regions that were never customized are generated from the
 * content type on the fly, so publishing a first page works without setup.
 *
 * There is no on/off switch: the website is live while it has something to
 * show, i.e. a homepage or at least one content type with frontend pages
 * (see isLive()). Without either, the workspace stays purely headless.
 */
final class SiteRepository
{
    public const MENU_LIMITS = ['max_depth' => 5, 'max_siblings' => 60, 'max_menus' => 20];

    public const RESERVED_PREFIXES = ['/admin', '/api', '/mcp', '/media', '/media-thumbs', '/_site', '/site-preview'];

    public const RESERVED_FILES = ['/sitemap.xml', '/robots.txt', '/favicon.ico'];

    private JsonStore $store;
    private JsonStore $themeStore;
    private JsonStore $revisions;
    private WorkspaceContext $workspace;
    private ContentTypeRepository $types;
    private ?array $settingsCache = null;
    private ?array $typesCache = null;

    public function __construct(?WorkspaceContext $workspace = null, ?ContentTypeRepository $types = null)
    {
        $this->workspace = $workspace ?? WorkspaceContext::active();
        $this->types = $types ?? new ContentTypeRepository($this->workspace);
        $this->store = new JsonStore($this->workspace->path('site'));
        $this->themeStore = new JsonStore($this->workspace->path('site') . '/theme');
        $this->revisions = new JsonStore($this->workspace->path('revisions') . '/site');


    }

    public function workspace(): WorkspaceContext
    {
        return $this->workspace;
    }

    /** @return array<string, array> content types keyed by name */
    public function types(): array
    {
        if ($this->typesCache === null) {
            $this->typesCache = [];
            foreach ($this->types->all() as $type) {
                $this->typesCache[(string) $type['name']] = $type;
            }
        }

        return $this->typesCache;
    }

    // ─── Settings ────────────────────────────────────────────────────────────

    public function settings(): array
    {
        return $this->settingsCache ??= $this->normalizeSettings($this->store->read('settings') ?? []);
    }

    public function saveSettings(array $input): array
    {
        return (new JsonStore($this->workspace->root()))->synchronized(function () use ($input): array {
            $this->settingsCache = null;
            if (isset($input['revision']) && $input['revision'] !== ($this->settings()['revision'] ?? '')) throw new \CometCMS\Core\ConflictException();
            return $this->saveSettingsUnlocked($input);
        }, 'routing');
    }

    private function saveSettingsUnlocked(array $input): array
    {
        $settings = $this->normalizeSettings($input);
        foreach ($settings['locales'] as $locale) $this->validateSettings(LocalizedValue::tree($settings, $locale, $settings['default_locale']));
        $this->validateEntryUrls($settings);
        $settings['updated_at'] = Security::now();
        $settings['revision'] = bin2hex(random_bytes(12));
        // Workspace languages and website availability are derived on every read.
        $this->store->write(array_diff_key($settings, array_flip(['enabled', 'locales', 'default_locale'])), 'settings');
        $this->settingsCache = $settings;
        $this->clearCache();

        return $settings;
    }

    /** Whether normalized settings publish anything: a homepage or frontend pages. */
    private static function isLive(array $settings): bool
    {
        if (($settings['homepage']['type'] ?? '') !== '') {
            return true;
        }

        foreach ($settings['routes'] ?? [] as $route) {
            if (!empty($route['enabled'])) {
                return true;
            }
        }

        return false;
    }

    public function defaultRoute(array $type): array
    {
        $name = (string) $type['name'];

        return [
            'enabled' => false,
            'pattern' => '/' . $name . '/{slug}',
            'index_path' => '/' . $name,
            'index_title' => '',
            'seo_description_field' => '',
            'seo_image_field' => '',
            'sitemap' => true,
        ];
    }

    /** Workspace languages used throughout the website. */
    public function availableLocales(): array
    {
        return (new LanguageSettings($this->workspace))->all()['locales'];
    }

    // ─── Theme ───────────────────────────────────────────────────────────────

    public function theme(): array
    {
        $stored = $this->themeStore->read('theme') ?? [];
        $path = $this->themeStore->path('style') . '.css';
        if (is_file($path)) {
            $css = file_get_contents($path);
            if ($css === false) {
                throw new \RuntimeException('Could not read the theme stylesheet.');
            }
            $stored['custom_css'] = $css;
        }
        $updated = self::modifiedAt($this->themeStore->path('theme') . '.json', $path);

        return Theme::normalize($stored) + ['updated_at' => $updated];
    }

    public function saveTheme(array $input, array $user = [], string $event = 'saved'): array
    {
        $theme = Theme::normalize($input);
        $this->writeRevision(['theme'], ['theme' => $this->theme()], $user, $event);
        $this->writeThemeStylesheet($theme['custom_css']);
        $this->themeStore->write(array_diff_key($theme, ['custom_css' => true]), 'theme');
        $this->clearCache();

        return $theme + ['updated_at' => self::modifiedAt($this->themeStore->path('theme') . '.json', $this->themeStore->path('style') . '.css')];
    }

    /** When the newest of the given files changed, formatted like Security::now(); '' if none exists. */
    private static function modifiedAt(string ...$paths): string
    {
        clearstatcache();
        $times = array_filter(array_map(static fn(string $path): int|false => is_file($path) ? filemtime($path) : false, $paths));

        return $times === [] ? '' : gmdate('Y-m-d\TH:i:s\Z', max($times));
    }

    /** Replace the stylesheet atomically so readers never see a partial edit. */
    private function writeThemeStylesheet(string $css): void
    {
        $path = $this->themeStore->path('style') . '.css';
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) {
            throw new \RuntimeException('Could not create the theme directory.');
        }
        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        try {
            if (file_put_contents($temporary, $css, LOCK_EX) !== strlen($css) || !rename($temporary, $path)) {
                throw new \RuntimeException('Could not write the theme stylesheet.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /** Version both generated styles and custom CSS for stylesheet and page caches. */
    public function stylesheetVersion(): string
    {
        $theme = $this->theme();
        $base = __DIR__ . '/assets/site.css';

        return substr(sha1(json_encode($theme) . '|' . (new FontRegistry($this->workspace))->fingerprint() . '|' . (is_file($base) ? (string) filemtime($base) : '') . '|' . (function_exists('comet_version') ? comet_version() : '')), 0, 12);
    }

    public function customStylesheetVersion(): string
    {
        return substr(sha1($this->theme()['custom_css']), 0, 12);
    }

    // ─── Menus ───────────────────────────────────────────────────────────────

    public function menus(): array
    {
        $stored = $this->store->read('menus');

        if ($stored === null) {
            return [
                ['name' => 'main', 'label' => 'Main', 'items' => [
                    ['id' => 'home', 'label' => 'Home', 'link' => ['type' => 'home', 'new_tab' => false], 'children' => []],
                ]],
                ['name' => 'footer', 'label' => 'Footer', 'items' => []],
            ];
        }

        return $this->normalizeMenus($stored['menus'] ?? []);
    }

    public function menu(string $name): ?array
    {
        foreach ($this->menus() as $menu) {
            if ($menu['name'] === $name) {
                return $menu;
            }
        }

        return null;
    }

    public function saveMenus(array $menus, ?string $revision = null): array
    {
        return $this->store->synchronized(function () use ($menus, $revision): array {
            if ($revision !== null && $revision !== LocalizedValue::hash($this->menus())) throw new \CometCMS\Core\ConflictException();
            return $this->saveMenusUnlocked($menus);
        }, 'menus');
    }

    private function saveMenusUnlocked(array $menus): array
    {
        $this->validateMenus($menus);
        $menus = $this->normalizeMenus($menus);
        $this->store->write(['menus' => $menus, 'updated_at' => Security::now()], 'menus');
        $this->clearCache();

        return $menus;
    }

    // ─── Regions (header / footer) ───────────────────────────────────────────

    public function region(string $region): array
    {
        $this->assertRegion($region);
        $stored = $this->themeStore->read('regions', $region);

        return ($stored['format_version'] ?? null) === LayoutSchema::FORMAT_VERSION ? LayoutSchema::normalize($stored['nodes'] ?? []) : LayoutSchema::defaultRegion($region);
    }

    public function regionCustomized(string $region): bool
    {
        $this->assertRegion($region);

        return ($this->themeStore->read('regions', $region)['format_version'] ?? null) === LayoutSchema::FORMAT_VERSION;
    }

    public function saveRegion(string $region, mixed $nodes, array $user = [], string $event = 'saved', ?string $revision = null): array
    {
        return $this->themeStore->synchronized(function () use ($region, $nodes, $user, $event, $revision): array {
            if ($revision !== null && $revision !== LocalizedValue::hash($this->region($region))) throw new \CometCMS\Core\ConflictException();
            return $this->saveRegionUnlocked($region, $nodes, $user, $event);
        }, ...['regions', $region]);
    }

    private function saveRegionUnlocked(string $region, mixed $nodes, array $user = [], string $event = 'saved'): array
    {
        $this->assertRegion($region);
        $nodes = LayoutSchema::normalize($nodes);
        $this->writeRevision(['regions', $region], $this->regionSnapshot($region), $user, $event);
        $this->themeStore->write(['format_version' => LayoutSchema::FORMAT_VERSION, 'nodes' => $nodes], 'regions', $region);
        $this->clearCache();

        return $nodes;
    }

    public function resetRegion(string $region, array $user = [], string $event = 'reset'): array
    {
        $this->assertRegion($region);
        $this->writeRevision(['regions', $region], $this->regionSnapshot($region), $user, $event);
        $this->themeStore->delete('regions', $region);
        $this->clearCache();

        return $this->region($region);
    }

    // ─── Layouts ─────────────────────────────────────────────────────────────

    public function layout(string $type, string $variant): array
    {
        $schema = $this->requireType($type);
        $this->assertVariant($schema, $variant);
        $stored = $this->themeStore->read('layouts', $type, $variant);

        return ($stored['format_version'] ?? null) === LayoutSchema::FORMAT_VERSION ? LayoutSchema::normalize($stored['nodes'] ?? [], $variant === 'detail') : LayoutSchema::defaultLayout($schema, $variant);
    }

    public function layoutCustomized(string $type, string $variant): bool
    {
        Security::assertSafeName($type);
        Security::assertSafeName($variant);

        return ($this->themeStore->read('layouts', $type, $variant)['format_version'] ?? null) === LayoutSchema::FORMAT_VERSION;
    }

    public function saveLayout(string $type, string $variant, mixed $nodes, array $user = [], string $event = 'saved', ?string $revision = null): array
    {
        return $this->themeStore->synchronized(function () use ($type, $variant, $nodes, $user, $event, $revision): array {
            if ($revision !== null && $revision !== LocalizedValue::hash($this->layout($type, $variant))) throw new \CometCMS\Core\ConflictException();
            return $this->saveLayoutUnlocked($type, $variant, $nodes, $user, $event);
        }, ...['layouts', $type, $variant]);
    }

    private function saveLayoutUnlocked(string $type, string $variant, mixed $nodes, array $user = [], string $event = 'saved'): array
    {
        $schema = $this->requireType($type);
        $this->assertVariant($schema, $variant);
        $nodes = LayoutSchema::normalize($nodes, $variant === 'detail');
        $this->writeRevision(['layouts', $type, $variant], $this->layoutSnapshot($type, $variant), $user, $event);
        $this->themeStore->write(['format_version' => LayoutSchema::FORMAT_VERSION, 'nodes' => $nodes], 'layouts', $type, $variant);
        $this->clearCache();

        return $nodes;
    }

    public function resetLayout(string $type, string $variant, array $user = [], string $event = 'reset'): array
    {
        $schema = $this->requireType($type);
        $this->assertVariant($schema, $variant);
        $this->writeRevision(['layouts', $type, $variant], $this->layoutSnapshot($type, $variant), $user, $event);
        $this->themeStore->delete('layouts', $type, $variant);
        $this->clearCache();

        return LayoutSchema::defaultLayout($schema, $variant);
    }

    /** The default card for collections without a custom card. */
    public function cardTemplate(string $type): array
    {
        return LayoutSchema::defaultCardTemplate($this->requireType($type));
    }

    // ─── Components ──────────────────────────────────────────────────────────

    /**
     * Reusable blocks: one section, column set or column with everything in
     * it. Layouts, regions and other components place them by id, so editing
     * a component changes every page it appears on. A component that shows
     * fields of the page's entry ("current" references) belongs to one
     * content type and is only offered in that type's layouts.
     *
     * @return list<array{id: string, name: string, root: string, type: string, created_at: string, updated_at: string}>
     */
    public function components(): array
    {
        $list = [];

        foreach ($this->themeStore->all('components') as $stored) {
            $component = $this->readComponent($stored);
            if ($component !== null) {
                unset($component['nodes']);
                $list[] = $component;
            }
        }
        usort($list, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $list;
    }

    public function component(string $id): ?array
    {
        if ($id === '' || LayoutSchema::key($id) !== $id) {
            return null;
        }

        $stored = $this->themeStore->read('components', $id);
        return $this->readComponent($stored);
    }

    /**
     * A readable component id, also its file name: the name as a slug,
     * numbered when taken. Renaming a component keeps its id.
     *
     * @param callable(string): bool $taken
     */
    public static function componentId(string $name, callable $taken): string
    {
        $slug = strtr($name, ['Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        if (function_exists('iconv')) {
            $slug = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug) ?: $slug;
        }
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($slug)), '-');
        $slug = rtrim(substr($slug, 0, 60), '-') ?: 'component';

        $id = $slug;
        for ($number = 2; $taken($id); $number++) {
            $id = $slug . '-' . $number;
        }

        return $id;
    }

    /** Create a component from one structure node (and everything inside it). */
    public function createComponent(mixed $name, mixed $nodes, mixed $type = '', array $user = []): array
    {
        $nodes = is_array($nodes) ? array_values($nodes) : [];
        $root = is_array($nodes[0] ?? null) ? (string) ($nodes[0]['kind'] ?? '') : '';
        if (count($nodes) !== 1 || !in_array($root, LayoutSchema::STRUCTURE, true)) {
            throw new ValidationException(['nodes' => 'A component is made from one section, column set or column.']);
        }

        $name = $this->componentName($name);
        $component = $this->validateComponent([
            'id' => self::componentId($name, fn(string $id): bool => is_file($this->themeStore->path('components', $id) . '.json')),
            'name' => $name,
            'root' => $root,
            'type' => '',
            'created_at' => Security::now(),
        ], $nodes, $type);

        return $this->writeComponent($component);
    }

    public function saveComponent(string $id, mixed $nodes, array $user = [], string $event = 'saved', ?string $revision = null): array
    {
        return $this->themeStore->synchronized(function () use ($id, $nodes, $user, $event, $revision): array {
            if ($revision !== null && $revision !== LocalizedValue::hash($this->component($id)['nodes'])) throw new \CometCMS\Core\ConflictException();
            return $this->saveComponentUnlocked($id, $nodes, $user, $event);
        }, ...['components', $id]);
    }

    private function saveComponentUnlocked(string $id, mixed $nodes, array $user = [], string $event = 'saved'): array
    {
        $current = $this->requireComponent($id);
        $component = $this->validateComponent($current, $nodes, $current['type']);
        $this->writeRevision(['components', $current['id']], $this->componentSnapshot($current), $user, $event);

        return $this->writeComponent($component);
    }

    public function renameComponent(string $id, mixed $name): array
    {
        $component = $this->requireComponent($id);
        $component['name'] = $this->componentName($name);

        return $this->writeComponent($component);
    }

    /**
     * Where a component is placed directly: stored layouts, regions, other
     * components and entries' page content. Generated default layouts never
     * contain components.
     *
     * @return list<array{target: string, type?: string, variant?: string, region?: string, id?: string, name?: string}>
     */
    public function componentUsages(string $id): array
    {
        $usages = [];
        $uses = static fn(?array $stored): bool => ($stored['format_version'] ?? null) === LayoutSchema::FORMAT_VERSION
            && in_array($id, LayoutSchema::componentRefs(is_array($stored['nodes'] ?? null) ? $stored['nodes'] : []), true);

        foreach ($this->types() as $name => $type) {
            foreach ($this->variantsFor($type) as $variant) {
                if ($uses($this->themeStore->read('layouts', (string) $name, $variant))) {
                    $usages[] = ['target' => 'layout', 'type' => (string) $name, 'variant' => $variant];
                }
            }
        }
        foreach (LayoutSchema::REGION_DOCUMENTS as $region) {
            if ($uses($this->themeStore->read('regions', $region))) {
                $usages[] = ['target' => 'region', 'region' => $region];
            }
        }
        foreach ($this->themeStore->all('components') as $stored) {
            if (($stored['id'] ?? '') !== $id && $uses($stored)) {
                $usages[] = ['target' => 'component', 'id' => (string) $stored['id'], 'name' => (string) ($stored['name'] ?? $stored['id'])];
            }
        }

        return [...$usages, ...$this->pageUsages($uses)];
    }

    /**
     * Entries whose own page content passes $uses (a stored {format_version,
     * nodes} value => bool), one usage per entry.
     *
     * @return list<array{target: string, type: string, id: string, name: string}>
     */
    private function pageUsages(callable $uses): array
    {
        $usages = [];
        $content = \CometCMS\Content\ContentRepository::make($this->workspace);
        foreach ($this->types() as $name => $type) {
            $fields = array_keys(array_filter($type['fields'] ?? [], static fn(mixed $config): bool => ($config['type'] ?? '') === 'page'));
            if ($fields === []) {
                continue;
            }
            foreach ($content->all((string) $name) as $entry) {
                foreach ($fields as $field) {
                    if (LayoutSchema::hasPageContent($entry[$field] ?? null) && $uses($entry[$field])) {
                        $usages[] = ['target' => 'entry', 'type' => (string) $name, 'id' => (string) $entry['id'], 'name' => (string) ($entry['title'] ?? $entry['id'])];
                        break;
                    }
                }
            }
        }

        return $usages;
    }

    /**
     * Where a website form is placed: stored layouts, regions, components and
     * entries' page content.
     *
     * @return list<array{target: string, type?: string, variant?: string, region?: string, id?: string, name?: string}>
     */
    public function formUsages(string $id): array
    {
        $usages = [];
        $uses = static function (?array $stored) use ($id): bool {
            $found = false;
            $scan = static function (array $nodes) use (&$scan, &$found, $id): void {
                foreach ($nodes as $node) {
                    if (is_array($node) && ($node['kind'] ?? '') === 'form' && ($node['props']['form'] ?? '') === $id) {
                        $found = true;
                        return;
                    }
                    $scan(is_array($node['children'] ?? null) ? $node['children'] : []);
                }
            };
            if (($stored['format_version'] ?? null) === LayoutSchema::FORMAT_VERSION && is_array($stored['nodes'] ?? null)) {
                $scan($stored['nodes']);
            }

            return $found;
        };

        foreach ($this->types() as $name => $type) {
            foreach ($this->variantsFor($type) as $variant) {
                if ($uses($this->themeStore->read('layouts', (string) $name, $variant))) {
                    $usages[] = ['target' => 'layout', 'type' => (string) $name, 'variant' => $variant];
                }
            }
        }
        foreach (LayoutSchema::REGION_DOCUMENTS as $region) {
            if ($uses($this->themeStore->read('regions', $region))) {
                $usages[] = ['target' => 'region', 'region' => $region];
            }
        }
        foreach ($this->themeStore->all('components') as $stored) {
            if ($uses($stored)) {
                $usages[] = ['target' => 'component', 'id' => (string) ($stored['id'] ?? ''), 'name' => (string) ($stored['name'] ?? $stored['id'] ?? '')];
            }
        }

        return [...$usages, ...$this->pageUsages($uses)];
    }

    /** Delete a component and its history, keeping all placements as detached copies. */
    public function deleteComponent(string $id): array
    {
        $component = $this->requireComponent($id);
        $id = $component['id'];
        $paths = array_map(static fn(string $region): array => ['regions', $region], LayoutSchema::REGION_DOCUMENTS);
        foreach ($this->types() as $name => $type) {
            foreach ($this->variantsFor($type) as $variant) {
                $paths[] = ['layouts', (string) $name, $variant];
            }
        }
        foreach ($this->components() as $other) {
            if ($other['id'] !== $id) {
                $paths[] = ['components', $other['id']];
            }
        }

        foreach ($paths as $path) {
            $stored = $this->themeStore->read(...$path);
            if ($this->hasComponentInstance($stored, $id)) {
                $stored['nodes'] = $this->detachComponentInstances($stored['nodes'], $component);
                $this->themeStore->write($stored, ...$path);
            }
            // Restoring an older layout must not bring back a missing component.
            foreach ($this->revisions->all(...$path) as $revision) {
                if ($this->hasComponentInstance($revision['snapshot'] ?? null, $id)) {
                    $revision['snapshot']['nodes'] = $this->detachComponentInstances($revision['snapshot']['nodes'], $component);
                    $this->revisions->write($revision, ...[...$path, $revision['id']]);
                }
            }
        }

        // Entries keep their page content, too, and so do their older versions.
        $content = \CometCMS\Content\ContentRepository::make($this->workspace);
        foreach (array_keys($this->types()) as $name) {
            $content->rewritePages((string) $name, fn(array $value): array => $this->hasComponentInstance($value, $id)
                ? ['nodes' => $this->detachComponentInstances($value['nodes'], $component)] + $value
                : $value);
        }

        $this->themeStore->delete('components', $id);
        foreach ($this->listRevisions(['components', $id]) as $revision) {
            $this->revisions->delete('components', $id, $revision['id']);
        }
        $directory = $this->revisions->path('components', $id);
        if (is_dir($directory) && (glob($directory . '/*') ?: []) === []) {
            @rmdir($directory);
        }
        $this->clearCache();

        return $component;
    }

    private function hasComponentInstance(?array $stored, string $id): bool
    {
        return ($stored['format_version'] ?? null) === LayoutSchema::FORMAT_VERSION
            && in_array($id, LayoutSchema::componentRefs(is_array($stored['nodes'] ?? null) ? $stored['nodes'] : []), true);
    }

    private function detachComponentInstances(array $nodes, array $component): array
    {
        return array_map(function (array $node) use ($component): array {
            if (($node['kind'] ?? '') === 'component' && ($node['props']['component'] ?? '') === $component['id']) {
                $copy = LayoutSchema::withNewIds($component['nodes'])[0];
                $copy['id'] = $node['id'];
                if (isset($node['name'])) {
                    $copy['name'] = $node['name'];
                }
                if ($copy['kind'] === 'column') {
                    $copy['props']['span'] = $node['props']['span'] ?? $copy['props']['span'];
                }

                return $copy;
            }
            $node['children'] = $this->detachComponentInstances($node['children'] ?? [], $component);

            return $node;
        }, $nodes);
    }

    public function componentRevisions(string $id): array
    {
        $id = $this->requireComponent($id)['id'];

        return $this->listRevisions(['components', $id]);
    }

    public function restoreComponentRevision(string $id, string $revisionId, array $user = []): ?array
    {
        $id = $this->requireComponent($id)['id'];
        $revision = $this->revisions->read('components', $id, $revisionId);
        if (($revision['snapshot']['format_version'] ?? null) !== LayoutSchema::FORMAT_VERSION || !is_array($revision['snapshot']['nodes'] ?? null)) {
            return null;
        }
        $component = $this->saveComponent($id, $revision['snapshot']['nodes'], $user, 'restored');

        return ['nodes' => $component['nodes'], 'customized' => true, 'component' => $component];
    }

    public function requireComponent(string $id): array
    {
        $component = $this->component($id);
        if ($component === null) {
            throw new \RuntimeException('Component not found.');
        }

        return $component;
    }

    /** Normalize a stored component; null when it is unreadable. */
    private function readComponent(?array $stored): ?array
    {
        if (!is_array($stored) || ($stored['format_version'] ?? null) !== LayoutSchema::FORMAT_VERSION) {
            return null;
        }
        $id = LayoutSchema::key($stored['id'] ?? '');
        $root = (string) ($stored['root'] ?? '');
        if ($id === '' || !in_array($root, LayoutSchema::STRUCTURE, true)) {
            return null;
        }

        return [
            'id' => $id,
            'name' => self::line($stored['name'] ?? '', 120) ?: $id,
            'root' => $root,
            'type' => LayoutSchema::key($stored['type'] ?? ''),
            'nodes' => LayoutSchema::normalizeComponent($stored['nodes'] ?? [], $root),
            'created_at' => (string) ($stored['created_at'] ?? ''),
            'updated_at' => self::modifiedAt($this->themeStore->path('components', $id) . '.json'),
        ];
    }

    /**
     * Check a component's tree: one node of its root kind, a content type
     * when it shows the page entry's fields, and no component inside that
     * contains this one again or belongs to another type.
     */
    private function validateComponent(array $component, mixed $nodes, mixed $type): array
    {
        $nodes = LayoutSchema::normalizeComponent($nodes, $component['root']);
        if ($nodes === []) {
            throw new ValidationException(['nodes' => 'A component needs its ' . $component['root'] . '.']);
        }

        $type = LayoutSchema::key($type);
        if ($type !== '' && !isset($this->types()[$type])) {
            $type = '';
        }
        if ($type === '' && LayoutSchema::usesSource($nodes, 'current')) {
            throw new ValidationException(['type' => 'This component shows fields of the page\'s entry, so it needs the content type it belongs to.']);
        }

        foreach (LayoutSchema::componentRefs($nodes) as $ref) {
            if ($ref === $component['id'] || $this->componentReaches($ref, $component['id'])) {
                throw new ValidationException(['nodes' => 'A component cannot contain itself.']);
            }
            $inner = $this->component($ref);
            if ($inner !== null && $inner['type'] !== '' && $inner['type'] !== $type) {
                throw new ValidationException(['nodes' => 'The component "' . $inner['name'] . '" belongs to another content type.']);
            }
        }

        $component['nodes'] = $nodes;
        $component['type'] = $type;

        return $component;
    }

    /** Whether component $from places $target, directly or further down. */
    private function componentReaches(string $from, string $target, array $seen = []): bool
    {
        if (isset($seen[$from])) {
            return false;
        }
        $seen[$from] = true;
        $stored = $this->themeStore->read('components', $from);
        foreach (LayoutSchema::componentRefs(is_array($stored['nodes'] ?? null) ? $stored['nodes'] : []) as $ref) {
            if ($ref === $target || $this->componentReaches($ref, $target, $seen)) {
                return true;
            }
        }

        return false;
    }

    private function componentName(mixed $name): string
    {
        $name = self::line($name, 120);
        if ($name === '') {
            throw new ValidationException(['name' => 'Give the component a name.']);
        }

        return $name;
    }

    private function writeComponent(array $component): array
    {
        // A fixed key order, so saving an unchanged component leaves its file unchanged.
        $this->themeStore->write([
            'format_version' => LayoutSchema::FORMAT_VERSION,
            'id' => $component['id'],
            'name' => $component['name'],
            'root' => $component['root'],
            'type' => $component['type'],
            'created_at' => $component['created_at'],
            'nodes' => $component['nodes'],
        ], 'components', $component['id']);
        $this->clearCache();

        return [...$component, 'updated_at' => self::modifiedAt($this->themeStore->path('components', $component['id']) . '.json')];
    }

    private function componentSnapshot(array $component): array
    {
        return ['format_version' => LayoutSchema::FORMAT_VERSION, 'nodes' => $component['nodes'], 'customized' => true];
    }

    public function needsLayoutUpgrade(string $type, string $variant): bool
    {
        Security::assertSafeName($type);
        Security::assertSafeName($variant);
        $stored = $this->themeStore->read('layouts', $type, $variant);
        return $stored !== null && ($stored['format_version'] ?? null) !== LayoutSchema::FORMAT_VERSION;
    }

    public function needsRegionUpgrade(string $region): bool
    {
        $this->assertRegion($region);
        $stored = $this->themeStore->read('regions', $region);
        return $stored !== null && ($stored['format_version'] ?? null) !== LayoutSchema::FORMAT_VERSION;
    }

    /** Variants a content type can have. */
    public function variantsFor(array $type): array
    {
        return LayoutSchema::VARIANTS;
    }

    public function themeRevisions(): array
    {
        return $this->listRevisions(['theme']);
    }

    public function regionRevisions(string $region): array
    {
        $this->assertRegion($region);

        return $this->listRevisions(['regions', $region]);
    }

    public function layoutRevisions(string $type, string $variant): array
    {
        $this->assertVariant($this->requireType($type), $variant);

        return $this->listRevisions(['layouts', $type, $variant]);
    }

    /** Read a revision without changing the saved website or its history. */
    public function themeRevision(string $id, bool $canEditCss = true): ?array
    {
        $theme = $this->revisions->read('theme', $id)['snapshot']['theme'] ?? null;
        if (!is_array($theme)) {
            return null;
        }
        if (!$canEditCss) {
            $theme['custom_css'] = $this->theme()['custom_css'];
        }
        return $theme;
    }

    public function layoutRevision(string $type, string $variant, string $id): ?array
    {
        $this->assertVariant($this->requireType($type), $variant);
        return $this->readLayoutRevision(['layouts', $type, $variant, $id]);
    }

    public function regionRevision(string $region, string $id): ?array
    {
        $this->assertRegion($region);
        return $this->readLayoutRevision(['regions', $region, $id]);
    }

    public function componentRevision(string $component, string $id): ?array
    {
        $component = $this->requireComponent($component)['id'];
        return $this->readLayoutRevision(['components', $component, $id]);
    }

    private function readLayoutRevision(array $path): ?array
    {
        $snapshot = $this->revisions->read(...$path)['snapshot'] ?? null;
        if (($snapshot['format_version'] ?? null) !== LayoutSchema::FORMAT_VERSION || !is_array($snapshot['nodes'] ?? null)) {
            return null;
        }
        return ['nodes' => $snapshot['nodes'], 'customized' => $snapshot['customized'] ?? true];
    }

    public function restoreThemeRevision(string $id, array $user = [], bool $canEditCss = true): ?array
    {
        $revision = $this->revisions->read('theme', $id);
        if (!is_array($revision['snapshot']['theme'] ?? null)) {
            return null;
        }
        $theme = $revision['snapshot']['theme'];
        if (!$canEditCss) {
            $theme['custom_css'] = $this->theme()['custom_css'];
        }

        return $this->saveTheme($theme, $user, 'restored');
    }

    public function restoreRegionRevision(string $region, string $id, array $user = []): ?array
    {
        $this->assertRegion($region);
        $revision = $this->revisions->read('regions', $region, $id);
        if (($revision['snapshot']['format_version'] ?? null) !== LayoutSchema::FORMAT_VERSION || !is_array($revision['snapshot']['nodes'] ?? null)) {
            return null;
        }
        $customized = ($revision['snapshot']['customized'] ?? true) === true;
        $nodes = $customized
            ? $this->saveRegion($region, $revision['snapshot']['nodes'], $user, 'restored')
            : $this->resetRegion($region, $user, 'restored');

        return ['nodes' => $nodes, 'customized' => $customized];
    }

    public function restoreLayoutRevision(string $type, string $variant, string $id, array $user = []): ?array
    {
        $this->assertVariant($this->requireType($type), $variant);
        $revision = $this->revisions->read('layouts', $type, $variant, $id);
        if (($revision['snapshot']['format_version'] ?? null) !== LayoutSchema::FORMAT_VERSION || !is_array($revision['snapshot']['nodes'] ?? null)) {
            return null;
        }
        $customized = ($revision['snapshot']['customized'] ?? true) === true;
        $nodes = $customized
            ? $this->saveLayout($type, $variant, $revision['snapshot']['nodes'], $user, 'restored')
            : $this->resetLayout($type, $variant, $user, 'restored');

        return ['nodes' => $nodes, 'customized' => $customized];
    }

    private function regionSnapshot(string $region): array
    {
        return ['format_version' => LayoutSchema::FORMAT_VERSION, 'nodes' => $this->region($region), 'customized' => $this->regionCustomized($region)];
    }

    private function layoutSnapshot(string $type, string $variant): array
    {
        return ['format_version' => LayoutSchema::FORMAT_VERSION, 'nodes' => $this->layout($type, $variant), 'customized' => $this->layoutCustomized($type, $variant)];
    }

    private function listRevisions(array $path): array
    {
        $revisions = $this->revisions->all(...$path);
        if (($path[0] ?? '') !== 'theme') {
            $revisions = array_values(array_filter($revisions, static fn(array $revision): bool => ($revision['snapshot']['format_version'] ?? null) === LayoutSchema::FORMAT_VERSION));
        }
        usort($revisions, static fn(array $a, array $b): int => strcmp($b['id'], $a['id']));
        foreach ($revisions as &$revision) {
            $revision['customized'] = $revision['snapshot']['customized'] ?? null;
            unset($revision['snapshot']);
        }

        return $revisions;
    }

    private function writeRevision(array $path, array $snapshot, array $user, string $event): void
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $id = $now->format('YmdHisu') . '-' . bin2hex(random_bytes(4));
        $this->revisions->write([
            'id' => $id,
            'created_at' => $now->format('Y-m-d\TH:i:s.v\Z'),
            'created_by' => $user['id'] ?? null,
            'created_by_name' => $user['username'] ?? $user['id'] ?? null,
            'event' => $event,
            'snapshot' => $snapshot,
        ], ...[...$path, $id]);

        $max = (int) comet_config('site.max_revisions', 50);
        if ($max < 0) {
            return;
        }
        foreach (array_slice($this->listRevisions($path), $max) as $revision) {
            $this->revisions->delete(...[...$path, $revision['id']]);
        }
    }

    /** Remove everything the website stored for a deleted content type. */
    public function forgetType(string $type): void
    {
        Security::assertSafeName($type);
        foreach (LayoutSchema::VARIANTS as $variant) {
            $this->themeStore->delete('layouts', $type, $variant);
            foreach ($this->listRevisions(['layouts', $type, $variant]) as $revision) {
                $this->revisions->delete('layouts', $type, $variant, $revision['id']);
            }
        }

        $directory = $this->themeStore->path('layouts', $type);
        if (is_dir($directory) && (glob($directory . '/*') ?: []) === []) {
            @rmdir($directory);
        }

        $stored = $this->store->read('settings');
        if (is_array($stored) && isset($stored['routes'][$type])) {
            unset($stored['routes'][$type]);
            if (($stored['homepage']['type'] ?? '') === $type) {
                $stored['homepage'] = ['type' => '', 'entry' => ''];
            }
            $this->store->write($stored, 'settings');
            $this->settingsCache = null;
        }

        $this->clearCache();
    }

    public function clearCache(): void
    {
        ApiCache::fromConfig($this->workspace)->clear();
    }

    // ─── Normalization & validation ──────────────────────────────────────────

    private function localized(mixed $value, callable $normalize): mixed
    {
        return LocalizedValue::normalize($value, $normalize, (new LanguageSettings($this->workspace))->all()['locales']);
    }

    private function normalizeSettings(array $input): array
    {
        $types = $this->types();
        $routes = [];

        foreach ($types as $name => $type) {
            $route = is_array($input['routes'][$name] ?? null) ? $input['routes'][$name] : [];
            $default = $this->defaultRoute($type);
            $routes[$name] = [
                'enabled' => (bool) ($route['enabled'] ?? $default['enabled']),
                'pattern' => $this->localized($route['pattern'] ?? $default['pattern'], self::normalizePath(...)),
                'index_path' => $this->localized($route['index_path'] ?? $default['index_path'], self::normalizePath(...)),
                'index_title' => $this->localized($route['index_title'] ?? '', static fn(mixed $v): string => self::line($v, 200)),
                'seo_description_field' => LayoutSchema::key($route['seo_description_field'] ?? ''),
                'seo_image_field' => LayoutSchema::key($route['seo_image_field'] ?? ''),
                'sitemap' => (bool) ($route['sitemap'] ?? true),
            ];

            if (($type['visibility'] ?? 'public') === 'private') {
                // Private types never render publicly, even if a stored
                // setting still says so (e.g. visibility changed later).
                $routes[$name]['enabled'] = false;
            }
        }

        $languages = (new LanguageSettings($this->workspace))->all();
        $defaultLocale = $languages['default_locale'];
        $locales = $languages['locales'];

        $homepage = is_array($input['homepage'] ?? null) ? $input['homepage'] : [];
        $homeType = LayoutSchema::key($homepage['type'] ?? '');
        $homeType = isset($types[$homeType]) ? $homeType : '';
        $homeEntry = $homeType !== '' && !empty($homepage['entry']) ? \CometCMS\Content\ContentRepository::make($this->workspace)->findByIdentifier($homeType, (string) $homepage['entry']) : null;
        $homepage = [
            'type' => $homeType,
            'entry' => $homeEntry !== null ? $homeEntry['uid'] : ($homeType !== '' ? LayoutSchema::key($homepage['entry'] ?? '') : ''),
        ];

        return [
            // Read-only: derived from the homepage and routes (see isLive()).
            // A stored private homepage is rejected on save, but must not make
            // the website live in the meantime.
            'enabled' => self::isLive([
                'homepage' => ($types[$homeType]['visibility'] ?? 'public') === 'private' ? [] : $homepage,
                'routes' => $routes,
            ]),
            'title' => $this->localized($input['title'] ?? '', static fn(mixed $v): string => self::line($v, 120)),
            'tagline' => $this->localized($input['tagline'] ?? '', static fn(mixed $v): string => self::line($v, 200)),
            'logo' => $this->localized($input['logo'] ?? '', self::media(...)),
            'favicon' => self::media($input['favicon'] ?? ''),
            'homepage' => $homepage,
            'locales' => $locales,
            'default_locale' => $defaultLocale,
            'domains' => self::domains($input['domains'] ?? []),
            'title_template' => $this->localized($input['title_template'] ?? '{title} · {site}', static fn(mixed $v): string => self::line($v, 200)) ?: '{title} · {site}',
            'description' => $this->localized($input['description'] ?? '', static fn(mixed $v): string => self::line($v, 300)),
            'og_image' => $this->localized($input['og_image'] ?? '', self::media(...)),
            'noindex' => (bool) ($input['noindex'] ?? false),
            'routes' => $routes,
            'updated_at' => (string) ($input['updated_at'] ?? ''),
            'revision' => (string) ($input['revision'] ?? ''),
        ];
    }

    private function validateSettings(array $settings): void
    {
        $types = $this->types();
        $errors = [];
        $literal = [];
        $patterns = [];

        foreach ($settings['routes'] as $name => $route) {
            if (!$route['enabled']) {
                continue;
            }

            $type = $types[$name];
            $key = 'routes.' . $name;

            $pattern = $route['pattern'];
            $placeholders = preg_match_all('/\{[^}]*\}/', $pattern, $found);

            if ($error = $this->pathError($pattern)) {
                $errors[$key . '.pattern'] = $error;
            } elseif ($placeholders !== 1 || $found[0][0] !== '{slug}') {
                $errors[$key . '.pattern'] = 'Collection URLs need exactly one {slug} placeholder.';
            } else {
                if (isset($patterns[$pattern])) {
                    $errors[$key . '.pattern'] = 'This URL pattern is already used by ' . $patterns[$pattern] . '.';
                }
                $patterns[$pattern] = $type['label'];
            }

            $index = $route['index_path'];
            if ($index !== '') {
                if ($error = $this->pathError($index)) {
                    $errors[$key . '.index_path'] = $error;
                } elseif (str_contains($index, '{')) {
                    $errors[$key . '.index_path'] = 'The list page URL cannot contain placeholders.';
                } elseif (isset($literal[$index])) {
                    $errors[$key . '.index_path'] = 'This URL is already used by ' . $literal[$index] . '.';
                } else {
                    $literal[$index] = $type['label'];
                }
            }
        }

        $home = $settings['homepage']['type'];
        if ($home !== '' && (($types[$home]['visibility'] ?? 'public') === 'private')) {
            $errors['homepage.type'] = 'Private content types cannot be used as the homepage.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    private function pathError(string $path): ?string
    {
        if ($path === '/' || $path === '') {
            return 'Use the homepage setting for the site root.';
        }

        if (!preg_match('#^(/[a-z0-9._~{}-]+)+$#', $path)) {
            return 'Use lowercase letters, numbers, dashes and slashes only.';
        }

        $first = explode('/', ltrim($path, '/'))[0];
        if (in_array($first, $this->availableLocales(), true)) return 'Language prefixes are reserved by CometCMS.';

        foreach (self::RESERVED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $prefix . ' is reserved by CometCMS.';
            }
        }

        if (in_array($path, self::RESERVED_FILES, true)) {
            return $path . ' is reserved by CometCMS.';
        }

        return null;
    }

    /** Check final paths as well as route templates, before either settings or entries change. */
    public function validateEntryUrls(array $settings, ?array $candidate = null): void
    {
        $content = \CometCMS\Content\ContentRepository::make($this->workspace);
        foreach ($settings['locales'] as $locale) {
            $resolved = LocalizedValue::tree($settings, $locale, $settings['default_locale']);
            $paths = [];
            $claim = static function (string $path, string $owner) use (&$paths, $locale): void {
                if ($path === '') return;
                if (isset($paths[$path]) && $paths[$path] !== $owner) throw new ValidationException(['slug' => 'URL collision in ' . $locale . ': ' . $path]);
                $paths[$path] = $owner;
            };
            foreach ($this->types() as $name => $type) {
                $route = $resolved['routes'][$name] ?? null;
                if (empty($route['enabled'])) continue;
                $claim($route['index_path'], 'index:' . $name);
                $entries = $content->all($name);
                if (($candidate['collection'] ?? '') === $name) {
                    $entries = array_values(array_filter($entries, static fn(array $entry): bool => $entry['id'] !== $candidate['id']));
                    $entries[] = $candidate;
                }
                $tree = !empty($type['hierarchical']) ? \CometCMS\Content\PageTree::byUid($entries) : null;
                $slugOf = static fn(array $entry): string => (string) $content->resolveLocaleFields($entry, $locale)['slug'];
                foreach ($entries as $entry) {
                    if (!isset($entry['publication'][$locale])) continue;
                    $slug = $tree !== null ? \CometCMS\Content\PageTree::encode(\CometCMS\Content\PageTree::path($tree, $entry, $slugOf)) : rawurlencode($slugOf($entry));
                    $claim(str_replace('{slug}', $slug, $route['pattern']), 'entry:' . $name . ':' . $entry['id']);
                }
            }
        }
    }

    public function normalizeMenus(mixed $menus): array
    {
        $normalized = [];
        $names = [];

        foreach (array_slice(is_array($menus) ? array_values($menus) : [], 0, self::MENU_LIMITS['max_menus']) as $menu) {
            if (!is_array($menu)) {
                continue;
            }

            $label = self::line($menu['label'] ?? '', 80);
            $name = LayoutSchema::key($menu['name'] ?? '') ?: Security::slug($label !== '' ? $label : 'menu');
            if (isset($names[$name])) {
                continue;
            }
            $names[$name] = true;

            $normalized[] = [
                'name' => $name,
                'label' => $label !== '' ? $label : ucfirst($name),
                'items' => $this->normalizeMenuItems($menu['items'] ?? [], 0),
            ];
        }

        return $normalized;
    }

    private function normalizeMenuItems(mixed $items, int $depth): array
    {
        $normalized = [];

        foreach (array_slice(is_array($items) ? array_values($items) : [], 0, self::MENU_LIMITS['max_siblings']) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $normalized[] = [
                'id' => LayoutSchema::key($item['id'] ?? '') ?: LayoutSchema::newId(),
                'label' => $this->localized($item['label'] ?? '', static fn(mixed $v): string => self::line($v, 120)),
                'link' => $this->localized($item['link'] ?? [], LayoutSchema::normalizeLink(...)),
                'children' => $depth + 1 < self::MENU_LIMITS['max_depth'] ? $this->normalizeMenuItems($item['children'] ?? [], $depth + 1) : [],
            ];
        }

        return $normalized;
    }

    /** Validate submissions before normalization, which defensively bounds stored data. */
    public function validateMenus(array $menus, bool $validateDestinations = true): void
    {
        if ($menus !== LocalizedValue::tree($menus, '', '')) {
            $languages = (new LanguageSettings($this->workspace))->all();
            foreach ($languages['locales'] as $locale) $this->validateMenus(LocalizedValue::tree($menus, $locale, $languages['default_locale']), $validateDestinations);
            return;
        }
        $errors = [];
        if (count($menus) > self::MENU_LIMITS['max_menus']) {
            $errors['menus'] = 'A website can have at most ' . self::MENU_LIMITS['max_menus'] . ' menus.';
        }
        $visit = function (mixed $items, int $depth, string $path) use (&$visit, &$errors, $validateDestinations): void {
            if (!is_array($items)) {
                $errors[$path] = 'Menu items must be a list.';
                return;
            }
            if ($items !== [] && $depth > self::MENU_LIMITS['max_depth']) {
                $errors[$path] = 'Menus can have at most ' . self::MENU_LIMITS['max_depth'] . ' levels.';
                return;
            }
            if (count($items) > self::MENU_LIMITS['max_siblings']) {
                $errors[$path] = 'A level can have at most ' . self::MENU_LIMITS['max_siblings'] . ' items.';
            }
            foreach ($items as $index => $item) {
                $key = $path . '.' . $index;
                if (!is_array($item)) {
                    $errors[$key] = 'Invalid menu item.';
                    continue;
                }
                if ($validateDestinations) {
                    $link = is_array($item['link'] ?? null) ? $item['link'] : [];
                    $normalized = LayoutSchema::normalizeLink($link);
                    $type = $link['type'] ?? 'none';
                    if ($normalized['type'] !== $type || $type === 'current') {
                        $errors[$key . '.link'] = 'Choose a menu destination.';
                    } elseif ($type === 'none' && trim((string) ($item['label'] ?? '')) === '') {
                        $errors[$key . '.label'] = 'Group headings need a label.';
                    } elseif ($type === 'url' && ($normalized['url'] ?? '') === '') {
                        $errors[$key . '.link'] = 'Enter a safe, non-empty URL.';
                    } elseif (in_array($type, ['entry', 'index'], true) && ($normalized['collection'] ?? '') === '') {
                        $errors[$key . '.link'] = 'Choose a content type.';
                    } elseif ($type === 'entry' && ($normalized['entry'] ?? '') === '') {
                        $errors[$key . '.link'] = 'Choose an entry.';
                    }
                    if ($type === 'anchor' || (in_array($type, ['home', 'entry', 'index'], true) && trim((string) ($link['anchor'] ?? '')) !== '')) {
                        if (($normalized['anchor'] ?? '') === '') {
                            $errors[$key . '.anchor'] = 'Use an anchor ID of 1–80 letters, numbers, underscores or hyphens.';
                        }
                    }
                }
                $visit($item['children'] ?? [], $depth + 1, $key . '.children');
            }
        };
        foreach ($menus as $index => $menu) {
            if (!is_array($menu)) {
                $errors['menus.' . $index] = 'Invalid menu.';
                continue;
            }
            $visit($menu['items'] ?? [], 1, 'menus.' . $index . '.items');
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    private function requireType(string $type): array
    {
        Security::assertSafeName($type);
        $types = $this->types();

        if (!isset($types[$type])) {
            throw new \RuntimeException('Content type not found.');
        }

        return $types[$type];
    }

    private function assertVariant(array $type, string $variant): void
    {
        if (!in_array($variant, $this->variantsFor($type), true)) {
            throw new \InvalidArgumentException('Unknown layout variant.');
        }
    }

    private function assertRegion(string $region): void
    {
        if (!in_array($region, LayoutSchema::REGION_DOCUMENTS, true)) {
            throw new \InvalidArgumentException('Unknown region.');
        }
    }

    public static function normalizePath(string $path): string
    {
        $path = strtolower(trim($path));

        return $path === '' ? '' : '/' . trim((string) preg_replace('#/+#', '/', $path), '/');
    }

    private static function line(mixed $value, int $max): string
    {
        return is_scalar($value) ? trim(Text::limit(str_replace(["\r", "\n"], ' ', (string) $value), $max)) : '';
    }

    private static function media(mixed $value): string
    {
        $value = is_scalar($value) ? basename(trim((string) $value)) : '';

        return preg_match('/^[^\/\\\\\0]{1,255}$/', $value) ? $value : '';
    }

    private static function domains(mixed $domains): array
    {
        if (is_string($domains)) {
            $domains = preg_split('/[\s,]+/', $domains) ?: [];
        }

        $normalized = [];

        foreach (is_array($domains) ? $domains : [] as $domain) {
            $domain = strtolower(trim((string) $domain));
            $domain = (string) preg_replace('#^[a-z]+://#', '', $domain);
            $domain = explode('/', $domain)[0];
            $domain = (string) preg_replace('/:\d+$/', '', $domain);

            if ($domain !== '' && preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/', $domain)) {
                $normalized[$domain] = true;
            }
        }

        return array_slice(array_keys($normalized), 0, 20);
    }

    /** Label of the workspace, used as the site title until one is set. */
    public function workspaceLabel(): string
    {
        return (string) ((new WorkspaceRepository())->find($this->workspace->slug())['label'] ?? $this->workspace->slug());
    }
}
