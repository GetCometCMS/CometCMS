<?php

declare(strict_types=1);

use CometCMS\Site\Icons;
use CometCMS\Site\LayoutSchema;
use CometCMS\Site\NodeRenderer;
use CometCMS\Site\SmartValue;

test('gallery elements save ordered media and migrate field galleries without losing identity', function (): void {
    $legacy = LayoutSchema::node('field', ['source' => SmartValue::reference('photos', 'item'), 'display' => 'gallery', 'columns' => 4, 'ratio' => '4:3', 'position' => 'top', 'label' => true, 'link' => true], ['radius' => 'lg']);
    $legacy['name'] = 'Project images';
    $column = LayoutSchema::node('column', [], [], [
        LayoutSchema::node('gallery', ['media' => ['second.svg', null, 'first.svg', 'second.svg'], 'columns' => 99]),
        LayoutSchema::node('field', ['source' => SmartValue::reference('steps'), 'display' => 'items'], [], [$legacy]),
        LayoutSchema::node('field', ['source' => SmartValue::reference('files'), 'display' => 'files']),
    ]);
    $normalized = LayoutSchema::normalizeComponent([$column], 'column');
    $children = $normalized[0]['children'];
    assert_same(['second.svg', 'first.svg'], $children[0]['props']['media']);
    assert_same(6, $children[0]['props']['columns']);
    $gallery = $children[1]['children'][0];
    assert_same('gallery', $gallery['kind']);
    assert_same($legacy['id'], $gallery['id']);
    assert_same($legacy['name'], $gallery['name']);
    assert_same($legacy['style'], $gallery['style']);
    assert_same($legacy['props']['source'], $gallery['props']['media']);
    foreach (['columns', 'ratio', 'position', 'label', 'link'] as $key) assert_same($legacy['props'][$key], $gallery['props'][$key]);
    assert_same('files', $children[2]['props']['display']);
    assert_same($normalized, LayoutSchema::normalizeComponent($normalized, 'column'));
    assert_same('media_list', LayoutSchema::spec()['kinds']['gallery']['props']['media']['binding']);
});

test('galleries render ordered media bindings captions and one-column grids while respecting visibility', function (): void {
    comet_site_test_setup();
    foreach (['first.svg', 'second.svg'] as $file) file_put_contents(comet_test_workspace_path() . '/media/' . $file, '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"></svg>');
    $media = new \CometCMS\Media\MediaRepository();
    $media->updateMeta('second.svg', 'Alt "two"', 'Title <two>');
    $ctx = comet_site_test_context();
    $renderer = new NodeRenderer($ctx);
    $node = LayoutSchema::node('gallery', ['media' => ['second.svg', 'missing.svg', 'first.svg'], 'columns' => 1, 'gap' => 'lg', 'captions' => true, 'ratio' => '4:3', 'fit' => 'contain', 'position' => 'top']);
    $html = $renderer->render([$node]);
    assert_same(2, substr_count($html, '<img'));
    assert_true(strpos($html, 'second.svg') < strpos($html, 'first.svg'));
    assert_true(str_contains($html, 'c-cols-1 c-gap-lg'));
    assert_true(str_contains($html, 'c-ratio-4-3 c-fit-contain c-obj-pos-top'));
    assert_true(str_contains($html, 'alt="Alt &quot;two&quot;"'));
    assert_true(str_contains($html, '<figcaption>Title &lt;two&gt;</figcaption>'));
    $node['props']['captions'] = false;
    assert_false(str_contains($renderer->render([$node]), '<figcaption>'));

    $state = ['type' => ['fields' => ['photos' => ['type' => 'media', 'multiple' => true, 'label' => 'Project photos']]], 'entry' => ['photos' => ['second.svg', 'first.svg']]];
    $node['props']['media'] = SmartValue::reference('photos');
    $node['props']['label'] = true;
    $html = $renderer->render([$node], $state);
    assert_same(2, substr_count($html, '<img'));
    assert_true(str_contains($html, 'Project photos</div>'));
    $state['type']['fields']['photos']['type'] = 'text';
    assert_same('', $renderer->render([$node], $state));
    $state['type']['fields']['photos']['type'] = 'media';
    $media->updateVisibility('second.svg', 'private');
    $html = (new NodeRenderer(comet_site_test_context()))->render([$node], $state);
    assert_same(1, substr_count($html, '<img'));
    assert_false(str_contains($html, 'second.svg'));
    $node['props']['media'] = [];
    assert_same('', $renderer->render([$node]));
    $ctx->annotate = true;
    assert_true(str_contains($renderer->render([$node]), 'Choose gallery images'));
});

test('masonry galleries retain natural heights and restore saved crops when disabled', function (): void {
    comet_site_test_setup();
    file_put_contents(comet_test_workspace_path() . '/media/tall.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="40"></svg>');
    $media = new \CometCMS\Media\MediaRepository();
    $media->updateMeta('tall.svg', 'Tall image', 'Caption');
    $gallery = LayoutSchema::node('gallery', ['media' => ['tall.svg'], 'masonry' => true, 'ratio' => '4:3', 'fit' => 'contain', 'position' => 'top', 'columns' => 4, 'gap' => 'lg', 'captions' => true]);
    $saved = LayoutSchema::normalizeComponent([LayoutSchema::node('column', [], [], [$gallery])], 'column')[0]['children'][0];
    assert_same(true, $saved['props']['masonry']);
    assert_same('4:3', $saved['props']['ratio']);
    assert_same(false, LayoutSchema::node('gallery')['props']['masonry']);
    $renderer = new NodeRenderer(comet_site_test_context());
    $html = $renderer->render([$saved]);
    assert_true(str_contains($html, 'c-gallery-masonry c-gallery c-cols-4 c-gap-lg'));
    assert_true(str_contains($html, '--c-gallery-columns:4;--c-gallery-gap:var(--space-lg)'));
    assert_true(str_contains($html, '<figcaption>Caption</figcaption>'));
    assert_true(str_contains($html, 'alt="Tall image"'));
    assert_false(str_contains($html, 'c-ratio'));
    $saved['props']['gap'] = 'none';
    assert_true(str_contains($renderer->render([$saved]), '--c-gallery-gap:0px'));
    $saved['props']['masonry'] = false;
    $html = $renderer->render([$saved]);
    assert_false(str_contains($html, 'c-gallery-masonry'));
    assert_true(str_contains($html, 'c-grid c-gallery'));
    assert_true(str_contains($html, 'c-ratio-4-3 c-fit-contain c-obj-pos-top'));
});

test('default layouts use galleries for multiple media including repeater items', function (): void {
    $type = ['name' => 'projects', 'fields' => ['photos' => ['type' => 'media', 'multiple' => true], 'steps' => ['type' => 'repeater', 'subfields' => [['key' => 'photos', 'type' => 'media', 'multiple' => true]]]]];
    $layout = LayoutSchema::defaultLayout($type, 'detail');
    $elements = $layout[0]['children'][0]['children'][0]['children'];
    $galleries = array_values(array_filter($elements, static fn(array $node): bool => $node['kind'] === 'gallery'));
    assert_same(1, count($galleries));
    assert_same(SmartValue::reference('photos'), $galleries[0]['props']['media']);
    $repeaters = array_values(array_filter($elements, static fn(array $node): bool => $node['kind'] === 'field'));
    assert_same('gallery', $repeaters[0]['children'][0]['kind']);
    assert_same(SmartValue::reference('photos', 'item'), $repeaters[0]['children'][0]['props']['media']);
    $saved = LayoutSchema::normalize($layout)[0]['children'][0]['children'][0]['children'];
    assert_same($galleries[0], $saved[2]);
    assert_same($repeaters[0]['children'][0], $saved[3]['children'][0]);
});

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

test('section minimum heights survive saving and render viewport presets including existing values', function (): void {
    comet_site_test_setup();
    $renderer = new NodeRenderer(comet_site_test_context());
    foreach (['', 'quarter', 'half', 'three-quarter', 'screen'] as $height) {
        $nodes = LayoutSchema::normalize([LayoutSchema::node('section', ['min_height' => $height, 'vertical_align' => 'center'], [], [
            LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], [LayoutSchema::node('heading', ['text' => 'Section content'])])]),
        ])]);
        assert_same($height, $nodes[0]['props']['min_height']);
        $html = $renderer->render($nodes);
        assert_true(str_contains($html, 'c-v-center'));
        if ($height === '') {
            assert_false(str_contains($html, 'c-min-h-'));
        } else {
            assert_true(str_contains($html, 'c-min-h-' . $height));
        }
    }
    $invalid = LayoutSchema::normalize([LayoutSchema::node('section', ['min_height' => 'invalid'])]);
    assert_same('', $invalid[0]['props']['min_height']);
});
