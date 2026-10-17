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
 *   settings.json               enabled, homepage, URL patterns, SEO defaults
 *   theme.json                  design tokens and custom CSS
 *   menus.json                  navigation menus
 *   regions/{header,footer}.json
 *   layouts/{type}/{variant}.json
 *
 * Layouts and regions that were never customized are generated from the
 * content type on the fly, so enabling the website works without setup.
 */
final class SiteRepository
{
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
        $this->store->write($settings, 'settings');
        $this->settingsCache = $settings;
        $this->clearCache();

        return $settings;
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
        $stored = $this->store->read('theme') ?? [];

        return Theme::normalize($stored) + ['updated_at' => (string) ($stored['updated_at'] ?? '')];
    }

    public function saveTheme(array $input, array $user = [], string $event = 'saved'): array
    {
        $theme = Theme::normalize($input);
        $this->writeRevision(['theme'], ['theme' => $this->theme()], $user, $event);
        $theme['updated_at'] = Security::now();
        $this->store->write($theme, 'theme');
        $this->clearCache();

        return $theme;
    }

    /** Changes whenever the generated stylesheet would change, for cache busting. */
    public function stylesheetVersion(): string
    {
        $theme = $this->store->read('theme') ?? [];
        $base = __DIR__ . '/assets/site.css';

        return substr(sha1(json_encode($theme) . '|' . (is_file($base) ? (string) filemtime($base) : '') . '|' . (function_exists('comet_version') ? comet_version() : '')), 0, 12);
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

        return $stored !== null ? LayoutSchema::normalize($stored['nodes'] ?? []) : LayoutSchema::defaultRegion($region);
    }

    public function regionCustomized(string $region): bool
    {
        $this->assertRegion($region);

        return $this->store->read('regions', $region) !== null;
    }

    public function saveRegion(string $region, mixed $nodes, array $user = [], string $event = 'saved'): array
    {
        $this->assertRegion($region);
        $nodes = LayoutSchema::normalize($nodes);
        $this->writeRevision(['regions', $region], $this->regionSnapshot($region), $user, $event);
        $this->store->write(['nodes' => $nodes, 'updated_at' => Security::now()], 'regions', $region);
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

        return $stored !== null ? LayoutSchema::normalize($stored['nodes'] ?? []) : LayoutSchema::defaultLayout($schema, $variant);
    }

    public function layoutCustomized(string $type, string $variant): bool
    {
        Security::assertSafeName($type);
        Security::assertSafeName($variant);

        return $this->store->read('layouts', $type, $variant) !== null;
    }

    public function saveLayout(string $type, string $variant, mixed $nodes, array $user = [], string $event = 'saved'): array
    {
        $schema = $this->requireType($type);
        $this->assertVariant($schema, $variant);
        $nodes = LayoutSchema::normalize($nodes);
        $this->writeRevision(['layouts', $type, $variant], $this->layoutSnapshot($type, $variant), $user, $event);
        $this->store->write(['nodes' => $nodes, 'updated_at' => Security::now()], 'layouts', $type, $variant);
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

    /** Variants a content type can have: single pages have no card or index. */
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
        if (!is_array($revision['snapshot']['nodes'] ?? null)) {
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
        if (!is_array($revision['snapshot']['nodes'] ?? null)) {
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
        return ['nodes' => $this->region($region), 'customized' => $this->regionCustomized($region)];
    }

    private function layoutSnapshot(string $type, string $variant): array
    {
        return ['nodes' => $this->layout($type, $variant), 'customized' => $this->layoutCustomized($type, $variant)];
    }

    private function listRevisions(array $path): array
    {
        $revisions = $this->revisions->all(...$path);
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

        return [
            'enabled' => (bool) ($input['enabled'] ?? false),
            'title' => self::line($input['title'] ?? '', 120),
            'tagline' => self::line($input['tagline'] ?? '', 200),
            'logo' => self::media($input['logo'] ?? ''),
            'favicon' => self::media($input['favicon'] ?? ''),
            'homepage' => [
                'type' => isset($types[$homeType]) ? $homeType : '',
                'entry' => isset($types[$homeType]) ? LayoutSchema::key($homepage['entry'] ?? '') : '',
            ],
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

        foreach (array_slice(is_array($menus) ? array_values($menus) : [], 0, 20) as $menu) {
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

        foreach (array_slice(is_array($items) ? array_values($items) : [], 0, 60) as $item) {
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
                'children' => $depth === 0 ? $this->normalizeMenuItems($item['children'] ?? [], 1) : [],
            ];
        }

        return $normalized;
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
