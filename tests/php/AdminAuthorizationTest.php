<?php

declare(strict_types=1);

/**
 * Run one admin controller action in a temporary PHP server as $userId.
 *
 * Test storage is wiped at the end of every request, so $seed (PHP source) must
 * create everything the request needs. Returns [status, decoded JSON body].
 */
function comet_admin_request(string $userId, string $seed, string $method, string $uri, string $action, array $body = []): array
{
    $routerPath = COMET_STORAGE . '/admin-authorization-router-' . bin2hex(random_bytes(4)) . '.php';
    file_put_contents(
        $routerPath,
        "<?php\n" .
        'require ' . var_export(__DIR__ . '/bootstrap.php', true) . ";\n" .
        "session_start();\n" .
        "\$users = new \\CometCMS\\Auth\\UserRepository();\n" .
        "\$roles = new \\CometCMS\\Auth\\RoleRepository();\n" .
        "\$roles->seed();\n" .
        "\$users->create('admin', 'secret-password', 'admin');\n" .
        $seed . "\n" .
        "\$_SESSION['user_id'] = " . var_export($userId, true) . ";\n" .
        "\$_SESSION['cometcms_csrf'] = 'test-token';\n" .
        "\$_SERVER['REQUEST_URI'] = " . var_export($uri, true) . ";\n" .
        $action . "\n"
    );

    $port = 18080 + random_int(0, 4000);
    $server = comet_test_start_php_server('127.0.0.1', $port, $routerPath);

    try {
        usleep(300000);
        $response = (string) file_get_contents('http://127.0.0.1:' . $port . $uri, false, stream_context_create([
            'http' => [
                'method' => $method,
                'ignore_errors' => true,
                'header' => "Content-Type: application/json\r\nX-CSRF-Token: test-token\r\n",
                'content' => json_encode($body),
            ],
        ]));
        preg_match('#^HTTP/\S+\s+(\d{3})#', $http_response_header[0] ?? '', $match);

        return [(int) ($match[1] ?? 0), json_decode($response, true)];
    } finally {
        comet_test_stop_process($server);
    }
}

const COMET_WRITER_SEED = <<<'PHP'
$roles->create(['label' => 'Writer', 'permissions' => [
    ['effect' => 'allow', 'actions' => ['content.read', 'content.create', 'content.update', 'content.delete'], 'resources' => ['content:*']],
    ['effect' => 'deny', 'actions' => ['content.update', 'content.delete'], 'resources' => ['content:posts:locked']],
]]);
$users->create('wes', 'secret-password', 'writer');
(new \CometCMS\Content\ContentTypeRepository())->save(['name' => 'posts', 'fields' => ['body' => ['type' => 'textarea']]]);
$content = \CometCMS\Content\ContentRepository::make();
$content->save('posts', ['title' => 'Locked', 'slug' => 'locked'], ['id' => 'admin']);
$content->save('posts', ['title' => 'Open', 'slug' => 'open'], ['id' => 'admin']);
PHP;

test('bulk edits enforce publish permission and per-entry denies', function (): void {
    $action = '(new \CometCMS\Controllers\Admin\ContentController(new \CometCMS\Core\Http()))->bulkUpdate("posts");';

    [$status, $json] = comet_admin_request('wes', COMET_WRITER_SEED, 'PATCH', '/admin/api/content/posts/bulk', $action, [
        'ids' => ['locked', 'open'],
        'data' => ['status' => 'published'],
    ]);
    assert_same(200, $status);
    assert_same(0, $json['data']['updated'] ?? null, 'A writer without content.publish must not publish in bulk.');

    [, $json] = comet_admin_request('wes', COMET_WRITER_SEED, 'PATCH', '/admin/api/content/posts/bulk', $action, [
        'ids' => ['locked', 'open'],
        'data' => ['body' => 'Edited'],
    ]);
    assert_same(1, $json['data']['updated'] ?? null);
    assert_same(['locked'], array_keys($json['data']['errors'] ?? []));
});

test('bulk deletes respect per-entry denies', function (): void {
    [$status, $json] = comet_admin_request('wes', COMET_WRITER_SEED, 'DELETE', '/admin/api/content/posts/bulk',
        '(new \CometCMS\Controllers\Admin\ContentController(new \CometCMS\Core\Http()))->bulkDelete("posts");',
        ['ids' => ['locked', 'open', 'missing']],
    );

    assert_same(200, $status);
    assert_same(1, $json['data']['deleted'] ?? null);
    assert_same(['locked', 'missing'], array_keys($json['data']['errors'] ?? []));
});

test('users cannot assign roles more powerful than their own', function (): void {
    $seed = <<<'PHP'
$roles->create(['label' => 'Manager', 'permissions' => [
    ['effect' => 'allow', 'actions' => ['users.read', 'users.update', 'users.create'], 'resources' => ['*']],
]]);
$users->create('mia', 'secret-password', 'manager');
PHP;
    $update = '(new \CometCMS\Controllers\Admin\UsersController(new \CometCMS\Core\Http()))->update("mia");';
    $store = '(new \CometCMS\Controllers\Admin\UsersController(new \CometCMS\Core\Http()))->store();';

    [$status, $json] = comet_admin_request('mia', $seed, 'PUT', '/admin/api/users/mia', $update, ['role' => 'admin']);
    assert_same(403, $status);
    assert_same('forbidden', $json['error']['code'] ?? null);

    [$status] = comet_admin_request('mia', $seed, 'POST', '/admin/api/users', $store, ['username' => 'eve', 'password' => 'secret-password', 'role' => 'admin']);
    assert_same(403, $status);

    // The sole admin cannot lock everyone out by stepping down.
    [$status, $json] = comet_admin_request('admin', $seed, 'PUT', '/admin/api/users/admin', '(new \CometCMS\Controllers\Admin\UsersController(new \CometCMS\Core\Http()))->update("admin");', ['role' => 'viewer']);
    assert_same(422, $status);
    assert_true(str_contains((string) ($json['error']['message'] ?? ''), 'Admin role'));
});

test('access tokens cannot exceed the permissions of their creator', function (): void {
    $seed = <<<'PHP'
$roles->create(['label' => 'Integrator', 'permissions' => [
    ['effect' => 'allow', 'actions' => ['tokens.create'], 'resources' => ['*']],
    ['effect' => 'allow', 'actions' => ['content.read'], 'resources' => ['content:*']],
]]);
$users->create('ian', 'secret-password', 'integrator');
PHP;
    $action = '(new \CometCMS\Controllers\Admin\TokensController(new \CometCMS\Core\Http()))->store();';

    [$status] = comet_admin_request('ian', $seed, 'POST', '/admin/api/tokens', $action, [
        'name' => 'Too much',
        'permissions' => [['effect' => 'allow', 'actions' => ['content.delete'], 'resources' => ['content:*']]],
    ]);
    assert_same(403, $status);

    [$status] = comet_admin_request('ian', $seed, 'POST', '/admin/api/tokens', $action, [
        'name' => 'Read only',
        'permissions' => [['effect' => 'allow', 'actions' => ['content.read'], 'resources' => ['content:posts:*']]],
    ]);
    assert_same(201, $status);
});

const COMET_CURATOR_SEED = <<<'PHP'
$roles->create(['label' => 'Curator', 'permissions' => [
    ['effect' => 'allow', 'actions' => ['media.read', 'media.update', 'media.delete'], 'resources' => ['media:category:hero']],
]]);
$users->create('hal', 'secret-password', 'curator');
$media = new \CometCMS\Media\MediaRepository();
$media->addCategory('hero');
file_put_contents($media->directory() . '/hero.txt', 'hero');
file_put_contents($media->directory() . '/other.txt', 'other');
$media->setUploadedCategory('hero.txt', 'hero');
PHP;

test('category-scoped media roles can list and change files in their category only', function (): void {
    $controller = '(new \CometCMS\Controllers\Admin\MediaController(new \CometCMS\Core\Http()))';

    [$status, $json] = comet_admin_request('hal', COMET_CURATOR_SEED, 'GET', '/admin/api/media', $controller . '->index();');
    assert_same(200, $status);
    assert_same(['hero.txt'], array_column($json['data'] ?? [], 'name'));
    assert_same(1, $json['meta']['stats']['total'] ?? null);

    [$status] = comet_admin_request('hal', COMET_CURATOR_SEED, 'PUT', '/admin/api/media/hero.txt/meta', $controller . '->updateMeta("hero.txt");', ['alt' => 'Hero']);
    assert_same(200, $status);

    [$status] = comet_admin_request('hal', COMET_CURATOR_SEED, 'PUT', '/admin/api/media/other.txt/meta', $controller . '->updateMeta("other.txt");', ['alt' => 'Other']);
    assert_same(403, $status);

    [$status, $json] = comet_admin_request('hal', COMET_CURATOR_SEED, 'POST', '/admin/api/media/bulk-delete', $controller . '->bulkDelete();', ['files' => ['hero.txt', 'other.txt']]);
    assert_same(403, $status);
    assert_same(['other.txt'], $json['error']['files'] ?? null);

    [$status] = comet_admin_request('hal', COMET_CURATOR_SEED, 'PUT', '/admin/api/media/hero.txt/category', $controller . '->categoryUpdate("hero.txt");', ['category' => '']);
    assert_same(200, $status);
});

test('signed-in users learn the default workspace, also in sub-directory installs', function (): void {
    // A browser remembering a workspace that does not exist must still reach /me,
    // and installs below /cms/ must match the same workspace-exempt routes.
    $seed = <<<'PHP'
(new \CometCMS\Workspaces\WorkspaceRepository())->save(['slug' => 'acme', 'label' => 'Acme']);
(new \CometCMS\Workspaces\WorkspaceRepository())->setDefault('acme');
$_SERVER['SCRIPT_NAME'] = '/cms/index.php';
$_SERVER['HTTP_X_COMET_WORKSPACE'] = 'missing';
PHP;

    [$status, $json] = comet_admin_request('admin', $seed, 'GET', '/cms/admin/api/me',
        '(new \CometCMS\Controllers\Admin\AuthController(new \CometCMS\Core\Http()))->me();');

    assert_same(200, $status);
    assert_same('acme', $json['data']['default_workspace'] ?? null);
});

test('signed-in admin users can preview private media without a token', function (): void {
    $seed = <<<'PHP'
$media = new \CometCMS\Media\MediaRepository();
file_put_contents($media->directory() . '/secret.txt', 'secret');
$media->updateVisibility('secret.txt', 'private');
$users->create('nobody', 'secret-password', 'viewer');
$roles->create(['label' => 'No media', 'permissions' => [['effect' => 'allow', 'actions' => ['dashboard.read'], 'resources' => ['*']]]]);
$users->update('nobody', ['role' => 'no-media']);
PHP;
    $action = '$api = new \CometCMS\Controllers\ApiController(new \CometCMS\Core\Http()); $api->useWorkspace("default", true); $api->mediaShow("secret.txt");';

    [$status] = comet_admin_request('admin', $seed, 'GET', '/media/default/secret.txt', $action);
    assert_same(200, $status);

    [$status] = comet_admin_request('nobody', $seed, 'GET', '/media/default/secret.txt', $action);
    assert_same(401, $status);
});
