<?php

declare(strict_types=1);

use CometCMS\Content\ContentTypeRepository;

test('content type repository saves normalized schemas', function (): void {
    $repository = new ContentTypeRepository();

    $repository->save([
        'name' => 'Blog Posts',
        'label' => 'Blog Posts',
        'icon' => 'MDI:Post',
        'locales' => ['EN_us', 'de DE', ''],
        'default_locale' => 'de-de',
        'fields' => [
            'body' => ['type' => 'markdown'],
        ],
    ]);

    $schema = $repository->find('blog-posts');

    assert_same('blog-posts', $schema['name']);
    assert_same('mdi:post', $schema['icon']);
    assert_same(['en-us', 'de-de'], $schema['locales']);
    assert_same('de-de', $schema['default_locale']);
    assert_same('public', $schema['visibility']);
    assert_same(['body', 'title', 'slug'], array_keys($schema['fields']));
});

test('content type repository normalizes API visibility and keeps public as the default', function (): void {
    $repository = new ContentTypeRepository();
    $repository->save(['name' => 'public-pages']);
    $repository->save(['name' => 'members', 'visibility' => 'private']);
    $repository->save(['name' => 'invalid', 'visibility' => 'secret']);

    assert_same('public', $repository->find('public-pages')['visibility']);
    assert_same('private', $repository->find('members')['visibility']);
    assert_same('public', $repository->find('invalid')['visibility']);
});

test('content type repository reorders saved schemas', function (): void {
    $repository = new ContentTypeRepository();

    $repository->save(['name' => 'pages']);
    $repository->save(['name' => 'posts']);
    $repository->reorder(['posts', 'pages']);

    assert_same(['posts', 'pages'], array_column($repository->all(), 'name'));
});

test('admin content type create rejects normalized name conflicts', function (): void {
    $routerPath = COMET_STORAGE . '/content-type-conflict-router.php';
    file_put_contents(
        $routerPath,
        "<?php\n" .
        "require " . var_export(__DIR__ . '/bootstrap.php', true) . ";\n" .
        "session_start();\n" .
        "\$_SESSION['user_id'] = 'admin';\n" .
        "\$_SESSION['cometcms_csrf'] = 'test-token';\n" .
        "\$users = new \\CometCMS\\Auth\\UserRepository();\n" .
        "if (!\$users->hasUsers()) \$users->create('admin', 'secret-password', 'admin');\n" .
        "(new \\CometCMS\\Content\\ContentTypeRepository())->save(['name' => 'startpage']);\n" .
        "\$_SERVER['REQUEST_URI'] = '/admin/api/content-types';\n" .
        "(new \\CometCMS\\Controllers\\Admin\\ContentTypesController(new \\CometCMS\\Core\\Http()))->store();\n"
    );

    $port = 18080 + random_int(0, 2000);
    $server = comet_test_start_php_server('127.0.0.1', $port, $routerPath);

    try {
        usleep(300000);

        $response = (string) file_get_contents(
            'http://127.0.0.1:' . $port,
            false,
            stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'ignore_errors' => true,
                    'header' => "Content-Type: application/json\r\nX-CSRF-Token: test-token\r\n",
                    'content' => '{"name":"Startpage"}',
                ],
            ])
        );

        assert_true(str_contains($http_response_header[0] ?? '', '422'));
        assert_true(str_contains($response, '"code":"validation_failed"'));
        assert_true(str_contains($response, 'already exists'));
    } finally {
        comet_test_stop_process($server);
    }
});

test('new content types require explicit API opt-in and default to no submissions', function (): void {
    $types = new ContentTypeRepository();
    $types->save(['name' => 'pages', 'visibility' => 'public']);
    $type = $types->find('pages');
    assert_false($type['api_enabled']);
    assert_false($type['allow_submissions']);

    $types->save([...$type, 'api_enabled' => true, 'visibility' => 'private']);
    assert_true($types->find('pages')['api_enabled']);
    assert_same('public', $types->find('pages')['visibility'], 'Enabling the API makes it public.');

    $types->save([...$types->find('pages'), 'api_enabled' => false]);
    assert_false($types->find('pages')['api_enabled']);
});

test('legacy API visibility is retained until explicitly reconfigured', function (): void {
    $store = new \CometCMS\Storage\JsonStore(comet_test_workspace_path() . '/content-types');
    $store->write(['name' => 'legacy', 'visibility' => 'public'], 'legacy');
    $types = new ContentTypeRepository();
    assert_true($types->find('legacy')['api_enabled']);
    $types->save([...$types->find('legacy'), 'api_enabled' => false]);
    assert_false($types->find('legacy')['api_enabled']);
});

test('submission-enabled schemas reject unusable fields before changing storage', function (): void {
    $types = new ContentTypeRepository();
    foreach ([[], ['file' => ['type' => 'media', 'required' => true]], ['email' => ['type' => 'text'], 'file' => ['type' => 'media', 'required' => true]]] as $fields) {
        assert_throws(\CometCMS\Core\ValidationException::class, static fn() => $types->save(['name' => 'applications', 'allow_submissions' => true, 'fields' => $fields]));
        assert_false($types->exists('applications'));
    }
    $types->save(['name' => 'about', 'singleton' => true, 'allow_submissions' => true]);
    assert_false($types->find('about')['allow_submissions'], 'Single pages cannot receive submissions.');
});
