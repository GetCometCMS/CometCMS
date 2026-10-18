<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Cache\ApiCache;
use CometCMS\Content\ContentTypeRepository;
use CometCMS\Core\Security;
use CometCMS\Core\ValidationException;
use CometCMS\Storage\JsonStore;
use CometCMS\Workspaces\WorkspaceContext;
use CometCMS\Workspaces\WorkspaceRepository;

/**
 * Website configuration of one workspace, stored under
 * storage/workspaces/{workspace}/site/:
 *
 *   settings.json               homepage, URL patterns, SEO defaults
 *   theme/tokens.json           design tokens
 *   theme/style.css             custom CSS, loaded after the generated theme
 *   menus.json                  navigation menus
 *   regions/{header,footer}.json
 *   layouts/{type}/{variant}.json
 *   components/{id}.json        reusable blocks placed in layouts and regions
 *   forms/{id}.json             website forms (see FormRepository)
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
        $settings = $this->normalizeSettings($input);
        $this->validateSettings($settings);
        $settings['updated_at'] = Security::now();
        // "enabled" is derived on every read and never stored.
        $this->store->write(array_diff_key($settings, ['enabled' => true]), 'settings');
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
        $singleton = !empty($type['singleton']);

        return [
            'enabled' => false,
            'pattern' => $singleton ? '/' . $name : '/' . $name . '/{slug}',
            'index_path' => $singleton ? '' : '/' . $name,
            'index_title' => '',
            'seo_description_field' => '',
            'seo_image_field' => '',
            'sitemap' => true,
        ];
    }

    /** Locales any content type defines; the website can offer a subset. */
    public function availableLocales(): array
    {
        $locales = [];

        foreach ($this->types() as $type) {
            foreach ((array) ($type['locales'] ?? []) as $locale) {
                $locales[(string) $locale] = true;
            }
        }

        return array_keys($locales);
    }

    // ─── Theme ───────────────────────────────────────────────────────────────

    public function theme(): array
    {
        // Older workspaces keep both tokens and CSS in site/theme.json.
        // Read them until the next save migrates the theme into its directory.
        $legacy = $this->store->read('theme');
        $stored = $legacy ?? $this->store->read('theme', 'tokens') ?? [];
        $path = $this->store->path('theme', 'style') . '.css';
        // A restored legacy backup takes precedence over the newer files.
        if ($legacy === null && is_file($path)) {
            $css = file_get_contents($path);
            if ($css === false) {
                throw new \RuntimeException('Could not read the theme stylesheet.');
            }
            $stored['custom_css'] = $css;
        }

        return Theme::normalize($stored) + ['updated_at' => (string) ($stored['updated_at'] ?? '')];
    }

    public function saveTheme(array $input, array $user = [], string $event = 'saved'): array
    {
        $theme = Theme::normalize($input);
        $this->writeRevision(['theme'], ['theme' => $this->theme()], $user, $event);
        $theme['updated_at'] = Security::now();
        $this->writeThemeStylesheet($theme['custom_css']);
        $this->store->write(array_diff_key($theme, ['custom_css' => true]), 'theme', 'tokens');
        $this->store->delete('theme');
        $this->clearCache();

        return $theme;
    }

    /** Replace the stylesheet atomically so readers never see a partial edit. */
    private function writeThemeStylesheet(string $css): void
    {
        $path = $this->store->path('theme', 'style') . '.css';
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

        return substr(sha1(json_encode($theme) . '|' . (is_file($base) ? (string) filemtime($base) : '') . '|' . (function_exists('comet_version') ? comet_version() : '')), 0, 12);
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
                    ['id' => 'home', 'label' => 'Home', 'labels' => [], 'link' => ['type' => 'home', 'new_tab' => false], 'children' => []],
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

    public function saveMenus(array $menus): array
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
        $stored = $this->store->read('regions', $region);

        return ($stored['format_version'] ?? null) === LayoutSchema::FORMAT_VERSION ? LayoutSchema::normalize($stored['nodes'] ?? []) : LayoutSchema::defaultRegion($region);
    }

    public function regionCustomized(string $region): bool
    {
        $this->assertRegion($region);

        return ($this->store->read('regions', $region)['format_version'] ?? null) === LayoutSchema::FORMAT_VERSION;
    }

    public function saveRegion(string $region, mixed $nodes, array $user = [], string $event = 'saved'): array
    {
        $this->assertRegion($region);
        $nodes = LayoutSchema::normalize($nodes);
        $this->writeRevision(['regions', $region], $this->regionSnapshot($region), $user, $event);
        $this->store->write(['format_version' => LayoutSchema::FORMAT_VERSION, 'nodes' => $nodes, 'updated_at' => Security::now()], 'regions', $region);
        $this->clearCache();

        return $nodes;
    }

    public function resetRegion(string $region, array $user = [], string $event = 'reset'): array
    {
        $this->assertRegion($region);
        $this->writeRevision(['regions', $region], $this->regionSnapshot($region), $user, $event);
        $this->store->delete('regions', $region);
        $this->clearCache();

        return $this->region($region);
    }

    // ─── Layouts ─────────────────────────────────────────────────────────────

    public function layout(string $type, string $variant): array
    {
        $schema = $this->requireType($type);
        $this->assertVariant($schema, $variant);
        $stored = $this->store->read('layouts', $type, $variant);

        return ($stored['format_version'] ?? null) === LayoutSchema::FORMAT_VERSION ? LayoutSchema::normalize($stored['nodes'] ?? []) : LayoutSchema::defaultLayout($schema, $variant);
    }

    public function layoutCustomized(string $type, string $variant): bool
    {
        Security::assertSafeName($type);
        Security::assertSafeName($variant);

        return ($this->store->read('layouts', $type, $variant)['format_version'] ?? null) === LayoutSchema::FORMAT_VERSION;
    }

    public function saveLayout(string $type, string $variant, mixed $nodes, array $user = [], string $event = 'saved'): array
    {
        $schema = $this->requireType($type);
        $this->assertVariant($schema, $variant);
        $nodes = LayoutSchema::normalize($nodes);
        $this->writeRevision(['layouts', $type, $variant], $this->layoutSnapshot($type, $variant), $user, $event);
        $this->store->write(['format_version' => LayoutSchema::FORMAT_VERSION, 'nodes' => $nodes, 'updated_at' => Security::now()], 'layouts', $type, $variant);
        $this->clearCache();

        return $nodes;
    }

    public function resetLayout(string $type, string $variant, array $user = [], string $event = 'reset'): array
    {
        $schema = $this->requireType($type);
        $this->assertVariant($schema, $variant);
        $this->writeRevision(['layouts', $type, $variant], $this->layoutSnapshot($type, $variant), $user, $event);
        $this->store->delete('layouts', $type, $variant);
        $this->clearCache();

        return LayoutSchema::defaultLayout($schema, $variant);
    }

    /**
     * The standard card a collection shows for entries of $type when it has
     * no card of its own: the type's card layout from before cards lived in
     * collections, or one generated from the type's fields.
     */
    public function cardTemplate(string $type): array
    {
        $schema = $this->requireType($type);
        $stored = $this->store->read('layouts', $type, 'card');

        return ($stored['format_version'] ?? null) === LayoutSchema::FORMAT_VERSION
            ? LayoutSchema::cardTemplate(LayoutSchema::normalize($stored['nodes'] ?? []))
            : LayoutSchema::defaultCardTemplate($schema);
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

        foreach ($this->store->all('components') as $stored) {
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

        return $this->readComponent($this->store->read('components', $id));
    }

    /** Create a component from one structure node (and everything inside it). */
    public function createComponent(mixed $name, mixed $nodes, mixed $type = '', array $user = []): array
    {
        $nodes = is_array($nodes) ? array_values($nodes) : [];
        $root = is_array($nodes[0] ?? null) ? (string) ($nodes[0]['kind'] ?? '') : '';
        if (count($nodes) !== 1 || !in_array($root, LayoutSchema::STRUCTURE, true)) {
            throw new ValidationException(['nodes' => 'A component is made from one section, column set or column.']);
        }

        $now = Security::now();
        $component = $this->validateComponent([
            'id' => 'c' . bin2hex(random_bytes(5)),
            'name' => $this->componentName($name),
            'root' => $root,
            'type' => '',
            'created_at' => $now,
        ], $nodes, $type);
        $this->writeComponent($component);

        return $component;
    }

    public function saveComponent(string $id, mixed $nodes, array $user = [], string $event = 'saved'): array
    {
        $current = $this->requireComponent($id);
        $component = $this->validateComponent($current, $nodes, $current['type']);
        $this->writeRevision(['components', $id], $this->componentSnapshot($current), $user, $event);
        $this->writeComponent($component);

        return $component;
    }

    public function renameComponent(string $id, mixed $name): array
    {
        $component = $this->requireComponent($id);
        $component['name'] = $this->componentName($name);
        $this->writeComponent($component);

        return $component;
    }

    /**
     * Where a component is placed directly: stored layouts, regions and other
     * components. Generated default layouts never contain components.
     *
     * @return list<array{target: string, type?: string, variant?: string, region?: string, id?: string, name?: string}>
     */
    public function componentUsages(string $id): array
    {
        $usages = [];
        $uses = static fn(?array $stored): bool => ($stored['format_version'] ?? null) === LayoutSchema::FORMAT_VERSION
            && in_array($id, LayoutSchema::componentRefs(is_array($stored['nodes'] ?? null) ? $stored['nodes'] : []), true);

        foreach ($this->types() as $name => $type) {
            // Old card layouts still render as their type's standard card.
            foreach ([...$this->variantsFor($type), 'card'] as $variant) {
                if ($uses($this->store->read('layouts', (string) $name, $variant))) {
                    $usages[] = ['target' => 'layout', 'type' => (string) $name, 'variant' => $variant];
                }
            }
        }
        foreach (LayoutSchema::REGIONS as $region) {
            if ($uses($this->store->read('regions', $region))) {
                $usages[] = ['target' => 'region', 'region' => $region];
            }
        }
        foreach ($this->store->all('components') as $stored) {
            if (($stored['id'] ?? '') !== $id && $uses($stored)) {
                $usages[] = ['target' => 'component', 'id' => (string) $stored['id'], 'name' => (string) ($stored['name'] ?? $stored['id'])];
            }
        }

        return $usages;
    }

    /**
     * Where a website form is placed: stored layouts, regions and components.
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
                if ($uses($this->store->read('layouts', (string) $name, $variant))) {
                    $usages[] = ['target' => 'layout', 'type' => (string) $name, 'variant' => $variant];
                }
            }
        }
        foreach (LayoutSchema::REGIONS as $region) {
            if ($uses($this->store->read('regions', $region))) {
                $usages[] = ['target' => 'region', 'region' => $region];
            }
        }
        foreach ($this->store->all('components') as $stored) {
            if ($uses($stored)) {
                $usages[] = ['target' => 'component', 'id' => (string) ($stored['id'] ?? ''), 'name' => (string) ($stored['name'] ?? $stored['id'] ?? '')];
            }
        }

        return $usages;
    }

    /** Delete a component and its history, keeping all placements as detached copies. */
    public function deleteComponent(string $id): array
    {
        $component = $this->requireComponent($id);
        $paths = array_map(static fn(string $region): array => ['regions', $region], LayoutSchema::REGIONS);
        foreach ($this->types() as $name => $type) {
            foreach ([...$this->variantsFor($type), 'card'] as $variant) {
                $paths[] = ['layouts', (string) $name, $variant];
            }
        }
        foreach ($this->components() as $other) {
            if ($other['id'] !== $id) {
                $paths[] = ['components', $other['id']];
            }
        }

        foreach ($paths as $path) {
            $stored = $this->store->read(...$path);
            if ($this->hasComponentInstance($stored, $id)) {
                $stored['nodes'] = $this->detachComponentInstances($stored['nodes'], $component);
                $stored['updated_at'] = Security::now();
                $this->store->write($stored, ...$path);
            }
            // Restoring an older layout must not bring back a missing component.
            foreach ($this->revisions->all(...$path) as $revision) {
                if ($this->hasComponentInstance($revision['snapshot'] ?? null, $id)) {
                    $revision['snapshot']['nodes'] = $this->detachComponentInstances($revision['snapshot']['nodes'], $component);
                    $this->revisions->write($revision, ...[...$path, $revision['id']]);
                }
            }
        }

        $this->store->delete('components', $id);
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
        $this->requireComponent($id);

        return $this->listRevisions(['components', $id]);
    }

    public function restoreComponentRevision(string $id, string $revisionId, array $user = []): ?array
    {
        $this->requireComponent($id);
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
            'updated_at' => (string) ($stored['updated_at'] ?? ''),
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
        $stored = $this->store->read('components', $from);
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

    private function writeComponent(array $component): void
    {
        $component['updated_at'] = Security::now();
        $this->store->write(['format_version' => LayoutSchema::FORMAT_VERSION, ...$component], 'components', $component['id']);
        $this->clearCache();
    }

    private function componentSnapshot(array $component): array
    {
        return ['format_version' => LayoutSchema::FORMAT_VERSION, 'nodes' => $component['nodes'], 'customized' => true];
    }

    public function needsLayoutUpgrade(string $type, string $variant): bool
    {
        Security::assertSafeName($type);
        Security::assertSafeName($variant);
        $stored = $this->store->read('layouts', $type, $variant);
        return $stored !== null && ($stored['format_version'] ?? null) !== LayoutSchema::FORMAT_VERSION;
    }

    public function needsRegionUpgrade(string $region): bool
    {
        $this->assertRegion($region);
        $stored = $this->store->read('regions', $region);
        return $stored !== null && ($stored['format_version'] ?? null) !== LayoutSchema::FORMAT_VERSION;
    }

    /** Variants a content type can have: single pages have no index. */
    public function variantsFor(array $type): array
    {
        return !empty($type['singleton']) ? ['detail'] : LayoutSchema::VARIANTS;
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
        // Including card layouts from before cards lived in collections.
        foreach ([...LayoutSchema::VARIANTS, 'card'] as $variant) {
            $this->store->delete('layouts', $type, $variant);
            foreach ($this->listRevisions(['layouts', $type, $variant]) as $revision) {
                $this->revisions->delete('layouts', $type, $variant, $revision['id']);
            }
        }

        $directory = $this->store->path('layouts', $type);
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

    private function normalizeSettings(array $input): array
    {
        $types = $this->types();
        $routes = [];

        foreach ($types as $name => $type) {
            $route = is_array($input['routes'][$name] ?? null) ? $input['routes'][$name] : [];
            $default = $this->defaultRoute($type);
            $routes[$name] = [
                'enabled' => (bool) ($route['enabled'] ?? $default['enabled']),
                'pattern' => self::normalizePath((string) ($route['pattern'] ?? $default['pattern'])),
                'index_path' => empty($type['singleton']) ? self::normalizePath((string) ($route['index_path'] ?? $default['index_path'])) : '',
                'index_title' => self::line($route['index_title'] ?? '', 200),
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

        $available = $this->availableLocales();
        $locales = array_values(array_intersect(
            $available,
            array_map('strval', is_array($input['locales'] ?? null) ? $input['locales'] : [])
        ));
        $defaultLocale = (string) ($input['default_locale'] ?? '');
        if ($locales === []) {
            $defaultLocale = '';
        } elseif (!in_array($defaultLocale, $locales, true)) {
            $defaultLocale = $locales[0];
        }

        $homepage = is_array($input['homepage'] ?? null) ? $input['homepage'] : [];
        $homeType = LayoutSchema::key($homepage['type'] ?? '');
        $homeType = isset($types[$homeType]) ? $homeType : '';
        $homepage = [
            'type' => $homeType,
            'entry' => $homeType !== '' ? LayoutSchema::key($homepage['entry'] ?? '') : '',
        ];

        return [
            // Read-only: derived from the homepage and routes (see isLive()).
            // A stored private homepage is rejected on save, but must not make
            // the website live in the meantime.
            'enabled' => self::isLive([
                'homepage' => ($types[$homeType]['visibility'] ?? 'public') === 'private' ? [] : $homepage,
                'routes' => $routes,
            ]),
            'title' => self::line($input['title'] ?? '', 120),
            'tagline' => self::line($input['tagline'] ?? '', 200),
            'logo' => self::media($input['logo'] ?? ''),
            'favicon' => self::media($input['favicon'] ?? ''),
            'homepage' => $homepage,
            'locales' => $locales,
            'default_locale' => $defaultLocale,
            'domains' => self::domains($input['domains'] ?? []),
            'title_template' => self::line($input['title_template'] ?? '{title} · {site}', 200) ?: '{title} · {site}',
            'description' => self::line($input['description'] ?? '', 300),
            'og_image' => self::media($input['og_image'] ?? ''),
            'noindex' => (bool) ($input['noindex'] ?? false),
            'not_found_title' => self::line($input['not_found_title'] ?? '', 200) ?: 'Page not found',
            'not_found_message' => self::line($input['not_found_message'] ?? '', 500) ?: 'The page you are looking for does not exist or has moved.',
            'routes' => $routes,
            'updated_at' => (string) ($input['updated_at'] ?? ''),
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
            $singleton = !empty($type['singleton']);
            $key = 'routes.' . $name;

            $pattern = $route['pattern'];
            $placeholders = preg_match_all('/\{[^}]*\}/', $pattern, $found);

            if ($error = $this->pathError($pattern)) {
                $errors[$key . '.pattern'] = $error;
            } elseif ($singleton && $placeholders > 0) {
                $errors[$key . '.pattern'] = 'Single pages use a fixed URL without placeholders.';
            } elseif (!$singleton && ($placeholders !== 1 || $found[0][0] !== '{slug}')) {
                $errors[$key . '.pattern'] = 'Collection URLs need exactly one {slug} placeholder.';
            } elseif ($singleton) {
                if (isset($literal[$pattern])) {
                    $errors[$key . '.pattern'] = 'This URL is already used by ' . $literal[$pattern] . '.';
                }
                $literal[$pattern] = $type['label'];
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

            $labels = [];
            foreach (is_array($item['labels'] ?? null) ? $item['labels'] : [] as $locale => $value) {
                $text = self::line($value, 120);
                if ($text !== '' && preg_match('/^[a-z0-9-]{1,20}$/', (string) $locale)) {
                    $labels[(string) $locale] = $text;
                }
            }

            $normalized[] = [
                'id' => LayoutSchema::key($item['id'] ?? '') ?: LayoutSchema::newId(),
                'label' => self::line($item['label'] ?? '', 120),
                'labels' => $labels,
                'link' => LayoutSchema::normalizeLink($item['link'] ?? []),
                'children' => $depth + 1 < self::MENU_LIMITS['max_depth'] ? $this->normalizeMenuItems($item['children'] ?? [], $depth + 1) : [],
            ];
        }

        return $normalized;
    }

    /** Validate submissions before normalization, which defensively bounds stored data. */
    public function validateMenus(array $menus, bool $validateDestinations = true): void
    {
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
        if (!in_array($region, LayoutSchema::REGIONS, true)) {
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
