<?php

declare(strict_types=1);

use CometCMS\Content\ContentRepository;
use CometCMS\Content\ContentTypeRepository;
use CometCMS\Media\MediaRepository;
use CometCMS\Site\LayoutSchema;
use CometCMS\Site\SiteContext;
use CometCMS\Site\SiteRepository;
use CometCMS\Site\SiteService;
use CometCMS\Storage\JsonStore;
use CometCMS\Workspaces\WorkspaceContext;
use CometCMS\Workspaces\WorkspaceRepository;

function comet_site_cache_test_get(string $path, array $query = [], ?SiteContext $context = null): array
{
    return (new SiteService($context ?? comet_site_test_context()))->respondCached($path, $query);
}

test('website cache hits normalize paths and ignore unrelated query parameters', function (): void {
    comet_site_test_setup();
    $first = comet_site_cache_test_get('/blog/hello');
    assert_same('MISS', $first['cache_status']);
    $second = comet_site_cache_test_get('/blog/hello/', ['page' => 1, 'utm_source' => 'newsletter']);
    assert_same('HIT', $second['cache_status']);
    assert_same($first['body'], $second['body']);

    assert_same('MISS', comet_site_cache_test_get('/blog', ['page' => 2])['cache_status']);
    assert_same('MISS', comet_site_cache_test_get('/blog', ['page' => PHP_INT_MAX])['cache_status']);
    assert_same('HIT', comet_site_cache_test_get('/blog', ['page' => 10000])['cache_status']);
});

test('website cache isolates origins and installation prefixes', function (): void {
    comet_site_test_setup();

    foreach ([
        ['https://example.test', ''],
        ['http://example.test', ''],
        ['https://other.test', ''],
        ['https://example.test', '/cms'],
    ] as [$origin, $base]) {
        $context = SiteContext::make(new SiteRepository(), $base, $origin);
        $response = comet_site_cache_test_get('/blog/hello', [], $context);
        assert_same('MISS', $response['cache_status']);
        assert_true(str_contains($response['body'], 'href="' . $origin . $base . '/blog/hello"'));
        assert_same('HIT', comet_site_cache_test_get('/blog/hello', [], $context)['cache_status']);
    }
});

test('website cache isolates workspaces and invalidation stays in its workspace', function (): void {
    comet_site_test_setup();
    (new WorkspaceRepository())->save(['slug' => 'other', 'label' => 'Other website']);
    $other = new WorkspaceContext('other');
    $otherSite = new SiteRepository($other);
    $otherSite->saveSettings(['enabled' => true, 'title' => 'Other website']);
    $otherContext = fn(): SiteContext => SiteContext::make(new SiteRepository($other), '', 'https://example.test');
    $defaultContext = fn(): SiteContext => SiteContext::make(new SiteRepository(new WorkspaceContext('default')), '', 'https://example.test');

    assert_same('MISS', comet_site_cache_test_get('/', [], $defaultContext())['cache_status']);
    $response = comet_site_cache_test_get('/', [], $otherContext());
    assert_same('MISS', $response['cache_status']);
    assert_true(str_contains($response['body'], 'Other website'));

    $otherSite->saveTheme(['base_size' => 20]);
    assert_same('HIT', comet_site_cache_test_get('/', [], $defaultContext())['cache_status']);
    assert_same('MISS', comet_site_cache_test_get('/', [], $otherContext())['cache_status']);
});

test('website cache bypasses previews without replacing public pages', function (): void {
    comet_site_test_setup();
    $live = comet_site_cache_test_get('/blog/hello');
    $context = comet_site_test_context();
    $context->preview = true;
    $context->override('layout:blog:detail', []);
    $preview = comet_site_cache_test_get('/blog/hello', [], $context);
    assert_same('BYPASS', $preview['cache_status']);
    assert_false($preview['cacheable']);
    assert_false(str_contains($preview['body'], 'First step'));

    $cached = comet_site_cache_test_get('/blog/hello');
    assert_same('HIT', $cached['cache_status']);
    assert_same($live['body'], $cached['body']);
});

test('website cache honors the configured off switch', function (): void {
    global $cometConfig;
    comet_site_test_setup();
    $enabled = $cometConfig['cache']['enabled'];
    $cometConfig['cache']['enabled'] = false;

    try {
        assert_same('MISS', comet_site_cache_test_get('/blog/hello')['cache_status']);
        assert_same('MISS', comet_site_cache_test_get('/blog/hello')['cache_status']);
        assert_same([], glob(comet_test_workspace_path() . '/cache/api/*.json'));
    } finally {
        $cometConfig['cache']['enabled'] = $enabled;
    }
});

test('scheduled publishing bounds cached pages including 404s and sitemaps', function (): void {
    comet_site_test_setup();
    $at = time() + 120;
    $content = ContentRepository::make();
    $content->save('blog', ['title' => 'Coming soon', 'status' => 'published', 'published_at' => gmdate('c', $at)], comet_site_test_user());
    // Even an unrouted type can be referenced by layouts and relations.
    $content->save('authors', ['title' => 'Next author', 'status' => 'published', 'published_at' => gmdate('c', $at - 60)], comet_site_test_user());

    foreach (['/blog/coming-soon', '/blog', '/sitemap.xml', '/robots.txt'] as $path) {
        assert_same('MISS', comet_site_cache_test_get($path)['cache_status']);
        assert_same('HIT', comet_site_cache_test_get($path)['cache_status']);
    }

    foreach (glob(comet_test_workspace_path() . '/cache/api/*.json') as $file) {
        $payload = json_decode(file_get_contents($file), true);
        assert_same($at - 60, $payload['expires_at'], 'Every response expires at the earliest publication.');
        // Simulate reaching that deadline without making the suite sleep.
        $payload['expires_at'] = time();
        file_put_contents($file, json_encode($payload));
    }

    // Advance the stored publication date as well, without triggering a write invalidation.
    $store = new JsonStore(comet_test_workspace_path() . '/content');
    $entry = $store->read('blog', 'coming-soon');
    $entry['publication']['en']['published_at'] = gmdate('c', time() - 1);
    $store->write($entry, 'blog', 'coming-soon');

    $published = comet_site_cache_test_get('/blog/coming-soon');
    assert_same('MISS', $published['cache_status']);
    assert_same(200, $published['status']);
    assert_true(str_contains(comet_site_cache_test_get('/sitemap.xml')['body'], '/blog/coming-soon'));
});

test('content updates unpublishing renaming trash and restore invalidate rendered pages', function (): void {
    comet_site_test_setup();
    $content = ContentRepository::make();
    $user = comet_site_test_user();
    comet_site_cache_test_get('/blog/hello');
    comet_site_cache_test_get('/sitemap.xml');

    $entry = $content->find('blog', 'hello');
    $entry['title'] = 'Updated headline';
    $content->save('blog', array_intersect_key($entry, array_flip(['title', 'status', 'slug'])), $user, 'hello');
    $response = comet_site_cache_test_get('/blog/hello');
    assert_same('MISS', $response['cache_status']);
    assert_true(str_contains($response['body'], 'Updated headline'));
    assert_same('MISS', comet_site_cache_test_get('/sitemap.xml')['cache_status']);

    $entry['status'] = 'draft';
    $content->save('blog', array_intersect_key($entry, array_flip(['title', 'status', 'slug'])), $user, 'hello');
    assert_same(404, comet_site_cache_test_get('/blog/hello')['status']);
    $entry['status'] = 'published';
    $entry['slug'] = 'renamed';
    $content->save('blog', array_intersect_key($entry, array_flip(['title', 'status', 'slug'])), $user, 'hello');
    assert_same(404, comet_site_cache_test_get('/blog/hello')['status']);
    assert_same(200, comet_site_cache_test_get('/blog/renamed')['status']);

    $content->softDelete('blog', $entry['id'], $user);
    assert_same(404, comet_site_cache_test_get('/blog/renamed')['status']);
    $content->restore('blog', $entry['id'], $user);
    assert_same(200, comet_site_cache_test_get('/blog/renamed')['status']);
});

test('website configuration saves and resets invalidate every cached response', function (): void {
    $site = comet_site_test_setup();
    foreach ([
        fn() => $site->saveSettings($site->settings()),
        fn() => $site->saveTheme(['base_size' => 20]),
        fn() => $site->saveMenus([]),
        fn() => $site->saveRegion('header', []),
        fn() => $site->resetRegion('header'),
        fn() => $site->saveLayout('blog', 'detail', []),
        fn() => $site->resetLayout('blog', 'detail'),
    ] as $change) {
        comet_site_cache_test_get('/blog/hello');
        assert_same('HIT', comet_site_cache_test_get('/blog/hello')['cache_status']);
        $change();
        assert_same('MISS', comet_site_cache_test_get('/blog/hello')['cache_status']);
    }
});

test('schema visibility and ordering changes invalidate website pages directly', function (): void {
    comet_site_test_setup();
    comet_site_cache_test_get('/blog/hello');
    $types = new ContentTypeRepository();
    $schema = $types->find('blog');
    $schema['visibility'] = 'private';
    $types->save($schema);
    $response = comet_site_cache_test_get('/blog/hello');
    assert_same('MISS', $response['cache_status']);
    assert_same(404, $response['status']);

    comet_site_cache_test_get('/');
    $types->reorder(['about', 'blog']);
    assert_same('MISS', comet_site_cache_test_get('/')['cache_status']);
});

test('media metadata visibility renaming and deletion invalidate website pages directly', function (): void {
    comet_site_test_setup();
    $media = new MediaRepository();
    file_put_contents($media->filePath('image.svg'), '<svg xmlns="http://www.w3.org/2000/svg"/>');
    $type = (new ContentTypeRepository())->find('blog');
    $layout = LayoutSchema::defaultLayout($type, 'detail');
    $layout[0]['children'][0]['children'][0]['children'][] = ['kind' => 'image', 'props' => ['media' => 'image.svg']];
    (new SiteRepository())->saveLayout('blog', 'detail', $layout);
    assert_true(str_contains(comet_site_cache_test_get('/blog/hello')['body'], '/image.svg'));

    $media->updateMeta('image.svg', 'New alt text', '');
    $response = comet_site_cache_test_get('/blog/hello');
    assert_same('MISS', $response['cache_status']);
    assert_true(str_contains($response['body'], 'alt="New alt text"'));

    $media->updateVisibility('image.svg', 'private');
    $response = comet_site_cache_test_get('/blog/hello');
    assert_same('MISS', $response['cache_status']);
    assert_false(str_contains($response['body'], '/image.svg'));

    $media->updateVisibility('image.svg', 'public');
    comet_site_cache_test_get('/blog/hello');
    $media->rename('image.svg', 'renamed.svg');
    assert_same('MISS', comet_site_cache_test_get('/blog/hello')['cache_status']);
    $media->delete('renamed.svg');
    assert_same('MISS', comet_site_cache_test_get('/blog/hello')['cache_status']);
});

test('renaming a workspace invalidates its fallback website title', function (): void {
    $site = comet_site_test_setup();
    $settings = $site->settings();
    $settings['title'] = '';
    $site->saveSettings($settings);
    comet_site_cache_test_get('/');
    (new WorkspaceRepository())->save(['label' => 'Renamed website'], 'default');
    $response = comet_site_cache_test_get('/');
    assert_same('MISS', $response['cache_status']);
    assert_true(str_contains($response['body'], 'Renamed website'));
});
