<?php

declare(strict_types=1);

use CometCMS\Localization\LanguageSettings;
use CometCMS\Site\LayoutSchema;
use CometCMS\Site\NodeRenderer;
use CometCMS\Site\SiteRepository;

test('HTML blocks preserve source and embedded styles through saving and public and builder rendering', function (): void {
    $site = comet_site_test_setup();
    $source = "\n<style>\n.promo > span { color: #123456; }\n</style>\n<section class=\"promo\" data-promo=\"custom\"><span style=\"font-weight: 700\">Hello &amp; welcome</span></section>\n";
    $block = LayoutSchema::node('html', ['html' => $source], ['class' => 'custom-block', 'padding_top' => 'md']);
    $saved = $site->saveLayout('about', 'detail', [LayoutSchema::node('section', [], [], [
        LayoutSchema::node('row', [], [], [LayoutSchema::node('column', [], [], [$block])]),
    ])]);
    $reloaded = (new SiteRepository())->layout('about', 'detail');
    assert_same($saved, $reloaded);
    assert_same($source, $reloaded[0]['children'][0]['children'][0]['children'][0]['props']['html']);
    assert_true(str_contains(comet_site_test_get('/about')['body'], $source));

    $ctx = comet_site_test_context($site);
    $ctx->preview = true;
    $ctx->annotate = true;
    $html = (new NodeRenderer($ctx))->render($reloaded);
    assert_true(str_contains($html, $source));
    assert_true(str_contains($html, 'data-node="' . $block['id'] . '"'));
    foreach (['c-el-html', 'custom-block', 'c-pt-md'] as $class) assert_true(str_contains($html, $class));
    assert_false(str_contains($html, 'c-prose'));
    assert_same('Hello & welcome', LayoutSchema::text($reloaded, 'en', 'en'));

    // Rich text continues to discard embedded styles and unsupported markup.
    $text = LayoutSchema::normalizeComponent([LayoutSchema::node('column', [], [], [LayoutSchema::node('text', ['html' => $source])])], 'column')[0]['children'][0];
    assert_false(str_contains($text['props']['html'], '<style>'));
    assert_false(str_contains($text['props']['html'], 'data-promo'));
});

test('HTML blocks support localized markup and repeater item placement', function (): void {
    (new LanguageSettings())->save(['locales' => ['en', 'de'], 'default_locale' => 'en']);
    $source = '<style>.greeting { color: red; }</style><p class="greeting">Hello</p>';
    $german = '<style>.greeting { color: blue; }</style><p class="greeting">Hallo</p>';
    $block = LayoutSchema::node('html', ['html' => ['type' => 'localized', 'values' => ['en' => $source, 'de' => $german]]]);
    $layout = LayoutSchema::normalizeComponent([LayoutSchema::node('column', [], [], [
        LayoutSchema::node('field', [], [], [$block]),
    ])], 'column');
    assert_same('html', $layout[0]['children'][0]['children'][0]['kind']);
    assert_same(['en' => $source, 'de' => $german], $layout[0]['children'][0]['children'][0]['props']['html']['values']);
    assert_same($layout, LayoutSchema::normalizeComponent($layout, 'column'));
    assert_same('Hello', LayoutSchema::text([$block], 'en', 'en'));
    assert_same('Hallo', LayoutSchema::text([$block], 'de', 'en'));
    assert_true(in_array('html', LayoutSchema::spec()['elements'], true));
    assert_same('raw_html', LayoutSchema::spec()['kinds']['html']['props']['html']['type']);
    assert_true(LayoutSchema::spec()['kinds']['html']['props']['html']['localized']);
});

test('empty HTML blocks show an editor placeholder and normalize invalid or oversized source', function (): void {
    comet_site_test_setup();
    $ctx = comet_site_test_context();
    $renderer = new NodeRenderer($ctx);
    $block = LayoutSchema::node('html');
    assert_same('', $renderer->render([$block]));
    $ctx->preview = true;
    $ctx->annotate = true;
    $html = $renderer->render([$block]);
    assert_true(str_contains($html, 'data-placeholder="addHtml"'));
    assert_true(str_contains($html, 'data-node="' . $block['id'] . '"'));
    $normalized = LayoutSchema::normalizeComponent([LayoutSchema::node('column', [], [], [
        LayoutSchema::node('html', ['html' => ['invalid']]),
        LayoutSchema::node('html', ['html' => str_repeat('ä', 50001)]),
    ])], 'column')[0]['children'];
    assert_same('', $normalized[0]['props']['html']);
    assert_same(str_repeat('ä', 50000), $normalized[1]['props']['html']);
});
