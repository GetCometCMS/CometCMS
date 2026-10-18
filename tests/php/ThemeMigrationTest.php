<?php

declare(strict_types=1);

use CometCMS\Backups\BackupService;
use CometCMS\Backups\RestoreService;
use CometCMS\Site\LayoutSchema;
use CometCMS\Site\SiteRepository;
use CometCMS\Storage\JsonStore;

function comet_theme_test_placement(string $id, string $component): array
{
    return ['id' => $id, 'kind' => 'component', 'props' => ['component' => $component, 'root' => 'section']];
}

/** A component file as releases before readable ids wrote it. */
function comet_theme_test_legacy_component(string $id, string $name, array $children = []): array
{
    return [
        'format_version' => LayoutSchema::FORMAT_VERSION,
        'id' => $id,
        'name' => $name,
        'root' => 'section',
        'type' => '',
        'created_at' => '2026-01-01T00:00:00Z',
        'updated_at' => '2026-01-02T00:00:00Z',
        'nodes' => [['id' => $id . '-sec', 'kind' => 'section', 'children' => [['id' => $id . '-row', 'kind' => 'row', 'children' => [
            ['id' => $id . '-col', 'kind' => 'column', 'children' => $children],
        ]]]]],
    ];
}

test('components get readable ids that stay when they are renamed', function (): void {
    $site = comet_site_test_setup();
    $section = [['kind' => 'section', 'children' => [['kind' => 'row']]]];

    assert_same('hero-banner', $site->createComponent('Hero Banner', $section)['id']);
    assert_same('hero-banner-2', $site->createComponent('Hero banner!', $section)['id']);
    assert_same('ueber-uns-strasse', $site->createComponent('Über uns · Straße', $section)['id']);
    assert_same('component', $site->createComponent('★', $section)['id']);
    assert_same('c0123456789-2', $site->createComponent('c0123456789', $section)['id'], 'New ids never look generated.');
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
    $site->saveLayout('blog', 'detail', [comet_theme_test_placement('p1', $component['id'])]);
    $site->saveRegion('footer', [comet_theme_test_placement('p2', $component['id'])]);
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

test('older website files move into the theme folder and components get readable ids', function (): void {
    comet_site_test_setup();
    $root = comet_test_workspace_path();
    $legacy = new JsonStore($root . '/site');
    $revisions = new JsonStore($root . '/revisions/site');
    $theme = $root . '/site/theme';
    // The test setup already saved into the theme folder; start from an older workspace.
    comet_test_remove_directory($theme . '/layouts');

    $legacy->write(comet_theme_test_legacy_component('c0000000001', 'Promo'), 'components', 'c0000000001');
    $legacy->write(comet_theme_test_legacy_component('c0000000002', 'Wrapper', [comet_theme_test_placement('inner', 'c0000000001')]), 'components', 'c0000000002');
    $legacy->write(comet_theme_test_legacy_component('c0000000003', 'Promo'), 'components', 'c0000000003');
    $legacy->write(['format_version' => LayoutSchema::FORMAT_VERSION, 'nodes' => [comet_theme_test_placement('p1', 'c0000000002')], 'updated_at' => 'x'], 'layouts', 'blog', 'detail');
    $legacy->write(['format_version' => LayoutSchema::FORMAT_VERSION, 'nodes' => [comet_theme_test_placement('p2', 'c0000000001')]], 'regions', 'footer');
    $revisions->write(['id' => 'r1', 'snapshot' => ['format_version' => LayoutSchema::FORMAT_VERSION, 'nodes' => [comet_theme_test_placement('p3', 'c0000000001')], 'customized' => true]], 'layouts', 'blog', 'detail', 'r1');
    $revisions->write(['id' => 'r2', 'snapshot' => ['format_version' => LayoutSchema::FORMAT_VERSION, 'nodes' => [], 'customized' => true]], 'components', 'c0000000001', 'r2');

    $site = new SiteRepository();

    foreach (['components', 'layouts', 'regions'] as $directory) {
        assert_false(is_dir($root . '/site/' . $directory), $directory . ' left site/.');
    }
    $ids = array_column($site->components(), 'id');
    sort($ids);
    assert_same(['promo', 'promo-2', 'wrapper'], $ids);
    assert_same(['c0000000001' => 'promo', 'c0000000002' => 'wrapper', 'c0000000003' => 'promo-2'], $legacy->read('component-aliases'));
    assert_same('2026-01-01T00:00:00Z', $site->component('promo')['created_at']);
    assert_same('2026-01-02T00:00:00Z', $site->component('promo')['updated_at'], 'The stored save time becomes the file time.');
    assert_false(str_contains((string) file_get_contents($theme . '/components/promo.json'), 'updated_at'));

    assert_same(['wrapper'], LayoutSchema::componentRefs($site->layout('blog', 'detail')));
    assert_same(['promo'], LayoutSchema::componentRefs($site->region('footer')));
    assert_same(['promo'], LayoutSchema::componentRefs((new JsonStore($theme))->read('components', 'wrapper')['nodes']));
    assert_same(['promo'], LayoutSchema::componentRefs($revisions->read('layouts', 'blog', 'detail', 'r1')['snapshot']['nodes']));
    assert_same(['r2'], array_column($site->componentRevisions('promo'), 'id'));
    assert_false(is_dir($root . '/revisions/site/components/c0000000001'));

    // Clients that still hold an old id keep working.
    assert_same('promo', $site->component('c0000000001')['id']);
    assert_same(['r2'], array_column($site->componentRevisions('c0000000001'), 'id'));
    assert_true(str_contains(comet_site_test_get('/blog/hello')['body'], 'c-section'));
});

test('older backups restore into the theme folder without duplicating migrated components', function (): void {
    comet_site_test_setup();
    $root = comet_test_workspace_path();
    $component = comet_theme_test_legacy_component('c00000000aa', 'Promo', [['id' => 'h', 'kind' => 'heading', 'props' => ['text' => 'From the backup']]]);
    $zipPath = COMET_STORAGE . '/backups/legacy-site.zip';
    $zip = new ZipArchive();
    assert_true($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
    $zip->addFromString('manifest.json', json_encode(['cms' => 'CometCMS', 'version' => '1.0.0']));
    $zip->addFromString('site/components/c00000000aa.json', json_encode($component));
    $zip->addFromString('site/layouts/blog/detail.json', json_encode(['format_version' => LayoutSchema::FORMAT_VERSION, 'nodes' => [comet_theme_test_placement('p1', 'c00000000aa')]]));
    $zip->addFromString('site/component-aliases.json', json_encode(['c00000000aa' => 'elsewhere']));
    $zip->close();

    (new RestoreService())->restore($zipPath, true, ['id' => 'admin'], ['site']);
    $site = new SiteRepository();
    assert_false(is_dir($root . '/site/components'));
    assert_same(['promo'], array_column($site->components(), 'id'));
    assert_same(['promo'], LayoutSchema::componentRefs($site->layout('blog', 'detail')));
    assert_same(['c00000000aa' => 'promo'], (new JsonStore($root . '/site'))->read('component-aliases'), 'Restores never replace the alias record.');

    $site->saveComponent('promo', [['kind' => 'section', 'children' => [['kind' => 'row']]]]);
    (new RestoreService())->restore($zipPath, false, ['id' => 'admin'], ['site']);
    assert_same(['promo'], array_column($site->components(), 'id'), 'The old file counts as the migrated component.');
    assert_false(str_contains(json_encode($site->component('promo')), 'From the backup'));

    (new RestoreService())->restore($zipPath, true, ['id' => 'admin'], ['site']);
    assert_same(['promo'], array_column($site->components(), 'id'));
    assert_true(str_contains(json_encode($site->component('promo')), 'From the backup'));

    $backup = (new BackupService())->create(['id' => 'admin'], ['site']);
    $zip = new ZipArchive();
    assert_true($zip->open(COMET_STORAGE . '/backups/' . $backup['name']));
    assert_true($zip->locateName('site/theme/components/promo.json') !== false);
    assert_true($zip->locateName('site/theme/layouts/blog/detail.json') !== false);
    $zip->close();
});
