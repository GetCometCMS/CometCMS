<?php

declare(strict_types=1);

use CometCMS\Backups\BackupService;
use CometCMS\Backups\RestoreService;
use CometCMS\Site\SiteRepository;
use CometCMS\Storage\JsonStore;

test('website backups restore separate theme files over both new and legacy themes', function (): void {
    $site = new SiteRepository();
    $theme = $site->saveTheme(['colors' => ['primary' => '#123456'], 'custom_css' => '.backup { color: red; }']);
    // Restored files are new files, so only their contents must match.
    $withoutTime = static fn(array $theme): array => array_diff_key($theme, ['updated_at' => true]);
    $backups = new BackupService();
    $backup = $backups->create(['id' => 'admin'], ['site']);
    $path = COMET_STORAGE . '/backups/' . $backup['name'];
    $zip = new ZipArchive();
    assert_true($zip->open($path));
    assert_same($theme['custom_css'], $zip->getFromName('site/theme/style.css'));
    assert_false(array_key_exists('custom_css', json_decode($zip->getFromName('site/theme/theme.json'), true)));
    $zip->close();

    $site->saveTheme(['custom_css' => '.changed {}']);
    (new RestoreService())->restore($path, true, ['id' => 'admin'], ['site']);
    assert_same($withoutTime($theme), $withoutTime($site->theme()));

    $store = new JsonStore($site->workspace()->path('site'));
    $store->write(['custom_css' => '.legacy {}'], 'theme');
    unlink($site->workspace()->path('site') . '/theme/theme.json');
    unlink($site->workspace()->path('site') . '/theme/style.css');
    $legacyBackup = $backups->create(['id' => 'admin'], ['site']);
    $legacyPath = COMET_STORAGE . '/backups/' . $legacyBackup['name'];
    (new RestoreService())->restore($path, false, ['id' => 'admin'], ['site']);
    assert_same('.legacy {}', $site->theme()['custom_css'], 'Restoring without overwrite preserves the existing theme.');
    (new RestoreService())->restore($path, true, ['id' => 'admin'], ['site']);
    assert_same($withoutTime($theme), $withoutTime($site->theme()));
    assert_null($store->read('theme'));
    (new RestoreService())->restore($legacyPath, false, ['id' => 'admin'], ['site']);
    assert_same($withoutTime($theme), $withoutTime($site->theme()), 'An old backup without overwrite preserves a migrated theme.');
    (new RestoreService())->restore($legacyPath, true, ['id' => 'admin'], ['site']);
    assert_same('.legacy {}', $site->theme()['custom_css']);
});

test('backup notes protect backups from deletion until cleared', function (): void {
    $name = 'example-backup.zip';
    $path = COMET_STORAGE . '/backups/' . $name;
    file_put_contents($path, 'zip-placeholder');

    $service = new BackupService();
    $backup = $service->setNote($name, 'Keep before launch', ['id' => 'admin']);

    assert_same('Keep before launch', $backup['note']);
    assert_true($backup['is_protected']);
    assert_file_exists_at($path . '.meta.json');
    assert_throws(RuntimeException::class, static fn() => $service->delete($name));
    assert_file_exists_at($path);

    $backup = $service->setNote($name, '   ', ['id' => 'admin']);

    assert_same('', $backup['note']);
    assert_false($backup['is_protected']);
    assert_false(is_file($path . '.meta.json'));

    $service->delete($name);

    assert_false(is_file($path));
});
