<?php

declare(strict_types=1);

use CometCMS\Site\LayoutSchema;
use CometCMS\Site\NodeRenderer;
use CometCMS\Site\Shapes;

test('section dividers keep known shapes and bounded settings, with heights per device', function (): void {
    $section = LayoutSchema::node('section', [
        'divider_top' => 'wave', 'divider_top_color' => 'custom-brand', 'divider_top_height' => 9999, 'divider_top_flip' => true,
        'divider_bottom' => '<script>', 'divider_bottom_color' => 'red;x', 'divider_bottom_invert' => 'yes',
    ], [], [LayoutSchema::node('row', [], [], [LayoutSchema::node('column')])]);
    $section['responsive'] = ['tablet' => ['props' => ['divider_top_height' => 40]], 'mobile' => ['props' => ['divider_top_height' => -5, 'divider_top' => 'curve']]];
    $props = LayoutSchema::normalize([$section])[0]['props'];
    $normalized = LayoutSchema::normalize([$section])[0];

    assert_same('wave', $props['divider_top']);
    assert_same('custom-brand', $props['divider_top_color']);
    assert_same(500, $props['divider_top_height']);
    assert_true($props['divider_top_flip']);
    assert_same('none', $props['divider_bottom']);
    assert_same('background', $props['divider_bottom_color']);
    assert_false($props['divider_bottom_invert']);
    // Only the height may change per device.
    assert_same(['tablet' => ['props' => ['divider_top_height' => 40]], 'mobile' => ['props' => ['divider_top_height' => 10]]], $normalized['responsive']);
    assert_same(['none', 'wave', 'curve', 'slope', 'triangle', 'double_wave', 'asymmetric_curve', 'mountains', 'steps', 'scallops', 'arrow', 'zigzag'], LayoutSchema::spec()['kinds']['section']['props']['divider_top']['options']);
    assert_same('divider', LayoutSchema::spec()['kinds']['section']['props']['divider_bottom']['picker']);
});

test('sections render dividers behind their content in the theme color and leave sections without any unchanged', function (): void {
    comet_site_test_setup();
    $renderer = new NodeRenderer(comet_site_test_context());
    $content = [LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], [LayoutSchema::node('heading')])])];
    $plain = $renderer->render([LayoutSchema::node('section', [], [], $content)]);
    assert_false(str_contains($plain, 'c-shape'));

    $section = LayoutSchema::node('section', ['divider_top' => 'zigzag', 'divider_top_height' => 120, 'divider_bottom' => 'curve', 'divider_bottom_color' => 'primary', 'divider_bottom_flip' => true, 'divider_bottom_invert' => true], [], $content);
    $section['responsive'] = ['mobile' => ['props' => ['divider_bottom_height' => 30]]];
    $html = $renderer->render(LayoutSchema::normalize([$section]));

    assert_true(str_contains($html, 'c-section c-section-contained c-has-shape'));
    assert_true(str_contains($html, '<div class="c-shape c-shape-top c-fg-background" style="--c-shape-h:120" aria-hidden="true"><svg viewBox="0 0 1200 120" preserveAspectRatio="none"'));
    assert_true(str_contains($html, '<div class="c-shape c-shape-bottom c-fg-primary" style="--c-shape-h:80;--c-m-shape-h:30"'));
    // Inverted: the whole box with the shape cut out; at the bottom that already faces its edge, so it is only flipped sideways.
    assert_true(str_contains($html, 'd="M0,0H1200V120H0ZM0,0Q600,240 1200,0Z" transform="matrix(-1 0 0 1 1200 0)"'));
    assert_true(str_contains(Shapes::dividerSvg('wave', 'bottom', false, false), 'transform="matrix(1 0 0 -1 0 120)"'));
    assert_false(str_contains(Shapes::dividerSvg('wave', 'top', false, false), 'transform'));
    assert_same(24, substr_count(Shapes::dividerPath('zigzag'), 'L') / 2);
    // Dividers come before the content, so they stay behind it.
    assert_true(strpos($html, 'c-shape-bottom') < strpos($html, 'c-container'));
});
