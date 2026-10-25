<?php

declare(strict_types=1);

use CometCMS\Site\LayoutSchema;
use CometCMS\Site\NodeRenderer;
use CometCMS\Site\Shadow;
use CometCMS\Site\Theme;

test('shadows bound custom values and reject CSS injection', function (): void {
    assert_same('md', Shadow::normalize('md'));
    assert_same('', Shadow::normalize('xs'));
    assert_same('', Shadow::normalize('xl'));
    assert_same('shadow-n1a2b3', Shadow::normalize('shadow-n1a2b3'));
    assert_same('', Shadow::normalize('shadow-n1;color:red'));
    assert_same('', Shadow::normalize('inset', true));
    assert_same('', Shadow::normalize('xl', true));
    assert_same('glow', Shadow::normalize('glow', true));
    assert_same('', Shadow::normalize('0 0 10px red'));
    assert_same('', Shadow::normalize(['color' => 'primary);color:red']));
    assert_same('', Shadow::normalize(['color' => []]));
    assert_same('', Shadow::normalize([]));
    $value = Shadow::normalize(['color' => 'custom-brand', 'x' => -900, 'y' => 300, 'blur' => -10, 'spread' => 999, 'opacity' => 250, 'inset' => 'true']);
    assert_same(['color' => 'custom-brand', 'x' => -100, 'y' => 100, 'blur' => 0, 'spread' => 100, 'opacity' => 100, 'inset' => false], $value);
    $text = Shadow::normalize($value + ['extra' => 'ignored'], true);
    assert_false(isset($text['spread']));
    assert_false(isset($text['inset']));
    assert_true(str_contains(Shadow::css($text, true), '-100px 100px 0px color-mix('));
    assert_true(str_contains(Shadow::css(['color' => 'accent', 'inset' => true]), 'inset 6px 6px 0px 0px'));
    assert_same('0px 2px 8px 0px color-mix(in srgb,#000 25%,transparent)', Shadow::css(['color' => 'black', 'x' => 0, 'y' => 2, 'blur' => 8, 'opacity' => 25]));
});

test('theme shadow presets are bounded, keep their labels and render with variants', function (): void {
    $defaults = Theme::normalize([]);
    assert_same(['hard', 'glow', 'inset'], array_keys($defaults['shadows']));
    assert_same([], Theme::normalize(['shadows' => []])['shadows']);
    $theme = Theme::normalize(['shadows' => [
        'shadow-card' => ['label' => '<b>Card</b>', 'color' => 'custom-brand', 'x' => 0, 'y' => 12, 'blur' => 900, 'opacity' => 40],
        'glow' => ['color' => 'accent', 'x' => 0, 'y' => 0, 'blur' => 30],
        'shadow-bad' => ['color' => 'red'],
        'evil}body{' => ['color' => 'primary'],
        'shadow-text' => 'md',
    ]]);
    assert_same(['shadow-card', 'glow'], array_keys($theme['shadows']));
    assert_same(['label' => 'Card', 'color' => 'custom-brand', 'x' => 0, 'y' => 12, 'blur' => 200, 'spread' => 0, 'opacity' => 40, 'inset' => false], $theme['shadows']['shadow-card']);
    assert_same(Theme::MAX_SHADOWS, count(Theme::normalize(['shadows' => array_fill_keys(array_map(fn($i) => 'shadow-s' . $i, range(1, 40)), ['color' => 'primary'])])['shadows']));
    $css = Theme::stylesheet($theme);
    assert_true(str_contains($css, '.c-sh-shadow-card{box-shadow:0px 12px 200px 0px color-mix(in srgb,var(--c-custom-brand,var(--c-primary)) 40%,transparent);}'));
    assert_true(str_contains($css, '.c-h-sh-shadow-card:is(:hover,.c-preview-hover){'));
    assert_true(str_contains($css, '.c-t-sh-glow{'));
    assert_false(str_contains($css, '.c-sh-hard{'));
    assert_true(str_contains(Theme::stylesheet([]), '.c-sh-inset{box-shadow:inset 0px 2px 8px 0px color-mix(in srgb,#000 25%,transparent);}'));
});

test('shadows survive saving with independent device and state settings and legacy presets', function (): void {
    $site = comet_site_test_setup();
    $custom = Shadow::normalize(['color' => 'accent', 'x' => 8, 'y' => 8]);
    $hover = Shadow::normalize(['color' => 'custom-brand', 'x' => 0, 'y' => 0, 'blur' => 24, 'opacity' => 55]);
    $heading = LayoutSchema::node('heading', [], ['shadow' => $custom, 'text_shadow' => 'hard']);
    $heading['responsive'] = ['tablet' => ['style' => ['shadow' => $hover]], 'mobile' => ['style' => ['shadow' => 'none']]];
    $heading['states'] = ['hover' => ['style' => ['shadow' => $hover, 'text_shadow' => Shadow::normalize($hover, true)]], 'focus' => ['style' => ['shadow' => 'glow']]];
    $nodes = [LayoutSchema::node('section', [], ['shadow' => 'md'], [LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], ['shadow' => 'hard'], [$heading])])])];
    $site->saveLayout('about', 'detail', $nodes);
    $saved = $site->layout('about', 'detail');
    assert_same('md', $saved[0]['style']['shadow']);
    $savedHeading = $saved[0]['children'][0]['children'][0]['children'][0];
    assert_same($custom, $savedHeading['style']['shadow']);
    assert_same($hover, $savedHeading['responsive']['tablet']['style']['shadow']);
    assert_same($hover, $savedHeading['states']['hover']['style']['shadow']);
    assert_same($saved, LayoutSchema::normalize($saved));
    foreach ([false, true] as $annotate) {
        $context = comet_site_test_context($site);
        $context->annotate = $annotate;
        $html = (new NodeRenderer($context))->render($saved);
        foreach (['c-sh-md', 'c-sh-hard', 'c-sh-custom-normal', 'c-t-sh-custom-tablet', 'c-m-sh-none', 'c-h-sh-custom-hover', 'c-h-ts-custom-hover', 'c-f-sh-glow', '--c-sh-custom-normal:8px 8px 0px 0px', '--c-sh-custom-tablet:0px 0px 24px 0px', '--c-sh-custom-hover:0px 0px 24px 0px', '--c-ts-custom-hover:0px 0px 24px color-mix('] as $expected) {
            assert_true(str_contains($html, $expected), $expected);
        }
        assert_false(str_contains($html, 'Array'));
    }
    $css = Theme::stylesheet([]);
    assert_true(str_contains($css, '.c-t-sh-custom-tablet{box-shadow: var(--c-sh-custom-tablet);}'));
    assert_true(str_contains($css, '.c-h-ts-custom-hover:is(:hover,.c-preview-hover){text-shadow: var(--c-ts-custom-hover);}'));
    assert_true(str_contains($css, '.c-f-sh-glow:is(:focus-visible,:has(:focus-visible),.c-preview-focus){'));
});

test('built-in shadow sizes are editable, bounded and independent of removable presets', function (): void {
    $defaults = Shadow::scaleDefaults();
    assert_same(['sm', 'md', 'lg'], array_keys($defaults));
    assert_same($defaults, Theme::normalize([])['shadow_scale']);
    assert_same($defaults, Theme::normalize(['shadow_scale' => null])['shadow_scale']);
    $theme = Theme::normalize(['shadow_scale' => [
        'sm' => ['color' => 'accent', 'x' => 7, 'y' => 12, 'blur' => 999, 'opacity' => 75, 'inset' => true],
        'lg' => ['color' => 'bad);color:red'],
        'xs' => ['color' => 'primary'],
        'xl' => ['color' => 'primary'],
        'none' => ['color' => 'primary'],
        'evil}body{' => ['color' => 'primary'],
    ], 'shadows' => []]);
    assert_same(array_keys($defaults), array_keys($theme['shadow_scale']));
    assert_same($defaults['lg'], $theme['shadow_scale']['lg']);
    assert_same(200, $theme['shadow_scale']['sm']['blur']);
    assert_same([], $theme['shadows']);
    assert_same($theme, Theme::normalize($theme));
    $css = Theme::stylesheet($theme);
    assert_false(str_contains($css, 'sh-xs'));
    assert_false(str_contains($css, 'sh-xl'));
    $value = 'inset 7px 12px 200px 0px color-mix(in srgb,var(--c-accent,var(--c-primary)) 75%,transparent)';
    foreach (['.c-sh-sm', '.c-t-sh-sm', '.c-m-sh-sm', '.c-h-sh-sm:is(:hover,.c-preview-hover)', '.c-f-sh-sm:is(:focus-visible,:has(:focus-visible),.c-preview-focus)'] as $selector) {
        assert_true(str_contains($css, $selector . '{box-shadow:' . $value . ';}'), $selector);
    }
    assert_true(str_contains($css, '.c-sh-none { box-shadow: none; }'));
    assert_true(str_contains(Shadow::scaleCss('sm', $defaults['sm']), ',0 1px 2px rgb(0 0 0 / 0.04)'));
    assert_false(str_contains(Shadow::scaleCss('sm', $theme['shadow_scale']['sm']), ',0 1px 2px'));
    $site = comet_site_test_setup();
    $site->saveTheme($theme);
    assert_same($theme['shadow_scale'], $site->theme()['shadow_scale']);
});
