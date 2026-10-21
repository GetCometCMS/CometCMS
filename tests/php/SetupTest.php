<?php

declare(strict_types=1);

use CometCMS\Content\ContentRepository;
use CometCMS\Content\ContentTypeRepository;
use CometCMS\Core\ValidationException;
use CometCMS\Setup\WebsiteStarter;
use CometCMS\Site\SiteContext;
use CometCMS\Site\SiteRepository;
use CometCMS\Site\SiteService;
use CometCMS\Workspaces\WorkspaceContext;
use CometCMS\Workspaces\WorkspaceRepository;

/** Exercise setup over HTTP and inspect its storage before subprocess cleanup. */
function comet_setup_request(array $body): array
{
    $routerPath = COMET_STORAGE . '/setup-router.php';
    $snapshot = <<<'PHP'
register_shutdown_function(static function (): void {
    $workspace = new \CometCMS\Workspaces\WorkspaceContext('my-website');
    $types = new \CometCMS\Content\ContentTypeRepository($workspace);
    $content = \CometCMS\Content\ContentRepository::make($workspace);
    echo "\n--state--\n" . json_encode([
        'users' => count((new \CometCMS\Auth\UserRepository())->all()),
        'types' => array_column($types->all(), 'name'),
        'api_enabled' => array_column($types->all(), 'api_enabled'),
        'pages' => $content->all('pages'),
        'events' => $content->all('events'),
        'settings' => (new \CometCMS\Site\SiteRepository($workspace))->settings(),
    ]);
});
PHP;
    file_put_contents($routerPath, "<?php\n" . $snapshot . "\n"
        . 'require ' . var_export(__DIR__ . '/bootstrap.php', true) . ";\n"
        . "session_start();\n"
        . '(new \CometCMS\Controllers\Admin\AuthController(new \CometCMS\Core\Http()))->setup();');

    $port = 18080 + random_int(0, 4000);
    $server = comet_test_start_php_server('127.0.0.1', $port, $routerPath);
    try {
        usleep(300000);
        $response = (string) file_get_contents('http://127.0.0.1:' . $port . '/admin/api/setup', false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'ignore_errors' => true,
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode(array_replace([
                    'username' => 'admin',
                    'password' => 'secret-password',
                    'workspace' => 'My Website',
                    'workspace_slug' => 'my-website',
                ], $body)),
            ],
        ]));
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

test('setup supports an explicit empty workspace', function (): void {
    foreach ([['starter_template' => 'empty']] as $body) {
        [$status, $json, $state] = comet_setup_request($body);

        assert_same(201, $status);
        assert_same('my-website', $json['meta']['workspace']);
        assert_same(1, $state['users']);
        assert_same([], $state['types']);
        assert_same([], $state['pages']);
        assert_same([], $state['events']);
        assert_false($state['settings']['enabled']);
        assert_same(['type' => '', 'entry' => ''], $state['settings']['homepage']);
    }
});

test('setup defaults to the website starter with a landing page entry pages and events', function (): void {
    [$status, $json, $state] = comet_setup_request([]);

    assert_same(201, $status);
    assert_same('my-website', $json['meta']['workspace']);
    assert_same(['pages', 'events'], $state['types']);
    assert_same([false, false], $state['api_enabled']);
    $slugs = array_column($state['pages'], 'slug');
    sort($slugs);
    assert_same(['about', 'hello-world', 'landing-page'], $slugs);
    assert_same(['published', 'published', 'published'], array_column($state['pages'], 'status'));
    assert_same(['community-meetup', 'creative-workshop'], array_column($state['events'], 'slug'));
    assert_same(['published', 'published'], array_column($state['events'], 'status'));
    assert_true($state['settings']['enabled']);
    assert_same('pages', $state['settings']['homepage']['type']);
    $home = array_values(array_filter($state['pages'], static fn(array $page): bool => $page['uid'] === $state['settings']['homepage']['entry']));
    assert_same('landing-page', $home[0]['slug'] ?? null);
    assert_true($state['settings']['routes']['pages']['enabled']);
    assert_true($state['settings']['routes']['events']['enabled']);
});

test('setup rejects unknown and malformed starting points before creating an account', function (): void {
    foreach (['unknown', ['website'], true] as $choice) {
        [$status, $json, $state] = comet_setup_request(['starter_template' => $choice]);

        assert_same(422, $status);
        assert_same('validation_failed', $json['error']['code']);
        assert_same(0, $state['users']);
        assert_same([], $state['types']);
    }
});

test('website starter renders all pages with working navigation and remains editable', function (): void {
    (new WorkspaceRepository())->save(['slug' => 'my-website', 'label' => 'My Website']);
    $workspace = new WorkspaceContext('my-website');
    $user = ['id' => 'admin', 'role' => 'admin'];
    (new WebsiteStarter($workspace))->create($user);

    $types = new ContentTypeRepository($workspace);
    assert_same(['pages', 'events'], array_column($types->all(), 'name'));
    assert_same(['title', 'slug', 'intro', 'body'], array_keys($types->find('pages')['fields']));
    assert_same([], (new ContentTypeRepository(new WorkspaceContext('default')))->all());

    $site = new SiteRepository($workspace);
    $service = new SiteService(SiteContext::make($site));
    foreach ([
        '/' => 'Make it yours',
        '/about' => 'Our story',
        '/hello-world' => 'Hello, world!',
        '/events' => 'Events',
        '/events/community-meetup' => 'Come say hello',
        '/events/creative-workshop' => 'An afternoon of making',
    ] as $path => $text) {
        $response = $service->respond($path, []);
        assert_same(200, $response['status'], $path . ' should render.');
        assert_true(str_contains($response['body'], $text), $path . ' should show its content.');
        assert_true(str_contains($response['body'], 'href="/about"'));
        assert_true(str_contains($response['body'], 'href="/hello-world"'));
        assert_true(str_contains($response['body'], 'href="/events"'));
    }

    $content = ContentRepository::make($workspace);
    assert_same(404, $service->respond('/pages', [])['status']);
    $content->save('pages', ['title' => 'Another page'], $user);
    assert_same(4, count($content->all('pages')));

    $about = $content->findByIdentifier('pages', 'about');
    $content->save('pages', ['title' => 'About us'], $user, $about['id']);
    assert_same('About us', $content->findByIdentifier('pages', 'about')['title']);

    $site->saveSettings(['homepage' => [], 'routes' => []]);
    assert_false($site->settings()['enabled']);
});

test('starter events demonstrate typed fields with different values and editable content', function (): void {
    $workspace = new WorkspaceContext('my-website');
    $user = ['id' => 'admin', 'role' => 'admin'];
    (new WebsiteStarter($workspace))->create($user);

    $schema = (new ContentTypeRepository($workspace))->find('events');
    foreach ([
        'starts_at' => 'datetime',
        'location' => 'text',
        'event_type' => 'select',
        'capacity' => 'number',
        'registration_open' => 'boolean',
    ] as $name => $type) {
        assert_same($type, $schema['fields'][$name]['type']);
    }
    $content = ContentRepository::make($workspace);
    $meetup = $content->findByIdentifier('events', 'community-meetup');
    $workshop = $content->findByIdentifier('events', 'creative-workshop');
    assert_true(strtotime($meetup['starts_at']) > time());
    assert_true(strtotime($workshop['starts_at']) > strtotime($meetup['starts_at']));
    assert_same('meetup', $meetup['event_type']);
    assert_same('workshop', $workshop['event_type']);
    assert_same(50, $meetup['capacity']);
    assert_same(16, $workshop['capacity']);
    assert_true($meetup['registration_open']);
    assert_false($workshop['registration_open']);

    $service = new SiteService(SiteContext::make(new SiteRepository($workspace)));
    $response = $service->respond('/events/community-meetup', []);
    assert_true(str_contains($response['body'], 'Community Hub'));
    assert_true(str_contains($response['body'], 'Meetup'));
    assert_true(str_contains($response['body'], '50'));

    $updated = $content->save('events', ['capacity' => '75', 'registration_open' => false], $user, $meetup['id']);
    assert_same(75, $updated['capacity']);
    assert_false($updated['registration_open']);
    assert_throws(ValidationException::class, fn() => $content->save('events', ['event_type' => 'invalid'], $user, $meetup['id']));
});
