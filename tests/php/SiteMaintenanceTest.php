<?php

declare(strict_types=1);

use CometCMS\Site\LayoutSchema;
use CometCMS\Site\PageRenderer;
use CometCMS\Site\SiteService;

test('maintenance mode answers every public URL with the maintenance page', function (): void {
    $site = comet_site_test_setup();
    assert_same(200, comet_site_test_get('/blog/hello')['status']);
    assert_false($site->settings()['maintenance']);

    $site->saveSettings(['maintenance' => true] + $site->settings());
    foreach (['/', '/blog/hello', '/about', '/nothing/here', '/robots.txt', '/sitemap.xml'] as $path) {
        $response = comet_site_test_get($path);
        assert_same(503, $response['status'], $path . ' is closed during maintenance.');
        assert_true(str_contains($response['body'], 'We&#039;ll be back soon') || str_contains($response['body'], "We'll be back soon"), $path . ' shows the default maintenance page.');
        assert_false(str_contains($response['body'], 'c-header'), 'The maintenance page has no header.');
        assert_true(str_contains($response['body'], 'noindex'));
    }

    $preview = (new SiteService(comet_site_test_context()))->previewEntry('blog', 'hello');
    assert_true(is_string($preview) && str_contains($preview, 'Some <strong>bold</strong> text.'), 'Editors keep previewing the real site.');

    $site->saveSettings(['maintenance' => false] + $site->settings());
    assert_same(200, comet_site_test_get('/blog/hello')['status']);
});

test('a maintenance page built in the Page Builder replaces the default', function (): void {
    $site = comet_site_test_setup();
    assert_true(in_array(LayoutSchema::MAINTENANCE, LayoutSchema::spec()['special_pages'], true));
    $defaults = LayoutSchema::normalizeRegion(LayoutSchema::MAINTENANCE, $site->region(LayoutSchema::MAINTENANCE));
    assert_same(['site_title', 'icon', 'heading', 'text'], array_column($defaults[0]['children'][0]['children'][0]['children'], 'kind'), 'The default template survives normalizing.');

    $site->saveSettings(['maintenance' => true] + $site->settings());
    $site->saveRegion(LayoutSchema::MAINTENANCE, [LayoutSchema::node('section', [], [], [LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], [LayoutSchema::node('heading', ['text' => 'Upgrading the engines', 'level' => 'h1'])])])])]);
    $response = comet_site_test_get('/about');
    assert_same(503, $response['status']);
    assert_true(str_contains($response['body'], 'Upgrading the engines'));

    $site->resetRegion(LayoutSchema::MAINTENANCE);
    assert_false(str_contains(comet_site_test_get('/about')['body'], 'Upgrading the engines'));
    assert_true(str_contains((new PageRenderer(comet_site_test_context()))->maintenance(), 'c-view-maintenance'));
});
