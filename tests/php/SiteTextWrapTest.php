<?php

declare(strict_types=1);

use CometCMS\Site\LayoutSchema;
use CometCMS\Site\NodeRenderer;
use CometCMS\Site\StyleVariants;
use CometCMS\Site\Theme;

test('text wrapping survives saving and renders base and device styles', function (): void {
    comet_site_test_setup();
    $renderer = new NodeRenderer(comet_site_test_context());
    $css = Theme::baseStylesheet();
    $variants = StyleVariants::css($css);
    foreach (['normal', 'nowrap', 'break-word', 'break-all', 'balance', 'pretty'] as $mode) {
        $heading = LayoutSchema::node('heading', ['text' => 'A long heading'], ['text_wrap' => $mode]);
        $heading['responsive'] = ['tablet' => ['style' => ['text_wrap' => 'normal']], 'mobile' => ['style' => ['text_wrap' => 'break-word']]];
        $nodes = LayoutSchema::normalizeComponent([LayoutSchema::node('column', [], [], [$heading])], 'column');
        $saved = $nodes[0]['children'][0];
        assert_same($mode, $saved['style']['text_wrap']);
        assert_same($heading['responsive'], $saved['responsive']);
        assert_same($nodes, LayoutSchema::normalizeComponent($nodes, 'column'));
        $html = $renderer->render($nodes);
        foreach (['c-tw-' . $mode, 'c-t-tw-normal', 'c-m-tw-break-word'] as $class) assert_true(str_contains($html, $class));
        assert_true(str_contains($css, '.c-tw-' . $mode . ' {'));
        foreach (['t', 'm'] as $device) assert_true(str_contains($variants, '.c-' . $device . '-tw-' . $mode . '{'));
    }
    $invalid = LayoutSchema::node('heading', [], ['text_wrap' => 'invalid']);
    $saved = LayoutSchema::normalizeComponent([LayoutSchema::node('column', [], [], [$invalid])], 'column');
    assert_same('', $saved[0]['children'][0]['style']['text_wrap'] ?? '');
    $default = LayoutSchema::node('heading');
    assert_false(str_contains($renderer->render([$default]), 'c-tw-'));
});
