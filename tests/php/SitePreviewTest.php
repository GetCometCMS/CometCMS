<?php

declare(strict_types=1);

/** Exercise the real entry point and session bootstrap using an isolated install. */
function comet_site_preview_test_install(): string
{
    $root = COMET_STORAGE . '/preview-install';
    mkdir($root, 0775, true);

    foreach (['app', 'config'] as $directory) {
        $source = COMET_ROOT . '/' . $directory;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        mkdir($root . '/' . $directory, 0775, true);
        foreach ($files as $file) {
            $target = $root . '/' . $directory . substr($file->getPathname(), strlen($source));
            if ($file->isDir()) {
                mkdir($target, 0775, true);
            } else {
                copy($file->getPathname(), $target);
            }
        }
    }

    copy(COMET_ROOT . '/index.php', $root . '/index.php');
    // Simulate the SCRIPT_NAME supplied by Apache in root and subdirectory installs.
    file_put_contents($root . '/router.php', <<<'PHP'
<?php
$_SERVER['SCRIPT_NAME'] = str_starts_with($_SERVER['REQUEST_URI'], '/cms/') ? '/cms/index.php' : '/index.php';
require __DIR__ . '/index.php';
PHP);

    return $root;
}

function comet_site_preview_test_request(int $port, string $path, string $cookie = '', string $method = 'GET', array $body = [], string $csrf = ''): array
{
    $body = file_get_contents('http://127.0.0.1:' . $port . $path, false, stream_context_create([
        'http' => [
            'ignore_errors' => true,
            'follow_location' => 0,
            'method' => $method,
            'header' => ($cookie !== '' ? 'Cookie: ' . $cookie . "\r\n" : '') . "Content-Type: application/json\r\nX-CSRF-Token: " . $csrf . "\r\n",
            'content' => $method === 'GET' ? '' : json_encode($body),
        ],
    ]));
    $headers = [];
    foreach ($http_response_header ?? [] as $header) {
        if (str_contains($header, ':')) {
            [$name, $value] = explode(':', $header, 2);
            $headers[strtolower($name)] = trim($value);
        }
    }
    preg_match('#^HTTP/\S+\s+(\d{3})#', $http_response_header[0] ?? '', $match);

    return [(int) ($match[1] ?? 0), (string) $body, $headers];
}

test('website history routes protect revisions and restore saved layouts regions and themes', function (): void {
    $root = comet_site_preview_test_install();
    $sessionPrefix = 'history-' . bin2hex(random_bytes(6));
    comet_test_run_php(['-r', 'require ' . var_export($root . '/app/bootstrap.php', true) . ';' . <<<'PHP'
$users = new \CometCMS\Auth\UserRepository();
$users->create('history-admin', 'secret-password', 'admin');
$users->create('history-viewer', 'secret-password', 'viewer');
(new \CometCMS\Auth\RoleRepository())->create(['label' => 'Designer', 'permissions' => [
    ['effect' => 'allow', 'actions' => ['site.manage'], 'resources' => ['site:*']],
]]);
$users->create('history-designer', 'secret-password', 'designer');
(new \CometCMS\Content\ContentTypeRepository())->save(['name' => 'about', 'singleton' => true]);
PHP .
        'foreach (["admin", "viewer", "designer"] as $role) { session_id(' . var_export($sessionPrefix, true) . ' . "-" . $role); session_start(); $_SESSION["user_id"] = "history-" . $role; $_SESSION["cometcms_csrf"] = "history-token"; session_write_close(); }'
    ]);
    $port = 22080 + random_int(0, 2000);
    $server = comet_test_start_php_server('127.0.0.1', $port, $root . '/router.php');
    $request = static function (string $path, string $method = 'GET', array $body = [], string $role = 'admin', string $csrf = 'history-token') use ($port, $sessionPrefix): array {
        [$status, $json] = comet_site_preview_test_request($port, '/cms/admin/api/site' . $path, $role !== '' ? 'cometcms_admin=' . $sessionPrefix . '-' . $role : '', $method, $body, $csrf);
        return [$status, json_decode($json, true)];
    };
    try {
        usleep(300000);
        assert_same(401, $request('/theme/revisions', 'GET', [], '')[0]);
        assert_same(403, $request('/theme/revisions', 'GET', [], 'viewer')[0]);
        $nodes = [\CometCMS\Site\LayoutSchema::node('section', [], ['background' => 'accent'])];
        assert_same(200, $request('/layouts/about/detail', 'PUT', ['nodes' => $nodes])[0]);
        assert_same(200, $request('/layouts/about/detail', 'PUT', ['nodes' => []])[0]);
        [$status, $history] = $request('/layouts/about/detail/revisions');
        assert_same(200, $status);
        assert_same(2, count($history['data']));
        assert_same('history-admin', $history['data'][0]['created_by']);
        $id = $history['data'][0]['id'];
        $restorePath = '/layouts/about/detail/revisions/' . $id . '/restore';
        assert_same(419, $request($restorePath, 'POST', [], 'admin', 'wrong')[0]);
        assert_same(403, $request($restorePath, 'POST', [], 'viewer')[0]);
        [$status, $restored] = $request($restorePath, 'POST');
        assert_same(200, $status);
        assert_same('accent', $restored['data']['nodes'][0]['style']['background']);
        assert_true($restored['data']['customized']);
        assert_same(404, $request('/layouts/about/detail/revisions/missing/restore', 'POST')[0]);

        $request('/regions/header', 'PUT', ['nodes' => $nodes]);
        $request('/regions/header', 'DELETE');
        [, $history] = $request('/regions/header/revisions');
        $id = $history['data'][0]['id'];
        [$status, $restored] = $request('/regions/header/revisions/' . $id . '/restore', 'POST');
        assert_same(200, $status);
        assert_true($restored['data']['customized']);
        assert_same(404, $request('/regions/footer/revisions/' . $id . '/restore', 'POST')[0]);

        $request('/theme', 'PUT', ['base_size' => 14, 'custom_css' => '.old {color:red}']);
        $request('/theme', 'PUT', ['base_size' => 20, 'custom_css' => '.new {color:blue}']);
        [, $history] = $request('/theme/revisions');
        $id = $history['data'][0]['id'];
        [$status, $restored] = $request('/theme/revisions/' . $id . '/restore', 'POST', [], 'designer');
        assert_same(200, $status);
        assert_same(14, $restored['data']['base_size']);
        assert_same('.new {color:blue}', $restored['data']['custom_css'], 'Designer restores cannot change administrator-only CSS.');
        [$status, $restored] = $request('/theme/revisions/' . $id . '/restore', 'POST');
        assert_same(200, $status);
        assert_same('.old {color:red}', $restored['data']['custom_css']);
    } finally {
        comet_test_stop_process($server);
    }
});

test('entry preview redirects preserve workspace locale and authenticated draft rendering', function (): void {
    $root = comet_site_preview_test_install();
    $sessionId = 'preview-' . bin2hex(random_bytes(8));
    comet_test_run_php(['-r', 'require ' . var_export($root . '/app/bootstrap.php', true) . ';' . <<<'PHP'
(new \CometCMS\Auth\UserRepository())->create('preview-editor', 'secret-password', 'editor');
$registry = new \CometCMS\Workspaces\WorkspaceRepository();
$registry->save(['slug' => 'preview-site', 'label' => 'Preview site']);
foreach (['default', 'preview-site'] as $slug) {
    $workspace = new \CometCMS\Workspaces\WorkspaceContext($slug);
    (new \CometCMS\Content\ContentTypeRepository($workspace))->save([
        'name' => 'startseite', 'singleton' => true, 'locales' => ['en', 'de'], 'default_locale' => 'en',
    ]);
    $content = \CometCMS\Content\ContentRepository::make($workspace);
    $entry = $content->save('startseite', ['title' => $slug . ' English draft', 'locale' => 'en'], ['id' => 'preview-editor']);
    $content->save('startseite', ['title' => $slug . ' Deutscher Entwurf', 'locale' => 'de'], ['id' => 'preview-editor'], $entry['id']);
    (new \CometCMS\Site\SiteRepository($workspace))->saveSettings([
        'enabled' => true, 'locales' => ['en', 'de'], 'default_locale' => 'en',
        'routes' => ['startseite' => ['enabled' => true]],
    ]);
}
PHP .
        'session_id(' . var_export($sessionId, true) . '); session_start(); $_SESSION["user_id"] = "preview-editor"; session_write_close();'
    ]);
    $cookie = 'cometcms_admin=' . $sessionId;
    $port = 22080 + random_int(0, 2000);
    $server = comet_test_start_php_server('127.0.0.1', $port, $root . '/router.php');

    try {
        usleep(300000);
        foreach (['', '/cms'] as $base) {
            $query = '?workspace=preview-site&locale=de';
            [$status, , $headers] = comet_site_preview_test_request($port, $base . '/site-preview/startseite/startseite' . $query, $cookie);
            assert_same(302, $status);
            assert_same($base . '/admin/site-preview/startseite/startseite' . $query, $headers['location'] ?? null);

            [$status, $html, $headers] = comet_site_preview_test_request($port, $headers['location'], $cookie);
            assert_same(200, $status);
            assert_true(str_contains($html, 'preview-site Deutscher Entwurf'));
            assert_false(str_contains($html, 'default Deutscher Entwurf'));
            assert_true(str_contains($html, '<html lang="de">'));
            assert_same('private, no-store', $headers['cache-control'] ?? null);
            assert_same('noindex, nofollow', $headers['x-robots-tag'] ?? null);

            [$status] = comet_site_preview_test_request($port, $base . '/admin/site-preview/startseite/startseite' . $query);
            assert_same(401, $status, 'An editor session is required to preview a draft.');
        }

        [$status] = comet_site_preview_test_request($port, '/startseite');
        assert_same(404, $status, 'The draft stays hidden on the public website.');
    } finally {
        comet_test_stop_process($server);
    }
});
