<?php

declare(strict_types=1);

use CometCMS\Site\LayoutSchema;
use CometCMS\Site\NodeRenderer;
use CometCMS\Site\Shadow;
use CometCMS\Site\StyleVariants;
use CometCMS\Site\Theme;

test('shadows bound custom values and reject CSS injection', function (): void {
    assert_same('md', Shadow::normalize('md'));
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
    $css = StyleVariants::css(Theme::baseStylesheet());
    assert_true(str_contains($css, '.c-t-sh-custom-tablet{box-shadow: var(--c-sh-custom-tablet);}'));
    assert_true(str_contains($css, '.c-h-ts-custom-hover:is(:hover,.c-preview-hover){text-shadow: var(--c-ts-custom-hover);}'));
    assert_true(str_contains($css, '.c-f-sh-glow:is(:focus-visible,:has(:focus-visible),.c-preview-focus){'));
});
