<?php

declare(strict_types=1);

use CometCMS\Site\Icons;
use CometCMS\Site\LayoutSchema;
use CometCMS\Site\NodeRenderer;
use CometCMS\Site\SmartValue;

test('accordion and icon elements survive layout saves and repeater templates', function (): void {
    $elements = [
        LayoutSchema::node('accordion', ['title' => 'Question', 'html' => '<p onclick="evil()">Answer<script>evil()</script></p>', 'open' => true]),
        LayoutSchema::node('icon', ['icon' => 'mdi:heart-outline', 'size' => 72]),
    ];
    $nodes = LayoutSchema::normalizeComponent([LayoutSchema::node('column', [], [], [
        ...$elements,
        LayoutSchema::node('field', ['source' => SmartValue::reference('steps')], [], $elements),
    ])], 'column')[0]['children'];

    assert_same(['accordion', 'icon', 'field'], array_column($nodes, 'kind'));
    assert_same(['accordion', 'icon'], array_column($nodes[2]['children'], 'kind'));
    assert_same('<p>Answer</p>', $nodes[0]['props']['html']);
    assert_same(true, $nodes[0]['props']['open']);
    assert_same('mdi:heart-outline', $nodes[1]['props']['icon']);
    assert_same(72, $nodes[1]['props']['size']);
    $spec = LayoutSchema::spec();
    assert_true(in_array('accordion', $spec['elements'], true));
    assert_true(in_array('icon', $spec['elements'], true));
    assert_same(Icons::names(), $spec['kinds']['icon']['props']['icon']['options']);
});

test('accordion renders safe native disclosure markup and supports content bindings', function (): void {
    $site = comet_site_test_setup();
    $renderer = new NodeRenderer(comet_site_test_context($site));
    $node = LayoutSchema::node('accordion', ['title' => '<script>Question</script>', 'html' => '<p onclick="evil()">Answer</p>']);
    $html = $renderer->render([$node]);
    assert_true(str_contains($html, '<details class="c-accordion">'));
    assert_true(str_contains($html, '&lt;script&gt;Question&lt;/script&gt;'));
    assert_true(str_contains($html, '<div class="c-accordion-content c-prose"><p>Answer</p>'));
    assert_false(str_contains($html, 'onclick'));
    $node['props']['open'] = true;
    assert_true(str_contains($renderer->render([$node]), '<details class="c-accordion" open>'));

    $bound = LayoutSchema::node('accordion', [
        'title' => SmartValue::reference('title', 'site'),
        'html' => ['type' => 'template', 'parts' => ['<p>About ', SmartValue::reference('title', 'site'), '</p>']],
    ]);
    $html = $renderer->render([$bound]);
    assert_true(str_contains($html, '>Comet Site</summary>'));
    assert_true(str_contains($html, '<p>About Comet Site</p>'));
});

test('icons render bundled SVG with theme styling and accessible optional links', function (): void {
    $site = comet_site_test_setup();
    $renderer = new NodeRenderer(comet_site_test_context($site));
    $node = LayoutSchema::node('icon', ['icon' => 'mdi:heart-outline', 'size' => 72], ['text_color' => 'primary', 'align' => 'center']);
    $html = $renderer->render([$node]);
    assert_true(str_contains($html, 'c-fg-primary c-ta-center'));
    assert_true(str_contains($html, 'width="72" height="72"'));
    assert_true(str_contains($html, 'fill="currentColor"'));
    assert_true(str_contains($html, 'aria-hidden="true"'));
    assert_true(str_contains($html, Icons::catalog()['mdi:heart-outline']['body']));
    assert_false(str_contains($html, '<script'));
    assert_false(str_contains($html, 'api.iconify'));

    $node['props']['label'] = 'Our "favorites"';
    $node['props']['link'] = ['type' => 'url', 'url' => 'https://example.test/favorites', 'new_tab' => true];
    $html = $renderer->render([$node]);
    assert_true(str_contains($html, 'href="https://example.test/favorites"'));
    assert_true(str_contains($html, 'aria-label="Our &quot;favorites&quot;"'));
    assert_true(str_contains($html, 'role="img"'));
    assert_true(str_contains($html, 'rel="noopener noreferrer"'));
    $node['props']['label'] = '';
    assert_true(str_contains($renderer->render([$node]), 'aria-label="Heart Outline"'));
});

test('icon validation restricts identifiers to bundled assets and clamps size', function (): void {
    $nodes = LayoutSchema::normalizeComponent([LayoutSchema::node('column', [], [], [
        LayoutSchema::node('icon', ['icon' => '../../evil.svg', 'size' => 9999]),
        LayoutSchema::node('icon', ['icon' => '<svg onload="evil()">', 'size' => -20, 'link' => ['type' => 'url', 'url' => 'javascript:evil()']]),
    ])], 'column')[0]['children'];
    assert_same('mdi:star-outline', $nodes[0]['props']['icon']);
    assert_same(240, $nodes[0]['props']['size']);
    assert_same('mdi:star-outline', $nodes[1]['props']['icon']);
    assert_same(12, $nodes[1]['props']['size']);
    assert_same('', $nodes[1]['props']['link']['url']);
});
