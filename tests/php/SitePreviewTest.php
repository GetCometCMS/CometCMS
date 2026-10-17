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

function comet_site_preview_test_request(int $port, string $path, string $cookie = ''): array
{
    $body = file_get_contents('http://127.0.0.1:' . $port . $path, false, stream_context_create([
        'http' => [
            'ignore_errors' => true,
            'follow_location' => 0,
            'header' => $cookie !== '' ? 'Cookie: ' . $cookie . "\r\n" : '',
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
