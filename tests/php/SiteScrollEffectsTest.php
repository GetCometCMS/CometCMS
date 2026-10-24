<?php

declare(strict_types=1);

use CometCMS\Site\LayoutSchema;
use CometCMS\Site\NodeRenderer;
use CometCMS\Site\StyleVariants;
use CometCMS\Site\Theme;

test('scroll effects are bounded, opt-in and restricted to sections and standalone images', function (): void {
    foreach (['section', 'image', 'gallery', 'row', 'heading'] as $kind) {
        $style = ['scroll_background' => 'parallax', 'scroll_element' => 'parallax', 'scroll_strength' => 'strong', 'scroll_direction' => 'faster'];
        $node = LayoutSchema::node($kind, [], $style);
        $node['responsive'] = ['mobile' => ['style' => $style]];
        $node['states'] = ['hover' => ['style' => $style]];
        $tree = match ($kind) {
            'section' => [$node],
            'row' => [LayoutSchema::node('section', [], [], [$node])],
            default => [LayoutSchema::node('section', [], [], [LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], [$node])])])],
        };
        $saved = LayoutSchema::normalize($tree)[0];
        if ($kind !== 'section') $saved = $saved['children'][0];
        if ($kind !== 'section' && $kind !== 'row') $saved = $saved['children'][0]['children'][0];
        $keys = match ($kind) {
            'section' => ['scroll_background', 'scroll_strength'],
            'image' => ['scroll_element', 'scroll_strength', 'scroll_direction'],
            default => [],
        };
        assert_same(array_intersect_key($style, array_flip($keys)), $saved['style']);
        assert_same($saved['style'], $saved['responsive']['mobile']['style'] ?? []);
        assert_false(isset($saved['states']));
    }
    $invalid = LayoutSchema::normalize([LayoutSchema::node('section', [], ['scroll_background' => 'bad', 'scroll_strength' => 'url(evil)'])])[0];
    assert_same([], $invalid['style']);
    assert_same([], LayoutSchema::node('section')['style']);
    assert_same('subtle', LayoutSchema::styleSpec()['scroll_strength']['default']);
});

test('scroll backgrounds isolate content and overlay and preserve blur and focal point in every preview mode', function (): void {
    $site = comet_site_test_setup();
    file_put_contents(comet_test_workspace_path() . '/media/parallax.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"/>');
    $style = ['background_image' => 'parallax.svg', 'scroll_background' => 'parallax', 'background_blur' => 8, 'background_position' => 'top', 'overlay' => 'dark', 'animation' => 'fade'];
    foreach ([[false, false], [true, false], [true, true]] as [$preview, $browse]) {
        $ctx = comet_site_test_context($site);
        $ctx->preview = $preview;
        $ctx->browsePreview = $browse;
        $renderer = new NodeRenderer($ctx);
        $section = LayoutSchema::node('section', [], $style, [LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], [LayoutSchema::node('heading', ['text' => 'Stationary content'])])])]);
        $html = $renderer->render([$section]);
        assert_true(str_contains($html, 'c-parallax-clip" aria-hidden="true"><span class="c-parallax-background"></span></span><div'), $html);
        foreach (['c-overlay-dark', 'c-bg-pos-top', '--c-background-blur:8px', 'c-anim-fade', 'c-m-scroll-background-none'] as $token) assert_true(str_contains($html, $token), $token);
        assert_same($preview && !$browse, str_contains($html, 'c-scroll-static'));
        $image = LayoutSchema::node('image', ['media' => 'parallax.svg', 'caption' => 'Caption', 'link' => ['type' => 'home']], ['animation' => 'zoom-in']);
        $image['responsive'] = ['mobile' => ['style' => ['scroll_element' => 'parallax', 'scroll_direction' => 'faster']]];
        $html = $renderer->render([$image]);
        foreach (['c-parallax-element', 'c-m-scroll-element-parallax', 'c-m-scroll-direction-faster', 'c-anim-zoom-in', 'Caption', 'c-image-link'] as $token) assert_true(str_contains($html, $token), $token);
        assert_false(str_contains($html, 'c-m-scroll-element-none'));
        unset($section['style']['scroll_background']);
        assert_false(str_contains($renderer->render([$section]), 'c-parallax-clip'));
    }
});

test('scroll device classes are generated independently of entrance and hover animations', function (): void {
    $css = StyleVariants::css(Theme::baseStylesheet());
    foreach (['.c-t-scroll-background-none', '.c-m-scroll-element-parallax', '.c-m-scroll-strength-strong', '.c-t-scroll-direction-faster'] as $selector) assert_true(str_contains($css, $selector), $selector);
    assert_false(str_contains($css, '.c-h-scroll-'));
    assert_false(str_contains($css, '.c-f-scroll-'));
});
