<?php

declare(strict_types=1);

use CometCMS\Site\GalleryLightbox;
use CometCMS\Site\LayoutSchema;
use CometCMS\Site\NodeRenderer;
use CometCMS\Site\PageRenderer;
use CometCMS\Site\SmartValue;
use CometCMS\Site\Tabs;

test('tabs preserve ordinary elements and nested layouts while rejecting misplaced panels', function (): void {
    $tabs = LayoutSchema::node('tabs', ['initial_tab' => -5], [], [
        LayoutSchema::node('tab', ['title' => 'Overview'], [], [
            LayoutSchema::node('heading', ['text' => 'Welcome']),
            LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], [LayoutSchema::node('image')])]),
        ]),
        LayoutSchema::node('text'),
        LayoutSchema::node('tab', ['title' => 'Details'], [], [LayoutSchema::node('form')]),
    ]);
    $saved = LayoutSchema::normalizeComponent([LayoutSchema::node('column', [], [], [$tabs, LayoutSchema::node('tab')])], 'column');
    assert_same(1, count($saved[0]['children']));
    $normalized = $saved[0]['children'][0];
    assert_same(['tab', 'tab'], array_column($normalized['children'], 'kind'));
    assert_same(['heading', 'row'], array_column($normalized['children'][0]['children'], 'kind'));
    assert_same('image', $normalized['children'][0]['children'][1]['children'][0]['children'][0]['kind']);
    assert_same('form', $normalized['children'][1]['children'][0]['kind']);
    assert_same(1, $normalized['props']['initial_tab']);
    assert_same($saved, LayoutSchema::normalizeComponent($saved, 'column'));
    assert_true(in_array('tabs', LayoutSchema::spec()['elements'], true));
    assert_false(in_array('tab', LayoutSchema::spec()['elements'], true));
});

test('tab markup escapes labels and gives repeated instances distinct accessible relationships', function (): void {
    $site = comet_site_test_setup();
    $ctx = comet_site_test_context($site);
    $tabs = LayoutSchema::node('tabs', ['title' => 'Product <details>', 'initial_tab' => 99], [], [
        LayoutSchema::node('tab', ['title' => 'First <script>'], [], [LayoutSchema::node('text', ['html' => SmartValue::reference('title')])]),
        LayoutSchema::node('tab', ['title' => 'Second'], [], [LayoutSchema::node('button', ['label' => 'Continue'])]),
    ]);
    $renderer = new NodeRenderer($ctx);
    $html = $renderer->render([$tabs, $tabs], ['entry' => $ctx->findEntry('blog', 'hello'), 'type' => $ctx->type('blog')]);
    assert_true(str_contains($html, 'aria-label="Product &lt;details&gt;"'));
    assert_true(str_contains($html, 'First &lt;script&gt;'));
    assert_false(str_contains($html, '<script>'));
    assert_true(str_contains($html, 'Hello &lt;script&gt;alert(1)&lt;/script&gt;'));
    assert_same(2, substr_count($html, 'aria-selected="true"'));
    assert_same(4, substr_count($html, 'role="tabpanel"'));
    assert_false(str_contains($html, ' hidden'), 'All panels are readable without scripts.');
    preg_match_all('/\bid="([^"]+)"/', $html, $ids);
    assert_same(count($ids[1]), count(array_unique($ids[1])));
    preg_match_all('/aria-controls="([^"]+)"/', $html, $controls);
    foreach ($controls[1] as $id) assert_true(in_array($id, $ids[1], true));
});

test('tabs load a hashed module alongside galleries and keep previews script free', function (): void {
    $site = comet_site_test_setup();
    file_put_contents(comet_test_workspace_path() . '/media/photo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="40"></svg>');
    $tabs = LayoutSchema::node('tabs', [], [], [LayoutSchema::node('tab', ['title' => 'Photos'], [], [
        LayoutSchema::node('gallery', ['media' => ['photo.svg'], 'lightbox' => true]),
    ])]);
    $site->saveLayout('blog', 'detail', [LayoutSchema::node('section', [], [], [LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], [$tabs, $tabs])])])]);
    $ctx = comet_site_test_context($site);
    $renderer = new PageRenderer($ctx);
    $html = $renderer->entryPage($ctx->type('blog'), $ctx->findEntry('blog', 'hello'));
    assert_same(1, substr_count($html, '<script type="module">' . Tabs::script()));
    assert_same(1, substr_count($html, '<script type="module">' . GalleryLightbox::script()));
    assert_same("'sha256-" . base64_encode(hash('sha256', Tabs::script(), true)) . "'", Tabs::cspSource());
    $ctx->preview = true;
    $preview = $renderer->layoutPreview($ctx->type('blog'), 'detail', $ctx->findEntry('blog', 'hello'));
    assert_false(str_contains($preview, '<script'));
    assert_true(str_contains($preview, 'data-tabs='));
    assert_true(str_contains($preview, "script-src 'none'"));
});

test('public tab pages allow both bundled module hashes in root and subdirectory installs', function (): void {
    $root = comet_site_preview_test_install();
    comet_test_run_php(['-r', 'require ' . var_export($root . '/app/bootstrap.php', true) . ';' . <<<'PHP'
(new \CometCMS\Workspaces\WorkspaceRepository())->all();
(new \CometCMS\Content\ContentTypeRepository())->save(['name' => 'about']);
\CometCMS\Content\ContentRepository::make()->save('about', ['title' => 'About', 'status' => 'published'], ['id' => 'admin']);
$site = new \CometCMS\Site\SiteRepository();
$site->saveSettings(['routes' => ['about' => ['enabled' => true, 'pattern' => '/{slug}', 'index_path' => '']]]);
file_put_contents($site->workspace()->path('media') . '/photo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="40"></svg>');
$gallery = \CometCMS\Site\LayoutSchema::node('gallery', ['media' => ['photo.svg'], 'lightbox' => true]);
$tabs = \CometCMS\Site\LayoutSchema::node('tabs', [], [], [\CometCMS\Site\LayoutSchema::node('tab', ['title' => 'Photos'], [], [$gallery])]);
$site->saveLayout('about', 'detail', [\CometCMS\Site\LayoutSchema::node('section', [], [], [\CometCMS\Site\LayoutSchema::node('row', [], [], [\CometCMS\Site\LayoutSchema::node('column', [], [], [$tabs])])])]);
PHP]);
    $port = 26080 + random_int(0, 2000);
    $server = comet_test_start_php_server('127.0.0.1', $port, $root . '/router.php');
    try {
        usleep(300000);
        foreach (['', '/cms'] as $base) {
            [$status, $html, $headers] = comet_site_preview_test_request($port, $base . '/about');
            assert_same(200, $status);
            $policy = $headers['content-security-policy'];
            assert_true(str_contains($policy, Tabs::cspSource()));
            assert_true(str_contains($policy, GalleryLightbox::cspSource()));
            assert_false(str_contains($policy, "script-src 'none'"));
            assert_false(str_contains($policy, "script-src 'self'"));
            assert_false(str_contains($policy, "'unsafe-eval'"));
            assert_true(str_contains($html, '<script type="module">' . Tabs::script()));
        }
    } finally {
        comet_test_stop_process($server);
    }
});
