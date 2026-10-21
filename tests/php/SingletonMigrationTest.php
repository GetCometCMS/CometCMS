<?php

declare(strict_types=1);

use CometCMS\Content\ContentRepository;
use CometCMS\Site\SiteContext;
use CometCMS\Site\SiteRepository;
use CometCMS\Site\SiteService;
use CometCMS\Storage\JsonStore;

/** Workspace files as releases with single page content types wrote them. */
function comet_singleton_test_seed(): void
{
    $root = comet_test_workspace_path();
    (new JsonStore($root))->write(['locales' => ['en', 'de'], 'default_locale' => 'en'], 'languages');
    $types = new JsonStore($root . '/content-types');
    $types->write(['name' => 'start', 'singleton' => true, 'fields' => ['intro' => ['type' => 'textarea']]], 'start');
    $types->write(['name' => 'imprint', 'singleton' => true, 'locales' => ['en', 'de'], 'default_locale' => 'en'], 'imprint');
    $types->write(['name' => 'blog'], 'blog');
    $entry = static fn(string $collection, string $uid, array $titles): array => [
        'id' => $collection, 'uid' => $uid, 'collection' => $collection,
        'values' => [
            'title' => ['type' => 'localized', 'values' => $titles],
            'slug' => ['type' => 'localized', 'values' => array_map(static fn(): string => $collection, $titles)],
        ],
        'publication' => array_map(static fn(): array => ['status' => 'published', 'published_at' => '2026-01-01T00:00:00Z'], $titles),
        'revision' => 'r1', 'created_at' => '2026-01-01T00:00:00Z', 'updated_at' => '2026-01-01T00:00:00Z',
    ];
    $content = new JsonStore($root . '/content');
    $content->write($entry('start', 'StartUid001', ['en' => 'Welcome']), 'start', 'start');
    $content->write($entry('imprint', 'ImprintUid1', ['en' => 'Imprint', 'de' => 'Impressum']), 'imprint', 'imprint');
    (new JsonStore($root . '/site'))->write([
        'enabled' => true,
        'homepage' => ['type' => 'start', 'entry' => ''],
        'routes' => [
            'start' => ['enabled' => true, 'pattern' => '/start', 'index_path' => ''],
            'imprint' => ['enabled' => true, 'pattern' => ['type' => 'localized', 'values' => ['en' => '/imprint', 'de' => '/legal/impressum']], 'index_path' => ''],
            'blog' => ['enabled' => true, 'pattern' => '/blog/{slug}', 'index_path' => '/blog'],
        ],
    ], 'settings');
    $reference = ['type' => 'reference', 'source' => 'single', 'collection' => 'start', 'path' => ['intro'], 'filters' => []];
    $node = ['id' => 'h1', 'kind' => 'heading', 'props' => ['text' => $reference]];
    (new JsonStore($root . '/site/theme/regions'))->write(['nodes' => [$node]], 'footer');
    (new JsonStore($root . '/revisions/site/regions/footer'))->write(['snapshot' => ['nodes' => [$node]]], '20260101000000-old');
}

test('single page content types become ordinary collections', function (): void {
    comet_singleton_test_seed();
    $site = new SiteRepository();
    $root = comet_test_workspace_path();

    assert_false(array_key_exists('singleton', (new JsonStore($root . '/content-types'))->read('start')));
    $settings = $site->settings();
    assert_same('/start/{slug}', $settings['routes']['start']['pattern']);
    assert_same('', $settings['routes']['start']['index_path']);
    assert_same('/imprint/{slug}', $settings['routes']['imprint']['pattern']);
    assert_same(['type' => 'start', 'entry' => 'StartUid001'], $settings['homepage']);

    $content = ContentRepository::make();
    assert_same('start', $content->find('start', 'start')['slug']);

    $migrated = ['type' => 'reference', 'source' => 'entry', 'path' => ['intro'], 'filters' => [], 'collection' => 'start', 'entry' => 'StartUid001'];
    assert_true($migrated == (new JsonStore($root . '/site/theme/regions'))->read('footer')['nodes'][0]['props']['text']);
    assert_true($migrated == (new JsonStore($root . '/revisions/site/regions/footer'))->read('20260101000000-old')['snapshot']['nodes'][0]['props']['text']);

    $service = new SiteService(SiteContext::make($site));
    assert_same(200, $service->respond('/', [])['status']);
    assert_same(200, $service->respond('/imprint/imprint', [])['status']);
    assert_same(404, $service->respond('/imprint', [])['status']);
    $site->saveSettings($site->settings());
    assert_false((new \CometCMS\Site\SingletonMigration($site->workspace()))->needed());
});
