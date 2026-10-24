<?php

declare(strict_types=1);

use CometCMS\Core\ValidationException;
use CometCMS\Site\LayoutSchema;
use CometCMS\Site\PageRenderer;
use CometCMS\Site\SiteRepository;
use CometCMS\Storage\JsonStore;

/** A section holding one heading with $text. */
function comet_template_test_section(string $text, string $prefix = 't'): array
{
    return comet_component_test_section([['id' => $prefix . 'h', 'kind' => 'heading', 'props' => ['text' => $text]]], $prefix);
}

function comet_template_test(string $id, array $parts = [], array $conditions = []): array
{
    return ['id' => $id, 'name' => ucfirst($id), 'conditions' => $conditions] + $parts + ['header' => 'default', 'content' => 'default', 'footer' => 'default'];
}

test('every page kind starts with the default template around its default content area', function (): void {
    $site = comet_site_test_setup();
    assert_same([SiteRepository::defaultTemplate()], $site->templates('blog', 'detail'));
    assert_same([['id' => 'default', 'name' => 'Default', 'customized' => false]], $site->contentAreas('blog', 'detail'));

    $html = comet_site_test_get('/blog/hello')['body'];
    assert_true(str_contains($html, '<header class="c-region c-header">'));
    assert_true(str_contains($html, '<footer class="c-region c-footer">'));

    // Saving only the default template stores nothing.
    $site->saveTemplates(['blog' => ['detail' => [SiteRepository::defaultTemplate()]]]);
    assert_null((new JsonStore($site->workspace()->path('site') . '/theme'))->read('templates', 'blog', 'detail'));
});

test('layouts and header and footer assignments migrate into content areas and templates unchanged', function (): void {
    $site = comet_site_test_setup();
    $component = $site->createComponent('Promo footer', [comet_template_test_section('Promo footer here', 'f')]);
    $site->saveLayout('blog', 'detail', [comet_template_test_section('Migrated layout')]);
    $site->saveLayout('blog', 'detail', [comet_template_test_section('Migrated layout, second version')]);
    $site->saveTemplates(['blog' => ['detail' => [SiteRepository::defaultTemplate(['header' => 'none', 'footer' => $component['id']])]]]);
    $before = comet_site_test_get('/blog/hello')['body'];
    $history = $site->layoutRevisions('blog', 'detail');

    // Put everything back where workspaces kept it before templates.
    $theme = comet_test_workspace_path() . '/site/theme';
    $revisions = comet_test_workspace_path() . '/revisions/site';
    mkdir($theme . '/layouts/blog', 0775, true);
    rename($theme . '/content/blog/detail/default.json', $theme . '/layouts/blog/detail.json');
    mkdir($revisions . '/layouts/blog', 0775, true);
    rename($revisions . '/content/blog/detail/default', $revisions . '/layouts/blog/detail');
    file_put_contents($theme . '/layouts/blog/card.json', '{"format_version":3,"nodes":[]}');
    (new JsonStore($theme))->write(['frames' => ['blog' => ['detail' => ['header' => 'none', 'footer' => $component['id']]]]], 'frames');
    (new JsonStore($theme))->delete('templates', 'blog', 'detail');

    $migrated = new SiteRepository();
    assert_same($before, comet_site_test_get('/blog/hello')['body'], 'The page renders byte for byte as before.');
    assert_same($history, $migrated->layoutRevisions('blog', 'detail'), 'The history moves along.');
    assert_same('none', $migrated->templates('blog', 'detail')[0]['header']);
    assert_false(is_dir($theme . '/layouts'));
    assert_false(is_dir($revisions . '/layouts'));
    assert_false(is_file($theme . '/frames.json'));
});

test('the first template whose conditions match an entry assembles its page', function (): void {
    $site = comet_site_test_setup();
    $longRead = $site->createContentArea('blog', 'detail', 'Long read');
    assert_same('long-read', $longRead['id']);
    $site->saveLayout('blog', 'detail', [comet_template_test_section('Long read content')], [], 'saved', null, 'long-read');

    $tips = comet_template_test('tips', ['header' => 'none', 'content' => 'long-read'], [['field' => 'category', 'op' => 'equals', 'value' => 'tips']]);
    $news = comet_template_test('news', ['footer' => 'none'], [['field' => 'category', 'op' => 'equals', 'value' => 'news']]);
    $site->saveTemplates(['blog' => ['detail' => [$news, $tips, SiteRepository::defaultTemplate()]]]);

    $html = comet_site_test_get('/blog/hello')['body'];
    assert_true(str_contains($html, 'Long read content'));
    assert_false(str_contains($html, '<header'), 'The tips template has no header.');
    assert_true(str_contains($html, '<footer class="c-region c-footer">'));
    assert_true(str_contains(comet_site_test_get('/blog')['body'], '<header class="c-region c-header">'), 'List pages keep their own templates.');

    // Earlier templates win when several match.
    $everything = comet_template_test('everything', ['footer' => 'none'], [['field' => 'nope', 'op' => 'not_empty', 'value' => null]]);
    assert_throws(ValidationException::class, fn() => $site->saveTemplates(['blog' => ['detail' => [$everything]]]), 'Unknown fields are rejected.');
    $everything['conditions'] = [['field' => 'body', 'op' => 'not_empty', 'value' => null]];
    $site->saveTemplates(['blog' => ['detail' => [$everything, $tips, SiteRepository::defaultTemplate()]]]);
    $html = comet_site_test_get('/blog/hello')['body'];
    assert_false(str_contains($html, 'Long read content'));
    assert_false(str_contains($html, '<footer'));
});

test('templates resolve by entry pick, conditions, order and default', function (): void {
    $default = SiteRepository::defaultTemplate();
    $featured = comet_template_test('featured', [], [['field' => 'featured', 'op' => 'equals', 'value' => true], ['field' => 'tags', 'op' => 'contains', 'value' => 'news']]);
    $templates = [$featured, $default];

    assert_same('conditions', SiteRepository::resolveTemplate($templates, ['featured' => true, 'tags' => ['news', 'tips']])['reason']);
    assert_same('default', SiteRepository::resolveTemplate($templates, ['featured' => true, 'tags' => ['tips']])['reason']);
    assert_same('default', SiteRepository::resolveTemplate($templates, null)['reason']);
    assert_same(['featured', 'picked'], array_values([SiteRepository::resolveTemplate($templates, ['featured' => false], 'featured')['template']['id'], SiteRepository::resolveTemplate($templates, ['featured' => false], 'featured')['reason']]));
    assert_same('default', SiteRepository::resolveTemplate($templates, ['featured' => false], 'missing')['reason'], 'A picked template that is gone is ignored.');
    assert_same('first', SiteRepository::resolveTemplate([comet_template_test('plain'), $default], null)['reason']);

    foreach ([
        [['field' => 'n', 'op' => 'equals', 'value' => '3'], ['n' => 3.0], true],
        [['field' => 'n', 'op' => 'not_equals', 'value' => 3], ['n' => 4], true],
        [['field' => 'text', 'op' => 'contains', 'value' => 'HELLO'], ['text' => '<p>hello world</p>'], true],
        [['field' => 'text', 'op' => 'empty', 'value' => null], ['text' => ''], true],
        [['field' => 'text', 'op' => 'empty', 'value' => null], [], true],
        [['field' => 'flag', 'op' => 'equals', 'value' => false], ['flag' => false], true],
        [['field' => 'flag', 'op' => 'equals', 'value' => 'true'], ['flag' => true], true],
        [['field' => 'text', 'op' => 'not_empty', 'value' => null], ['text' => null], false],
    ] as [$condition, $entry, $expected]) {
        assert_same($expected, SiteRepository::matchesConditions([$condition], $entry), json_encode([$condition, $entry]));
    }
});

test('saving templates rejects unknown parts and conditions that do not fit', function (): void {
    $site = comet_site_test_setup();
    $column = $site->createComponent('Column', [['id' => 'c', 'kind' => 'column', 'children' => []]]);
    $about = $site->createComponent('About header', [comet_component_test_section([LayoutSchema::node('field', ['field' => 'intro'])])], 'about');
    $blog = $site->createComponent('Blog header', [comet_component_test_section([LayoutSchema::node('field', ['field' => 'category'])], 'b')], 'blog');
    $default = SiteRepository::defaultTemplate();

    foreach ([
        'nonsense',
        ['missing' => ['detail' => [$default]]],
        ['blog' => ['card' => [$default]]],
        ['blog' => ['detail' => ['not a list' => $default]]],
        ['blog' => ['detail' => [comet_template_test('a', ['header' => 'missing-component'])]]],
        ['blog' => ['detail' => [comet_template_test('a', ['header' => $column['id']])]]],
        ['blog' => ['detail' => [comet_template_test('a', ['footer' => $about['id']])]]],
        ['blog' => ['detail' => [comet_template_test('a', ['content' => 'missing-area'])]]],
        ['blog' => ['detail' => [comet_template_test('a'), comet_template_test('a')]]],
        ['blog' => ['detail' => [['id' => 'a', 'name' => '  '] + comet_template_test('a')]]],
        ['blog' => ['detail' => [comet_template_test('a', [], [['field' => 'steps', 'op' => 'empty']])]]],
        ['blog' => ['detail' => [comet_template_test('a', [], [['field' => 'author', 'op' => 'equals', 'value' => 'ada']])]]],
        ['blog' => ['detail' => [comet_template_test('a', [], [['field' => 'category', 'op' => 'equals', 'value' => ['tips']]])]]],
        ['blog' => ['detail' => [SiteRepository::defaultTemplate() + ['conditions' => []], comet_template_test('default', [], [['field' => 'body', 'op' => 'empty']])]]],
        ['blog' => ['index' => [comet_template_test('a', [], [['field' => 'body', 'op' => 'empty']])]]],
    ] as $templates) {
        assert_throws(ValidationException::class, fn() => $site->saveTemplates($templates), json_encode($templates));
    }

    // Unknown keys are dropped and the default template moves last.
    $saved = $site->saveTemplates(['blog' => ['index' => [$default, comet_template_test('a', ['header' => $blog['id'], 'sidebar' => 'none'])]]]);
    assert_same([comet_template_test('a', ['header' => $blog['id']]), $default], $saved['blog']['index']);
});

test('content areas are copies to design apart and cannot be deleted while a template shows them', function (): void {
    $site = comet_site_test_setup();
    $site->saveLayout('blog', 'detail', [comet_template_test_section('Original')]);
    $area = $site->createContentArea('blog', 'detail', 'Compact');
    assert_same('Original', $site->layout('blog', 'detail', 'compact')[0]['children'][0]['children'][0]['children'][0]['props']['text']);
    assert_true($site->layout('blog', 'detail', 'compact')[0]['id'] !== $site->layout('blog', 'detail')[0]['id'], 'The copy gets its own node ids.');
    assert_same('compact-2', $site->createContentArea('blog', 'detail', 'Compact')['id']);
    assert_same(['default', 'compact', 'compact-2'], array_column($site->contentAreas('blog', 'detail'), 'id'));
    assert_same('Short', $site->renameContentArea('blog', 'detail', $area['id'], 'Short')['name']);
    assert_same(1, count($site->layoutRevisions('blog', 'detail', 'compact')));

    $site->saveTemplates(['blog' => ['detail' => [comet_template_test('short', ['content' => 'compact']), SiteRepository::defaultTemplate()]]]);
    assert_throws(ValidationException::class, fn() => $site->deleteContentArea('blog', 'detail', 'compact'));
    assert_throws(ValidationException::class, fn() => $site->deleteContentArea('blog', 'detail', 'default'));
    $site->saveTemplates(['blog' => ['detail' => [SiteRepository::defaultTemplate()]]]);
    $site->deleteContentArea('blog', 'detail', 'compact');
    assert_false($site->contentAreaExists('blog', 'detail', 'compact'));
    assert_same([], $site->layoutRevisions('blog', 'detail', 'compact'));
    // An unknown area renders the default one.
    assert_same($site->layout('blog', 'detail'), $site->layout('blog', 'detail', 'compact'));
});

test('deleting a component or content type cleans up the templates that used it', function (): void {
    $site = comet_site_test_setup();
    $component = $site->createComponent('Blog footer', [comet_template_test_section('Blog footer here', 'f')]);
    $site->saveTemplates([
        'blog' => ['detail' => [SiteRepository::defaultTemplate(['footer' => $component['id']])]],
        'about' => ['detail' => [SiteRepository::defaultTemplate(['header' => 'none'])]],
    ]);
    assert_matches('#<footer class="c-region c-footer">.*Blog footer here.*</footer>#s', comet_site_test_get('/blog/hello')['body']);
    assert_same([['target' => 'template', 'type' => 'blog', 'variant' => 'detail', 'id' => 'default', 'name' => 'Default', 'region' => 'footer']], $site->componentUsages($component['id']));

    $site->deleteComponent($component['id']);
    assert_same('default', $site->templates('blog', 'detail')[0]['footer']);
    assert_false(str_contains(comet_site_test_get('/blog/hello')['body'], 'Blog footer here'));

    $site->createContentArea('blog', 'detail', 'Compact');
    $site->forgetType('blog');
    $theme = comet_test_workspace_path() . '/site/theme';
    assert_false(is_dir($theme . '/content/blog'));
    assert_false(is_dir(comet_test_workspace_path() . '/revisions/site/content/blog'));
    assert_same('none', $site->templates('about', 'detail')[0]['header']);
});

test('builder previews show unsaved templates and the content area being edited inside them', function (): void {
    $site = comet_site_test_setup();
    $site->createContentArea('blog', 'detail', 'Compact');
    $context = comet_site_test_context();
    $context->preview = true;
    $context->annotate = true;
    $context->override('templates', $site->validateTemplates(['blog' => ['detail' => [
        comet_template_test('compact', ['header' => 'none', 'content' => 'compact'], [['field' => 'category', 'op' => 'equals', 'value' => 'news']]),
        SiteRepository::defaultTemplate(),
    ]]]));
    $renderer = new PageRenderer($context);

    // The compact area shows inside the template that uses it, even for an entry it would not match.
    $html = $renderer->layoutPreview($site->types()['blog'], 'detail', null, 'compact');
    assert_same(['compact', 'area'], [$renderer->template()['template']['id'], $renderer->template()['reason']]);
    assert_true(str_contains($html, 'class="c-region c-header c-region-none"'));
    assert_true(str_contains($html, 'data-placeholder="headerNone"'));

    $renderer->layoutPreview($site->types()['blog'], 'detail', null);
    assert_same('default', $renderer->template()['reason']);

    $context->override('template:blog:detail', 'compact');
    $renderer->layoutPreview($site->types()['blog'], 'detail', null);
    assert_same('previewed', $renderer->template()['reason']);
    assert_same('default', $renderer->template()['live'], 'The builder still knows which template the page uses live.');
    assert_same([SiteRepository::defaultTemplate()], $site->templates('blog', 'detail'), 'Nothing is saved.');
});

test('an entry can pick its template, and a pick that is gone falls back to the automatic one', function (): void {
    $site = comet_site_test_setup();
    $site->createContentArea('blog', 'detail', 'Landing');
    $site->saveLayout('blog', 'detail', [comet_template_test_section('Landing content')], [], 'saved', null, 'landing');
    $site->saveTemplates(['blog' => ['detail' => [
        comet_template_test('landing', ['header' => 'none', 'content' => 'landing'], [['field' => 'category', 'op' => 'equals', 'value' => 'news']]),
        SiteRepository::defaultTemplate(),
    ]]]);
    $content = \CometCMS\Content\ContentRepository::make();
    $user = comet_site_test_user();
    assert_false(str_contains(comet_site_test_get('/blog/hello')['body'], 'Landing content'));

    $saved = $content->save('blog', ['page_template' => 'landing'], $user, 'hello');
    assert_same('landing', $saved['page_template']);
    assert_true(str_contains(comet_site_test_get('/blog/hello')['body'], 'Landing content'), 'The pick wins over conditions.');
    assert_throws(ValidationException::class, fn() => $content->save('blog', ['page_template' => '../x'], $user, 'hello'));

    $site->saveTemplates(['blog' => ['detail' => [SiteRepository::defaultTemplate()]]]);
    assert_false(str_contains(comet_site_test_get('/blog/hello')['body'], 'Landing content'), 'A deleted template is ignored.');
    assert_false(array_key_exists('page_template', $content->save('blog', ['page_template' => ''], $user, 'hello')), 'Empty goes back to automatic.');
    assert_throws(ValidationException::class, fn() => (new \CometCMS\Content\ContentTypeRepository())->save(['name' => 'odd', 'fields' => ['page_template' => ['type' => 'text']]]));
});

test('the preview admin bar names the template of the page, why it applies and links to its parts', function (): void {
    $site = comet_site_test_setup();
    $footer = $site->createComponent('Promo footer', [comet_template_test_section('Promo', 'f')]);
    $site->createContentArea('blog', 'detail', 'Long read');
    $site->saveTemplates(['blog' => ['detail' => [
        comet_template_test('tips', ['header' => 'none', 'content' => 'long-read', 'footer' => $footer['id']], [['field' => 'category', 'op' => 'equals', 'value' => 'tips']]),
        SiteRepository::defaultTemplate(),
    ]]]);
    $context = comet_site_test_context();
    $context->preview = true;
    $context->browsePreview = true;
    $context->previewBanner = true;
    $context->adminUser = ['username' => 'admin', 'language' => 'en'];
    $context->adminCan = static fn(): bool => true;
    $html = (new \CometCMS\Site\SiteService($context))->respond('/blog/hello', [])['body'];
    $document = new DOMDocument();
    $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $menu = (new DOMXPath($document))->query('//details[contains(@class,"c-admin-templates")]')->item(0);
    assert_true($menu !== null, 'The bar has a template menu.');
    $text = preg_replace('/\s+/', ' ', $menu->textContent);
    foreach (['Template: Tips', 'Its conditions match: category = tips', 'No header', 'Long read', 'Promo footer', 'Edit template'] as $expected) {
        assert_true(str_contains($text, $expected), $expected . ' in ' . $text);
    }
    $links = array_map(static fn(DOMElement $link): string => $link->getAttribute('href'), iterator_to_array((new DOMXPath($document))->query('.//a', $menu)));
    assert_true((bool) array_filter($links, static fn(string $href): bool => str_contains($href, '/page-builder/builder/layout/blog/detail') && str_contains($href, 'area=long-read') && str_contains($href, 'template=tips')));
    assert_true(in_array(true, array_map(static fn(string $href): bool => str_contains($href, '/page-builder/builder/component/' . $footer['id']), $links), true));
    assert_true((bool) array_filter($links, static fn(string $href): bool => str_contains($href, '/page-builder/templates') && str_contains($href, 'template=tips')));

    $context->adminCan = static fn(string $action): bool => $action !== 'site.manage';
    assert_false(str_contains((new \CometCMS\Site\SiteService($context))->respond('/blog/hello', [])['body'], '<details class="c-admin-dropdown c-admin-templates"'), 'Only site designers see it.');
});

test('the preview admin bar keeps its template menu out of the way until a page kind uses templates', function (): void {
    $site = comet_site_test_setup();
    $menu = '<details class="c-admin-dropdown c-admin-templates"';
    // A fresh context per page, as each request has.
    $render = static function (): string {
        $context = comet_site_test_context();
        $context->preview = true;
        $context->browsePreview = true;
        $context->previewBanner = true;
        $context->adminUser = ['username' => 'admin', 'language' => 'en'];
        $context->adminCan = static fn(): bool => true;
        return (new \CometCMS\Site\SiteService($context))->respond('/blog/hello', [])['body'];
    };

    assert_false(str_contains($render(), $menu), 'Only the default template and content area.');
    $site->createContentArea('blog', 'detail', 'Long read');
    assert_true(str_contains($render(), $menu), 'A second content area brings the menu.');
    $site->deleteContentArea('blog', 'detail', 'long-read');
    $site->saveTemplates(['blog' => ['detail' => [comet_template_test('plain'), SiteRepository::defaultTemplate()]]]);
    assert_true(str_contains($render(), $menu), 'So does a second template.');
});
