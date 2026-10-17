<?php

declare(strict_types=1);

namespace CometCMS\Controllers\Admin;

use CometCMS\Auth\Auth;
use CometCMS\Content\ContentRepository;
use CometCMS\Core\Http;
use CometCMS\Core\ValidationException;
use CometCMS\Site\LayoutSchema;
use CometCMS\Site\PageRenderer;
use CometCMS\Site\SiteContext;
use CometCMS\Site\SiteRepository;
use CometCMS\Site\SiteService;
use CometCMS\Site\Theme;
use CometCMS\Workspaces\WorkspaceContext;

/**
 * Admin API for the optional website: settings, theme, menus, header and
 * footer, per-type layouts and the live previews used by the builder.
 */
final class SiteController extends BaseController
{
    private SiteRepository $site;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->site = new SiteRepository(WorkspaceContext::active());
    }

    public function index(): never
    {
        $user = $this->requirePermission('site.manage', ['resource' => 'site:*']);

        $layouts = [];
        foreach ($this->site->types() as $name => $type) {
            foreach ($this->site->variantsFor($type) as $variant) {
                $layouts[$name][$variant] = $this->site->layoutCustomized($name, $variant);
            }
        }

        $this->json(['data' => [
            'settings' => $this->site->settings(),
            'theme' => $this->site->theme(),
            'menus' => $this->site->menus(),
            'regions' => array_combine(LayoutSchema::REGIONS, array_map(fn(string $region): bool => $this->site->regionCustomized($region), LayoutSchema::REGIONS)),
            'layouts' => $layouts,
            'types' => array_values($this->site->types()),
            'available_locales' => $this->site->availableLocales(),
            'fonts' => array_keys(Theme::FONT_STACKS),
            'theme_defaults' => Theme::defaults(),
            'site_url' => $this->siteUrl(),
            'workspace' => $this->site->workspace()->slug(),
            'workspace_label' => $this->site->workspaceLabel(),
            'can_edit_css' => Auth::allows($user, 'admin'),
        ]]);
    }

    /** Lightweight summary for editors: is the website on, which types are published. */
    public function status(): never
    {
        $user = $this->requireUser();
        $settings = $this->site->settings();
        $types = [];

        foreach ($settings['routes'] as $name => $route) {
            $types[$name] = [
                'enabled' => $settings['enabled'] && $route['enabled'],
                'pages_enabled' => $route['enabled'],
                'pattern' => $route['pattern'],
                'index_path' => $route['index_path'],
            ];
        }

        $this->json(['data' => [
            'enabled' => $settings['enabled'],
            'can_manage' => $this->can($user, 'site.manage', ['resource' => 'site:*']),
            'site_url' => $this->siteUrl(),
            'types' => $types,
        ]]);
    }

    /** Live URL and draft preview URL for one entry. */
    public function links(string $type, string $id): never
    {
        $user = $this->requirePermission('content.read', ['type' => 'content', 'collection' => $type]);
        $settings = $this->site->settings();
        $context = $this->context();
        $entry = $context->content->findByIdentifier($type, $id);
        $url = null;

        if ($settings['enabled'] && $entry !== null && $context->content->isPubliclyVisible($entry)) {
            $locale = (string) ($_GET['locale'] ?? '');
            $url = $this->liveUrl($context->entryUrl($type, $entry, in_array($locale, $context->locales(), true) ? $locale : null), $context);
        }

        $routed = $context->route($type) !== null || ($settings['homepage']['type'] ?? '') === $type;

        $this->json(['data' => [
            'enabled' => $settings['enabled'],
            'routed' => $routed,
            'url' => $url,
            'previewable' => $entry !== null && $context->isPublicType($type),
            'can_manage' => $this->can($user, 'site.manage', ['resource' => 'site:*']),
        ]]);
    }

    public function updateSettings(): never
    {
        $this->requirePermission('site.manage', ['resource' => 'site:*']);
        $this->verifyCsrf();

        try {
            $settings = $this->site->saveSettings($this->requestJson());
        } catch (ValidationException $e) {
            $this->json(['error' => ['code' => 'validation_failed', 'message' => $e->getMessage(), 'fields' => $e->fields()]], 422);
        }

        $this->logger->info('site.settings_updated', ['workspace' => $this->site->workspace()->slug()]);
        $this->json(['data' => $settings]);
    }

    public function updateTheme(): never
    {
        $user = $this->requirePermission('site.manage', ['resource' => 'site:*']);
        $this->verifyCsrf();
        $input = $this->requestJson();
        $current = $this->site->theme();

        // Custom CSS is a free-form value; only administrators may change it.
        if (!Auth::allows($user, 'admin')) {
            $input['custom_css'] = $current['custom_css'];
        }

        $theme = $this->site->saveTheme($input, $user);
        $this->logger->info('site.theme_updated', ['workspace' => $this->site->workspace()->slug()]);
        $this->json(['data' => $theme]);
    }

    public function updateMenus(): never
    {
        $this->requirePermission('site.manage', ['resource' => 'site:*']);
        $this->verifyCsrf();
        $menus = $this->site->saveMenus($this->requestJson()['menus'] ?? []);
        $this->logger->info('site.menus_updated', ['count' => count($menus)]);
        $this->json(['data' => $menus]);
    }

    public function layout(string $type, string $variant): never
    {
        $this->requirePermission('site.manage', ['resource' => 'site:*']);
        $schema = $this->requireType($type, $variant);

        $context = $this->context();

        $this->json(['data' => [
            'nodes' => $this->site->layout($type, $variant),
            'customized' => $this->site->layoutCustomized($type, $variant),
            'type' => $schema,
            'samples' => $this->samples($type, LayoutSchema::key($_GET['entry'] ?? '')),
            'index_url' => $this->liveUrl($context->indexUrl($type), $context),
            'home_url' => $this->liveUrl($context->homeUrl(), $context),
            ...$this->builderContext(),
        ]]);
    }

    public function updateLayout(string $type, string $variant): never
    {
        $user = $this->requirePermission('site.manage', ['resource' => 'site:*']);
        $this->verifyCsrf();
        $this->requireType($type, $variant);
        $nodes = $this->site->saveLayout($type, $variant, $this->requestJson()['nodes'] ?? [], $user);
        $this->logger->info('site.layout_updated', ['type' => $type, 'variant' => $variant]);
        $this->json(['data' => ['nodes' => $nodes, 'customized' => true]]);
    }

    public function resetLayout(string $type, string $variant): never
    {
        $user = $this->requirePermission('site.manage', ['resource' => 'site:*']);
        $this->verifyCsrf();
        $this->requireType($type, $variant);
        $nodes = $this->site->resetLayout($type, $variant, $user);
        $this->logger->info('site.layout_reset', ['type' => $type, 'variant' => $variant]);
        $this->json(['data' => ['nodes' => $nodes, 'customized' => false]]);
    }

    public function region(string $region): never
    {
        $this->requirePermission('site.manage', ['resource' => 'site:*']);
        $this->requireRegion($region);

        $context = $this->context();

        $this->json(['data' => [
            'nodes' => $this->site->region($region),
            'customized' => $this->site->regionCustomized($region),
            'home_url' => $this->liveUrl($context->homeUrl(), $context),
            ...$this->builderContext(),
        ]]);
    }

    public function updateRegion(string $region): never
    {
        $user = $this->requirePermission('site.manage', ['resource' => 'site:*']);
        $this->verifyCsrf();
        $this->requireRegion($region);
        $nodes = $this->site->saveRegion($region, $this->requestJson()['nodes'] ?? [], $user);
        $this->logger->info('site.region_updated', ['region' => $region]);
        $this->json(['data' => ['nodes' => $nodes, 'customized' => true]]);
    }

    public function resetRegion(string $region): never
    {
        $user = $this->requirePermission('site.manage', ['resource' => 'site:*']);
        $this->verifyCsrf();
        $this->requireRegion($region);
        $nodes = $this->site->resetRegion($region, $user);
        $this->logger->info('site.region_reset', ['region' => $region]);
        $this->json(['data' => ['nodes' => $nodes, 'customized' => false]]);
    }

    public function themeRevisions(): never
    {
        $this->requirePermission('site.manage', ['resource' => 'site:*']);
        $this->json(['data' => $this->site->themeRevisions()]);
    }

    public function restoreThemeRevision(string $id): never
    {
        $user = $this->requirePermission('site.manage', ['resource' => 'site:*']);
        $this->verifyCsrf();
        $theme = $this->site->restoreThemeRevision($id, $user, Auth::allows($user, 'admin'));
        $this->sendRestoredRevision($theme, ['target' => 'theme', 'revision' => $id]);
    }

    public function layoutRevisions(string $type, string $variant): never
    {
        $this->requirePermission('site.manage', ['resource' => 'site:*']);
        $this->requireType($type, $variant);
        $this->json(['data' => $this->site->layoutRevisions($type, $variant)]);
    }

    public function restoreLayoutRevision(string $type, string $variant, string $id): never
    {
        $user = $this->requirePermission('site.manage', ['resource' => 'site:*']);
        $this->verifyCsrf();
        $this->requireType($type, $variant);
        $result = $this->site->restoreLayoutRevision($type, $variant, $id, $user);
        $this->sendRestoredRevision($result, ['target' => 'layout', 'type' => $type, 'variant' => $variant, 'revision' => $id]);
    }

    public function regionRevisions(string $region): never
    {
        $this->requirePermission('site.manage', ['resource' => 'site:*']);
        $this->requireRegion($region);
        $this->json(['data' => $this->site->regionRevisions($region)]);
    }

    public function restoreRegionRevision(string $region, string $id): never
    {
        $user = $this->requirePermission('site.manage', ['resource' => 'site:*']);
        $this->verifyCsrf();
        $this->requireRegion($region);
        $result = $this->site->restoreRegionRevision($region, $id, $user);
        $this->sendRestoredRevision($result, ['target' => 'region', 'region' => $region, 'revision' => $id]);
    }

    private function sendRestoredRevision(?array $result, array $details): never
    {
        if ($result === null) {
            $this->json(['error' => ['code' => 'not_found', 'message' => 'Revision not found.']], 404);
        }
        $this->logger->info('site.revision_restored', $details);
        $this->json(['data' => $result]);
    }

    /**
     * Render unsaved work. Body: {target: layout|region|page, type, variant,
     * region, nodes, entry, locale, theme, menus, path}.
     */
    public function preview(): never
    {
        $this->requirePermission('site.manage', ['resource' => 'site:*']);
        $this->verifyCsrf();
        $body = $this->requestJson();
        $context = $this->context();
        $context->preview = true;

        if (is_array($body['theme'] ?? null)) {
            $context->override('theme', Theme::normalize($body['theme']));
        }
        if (is_array($body['menus'] ?? null)) {
            $context->override('menus', $this->site->normalizeMenus($body['menus']));
        }

        $locale = (string) ($body['locale'] ?? '');
        $target = (string) ($body['target'] ?? 'page');

        if ($target === 'page') {
            $response = (new SiteService($context))->respond((string) ($body['path'] ?? '/'), []);
            $this->json(['data' => ['html' => $response['body']]]);
        }

        $context->annotate = true;
        $renderer = new PageRenderer($context);

        if ($target === 'region') {
            $region = (string) ($body['region'] ?? '');
            $this->requireRegion($region);
            if (array_key_exists('nodes', $body)) {
                $context->override('region:' . $region, LayoutSchema::normalize($body['nodes']));
            }
            $context->locale = in_array($locale, $context->locales(), true) ? $locale : $context->defaultLocale();

            $this->json(['data' => ['html' => $renderer->regionPreview($region)]]);
        }

        $type = (string) ($body['type'] ?? '');
        $variant = (string) ($body['variant'] ?? 'detail');
        $schema = $this->requireType($type, $variant);

        if (array_key_exists('nodes', $body)) {
            $context->override('layout:' . $type . ':' . $variant, LayoutSchema::normalize($body['nodes']));
        }

        if ($locale !== '' && in_array($locale, (array) ($schema['locales'] ?? []), true)) {
            $context->locale = $locale;
        }

        $identifier = LayoutSchema::key($body['entry'] ?? '');
        $entry = $identifier !== '' ? $context->findEntry($type, $identifier, true) : null;
        if ($entry === null && !empty($schema['singleton'])) {
            $entry = $context->singletonEntry($type, true);
        }

        $samples = [];
        if ($variant === 'card') {
            foreach ($context->query($type, ['limit' => 3, 'sort' => '-published_at'])['data'] as $sample) {
                if (($sample['slug'] ?? '') !== ($entry['slug'] ?? null)) {
                    $samples[] = $context->localize($type, $sample);
                }
            }
        }

        $this->json(['data' => ['html' => $renderer->layoutPreview($schema, $variant, $entry, $samples)]]);
    }

    /** Full-page draft preview of one entry, opened from the entry editor. */
    public function previewEntry(string $type, string $id): never
    {
        $this->requirePermission('content.read', ['type' => 'content', 'collection' => $type]);
        $html = (new SiteService($this->context()))->previewEntry($type, $id, (string) ($_GET['locale'] ?? ''));

        if ($html === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'This entry cannot be previewed on the website.';
            exit;
        }

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: private, no-store');
        header('X-Robots-Tag: noindex, nofollow');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; media-src 'self' https:; style-src 'self' 'unsafe-inline' https:; font-src 'self' data: https:; script-src 'none'; object-src 'none'; base-uri 'self'; form-action 'none'");
        echo $html;
        exit;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function context(): SiteContext
    {
        return SiteContext::make($this->site, rtrim($this->http->url('/'), '/'), $this->requestOrigin());
    }

    private function builderContext(): array
    {
        return [
            'types' => array_values($this->site->types()),
            'menus' => array_map(static fn(array $menu): array => ['name' => $menu['name'], 'label' => $menu['label']], $this->site->menus()),
            'locales' => $this->site->settings()['locales'],
            'default_locale' => $this->site->settings()['default_locale'],
            'spec' => LayoutSchema::spec(),
        ];
    }

    /** Recent entries to preview with; `$ensure` keeps a requested entry in the list. */
    private function samples(string $type, string $ensure = ''): array
    {
        $content = ContentRepository::make($this->site->workspace());
        $entries = $content->query($type, ['sort' => '-updated_at', 'limit' => 50], true)['data'];

        if ($ensure !== '' && !in_array($ensure, array_map(static fn(array $entry): string => (string) ($entry['slug'] ?? $entry['id'] ?? ''), $entries), true)) {
            $requested = $content->findByIdentifier($type, $ensure);
            if ($requested !== null) {
                array_unshift($entries, $requested);
            }
        }

        return array_map(static fn(array $entry): array => [
            'id' => (string) ($entry['slug'] ?? $entry['id'] ?? ''),
            'title' => (string) ($entry['title'] ?? ''),
            'status' => (string) ($entry['status'] ?? 'draft'),
        ], $entries);
    }

    private function requireType(string $type, string $variant): array
    {
        $schema = $this->site->types()[$type] ?? null;

        if ($schema === null) {
            $this->json(['error' => ['code' => 'not_found', 'message' => 'Content type not found.']], 404);
        }

        if (!in_array($variant, $this->site->variantsFor($schema), true)) {
            $this->json(['error' => ['code' => 'not_found', 'message' => 'Unknown layout variant.']], 404);
        }

        return $schema;
    }

    private function requireRegion(string $region): void
    {
        if (!in_array($region, LayoutSchema::REGIONS, true)) {
            $this->json(['error' => ['code' => 'not_found', 'message' => 'Unknown region.']], 404);
        }
    }

    /** Absolute live URL for a site path, or null while the website is off. */
    private function liveUrl(?string $path, SiteContext $context): ?string
    {
        if ($path === null || !$this->site->settings()['enabled']) {
            return null;
        }

        return $this->siteUrl() . substr($path, strlen($context->basePath));
    }

    /** Public address of the website: its first domain, or this server. */
    private function siteUrl(): string
    {
        $domains = $this->site->settings()['domains'];
        $base = rtrim($this->http->url('/'), '/');

        return $domains !== [] ? 'https://' . $domains[0] . $base : $this->requestOrigin() . $base;
    }

    private function requestOrigin(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');

        if ($host === '' || !preg_match('/^[A-Za-z0-9.-]+(:\d+)?$/', $host)) {
            return '';
        }

        return ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . $host;
    }
}
