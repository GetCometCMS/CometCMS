<?php

declare(strict_types=1);

use CometCMS\Auth\PermissionService;

function comet_permission_test_service(): PermissionService
{
    return new PermissionService();
}

function comet_permission_test_token(array $permissions): array
{
    return [
        '_principal_type' => 'token',
        'id' => 'token-user',
        'permissions' => $permissions,
    ];
}

test('permission service lets explicit deny override broader allows', function (): void {
    $service = comet_permission_test_service();
    $principal = comet_permission_test_token([
        ['effect' => 'allow', 'actions' => ['content.*'], 'resources' => ['content:*']],
        ['effect' => 'deny', 'actions' => ['content.delete'], 'resources' => ['content:posts:locked']],
    ]);

    assert_true($service->allows($principal, 'content.update', ['type' => 'content', 'collection' => 'posts', 'id' => 'locked']));
    assert_false($service->allows($principal, 'content.delete', ['type' => 'content', 'collection' => 'posts', 'id' => 'locked']));
    assert_true($service->allows($principal, 'content.delete', ['type' => 'content', 'collection' => 'posts', 'id' => 'open']));
});

test('permission service matches wildcard resources and content slugs', function (): void {
    $service = comet_permission_test_service();
    $principal = comet_permission_test_token([
        ['effect' => 'allow', 'actions' => ['content.read'], 'resources' => ['content:pages:welcome']],
        ['effect' => 'allow', 'actions' => ['media.update'], 'resources' => ['media:category:Docs']],
    ]);

    assert_true($service->allows($principal, 'content.read', [
        'type' => 'content',
        'collection' => 'pages',
        'entry' => ['id' => 'entry-1', 'slug' => 'welcome'],
    ]));
    assert_true($service->allows($principal, 'media.update', [
        'type' => 'media',
        'file' => 'guide.pdf',
        'category' => 'Docs',
    ]));
    assert_false($service->allows($principal, 'media.update', [
        'type' => 'media',
        'file' => 'guide.pdf',
        'category' => 'Images',
    ]));
});

test('permission service restricts changed fields', function (): void {
    $service = comet_permission_test_service();
    $principal = comet_permission_test_token([
        ['effect' => 'allow', 'actions' => ['content.update'], 'resources' => ['content:posts:*'], 'fields' => ['title', 'summary']],
    ]);

    assert_true($service->allows($principal, 'content.update', [
        'type' => 'content',
        'collection' => 'posts',
        'fields' => ['title'],
    ]));
    assert_false($service->allows($principal, 'content.update', [
        'type' => 'content',
        'collection' => 'posts',
        'fields' => ['title', 'secret_notes'],
    ]));
});

test('permission service applies owner status and locale conditions', function (): void {
    $service = comet_permission_test_service();
    $principal = comet_permission_test_token([
        [
            'effect' => 'allow',
            'actions' => ['content.update'],
            'resources' => ['content:posts:*'],
            'conditions' => ['own' => true, 'status' => ['draft'], 'locales' => ['en']],
        ],
    ]);

    $context = [
        'type' => 'content',
        'collection' => 'posts',
        'entry' => ['id' => 'draft-post', 'author_id' => 'token-user', 'status' => 'draft'],
        'principal' => ['id' => 'token-user'],
        'locale' => 'en',
    ];

    assert_true($service->allows($principal, 'content.update', $context));
    assert_false($service->allows($principal, 'content.update', [
        ...$context,
        'entry' => ['id' => 'draft-post', 'author_id' => 'someone-else', 'status' => 'draft'],
    ]));
    assert_false($service->allows($principal, 'content.update', [
        ...$context,
        'entry' => ['id' => 'published-post', 'author_id' => 'token-user', 'status' => 'published'],
    ]));
    assert_false($service->allows($principal, 'content.update', [
        ...$context,
        'locale' => 'de',
    ]));
});

test('admins can delegate every permission shape the grants editor produces', function (): void {
    $service = comet_permission_test_service();
    $admin = ['id' => 'admin', 'role' => 'admin'];
    $editorGrants = \CometCMS\Auth\RoleRepository::defaultPermissions('editor');

    foreach ([
        $editorGrants,
        \CometCMS\Auth\RoleRepository::defaultPermissions('viewer'),
        \CometCMS\Auth\RoleRepository::defaultPermissions('admin'),
        [['effect' => 'allow', 'actions' => ['*'], 'resources' => ['*']]],
        [['effect' => 'allow', 'actions' => ['content.*'], 'resources' => ['content:*:*']]],
        [['effect' => 'allow', 'actions' => ['content.read', 'content.update'], 'resources' => ['workspace:site-a:content:posts:*'], 'fields' => ['title']]],
        [['effect' => 'allow', 'actions' => ['content.update'], 'resources' => ['content:posts:abc'], 'conditions' => ['own' => true]]],
        [['effect' => 'allow', 'actions' => ['media.read', 'media.upload'], 'resources' => ['media:category:hero']]],
        [['effect' => 'allow', 'actions' => ['backups.read', 'backups.create'], 'resources' => ['backups:*']]],
        [['effect' => 'allow', 'actions' => ['workspaces.manage'], 'resources' => ['workspaces:site-a']]],
        [['effect' => 'allow', 'actions' => ['users.read', 'tokens.create', 'roles.update'], 'resources' => ['*']]],
    ] as $grants) {
        assert_null($service->firstUncovered($admin, $grants), json_encode($grants));
    }
});

test('delegation is refused when the actor lacks an action or scope', function (): void {
    $service = comet_permission_test_service();
    $manager = comet_permission_test_token([
        ['effect' => 'allow', 'actions' => ['users.read', 'users.update'], 'resources' => ['*']],
        ['effect' => 'allow', 'actions' => ['content.read', 'content.update'], 'resources' => ['content:posts:*'], 'fields' => ['title', 'body']],
        ['effect' => 'deny', 'actions' => ['content.update'], 'resources' => ['content:posts:locked']],
    ]);

    // Assigning the Admin role to oneself is the escalation this prevents.
    assert_same(
        ['action' => 'dashboard.read', 'resource' => '*'],
        $service->firstUncovered($manager, \CometCMS\Auth\RoleRepository::defaultPermissions('admin')),
    );
    // Broader collection scope than the actor holds.
    assert_true($service->firstUncovered($manager, [['actions' => ['content.read'], 'resources' => ['content:*']]]) !== null);
    assert_true($service->firstUncovered($manager, [['actions' => ['content.read'], 'resources' => ['*']]]) !== null);
    // Field-restricted actors cannot delegate unrestricted or wider field access.
    assert_true($service->firstUncovered($manager, [['actions' => ['content.update'], 'resources' => ['content:posts:a']]]) !== null);
    assert_true($service->firstUncovered($manager, [['actions' => ['content.update'], 'resources' => ['content:posts:a'], 'fields' => ['price']]]) !== null);
    // A deny on part of the scope means the actor cannot delegate the whole scope.
    assert_true($service->firstUncovered($manager, [['actions' => ['content.update'], 'resources' => ['content:posts:*'], 'fields' => ['title']]]) !== null);
    // Within its own limits delegation works, and delegated denies are always fine.
    assert_null($service->firstUncovered($manager, [
        ['actions' => ['content.read'], 'resources' => ['workspace:site-a:content:posts:intro'], 'fields' => ['title']],
        ['effect' => 'deny', 'actions' => ['*'], 'resources' => ['*']],
    ]));
});
