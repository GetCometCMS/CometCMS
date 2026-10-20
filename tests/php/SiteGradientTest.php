<?php

declare(strict_types=1);

use CometCMS\Site\Gradient;
use CometCMS\Site\LayoutSchema;
use CometCMS\Site\NodeRenderer;
use CometCMS\Site\SiteRepository;
use CometCMS\Site\SmartValue;

test('gradients reject arbitrary CSS and normalize their bounded settings', function (): void {
    $linear = ['type' => 'linear', 'from' => 'primary', 'to' => 'custom-brand'];
    assert_same($linear + ['angle' => 135], Gradient::normalize($linear));
    assert_same(0, Gradient::normalize($linear + ['angle' => -50])['angle']);
    assert_same(360, Gradient::normalize($linear + ['angle' => 500])['angle']);
    assert_same(135, Gradient::normalize($linear + ['angle' => []])['angle']);
    assert_same(['type' => 'radial', 'from' => 'primary', 'to' => 'accent'], Gradient::normalize(['type' => 'radial', 'from' => 'primary', 'to' => 'accent', 'angle' => 30, 'extra' => 'ignored']));
    foreach (['linear-gradient(red,blue)', null, [], ['type' => []],
        array_replace($linear, ['from' => 'custom-x);color:red']), array_replace($linear, ['to' => []]),
        array_replace($linear, ['type' => 'conic']), array_replace($linear, ['from' => '']),
    ] as $invalid) {
        assert_same([], Gradient::normalize($invalid));
        assert_same('', Gradient::css($invalid));
    }
    assert_same(135, Gradient::normalize($linear + ['angle' => '10);color:red'])['angle']);
    assert_same([], Gradient::normalize(['type' => 'linear', 'from' => 'primary']));
});

test('gradient backgrounds and text survive saving and reloading without changing older layouts', function (): void {
    $site = comet_site_test_setup();
    $gradient = ['type' => 'linear', 'from' => 'primary', 'to' => 'custom-brand', 'angle' => 90];
    $nodes = [LayoutSchema::node('section', [], ['background_gradient' => $gradient], [
        LayoutSchema::node('row', [], ['background_gradient' => $gradient], [
            LayoutSchema::node('column', [], ['background_gradient' => $gradient], [
                LayoutSchema::node('heading', [], ['background' => 'surface', 'background_gradient' => $gradient, 'text_gradient' => $gradient]),
                LayoutSchema::node('text', [], ['text_gradient' => $gradient]),
                LayoutSchema::node('button', [], ['text_gradient' => $gradient]),
            ]),
        ]),
    ])];
    $site->saveLayout('about', 'detail', $nodes);
    $saved = (new SiteRepository())->layout('about', 'detail');
    $section = $saved[0];
    $row = $section['children'][0];
    $column = $row['children'][0];
    foreach ([$section, $row, $column, $column['children'][0]] as $node) {
        assert_same($gradient, $node['style']['background_gradient']);
    }
    assert_same($gradient, $column['children'][0]['style']['text_gradient']);
    assert_same($gradient, $column['children'][1]['style']['text_gradient']);
    assert_false(isset($column['children'][2]['style']['text_gradient']));
    $site->saveLayout('about', 'detail', [LayoutSchema::node('section', [], ['background' => 'surface', 'background_gradient' => [], 'text_gradient' => $gradient])]);
    assert_same(['background' => 'surface'], (new SiteRepository())->layout('about', 'detail')[0]['style']);
    $invalid = LayoutSchema::normalize([LayoutSchema::node('section', [], ['background_gradient' => ['type' => 'linear', 'from' => 'red;bad', 'to' => 'accent']])]);
    assert_same([], $invalid[0]['style']);
});

test('gradients render in previews and public output and restore the saved background image when disabled', function (): void {
    $site = comet_site_test_setup();
    file_put_contents(comet_test_workspace_path() . '/media/gradient-test.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"/>');
    $gradient = ['type' => 'radial', 'from' => 'primary', 'to' => 'accent'];
    $style = ['background' => 'surface', 'background_gradient' => $gradient, 'background_image' => 'gradient-test.svg', 'background_blur' => 8, 'overlay' => 'dark'];
    $children = [LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], [LayoutSchema::node('text')])])];
    foreach ([false, true] as $annotate) {
        $ctx = comet_site_test_context($site);
        $ctx->annotate = $annotate;
        $renderer = new NodeRenderer($ctx);
        $html = $renderer->render([LayoutSchema::node('section', [], $style, $children)]);
        assert_true(str_contains($html, 'background-image:radial-gradient(ellipse at center,var(--c-primary, var(--c-primary)),var(--c-accent, var(--c-accent)))'));
        assert_true(str_contains($html, 'c-bg-surface'));
        assert_false(str_contains($html, 'c-has-bg-image'));
        assert_false(str_contains($html, 'c-overlay-dark'));
        assert_false(str_contains($html, 'c-background-blur'));
        $html = $renderer->render([LayoutSchema::node('section', [], array_replace($style, ['background_gradient' => []]), $children)]);
        assert_true(str_contains($html, 'c-background-blur'));
        assert_true(str_contains($html, 'gradient-test.svg'));

        $textStyle = ['background' => 'surface', 'background_gradient' => $gradient, 'text_color' => 'text', 'text_gradient' => ['type' => 'linear', 'from' => 'primary', 'to' => 'accent', 'angle' => 45]];
        $html = $renderer->render([
            LayoutSchema::node('heading', ['text' => SmartValue::reference('title', 'site'), 'link' => ['type' => 'home']], $textStyle),
            LayoutSchema::node('text', ['html' => '<p>Hello <strong>world</strong></p>'], $textStyle),
        ]);
        assert_true(str_contains($html, '<h2 class="c-heading c-text-gradient" style="--c-text-gradient:linear-gradient(45deg,'));
        assert_true(str_contains($html, '<div class="c-prose c-text-gradient"'));
        assert_true(str_contains($html, '<a href="/">Comet Site</a>'));
        assert_true(str_contains($html, '<strong>world</strong>'));
        assert_same(2, substr_count($html, 'background-image:radial-gradient('));
        $plain = $renderer->render([LayoutSchema::node('heading'), LayoutSchema::node('text')]);
        assert_false(str_contains($plain, 'c-text-gradient'));
    }
});
