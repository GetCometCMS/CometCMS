<?php

declare(strict_types=1);

use CometCMS\Core\ValidationException;
use CometCMS\Site\LayoutSchema;
use CometCMS\Site\PageRenderer;

test('layouts show the site header and footer until another one is assigned', function (): void {
    $site = comet_site_test_setup();
    assert_same([], $site->frames());
    assert_same(['header' => 'default', 'footer' => 'default'], $site->frame('blog', 'detail'));

    $html = comet_site_test_get('/blog/hello')['body'];
    assert_true(str_contains($html, '<header class="c-region c-header">'));
    assert_true(str_contains($html, '<footer class="c-region c-footer">'));

    // "default" is never stored.
    assert_same([], $site->saveFrames(['blog' => ['detail' => ['header' => 'default']]]));
});

test('a layout without header shows none, while other layouts keep theirs', function (): void {
    $site = comet_site_test_setup();
    $site->saveFrames(['blog' => ['detail' => ['header' => 'none']]]);

    $entry = comet_site_test_get('/blog/hello')['body'];
    assert_false(str_contains($entry, '<header'), 'The entry page has no header.');
    assert_true(str_contains($entry, '<footer class="c-region c-footer">'));
    assert_true(str_contains(comet_site_test_get('/blog')['body'], '<header class="c-region c-header">'), 'The list page keeps its header.');
    assert_true(str_contains(comet_site_test_get('/about')['body'], '<header class="c-region c-header">'), 'Other types keep their header.');

    // The builder keeps a selectable stand-in.
    $context = comet_site_test_context();
    $context->annotate = true;
    $context->preview = true;
    $preview = (new PageRenderer($context))->layoutPreview($site->types()['blog'], 'detail', null);
    assert_true(str_contains($preview, 'class="c-region c-header c-region-none"'));
    assert_true(str_contains($preview, 'data-placeholder="headerNone"'));
});

test('a section component can stand in for the footer and falls back when deleted', function (): void {
    $site = comet_site_test_setup();
    $component = $site->createComponent('Blog footer', [comet_component_test_section([LayoutSchema::node('heading', ['text' => 'Blog footer here'])])]);
    $site->saveFrames(['blog' => ['detail' => ['footer' => $component['id']]]]);

    $html = comet_site_test_get('/blog/hello')['body'];
    assert_matches('#<footer class="c-region c-footer">.*Blog footer here.*</footer>#s', $html);
    assert_false(str_contains(comet_site_test_get('/about')['body'], 'Blog footer here'));
    assert_same([['target' => 'frame', 'type' => 'blog', 'variant' => 'detail', 'region' => 'footer']], $site->componentUsages($component['id']));

    $site->deleteComponent($component['id']);
    assert_same([], $site->frames());
    assert_false(str_contains(comet_site_test_get('/blog/hello')['body'], 'Blog footer here'));
});

test('only section components that are shared or of the type can be assigned', function (): void {
    $site = comet_site_test_setup();
    $column = $site->createComponent('Column', [['id' => 'c', 'kind' => 'column', 'children' => []]]);
    $about = $site->createComponent('About header', [comet_component_test_section([LayoutSchema::node('field', ['field' => 'intro'])])], 'about');
    $blog = $site->createComponent('Blog header', [comet_component_test_section([LayoutSchema::node('field', ['field' => 'category'])], 'b')], 'blog');

    foreach ([
        ['missing' => ['detail' => ['header' => 'none']]],
        ['blog' => ['card' => ['header' => 'none']]],
        ['blog' => ['detail' => ['header' => 'missing-component']]],
        ['blog' => ['detail' => ['header' => $column['id']]]],
        ['blog' => ['detail' => ['header' => $about['id']]]],
        'nonsense',
    ] as $frames) {
        assert_throws(ValidationException::class, fn() => $site->saveFrames($frames), json_encode($frames));
    }

    assert_same(['blog' => ['index' => ['header' => $blog['id']]]], $site->saveFrames(['blog' => ['index' => ['header' => $blog['id'], 'sidebar' => 'none']]]));
});

test('deleting a content type forgets its header and footer assignments', function (): void {
    $site = comet_site_test_setup();
    $site->saveFrames(['blog' => ['detail' => ['header' => 'none']], 'about' => ['detail' => ['footer' => 'none']]]);
    $site->forgetType('blog');

    assert_same(['about' => ['detail' => ['footer' => 'none']]], $site->frames());
});

test('builder previews show unsaved header and footer assignments', function (): void {
    $site = comet_site_test_setup();
    $context = comet_site_test_context();
    $context->preview = true;
    $context->override('frames', $site->normalizeFrames(['blog' => ['detail' => ['footer' => 'none']]], true));

    $html = (new PageRenderer($context))->layoutPreview($site->types()['blog'], 'detail', null);
    assert_false(str_contains($html, '<footer'));
    assert_same([], $site->frames(), 'Nothing is saved.');
});
