<?php

declare(strict_types=1);

use CometCMS\Site\SiteRepository;

test('components get readable ids that stay when they are renamed', function (): void {
    $site = comet_site_test_setup();
    $section = [['kind' => 'section', 'children' => [['kind' => 'row']]]];

    assert_same('hero-banner', $site->createComponent('Hero Banner', $section)['id']);
    assert_same('hero-banner-2', $site->createComponent('Hero banner!', $section)['id']);
    assert_same('ueber-uns-strasse', $site->createComponent('Über uns · Straße', $section)['id']);
    assert_same('component', $site->createComponent('★', $section)['id']);
    assert_same('c0123456789', $site->createComponent('c0123456789', $section)['id'], 'All name slugs are available.');
    assert_file_exists_at($site->workspace()->path('site') . '/theme/components/hero-banner.json');

    $renamed = $site->renameComponent('hero-banner', 'Startseite');
    assert_same('hero-banner', $renamed['id']);
    assert_same('Startseite', $site->component('hero-banner')['name']);
});

test('theme files store no save time and stay unchanged when saved unchanged', function (): void {
    $site = comet_site_test_setup();
    $theme = $site->workspace()->path('site') . '/theme';
    $section = [['kind' => 'section', 'children' => [['kind' => 'row']]]];

    $saved = $site->saveTheme(['colors' => ['primary' => '#123456']]);
    $component = $site->createComponent('Promo', $section);
    $site->saveLayout('blog', 'detail', [comet_component_test_instance('p1', $component['id'])]);
    $site->saveRegion('footer', [comet_component_test_instance('p2', $component['id'])]);
    $files = ['theme.json', 'components/promo.json', 'layouts/blog/detail.json', 'regions/footer.json'];
    $before = [];
    foreach ($files as $file) {
        $before[$file] = file_get_contents($theme . '/' . $file);
        assert_false(str_contains($before[$file], 'updated_at'), $file . ' has no save time.');
    }
    assert_matches('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $saved['updated_at']);
    assert_same($saved['updated_at'], $site->theme()['updated_at']);
    assert_same($site->component('promo')['updated_at'], $component['updated_at']);

    $site->saveTheme($site->theme());
    $site->saveComponent('promo', $site->component('promo')['nodes']);
    $site->saveLayout('blog', 'detail', $site->layout('blog', 'detail'));
    $site->saveRegion('footer', $site->region('footer'));
    foreach ($files as $file) {
        assert_same($before[$file], file_get_contents($theme . '/' . $file), $file . ' is unchanged.');
    }
});

