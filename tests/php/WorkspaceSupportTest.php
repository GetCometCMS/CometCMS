<?php

declare(strict_types=1);

use CometCMS\Controllers\Admin\AuthController;
use CometCMS\Auth\PermissionService;
use CometCMS\Content\ContentRepository;
use CometCMS\Content\ContentTypeRepository;
use CometCMS\Core\Http;
use CometCMS\Media\MediaRepository;
use CometCMS\Storage\SettingsStore;
use CometCMS\Workspaces\WorkspaceContext;
use CometCMS\Workspaces\WorkspaceRepository;

test('workspace repository creates default and custom workspace folders', function (): void {
    $repository = new WorkspaceRepository();
    $workspaces = $repository->all();

    assert_same('default', $workspaces[0]['slug']);

    $site = $repository->save(['slug' => 'site-a', 'label' => 'Site A']);

    assert_same('site-a', $site['slug']);
    assert_true(is_dir(comet_test_workspace_path('site-a') . '/content-types'));
    assert_true(is_dir(comet_test_workspace_path('site-a') . '/media'));
    assert_true(is_dir(comet_test_workspace_path('site-a') . '/media-variants'));
});

test('workspace repository picks up renamed workspace folders on disk', function (): void {
    $repository = new WorkspaceRepository();
    $repository->save(['slug' => 'site-a', 'label' => 'Site A']);
    $repository->setDefault('site-a');

    rename(comet_test_workspace_path('site-a'), comet_test_workspace_path('site-renamed'));

    $slugs = array_map(static fn(array $workspace): string => (string) $workspace['slug'], $repository->all(true));

    assert_true(in_array('site-renamed', $slugs, true));
    assert_false(in_array('site-a', $slugs, true));
    assert_same('site-renamed', $repository->getDefault());
});

test('workspace reset follows the configured default after a folder rename', function (): void {
    $repository = new WorkspaceRepository();
    $repository->all();

    rename(comet_test_workspace_path('default'), comet_test_workspace_path('site-renamed'));
    $repository->all(true);

    WorkspaceContext::reset();
    new ContentTypeRepository();

    assert_same('site-renamed', WorkspaceContext::active()->slug());
    assert_false(is_dir(comet_test_workspace_path('default')));
});

test('archiving the current default workspace falls back to another workspace', function (): void {
    $repository = new WorkspaceRepository();
    $repository->save(['slug' => 'site-a', 'label' => 'Site A']);
    $repository->save(['slug' => 'site-b', 'label' => 'Site B']);
    $repository->setDefault('site-a');

    $workspace = $repository->archive('site-a');

    assert_true((bool) ($workspace['archived'] ?? false));
    assert_same('site-b', $repository->getDefault());
});

test('workspace named default can be archived when it is not the configured default', function (): void {
    $repository = new WorkspaceRepository();
    $repository->all();
    $repository->save(['slug' => 'site-a', 'label' => 'Site A']);
    $repository->setDefault('site-a');

    $workspace = $repository->archive('default');

    assert_true((bool) ($workspace['archived'] ?? false));
    assert_same('site-a', $repository->getDefault());
});

test('setup controller construction does not create a default workspace before setup', function (): void {
    comet_test_remove_directory(comet_test_workspace_path('default'));
    @unlink(COMET_STORAGE . '/settings.json');

    $_SERVER['REQUEST_URI'] = '/admin/api/setup';
    $_SERVER['SCRIPT_NAME'] = '/index.php';

    new AuthController(new Http());

    $settings = (new SettingsStore())->all();

    assert_false(isset($settings['workspaces']));
    assert_false(is_dir(comet_test_workspace_path('default')));
});

test('workspace content types entries and media are isolated', function (): void {
    $registry = new WorkspaceRepository();
    $registry->save(['slug' => 'site-a', 'label' => 'Site A']);
    $registry->save(['slug' => 'site-b', 'label' => 'Site B']);

    $user = ['id' => 'admin'];

    WorkspaceContext::setActive('site-a');
    $siteA = WorkspaceContext::active();
    (new ContentTypeRepository($siteA))->save(['name' => 'posts', 'fields' => ['title' => ['type' => 'text']]]);
    ContentRepository::make($siteA)->save('posts', ['title' => 'Site A Post', 'slug' => 'site-a-post', 'status' => 'published'], $user);
    file_put_contents($siteA->path('media') . '/hero.jpg', 'a');

    WorkspaceContext::setActive('site-b');
    $siteB = WorkspaceContext::active();
    (new ContentTypeRepository($siteB))->save(['name' => 'pages', 'fields' => ['title' => ['type' => 'text']]]);
    ContentRepository::make($siteB)->save('pages', ['title' => 'Site B Page', 'slug' => 'site-b-page', 'status' => 'published'], $user);
    file_put_contents($siteB->path('media') . '/logo.jpg', 'b');

    assert_true((new ContentTypeRepository($siteA))->exists('posts'));
    assert_false((new ContentTypeRepository($siteA))->exists('pages'));
    assert_same(1, count(ContentRepository::make($siteA)->all('posts')));
    assert_same(['hero.jpg'], array_map(static fn(array $file): string => $file['name'], (new MediaRepository($siteA))->files()));
});

test('permission service supports workspace scoped resources with legacy fallback', function (): void {
    $service = new PermissionService();
    $workspacePrincipal = [
        '_principal_type' => 'token',
        'permissions' => [
            ['effect' => 'allow', 'actions' => ['content.read'], 'resources' => ['workspace:site-a:content:posts:*']],
        ],
    ];
    $legacyPrincipal = [
        '_principal_type' => 'token',
        'permissions' => [
            ['effect' => 'allow', 'actions' => ['content.read'], 'resources' => ['content:*']],
        ],
    ];

    assert_true($service->allows($workspacePrincipal, 'content.read', [
        'type' => 'content',
        'workspace' => 'site-a',
        'collection' => 'posts',
    ]));
    assert_false($service->allows($workspacePrincipal, 'content.read', [
        'type' => 'content',
        'workspace' => 'site-b',
        'collection' => 'posts',
    ]));
    assert_true($service->allows($legacyPrincipal, 'content.read', [
        'type' => 'content',
        'workspace' => 'site-b',
        'collection' => 'posts',
    ]));
});
