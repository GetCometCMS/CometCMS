<?php

declare(strict_types=1);

/** Exercise workspace creation and inspect both workspaces before subprocess cleanup. */
function comet_workspace_creation_request(array $body, string $extraSeed = ''): array
{
    $routerPath = COMET_STORAGE . '/workspace-creation-router.php';
    $snapshot = <<<'PHP'
register_shutdown_function(static function (): void {
    $active = \CometCMS\Workspaces\WorkspaceContext::active()->slug();
    $registry = new \CometCMS\Workspaces\WorkspaceRepository();
    $created = $registry->find('new-site', true);
    $source = new \CometCMS\Workspaces\WorkspaceContext('default');
    $sourceTypes = (new \CometCMS\Content\ContentTypeRepository($source))->all();
    $state = ['active_workspace' => $active, 'workspace' => $created, 'source_types' => $sourceTypes,
        'source_homepage' => (new \CometCMS\Site\SiteRepository($source))->settings()['homepage']];
    if ($created !== null) {
        $workspace = new \CometCMS\Workspaces\WorkspaceContext('new-site');
        $state['types'] = (new \CometCMS\Content\ContentTypeRepository($workspace))->all();
        $content = \CometCMS\Content\ContentRepository::make($workspace);
        $state['pages'] = $content->all('pages');
        $state['events'] = $content->all('events');
        $site = new \CometCMS\Site\SiteRepository($workspace);
        $state['settings'] = $site->settings();
        $state['menus'] = $site->menus();
        $response = (new \CometCMS\Site\SiteService(\CometCMS\Site\SiteContext::make($site)))->respond('/', []);
        $state['homepage_status'] = $response['status'] ?? null;
    }
    echo "\n--state--\n" . json_encode($state);
});
PHP;
    $seed = <<<'PHP'
session_start();
(new \CometCMS\Auth\UserRepository())->create('admin', 'secret-password', 'admin');
$_SESSION['user_id'] = 'admin';
$_SESSION['cometcms_csrf'] = 'test-token';
(new \CometCMS\Content\ContentTypeRepository())->save(['name' => 'original']);
(new \CometCMS\Site\SiteRepository())->saveSettings(['homepage' => ['type' => 'original']]);
PHP;
    file_put_contents($routerPath, "<?php\n" . $snapshot . "\n"
        . 'require ' . var_export(__DIR__ . '/bootstrap.php', true) . ";\n" . $seed . "\n" . $extraSeed . "\n"
        . '(new \CometCMS\Controllers\Admin\WorkspacesController(new \CometCMS\Core\Http()))->store();');
    $port = 18080 + random_int(0, 4000);
    $server = comet_test_start_php_server('127.0.0.1', $port, $routerPath);
    try {
        usleep(300000);
        $response = (string) file_get_contents('http://127.0.0.1:' . $port . '/admin/api/workspaces', false, stream_context_create(['http' => [
            'method' => 'POST', 'ignore_errors' => true,
            'header' => "Content-Type: application/json\r\nX-CSRF-Token: test-token\r\n",
            'content' => json_encode(array_replace(['label' => 'New site', 'slug' => 'new-site'], $body)),
        ]]));
        $responseHeaders = function_exists('http_get_last_response_headers')
            ? (http_get_last_response_headers() ?? [])
            : (${'http_response_header'} ?? []);
        preg_match('#^HTTP/\S+\s+(\d{3})#', $responseHeaders[0] ?? '', $match);
        [$json, $state] = explode("\n--state--\n", $response, 2);
        return [(int) ($match[1] ?? 0), json_decode($json, true), json_decode($state, true)];
    } finally {
        comet_test_stop_process($server);
    }
}

test('new workspace website starter creates pages and a working homepage without events', function (): void {
    [$status, $json, $state] = comet_workspace_creation_request(['starter_template' => 'website']);
    assert_same(201, $status);
    assert_same('new-site', $json['data']['slug']);
    assert_same(['pages'], array_column($state['types'], 'name'));
    assert_same([false], array_column($state['types'], 'api_enabled'));
    assert_same(['title', 'slug', 'intro', 'body'], array_keys($state['types'][0]['fields']));
    $slugs = array_column($state['pages'], 'slug');
    sort($slugs);
    assert_same(['about', 'hello-world', 'landing-page'], $slugs);
    assert_same([], $state['events']);
    assert_true($state['settings']['enabled']);
    assert_same('pages', $state['settings']['homepage']['type']);
    assert_true($state['settings']['routes']['pages']['enabled']);
    assert_false(isset($state['settings']['routes']['events']));
    assert_same(['home', 'about', 'hello-world'], array_column($state['menus'][0]['items'], 'id'));
    assert_same(200, $state['homepage_status']);
    assert_same('default', $state['active_workspace']);
    assert_same(['original'], array_column($state['source_types'], 'name'));
    assert_same('original', $state['source_homepage']['type']);
});

test('new workspace creation supports an empty choice and preserves empty API defaults', function (): void {
    foreach ([[], ['starter_template' => 'empty']] as $body) {
        [$status, , $state] = comet_workspace_creation_request($body);
        assert_same(201, $status);
        assert_same([], $state['types']);
        assert_same([], $state['pages']);
        assert_same([], $state['events']);
        assert_false($state['settings']['enabled']);
        assert_same(['type' => '', 'entry' => ''], $state['settings']['homepage']);
    }
});

test('new workspace creation rejects malformed starters before creating a workspace', function (): void {
    foreach (['events', ['website'], true] as $choice) {
        [$status, $json, $state] = comet_workspace_creation_request(['starter_template' => $choice]);
        assert_same(422, $status);
        assert_same('validation_failed', $json['error']['code']);
        assert_null($state['workspace']);
    }
});

test('new workspace creation cannot seed over an existing workspace', function (): void {
    $seed = <<<'PHP'
(new \CometCMS\Workspaces\WorkspaceRepository())->save(['slug' => 'new-site', 'label' => 'Existing site']);
(new \CometCMS\Content\ContentTypeRepository(new \CometCMS\Workspaces\WorkspaceContext('new-site')))->save(['name' => 'custom']);
PHP;
    [$status, $json, $state] = comet_workspace_creation_request(['starter_template' => 'website'], $seed);
    assert_same(422, $status);
    assert_same('validation_failed', $json['error']['code']);
    assert_same('Existing site', $state['workspace']['label']);
    assert_same(['custom'], array_column($state['types'], 'name'));
    assert_same([], $state['pages']);
});
