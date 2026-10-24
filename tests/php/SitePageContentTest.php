<?php

declare(strict_types=1);

use CometCMS\Content\ContentRepository;
use CometCMS\Content\ContentTypeRepository;
use CometCMS\Fields\FieldRegistry;
use CometCMS\Site\LayoutSchema;
use CometCMS\Site\PageRenderer;
use CometCMS\Site\SiteRepository;

/** The test site with a page field on the blog. */
function comet_page_test_setup(): SiteRepository
{
    $site = comet_site_test_setup();
    $types = new ContentTypeRepository();
    $blog = $types->find('blog');
    $blog['fields']['design'] = ['type' => 'page', 'label' => 'Design', 'required' => true];
    $types->save($blog);

    return new SiteRepository();
}

/** One section with one column holding the given elements. */
function comet_page_test_section(string $prefix, array $elements): array
{
    return ['id' => $prefix . 's', 'kind' => 'section', 'children' => [
        ['id' => $prefix . 'r', 'kind' => 'row', 'children' => [
            ['id' => $prefix . 'c', 'kind' => 'column', 'children' => $elements],
        ]],
    ]];
}

function comet_page_test_heading(string $id, string $text): array
{
    return ['id' => $id, 'kind' => 'heading', 'props' => ['text' => $text, 'level' => 'h2']];
}

test('page content editor offers titled entries using stable identifiers', function (): void {
    $seed = <<<'PHP'
$types = new \CometCMS\Content\ContentTypeRepository();
$types->save(['name' => 'pages', 'fields' => ['design' => ['type' => 'page']]]);
$content = \CometCMS\Content\ContentRepository::make();
$current = $content->save('pages', ['title' => 'Home', 'slug' => 'home', 'status' => 'published'], ['id' => 'admin']);
$content->save('pages', ['title' => 'About', 'slug' => 'about', 'status' => 'draft'], ['id' => 'admin']);
(new \CometCMS\Site\SiteRepository())->saveSettings(['routes' => ['pages' => ['enabled' => true]]]);
PHP;
    [$status, $body] = comet_admin_request('admin', $seed, 'GET', '/admin/api/site/pages/pages/home',
        '(new \CometCMS\Controllers\Admin\SiteController(new \CometCMS\Core\Http()))->page("pages", $current["uid"]);');

    assert_same(200, $status);
    assert_same('home', $body['data']['entry']['slug']);
    $entries = array_column($body['data']['samples'], null, 'title');
    assert_same('published', $entries['Home']['status']);
    assert_same('draft', $entries['About']['status']);
    assert_same($body['data']['entry']['id'], $entries['Home']['id']);
    assert_true($entries['Home']['id'] !== $entries['About']['id']);
});

test('builder entry labels fall back to available titles after changing the default language', function (): void {
    $seed = <<<'PHP'
(new \CometCMS\Localization\LanguageSettings())->save(['locales' => ['en', 'de'], 'default_locale' => 'en']);
(new \CometCMS\Content\ContentTypeRepository())->save(['name' => 'pages', 'fields' => ['design' => ['type' => 'page']]]);
$content = \CometCMS\Content\ContentRepository::make();
$current = $content->save('pages', ['title' => 'About', 'slug' => 'about', 'status' => 'published'], ['id' => 'admin']);
$home = $content->save('pages', ['title' => 'Home', 'slug' => 'home', 'status' => 'published'], ['id' => 'admin']);
$content->save('pages', ['title' => 'Startseite', 'slug' => 'startseite', 'locale' => 'de', 'status' => 'published'], ['id' => 'admin'], $home['id']);
(new \CometCMS\Localization\LanguageSettings())->save(['locales' => ['en', 'de'], 'default_locale' => 'de']);
(new \CometCMS\Site\SiteRepository())->saveSettings(['routes' => ['pages' => ['enabled' => true]]]);
PHP;
    [$status, $body] = comet_admin_request('admin', $seed, 'GET', '/admin/api/site/pages/pages/about',
        '(new \CometCMS\Controllers\Admin\SiteController(new \CometCMS\Core\Http()))->page("pages", $current["uid"]);');

    assert_same(200, $status);
    assert_same('About', $body['data']['entry']['title']);
    $entries = array_column($body['data']['samples'], null, 'title');
    assert_true(isset($entries['About'], $entries['Startseite']));
    assert_same($body['data']['entry']['id'], $entries['About']['id']);
    assert_same('draft', $entries['About']['status']);
    assert_same('published', $entries['Startseite']['status']);
});

/** A detail layout: a frame heading, then the page content place with a default section. */
function comet_page_test_layout(): array
{
    return [
        comet_page_test_section('frame', [comet_page_test_heading('frameh', 'Shared frame')]),
        ['id' => 'place', 'kind' => 'page_content', 'props' => ['field' => 'design'], 'children' => [
            comet_page_test_section('def', [comet_page_test_heading('defh', 'Default content')]),
        ]],
    ];
}

test('page fields are shared by all languages and store sections with their format', function (): void {
    comet_page_test_setup();
    $config = (new ContentTypeRepository())->find('blog')['fields']['design'];
    assert_false($config['localized']);
    assert_false(array_key_exists('required', $config));

    $type = FieldRegistry::builtins()->get('page');
    assert_null($type->normalize([], []));
    assert_null($type->normalize(['format_version' => LayoutSchema::FORMAT_VERSION, 'nodes' => []], []));
    $value = $type->normalize([comet_page_test_section('a', [comet_page_test_heading('h', 'Hi')]), ['kind' => 'heading']], []);
    assert_same(LayoutSchema::FORMAT_VERSION, $value['format_version']);
    assert_same(['as'], array_column($value['nodes'], 'id'));
    // Content saved in another format is kept until it is saved again.
    $old = ['format_version' => 1, 'nodes' => [['id' => 'x', 'kind' => 'section']]];
    assert_same($old, $type->normalize($old, []));
    assert_true(LayoutSchema::pageNeedsUpgrade($old));
});

test('page content goes between sections or into columns of detail layouts, once per field', function (): void {
    $place = static fn(string $id, string $field = 'design', array $children = []): array => ['id' => $id, 'kind' => 'page_content', 'props' => ['field' => $field], 'children' => $children];
    $layout = [
        $place('p1', 'design', [comet_page_test_section('d', [$place('nested')])]),
        $place('p2'),
        comet_page_test_section('s', [
            $place('p3', 'other'),
            ['id' => 'list', 'kind' => 'collection', 'props' => ['type' => 'blog'], 'children' => [
                ['id' => 'card', 'kind' => 'column', 'children' => [$place('incard', 'third')]],
            ]],
        ]),
    ];

    $nodes = LayoutSchema::normalize($layout, true);
    assert_same(['p1', 'ss'], array_column($nodes, 'id'));
    assert_same(['ds'], array_column($nodes[0]['children'], 'id'));
    assert_same([], $nodes[0]['children'][0]['children'][0]['children'][0]['children'], 'Page content never nests.');
    $column = $nodes[1]['children'][0]['children'][0]['children'];
    assert_same(['p3', 'list'], array_column($column, 'id'));
    assert_same([], $column[1]['children'][0]['children'], 'Cards hold no page content.');
    assert_same(['design', 'other'], array_keys(LayoutSchema::pagePlaces($nodes)));

    // Regions, components and other layouts never hold it.
    assert_same(['ss'], array_column(LayoutSchema::normalize($layout), 'id'));
    assert_false(in_array('page_content', LayoutSchema::spec()['elements'], true));
    assert_true(in_array('page_content', LayoutSchema::spec(true)['root'], true));
});

test('two entries of one collection render their own page content and others keep the default', function (): void {
    $site = comet_page_test_setup();
    $site->saveLayout('blog', 'detail', comet_page_test_layout());
    $content = ContentRepository::make();
    $user = comet_site_test_user();
    $content->save('blog', ['title' => 'Second', 'slug' => 'second', 'status' => 'published'], $user);
    $content->save('blog', ['title' => 'Third', 'slug' => 'third', 'status' => 'published'], $user);
    $hello = $content->findByIdentifier('blog', 'hello');
    $second = $content->findByIdentifier('blog', 'second');

    $content->savePage('blog', $hello['id'], 'design', [comet_page_test_section('h', [
        ['id' => 'hh', 'kind' => 'heading', 'props' => ['text' => 'Hello design'], 'style' => ['background' => 'primary', 'font_size' => '4xl']],
    ])], $user, $hello['revision']);
    $content->savePage('blog', $second['id'], 'design', [comet_page_test_section('x', [
        ['id' => 'xh', 'kind' => 'heading', 'props' => ['text' => 'Second design'], 'style' => ['text_color' => 'muted']],
    ])], $user);

    $first = comet_site_test_get('/blog/hello')['body'];
    assert_true(str_contains($first, 'Shared frame'));
    assert_true(str_contains($first, 'Hello design'));
    assert_true(str_contains($first, 'c-bg-primary') || str_contains($first, 'c-fs-4xl'), 'The entry keeps its own styling.');
    assert_false(str_contains($first, 'Default content'));
    $other = comet_site_test_get('/blog/second')['body'];
    assert_true(str_contains($other, 'Second design'));
    assert_false(str_contains($other, 'Hello design'));
    $plain = comet_site_test_get('/blog/third')['body'];
    assert_true(str_contains($plain, 'Shared frame'));
    assert_true(str_contains($plain, 'Default content'));
});

test('resetting page content returns the entry to the layout default and keeps a revision', function (): void {
    $site = comet_page_test_setup();
    $site->saveLayout('blog', 'detail', comet_page_test_layout());
    $content = ContentRepository::make();
    $user = comet_site_test_user();
    $hello = $content->findByIdentifier('blog', 'hello');
    $saved = $content->savePage('blog', $hello['id'], 'design', [comet_page_test_section('h', [comet_page_test_heading('hh', 'Own page')])], $user);
    assert_true(str_contains(comet_site_test_get('/blog/hello')['body'], 'Own page'));

    $reset = $content->savePage('blog', $hello['id'], 'design', null, $user, $saved['revision']);
    assert_null($reset['design']);
    $html = comet_site_test_get('/blog/hello')['body'];
    assert_true(str_contains($html, 'Default content'));
    assert_false(str_contains($html, 'Own page'));
    $kept = array_filter($content->revisions('blog', $hello['id']), static fn(array $revision): bool => LayoutSchema::hasPageContent($revision['entry']['design'] ?? null));
    assert_same(1, count($kept), 'The reset keeps the design in the history.');

    try {
        $content->savePage('blog', $hello['id'], 'design', [], $user, $saved['revision']);
        throw new RuntimeException('A stale revision must conflict.');
    } catch (\CometCMS\Core\ConflictException) {
    }
});

test('page content in a column fills the column and the builder previews only the draft', function (): void {
    $site = comet_page_test_setup();
    $site->saveLayout('blog', 'detail', [comet_page_test_section('frame', [
        ['id' => 'place', 'kind' => 'page_content', 'props' => ['field' => 'design']],
    ])]);
    $html = comet_site_test_get('/blog/hello')['body'];
    assert_false(str_contains($html, 'c-page-content'), 'An empty place without default content renders nothing.');

    $context = comet_site_test_context($site);
    $context->preview = true;
    $context->annotate = true;
    $context->pageDraft = ['field' => 'design', 'nodes' => LayoutSchema::normalize([comet_page_test_section('d', [comet_page_test_heading('dh', 'Draft text')])])];
    $schema = $site->types()['blog'];
    $entry = $context->findEntry('blog', 'hello', true);
    $preview = (new PageRenderer($context))->pagePreview($schema, $entry, 'design');
    assert_true(str_contains($preview, 'c-page-content c-page-content-embedded" data-drop-root'));
    assert_true(str_contains($preview, 'data-node="dh"'));
    assert_false(str_contains($preview, 'data-node="frames"'), 'The frame is not edited here.');
    assert_matches('/<main id="main" class="c-main">/', $preview);
});

test('empty page content in nested rows keeps its editing area and styled parents', function (): void {
    $site = comet_page_test_setup();
    $layout = [comet_page_test_section('frame', [
        ['id' => 'nested-row', 'kind' => 'row', 'children' => [
            ['id' => 'nested-column', 'kind' => 'column', 'children' => [
                ['id' => 'place', 'kind' => 'page_content', 'props' => ['field' => 'design']],
            ]],
        ]],
    ])];
    $layout[0]['style'] = ['background' => 'primary'];
    $site->saveLayout('blog', 'detail', $layout);

    $context = comet_site_test_context($site);
    $context->preview = true;
    $context->annotate = true;
    $schema = $site->types()['blog'];
    $entry = $context->findEntry('blog', 'hello', true);
    foreach ([[], [comet_page_test_section('empty', [])]] as $draft) {
        $context->pageDraft = ['field' => 'design', 'nodes' => LayoutSchema::normalize($draft)];
        $preview = (new PageRenderer($context))->pagePreview($schema, $entry, 'design');
        assert_true(str_contains($preview, 'c-page-content c-page-content-embedded" data-drop-root'), 'An empty nested area must remain editable.');
        assert_matches('/<section[^>]*class="[^"]*c-bg-primary/', $preview, 'The styled parent section must remain visible.');
        assert_true(substr_count($preview, 'class="c-row ') >= 2, 'Both parent rows must remain visible.');
        assert_false(str_contains($preview, 'data-node="frames"'), 'Shared parents remain outside the editable content.');
    }

    assert_false(str_contains(comet_site_test_get('/blog/hello')['body'], 'c-page-content'), 'The live page still omits the empty area.');
});

test('spacer-only rows survive in the entry builder and published page', function (): void {
    $site = comet_page_test_setup();
    $layout = [
        comet_page_test_section('spacer', [
            ['id' => 'nested-row', 'kind' => 'row', 'children' => [
                ['id' => 'nested-column', 'kind' => 'column', 'children' => [
                    ['id' => 'space', 'kind' => 'spacer', 'props' => ['size' => 'xl']],
                ]],
            ]],
        ]),
        comet_page_test_section('empty', []),
        ['id' => 'place', 'kind' => 'page_content', 'props' => ['field' => 'design']],
    ];
    $layout[0]['style'] = ['background' => 'primary'];
    $site->saveLayout('blog', 'detail', $layout);

    $context = comet_site_test_context($site);
    $context->preview = true;
    $context->annotate = true;
    $context->pageDraft = ['field' => 'design', 'nodes' => []];
    $preview = (new PageRenderer($context))->pagePreview($site->types()['blog'], $context->findEntry('blog', 'hello', true), 'design');
    $published = comet_site_test_get('/blog/hello')['body'];

    foreach ([$preview, $published] as $html) {
        preg_match('/<main\b[^>]*>(.*?)<\/main>/s', $html, $main);
        $html = $main[1] ?? '';
        assert_true(str_contains($html, 'c-spacer c-spacer-xl'), 'The spacer must retain its configured height.');
        assert_same(2, substr_count($html, 'class="c-row '), 'Both spacer ancestors survive; the truly empty row stays hidden.');
        assert_matches('/<section[^>]*class="[^"]*c-bg-primary/', $html, 'The spacer keeps its styled section.');
    }
    assert_false(str_contains($preview, 'data-node="space"'), 'The shared spacer stays outside the editable draft.');
});

test('the page builder marks the entry\'s other page content areas', function (): void {
    $site = comet_page_test_setup();
    $types = new \CometCMS\Content\ContentTypeRepository();
    $blog = $types->find('blog');
    $blog['fields']['aside'] = ['type' => 'page', 'label' => 'Aside'];
    $types->save($blog);
    $site->saveLayout('blog', 'detail', [
        ['id' => 'main', 'kind' => 'page_content', 'props' => ['field' => 'design']],
        comet_page_test_section('side-frame', [
            ['id' => 'side', 'kind' => 'page_content', 'props' => ['field' => 'aside']],
        ]),
    ]);

    $context = comet_site_test_context($site);
    $context->preview = true;
    $context->annotate = true;
    $context->pageDraft = ['field' => 'design', 'nodes' => []];
    $preview = (new PageRenderer($context))->pagePreview($site->types()['blog'], $context->findEntry('blog', 'hello', true), 'design');
    assert_true(str_contains($preview, '<div class="c-page-content" data-drop-root>'));
    assert_true(str_contains($preview, 'data-page-area="aside"'));
    assert_false(str_contains($preview, 'data-page-area="design"'));
    assert_false(str_contains(comet_site_test_get('/blog/hello')['body'], 'data-page-area'), 'The live page has no builder markup.');
});

test('a detail layout generated for a type with a page field starts in its page content', function (): void {
    $site = comet_page_test_setup();
    $layout = $site->layout('blog', 'detail');
    assert_same(['page_content'], array_column($layout, 'kind'));
    assert_same('design', $layout[0]['props']['field']);
    assert_same('section', $layout[0]['children'][0]['kind']);
    // The generated layout keeps its ids, and saving it keeps the place.
    assert_same($layout, $site->layout('blog', 'detail'));
    assert_same(['page_content'], array_column($site->saveLayout('blog', 'detail', $layout), 'kind'));
    assert_same(['section'], array_column($site->layout('blog', 'index'), 'kind'));
});

test('components and forms used in page content are found, and deleting a component keeps a copy', function (): void {
    $site = comet_page_test_setup();
    $component = $site->createComponent('Promo', [comet_page_test_section('p', [comet_page_test_heading('ph', 'Promo text')])]);
    $content = ContentRepository::make();
    $user = comet_site_test_user();
    $hello = $content->findByIdentifier('blog', 'hello');
    $content->savePage('blog', $hello['id'], 'design', [['id' => 'inst', 'kind' => 'component', 'props' => ['component' => $component['id'], 'root' => 'section']]], $user);

    $usages = $site->componentUsages($component['id']);
    assert_same([['target' => 'entry', 'type' => 'blog', 'id' => $hello['id'], 'name' => $hello['title']]], $usages);

    $site->deleteComponent($component['id']);
    $value = $content->find('blog', $hello['id'])['design'];
    assert_same('section', $value['nodes'][0]['kind']);
    assert_same('inst', $value['nodes'][0]['id']);
    assert_true(str_contains(comet_site_test_get('/blog/hello')['body'], 'Promo text'));
});

test('page content texts are searched and describe entries without a text field', function (): void {
    $site = comet_page_test_setup();
    $content = ContentRepository::make();
    $user = comet_site_test_user();
    $hello = $content->findByIdentifier('blog', 'hello');
    $content->savePage('blog', $hello['id'], 'design', [comet_page_test_section('h', [
        comet_page_test_heading('hh', 'Unmistakable zebra'),
        ['id' => 'ht', 'kind' => 'text', 'props' => ['html' => '<p>Stripes &amp; more</p>']],
    ])], $user);

    $context = comet_site_test_context($site);
    $hits = (new \CometCMS\Site\SiteSearch($context))->search('zebra', ['blog']);
    assert_same(['Hello <script>alert(1)</script>'], array_column($hits, 'title'));
    assert_same('Unmistakable zebra Stripes & more', LayoutSchema::text($content->find('blog', $hello['id'])['design']['nodes'], 'en', 'en'));
});

test('page fields saved with the untranslated builder key get a readable label', function (): void {
    comet_page_test_setup();
    $types = new ContentTypeRepository();
    $blog = $types->find('blog');
    $blog['fields']['design']['label'] = 'site.page.fieldLabel';
    $types->save($blog);

    assert_same('Page content', (new ContentTypeRepository())->find('blog')['fields']['design']['label']);
});
