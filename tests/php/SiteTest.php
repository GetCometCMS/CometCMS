<?php

declare(strict_types=1);

use CometCMS\Auth\PermissionService;
use CometCMS\Auth\RoleRepository;
use CometCMS\Cache\ApiCache;
use CometCMS\Content\ContentRepository;
use CometCMS\Content\ContentTypeRepository;
use CometCMS\Core\ValidationException;
use CometCMS\Site\LayoutSchema;
use CometCMS\Site\NodeRenderer;
use CometCMS\Site\PageRenderer;
use CometCMS\Site\SiteContext;
use CometCMS\Site\SiteRepository;
use CometCMS\Site\SiteService;
use CometCMS\Site\Theme;
use CometCMS\Storage\JsonStore;

function comet_site_test_user(): array
{
    return ['id' => 'admin', 'role' => 'admin'];
}

/** A "blog" collection, an "about" single page and a private "notes" type. */
function comet_site_test_setup(array $routes = ['blog' => ['enabled' => true], 'about' => ['enabled' => true]], array $settings = []): SiteRepository
{
    $types = new ContentTypeRepository();
    $types->save([
        'name' => 'authors',
        'fields' => ['bio' => ['type' => 'textarea']],
    ]);
    $types->save([
        'name' => 'blog',
        'label' => 'Blog',
        'fields' => [
            'body' => ['type' => 'markdown', 'label' => 'Body'],
            'category' => ['type' => 'select', 'options' => ['news' => 'News', 'tips' => 'Tips & tricks']],
            'author' => ['type' => 'relation', 'target' => 'authors'],
            'steps' => ['type' => 'repeater', 'subfields' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'Heading'],
                ['key' => 'detail', 'type' => 'textarea', 'label' => 'Detail'],
            ]],
        ],
    ]);
    $types->save(['name' => 'about', 'singleton' => true, 'fields' => ['intro' => ['type' => 'textarea']]]);
    $types->save(['name' => 'notes', 'visibility' => 'private', 'fields' => []]);

    $content = ContentRepository::make();
    $user = comet_site_test_user();
    $content->save('authors', ['title' => 'Ada', 'bio' => 'Writes code.', 'status' => 'published'], $user);
    $content->save('blog', [
        'title' => 'Hello <script>alert(1)</script>',
        'slug' => 'hello',
        'body' => "Some **bold** text.\n\n<script>alert('x')</script>\n\n[bad](javascript:alert(1))",
        'category' => 'tips',
        'author' => 'ada',
        'steps' => [['heading' => 'First step', 'detail' => 'Do it'], ['heading' => 'Second step', 'detail' => 'Again']],
        'status' => 'published',
    ], $user);
    $content->save('blog', ['title' => 'Secret draft', 'slug' => 'secret-draft', 'status' => 'draft'], $user);
    $content->save('about', ['title' => 'About us', 'intro' => 'We build things.', 'status' => 'published'], $user);
    $content->save('notes', ['title' => 'Private note', 'status' => 'published'], $user);

    $site = new SiteRepository();
    $site->saveSettings(array_replace_recursive(['enabled' => true, 'title' => 'Comet Site', 'routes' => $routes], $settings));

    return new SiteRepository();
}

function comet_site_test_context(?SiteRepository $site = null): SiteContext
{
    return SiteContext::make($site ?? new SiteRepository(), '', 'https://example.test');
}

function comet_site_test_get(string $path, array $query = []): array
{
    return (new SiteService(comet_site_test_context()))->respond($path, $query);
}

test('layout schema drops unknown kinds, misplaced nodes and unsafe values', function (): void {
    $nodes = LayoutSchema::normalize([
        ['kind' => 'heading', 'props' => ['text' => 'Not allowed at the root']],
        ['id' => 'dup', 'kind' => 'section', 'props' => ['width' => 'gigantic', 'unknown' => 1], 'style' => ['padding_y' => 'lg', 'background' => 'url(evil)', 'class' => 'ok bad"class <x> also-ok'], 'children' => [
            ['id' => 'dup', 'kind' => 'row', 'children' => [
                ['kind' => 'column', 'props' => ['span' => ['mobile' => 40, 'tablet' => 6]], 'children' => [
                    ['kind' => 'text', 'props' => ['html' => '<p onclick="x()">Hi<script>alert(1)</script></p>']],
                    ['kind' => 'button', 'props' => ['link' => ['type' => 'url', 'url' => 'javascript:alert(1)']]],
                    ['kind' => 'row'],
                    ['kind' => 'marquee'],
                    ['kind' => 'field', 'props' => ['field' => 'steps'], 'children' => [
                        ['kind' => 'field', 'props' => ['field' => 'heading'], 'children' => [['kind' => 'text']]],
                        ['kind' => 'collection'],
                    ]],
                ]],
            ]],
        ]],
    ]);

    assert_same(1, count($nodes));
    $section = $nodes[0];
    assert_same('contained', $section['props']['width']);
    assert_false(array_key_exists('unknown', $section['props']));
    assert_same(['padding_y' => 'lg', 'class' => 'ok also-ok'], $section['style']);

    $row = $section['children'][0];
    assert_true($row['id'] !== 'dup', 'Duplicate ids are replaced.');
    $column = $row['children'][0];
    assert_same(['mobile' => 12, 'tablet' => 6, 'desktop' => 0], $column['props']['span']);
    assert_same(['text', 'button', 'field'], array_column($column['children'], 'kind'));
    assert_same('<p>Hi</p>', $column['children'][0]['props']['html']);
    assert_same('', $column['children'][1]['props']['link']['url']);

    $template = $column['children'][2]['children'];
    assert_same(['field'], array_column($template, 'kind'), 'Collections are not allowed in item templates.');
    assert_same([], $template[0]['children'], 'Item templates cannot nest.');
});

test('default layouts are generated from the content type fields', function (): void {
    comet_site_test_setup();
    $site = new SiteRepository();
    $detail = json_encode($site->layout('blog', 'detail'));

    foreach (['"title"', '"body"', '"category"', '"author"', '"steps"', '"heading"'] as $needle) {
        assert_true(str_contains($detail, $needle), 'Detail layout should bind ' . $needle);
    }

    assert_true(str_contains(json_encode($site->layout('blog', 'index')), '"collection"'));
    assert_false($site->layoutCustomized('blog', 'detail'));
    assert_throws(InvalidArgumentException::class, fn() => $site->layout('about', 'card'));
});

test('website settings reject reserved, malformed and duplicate URLs', function (): void {
    comet_site_test_setup();
    $site = new SiteRepository();

    $cases = [
        ['routes' => ['blog' => ['enabled' => true, 'pattern' => '/admin/{slug}']]],
        ['routes' => ['blog' => ['enabled' => true, 'pattern' => '/blog']]],
        ['routes' => ['about' => ['enabled' => true, 'pattern' => '/about/{slug}']]],
        ['routes' => ['blog' => ['enabled' => true, 'index_path' => '/about'], 'about' => ['enabled' => true, 'pattern' => '/about']]],
        ['routes' => ['blog' => ['enabled' => true, 'pattern' => '/Blog Posts/{slug}']]],
    ];

    foreach ($cases as $index => $settings) {
        assert_throws(ValidationException::class, fn() => $site->saveSettings($settings), 'Case ' . $index . ' should be rejected.');
    }

    $saved = $site->saveSettings(['enabled' => true, 'routes' => ['notes' => ['enabled' => true], 'blog' => ['enabled' => true, 'pattern' => '/posts//{slug}/']]]);
    assert_false($saved['routes']['notes']['enabled'], 'Private types can never be routed.');
    assert_same('/posts/{slug}', $saved['routes']['blog']['pattern']);
});

test('website renders published entries with escaped content and sanitized markdown', function (): void {
    comet_site_test_setup();
    $response = comet_site_test_get('/blog/hello');

    assert_same(200, $response['status']);
    $html = $response['body'];
    assert_true(str_contains($html, 'Hello &lt;script&gt;alert(1)&lt;/script&gt;'), 'Titles are escaped.');
    assert_true(str_contains($html, '<strong>bold</strong>'), 'Markdown is rendered.');
    assert_false(str_contains($html, '<script'), 'No script tags reach the page.');
    assert_false(str_contains($html, 'javascript:'), 'Unsafe links are removed.');
    assert_true(str_contains($html, 'Tips &amp; tricks'), 'Select values show their labels.');
    assert_true(str_contains($html, 'First step') && str_contains($html, 'Second step'), 'Repeater rows render through the item template.');
    assert_true(str_contains($html, 'Ada'), 'Relations render related entries.');
    assert_true(str_contains($html, '<link rel="canonical" href="https://example.test/blog/hello">'));
    assert_true(str_contains($html, '<title>Hello &lt;script&gt;alert(1)&lt;/script&gt; · Comet Site</title>'));
    assert_true(str_contains($html, '/_site/default/style.css?v='));
});

test('website hides drafts, private types and unknown paths', function (): void {
    comet_site_test_setup();

    assert_same(404, comet_site_test_get('/blog/secret-draft')['status']);
    assert_same(404, comet_site_test_get('/notes/private-note')['status']);
    assert_same(404, comet_site_test_get('/nothing/here')['status']);

    $index = comet_site_test_get('/blog');
    assert_same(200, $index['status']);
    assert_true(str_contains($index['body'], '/blog/hello'));
    assert_false(str_contains($index['body'], 'Secret draft'));

    $about = comet_site_test_get('/about');
    assert_same(200, $about['status']);
    assert_true(str_contains($about['body'], 'We build things.'));

    $preview = (new SiteService(comet_site_test_context()))->previewEntry('blog', 'secret-draft');
    assert_true(is_string($preview) && str_contains($preview, 'Secret draft'), 'Drafts can be previewed by editors.');
    assert_true(str_contains((string) $preview, 'noindex'));
    assert_null((new SiteService(comet_site_test_context()))->previewEntry('notes', 'private-note'));
});

test('website homepage falls back to an overview and follows the homepage setting', function (): void {
    $site = comet_site_test_setup();
    $auto = comet_site_test_get('/');
    assert_same(200, $auto['status']);
    assert_true(str_contains($auto['body'], 'Comet Site'));
    assert_true(str_contains($auto['body'], '/blog/hello'));

    $settings = $site->settings();
    $settings['homepage'] = ['type' => 'about', 'entry' => ''];
    $site->saveSettings($settings);

    $home = comet_site_test_get('/');
    assert_true(str_contains($home['body'], 'We build things.'));
    $about = comet_site_test_get('/about');
    assert_true(str_contains($about['body'], '<link rel="canonical" href="https://example.test/">'), 'The homepage entry is canonical at the root.');
});

test('website routes localized entries under a locale prefix', function (): void {
    (new ContentTypeRepository())->save(['name' => 'pages', 'locales' => ['en', 'de'], 'default_locale' => 'en', 'fields' => ['intro' => ['type' => 'textarea']]]);
    $content = ContentRepository::make();
    $content->save('pages', ['title' => 'Welcome', 'slug' => 'welcome', 'intro' => 'English intro', 'status' => 'published', 'locale' => 'en'], comet_site_test_user());
    $content->save('pages', ['title' => 'Willkommen', 'slug' => 'welcome', 'intro' => 'Deutsche Einleitung', 'status' => 'published', 'locale' => 'de'], comet_site_test_user(), 'welcome');

    (new SiteRepository())->saveSettings(['enabled' => true, 'locales' => ['en', 'de'], 'default_locale' => 'en', 'routes' => ['pages' => ['enabled' => true]]]);

    $english = comet_site_test_get('/pages/welcome');
    $german = comet_site_test_get('/de/pages/welcome');

    assert_true(str_contains($english['body'], 'English intro'));
    assert_true(str_contains($german['body'], 'Deutsche Einleitung'));
    assert_true(str_contains($german['body'], '<html lang="de">'));
    assert_true(str_contains($german['body'], 'hreflang="en" href="https://example.test/pages/welcome"'));
    assert_true(str_contains($german['body'], 'hreflang="de" href="https://example.test/de/pages/welcome"'));
});

test('website sitemap and robots list only public pages', function (): void {
    comet_site_test_setup();
    $sitemap = comet_site_test_get('/sitemap.xml');

    assert_same('application/xml; charset=utf-8', $sitemap['content_type']);
    assert_true(str_contains($sitemap['body'], '<loc>https://example.test/blog/hello</loc>'));
    assert_true(str_contains($sitemap['body'], '<loc>https://example.test/about</loc>'));
    assert_false(str_contains($sitemap['body'], 'secret-draft'));
    assert_false(str_contains($sitemap['body'], 'private-note'));
    assert_true(str_contains(comet_site_test_get('/robots.txt')['body'], 'Sitemap: https://example.test/sitemap.xml'));
});

test('website answers for its domains or as the default workspace', function (): void {
    assert_null(SiteService::workspaceForHost('example.test'), 'No website until one is enabled.');

    comet_site_test_setup();
    assert_same('default', SiteService::workspaceForHost('anything.test')?->slug());

    $site = new SiteRepository();
    $settings = $site->settings();
    $settings['domains'] = ['https://www.example.test:443/path', 'bad domain'];
    $saved = $site->saveSettings($settings);

    assert_same(['www.example.test'], $saved['domains']);
    assert_same('default', SiteService::workspaceForHost('WWW.example.test:8080')?->slug());
    assert_null(SiteService::workspaceForHost('other.test'), 'A site limited to its domains ignores other hosts.');
});

test('saving website changes clears cached pages', function (): void {
    comet_site_test_setup();
    $cache = ApiCache::fromConfig();
    $key = $cache->key('site|example.test|/blog/hello', '');
    $cache->put($key, ['status' => 200, 'body' => 'cached', 'content_type' => 'text/html']);
    assert_true($cache->get($key) !== null);

    (new SiteRepository())->saveLayout('blog', 'detail', LayoutSchema::defaultLayout((new ContentTypeRepository())->find('blog'), 'detail'));

    assert_null($cache->get($key));
});

test('website status keeps configured frontend pages separate from the global website switch', function (): void {
    $seed = <<<'PHP'
$users->create('editor', 'secret-password', 'editor');
$types = new \CometCMS\Content\ContentTypeRepository();
$types->save(['name' => 'pages']);
$types->save(['name' => 'data-only']);
$types->save(['name' => 'notes', 'visibility' => 'private']);
(new \CometCMS\Site\SiteRepository())->saveSettings([
    'enabled' => false,
    'routes' => ['pages' => ['enabled' => true], 'notes' => ['enabled' => true]],
]);
PHP;

    [$status, $json] = comet_admin_request('editor', $seed, 'GET', '/admin/api/site/status',
        '(new \CometCMS\Controllers\Admin\SiteController(new \CometCMS\Core\Http()))->status();');

    assert_same(200, $status);
    assert_false($json['data']['enabled']);
    assert_false($json['data']['can_manage'], 'Editors can see the status without website management permission.');
    assert_true($json['data']['types']['pages']['pages_enabled']);
    assert_false($json['data']['types']['pages']['enabled'], 'Configured pages are not live while the website is off.');
    assert_false($json['data']['types']['data-only']['pages_enabled']);
    assert_false($json['data']['types']['notes']['pages_enabled'], 'Private types cannot enable public pages.');
});

test('layout previews annotate nodes and render unsaved changes', function (): void {
    comet_site_test_setup();
    $context = comet_site_test_context();
    $context->preview = true;
    $context->annotate = true;
    $context->override('layout:blog:detail', LayoutSchema::normalize([
        ['id' => 'sec1', 'kind' => 'section', 'children' => [
            ['id' => 'row1', 'kind' => 'row', 'children' => [
                ['id' => 'col1', 'kind' => 'column', 'children' => [
                    ['id' => 'h1', 'kind' => 'heading', 'props' => ['text' => 'Unsaved heading']],
                    ['id' => 'f1', 'kind' => 'field', 'props' => ['field' => 'removed_field']],
                ]],
            ]],
        ]],
    ]));

    $type = (new ContentTypeRepository())->find('blog');
    $html = (new PageRenderer($context))->layoutPreview($type, 'detail', $context->findEntry('blog', 'secret-draft', true));

    assert_true(str_contains($html, 'Unsaved heading'));
    assert_true(str_contains($html, 'data-node="h1"'));
    assert_true(str_contains($html, 'Missing field “removed_field”'));
    assert_true(str_contains($html, '<style>'), 'Previews inline the theme.');
    assert_true(str_contains($html, 'data-node="sec1"'));
});

test('theme normalizes values and keeps custom css inside its style element', function (): void {
    $theme = Theme::normalize([
        'colors' => ['primary' => '#ABC', 'text' => 'red; background:url(x)'],
        'fonts' => ['body' => 'comic-sans'],
        'base_size' => 99,
        'custom_css' => 'body{color:red}</style><script>alert(1)</script>',
    ]);

    assert_same('#aabbcc', $theme['colors']['primary']);
    assert_same(Theme::defaults()['colors']['text'], $theme['colors']['text']);
    assert_same('system', $theme['fonts']['body']);
    assert_same(24, $theme['base_size']);

    $css = Theme::stylesheet($theme);
    assert_true(str_contains($css, '--c-primary:#aabbcc;'));
    assert_true(str_contains($css, '--c-on-primary:'));
    assert_false(stripos($css, '</style') !== false);
    assert_true(str_contains($css, 'body{color:red}'));
});

test('custom theme colors validate tokens and values and generate both palettes', function (): void {
    $theme = Theme::normalize([
        'mode' => 'auto',
        'custom_colors' => [
            'custom-brand' => ['label' => ' Brand ', 'color' => '#ABC', 'dark_color' => '#123456'],
            'custom-fallback' => ['label' => '<b>Fallback</b>', 'color' => '#ff0000', 'dark_color' => 'invalid'],
            'custom-invalid' => ['label' => 'Bad', 'color' => 'red; background:url(x)'],
            'custom-array' => ['color' => ['#ffffff']],
            'custom-</style><script>' => ['color' => '#ffffff'],
            'primary' => ['color' => '#ffffff'],
        ],
    ]);
    assert_same(['custom-brand', 'custom-fallback'], array_keys($theme['custom_colors']));
    assert_same(['label' => 'Brand', 'color' => '#aabbcc', 'dark_color' => '#123456'], $theme['custom_colors']['custom-brand']);
    assert_same('Fallback', $theme['custom_colors']['custom-fallback']['label']);
    assert_same('#ff0000', $theme['custom_colors']['custom-fallback']['dark_color']);
    assert_same([], Theme::normalize([])['custom_colors']);

    $css = Theme::stylesheet($theme);
    assert_true(str_contains($css, '--c-custom-brand:#aabbcc;'));
    assert_true(str_contains($css, '@media (prefers-color-scheme: dark){:root{'));
    assert_true(str_contains($css, '--c-custom-brand:#123456;'));
    assert_true(str_contains($css, '.c-bg-custom-brand{background-color:var(--c-custom-brand);color:var(--c-on-custom-brand);}'));
    assert_true(str_contains($css, '.c-fg-custom-brand{color:var(--c-custom-brand);}'));
    assert_false(str_contains($css, '</style>'));
    assert_false(str_contains($css, 'background:url(x)'));
    assert_true(str_contains(Theme::stylesheet([...$theme, 'mode' => 'dark']), ':root{color-scheme:dark;'));

    $colors = [];
    for ($index = 0; $index < 70; $index++) {
        $colors['custom-color-' . $index] = ['label' => 'Color', 'color' => '#abcdef'];
    }
    assert_same(Theme::MAX_CUSTOM_COLORS, count(Theme::normalize(['custom_colors' => $colors])['custom_colors']));
});

test('saved custom colors keep their layout references when renamed or recolored', function (): void {
    $site = comet_site_test_setup();
    $site->saveTheme(['custom_colors' => ['custom-brand' => ['label' => 'Brand', 'color' => '#abcdef']]]);
    $layout = $site->saveLayout('about', 'detail', [LayoutSchema::node('section', [], ['background' => 'custom-brand', 'text_color' => 'border'])]);
    assert_same('custom-brand', $layout[0]['style']['background']);
    assert_same('border', $layout[0]['style']['text_color']);

    $site->saveTheme(['custom_colors' => ['custom-brand' => ['label' => 'Renamed', 'color' => '#fedcba']]]);
    $reloaded = new SiteRepository();
    assert_same('Renamed', $reloaded->theme()['custom_colors']['custom-brand']['label']);
    assert_same('custom-brand', $reloaded->layout('about', 'detail')[0]['style']['background']);
    assert_true(str_contains(Theme::stylesheet($reloaded->theme()), '--c-custom-brand:#fedcba;'));

    $invalid = LayoutSchema::normalize([LayoutSchema::node('section', [], ['background' => 'custom-evil; color:red', 'text_color' => ['primary']])])[0];
    assert_same([], $invalid['style']);
});

test('dividers use their text color for the line and preserve the default border color', function (): void {
    $site = comet_site_test_setup();
    $renderer = new NodeRenderer(comet_site_test_context($site));
    foreach (['accent', 'custom-brand'] as $color) {
        $node = LayoutSchema::node('divider', [], ['text_color' => $color]);
        $html = $renderer->render([$node]);
        assert_true(str_contains($html, 'c-fg-' . $color));
        assert_true(str_contains($html, '<hr class="c-divider c-divider-colored">'));
    }
    assert_true(str_contains($renderer->render([LayoutSchema::node('divider')]), '<hr class="c-divider">'));
});

test('deleting a content type removes its website layouts and route', function (): void {
    comet_site_test_setup();
    $site = new SiteRepository();
    $site->saveLayout('blog', 'card', []);
    assert_true($site->layoutCustomized('blog', 'card'));

    (new ContentTypeRepository())->delete('blog');

    $store = new JsonStore(comet_test_workspace_path() . '/site');
    assert_null($store->read('layouts', 'blog', 'card'));
    assert_false(isset($store->read('settings')['routes']['blog']));
});

test('the locked admin role always holds new built-in permissions', function (): void {
    $store = new JsonStore(COMET_STORAGE . '/roles');
    $store->write(['id' => 'admin', 'name' => 'admin', 'permissions' => [['effect' => 'allow', 'actions' => ['dashboard.read'], 'resources' => ['*']]]], 'admin');

    $permissions = new PermissionService(new RoleRepository());
    assert_true($permissions->allows(['id' => 'a', 'role' => 'admin'], 'site.manage', ['resource' => 'site:*']));
    assert_false($permissions->allows(['id' => 'e', 'role' => 'editor'], 'site.manage', ['resource' => 'site:*']));
});

test('background images and cropped images follow their size and focal point', function (): void {
    $nodes = LayoutSchema::normalize([
        ['kind' => 'section', 'props' => ['background_image' => 'x.jpg', 'background_size' => 'contain', 'background_position' => 'top-right', 'background_repeat' => true], 'children' => [
            ['kind' => 'row', 'children' => [['kind' => 'column', 'children' => [
                ['kind' => 'image', 'props' => ['media' => 'x.jpg', 'ratio' => '16:9', 'position' => 'bottom-left']],
                ['kind' => 'image', 'props' => ['position' => 'somewhere']],
            ]]]],
        ]],
    ]);

    $section = $nodes[0]['props'];
    assert_same(['contain', 'top-right', true], [$section['background_size'], $section['background_position'], $section['background_repeat']]);
    $images = $nodes[0]['children'][0]['children'][0]['children'];
    assert_same('bottom-left', $images[0]['props']['position']);
    assert_same('center', $images[1]['props']['position'], 'Unknown focal points fall back to the center.');

    $css = Theme::stylesheet([]);
    foreach (['.c-bg-size-contain', '.c-bg-pos-top-right', '.c-bg-repeat', '.c-obj-pos-bottom-left > img'] as $selector) {
        assert_true(str_contains($css, $selector), $selector . ' is styled.');
    }
});

test('theme spacing and radius tokens drive the stylesheet', function (): void {
    $theme = Theme::normalize([
        'space' => ['xs' => 2, 'md' => 24, 'xl' => 999],
        'radii' => ['sm' => 3, 'lg' => 99],
        'radius' => 'lg',
    ]);

    assert_same(['xs' => 2, 'sm' => 8, 'md' => 24, 'lg' => 32, 'xl' => 400, '2xl' => 96], $theme['space']);
    assert_same(['sm' => 3, 'md' => 8, 'lg' => 64], $theme['radii']);

    $css = Theme::stylesheet($theme);
    assert_true(str_contains($css, '--space-md:1.5rem;'), 'Spacing tokens become rem values.');
    assert_true(str_contains($css, '--radius-sm:3px;'));
    assert_true(str_contains($css, '--radius:var(--radius-lg);'), 'The default corner points at a token.');
    assert_true(str_contains($css, '.c-r-md { border-radius: var(--radius-md); }'));
    assert_true(str_contains(Theme::stylesheet(['radius' => 'full']), '--radius-box:var(--radius-lg);'), 'Round corners do not turn images into pills.');

    // Themes saved before tokens existed keep their look.
    $legacy = Theme::normalize(['spacing' => 'relaxed', 'radius' => 'xl']);
    assert_same(40, $legacy['space']['lg'], 'A relaxed density scales the default scale.');
    assert_same('lg', $legacy['radius']);
    assert_false(array_key_exists('spacing', $legacy));
});
