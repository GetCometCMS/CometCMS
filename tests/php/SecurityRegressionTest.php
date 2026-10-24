<?php

declare(strict_types=1);

use CometCMS\Content\ContentRepository;
use CometCMS\Content\ContentTypeRepository;
use CometCMS\Core\MimeDetector;
use CometCMS\Media\MediaRepository;
use CometCMS\Mcp\McpServer;

function comet_security_relation_seed(): void
{
    $types = new ContentTypeRepository();
    $types->save(['name' => 'members', 'visibility' => 'private', 'fields' => ['secret' => ['type' => 'text']]]);
    $types->save(['name' => 'posts', 'api_enabled' => true, 'fields' => [
        'member' => ['type' => 'relation', 'target' => 'members'],
        'members' => ['type' => 'relation', 'target' => 'members', 'multiple' => true],
        'blocks' => ['type' => 'repeater', 'subfields' => [['key' => 'member', 'type' => 'relation', 'target' => 'members']]],
    ]]);
    $content = ContentRepository::make();
    $member = $content->save('members', ['title' => 'Private member', 'secret' => 'never-leak-this', 'status' => 'published'], ['id' => 'admin']);
    $content->save('posts', ['title' => 'Post', 'slug' => 'post', 'status' => 'published', 'member' => $member['id'], 'members' => [$member['id']], 'blocks' => [['member' => $member['id']]]], ['id' => 'admin']);
}

test('security anonymous MCP cannot discover disabled schemas or entries and management remains available', function (): void {
    $types = new ContentTypeRepository();
    $types->save(['name' => 'hidden', 'api_enabled' => false]);
    $types->save(['name' => 'posts', 'api_enabled' => true, 'fields' => ['related' => ['type' => 'relation', 'target' => 'hidden']]]);
    $content = ContentRepository::make();
    $hidden = $content->save('hidden', ['title' => 'Not API exposed', 'status' => 'published'], ['id' => 'admin']);
    $content->save('posts', ['title' => 'Post', 'slug' => 'post', 'related' => $hidden['id'], 'status' => 'published'], ['id' => 'admin']);
    [, $result] = comet_test_mcp_tool(null, 'list_content_types');
    assert_false(in_array('hidden', array_column($result['data'], 'name'), true));
    foreach (['get_content_type', 'get_entry', 'list_entries'] as $tool) {
        [, $result] = comet_test_mcp_tool(null, $tool, ['collection' => 'hidden', 'identifier' => $hidden['id']]);
        assert_same(404, $result['error']['status']);
    }
    foreach (['get_entry', 'list_entries'] as $tool) {
        [, $result] = comet_test_mcp_tool(null, $tool, ['collection' => 'posts', 'identifier' => 'post', 'include' => 'related']);
        $entry = $tool === 'get_entry' ? $result['data'] : $result['data'][0];
        assert_null($entry['data']['related']);
    }
    $token = comet_test_mcp_token([['actions' => ['schema.read', 'content.read'], 'resources' => ['*']]]);
    [, $result] = comet_test_mcp_tool($token, 'get_entry', ['collection' => 'hidden', 'identifier' => $hidden['id']]);
    assert_same('Not API exposed', $result['data']['title']);
});

test('security MCP relation reads require the target entry grant including nested serialization', function (): void {
    comet_security_relation_seed();
    $content = ContentRepository::make();
    $member = $content->all('members')[0];
    $sourceGrant = ['actions' => ['content.read'], 'resources' => ['content:posts:*']];
    $deniedTokens = [
        comet_test_mcp_token([$sourceGrant]),
        comet_test_mcp_token([$sourceGrant, ['actions' => ['content.read'], 'resources' => ['workspace:other:content:members:*']]]),
        comet_test_mcp_token([$sourceGrant, ['actions' => ['content.read'], 'resources' => ['content:members:*'], 'conditions' => ['locales' => ['de']]]]),
        comet_test_mcp_token([$sourceGrant, ['actions' => ['content.read'], 'resources' => ['content:members:*']], ['effect' => 'deny', 'actions' => ['content.read'], 'resources' => ['content:members:' . $member['uid']]]]),
    ];
    foreach ($deniedTokens as $token) {
        foreach (['get_entry', 'list_entries'] as $tool) {
            [, $result] = comet_test_mcp_tool($token, $tool, ['collection' => 'posts', 'identifier' => 'post', 'include' => 'member,members']);
            $entry = $tool === 'get_entry' ? $result['data'] : $result['data'][0];
            assert_null($entry['data']['member']);
            assert_same([], $entry['data']['members']);
            assert_null($entry['data']['blocks'][0]['member']);
            assert_false(str_contains(json_encode($result), 'never-leak-this'));
        }
    }
    $token = comet_test_mcp_token([$sourceGrant, ['actions' => ['content.read'], 'resources' => ['workspace:default:content:members:' . $member['uid']]]]);
    foreach (['get_entry', 'list_entries'] as $tool) {
        [, $result] = comet_test_mcp_tool($token, $tool, ['collection' => 'posts', 'identifier' => 'post', 'include' => 'member,members']);
        $entry = $tool === 'get_entry' ? $result['data'] : $result['data'][0];
        assert_same('never-leak-this', $entry['data']['member']['data']['secret']);
        assert_same('never-leak-this', $entry['data']['members'][0]['data']['secret']);
        assert_same($member['uid'], $entry['data']['blocks'][0]['member']);
    }
    // Repeaters and arbitrarily nested values must use the same serializer guard.
    $server = new McpServer();
    $server->handle(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'], 'default', $deniedTokens[0]);
    $value = (new ReflectionMethod($server, 'publicValue'))->invoke($server, [['nested' => [$member]]], ['type' => 'repeater'], true);
    assert_false(str_contains(json_encode($value), 'never-leak-this'));
});

test('security REST relation reads agree with MCP exposure and target authorization', function (): void {
    foreach (['anonymous', 'posts', 'member'] as $mode) {
        foreach (['public', 'private', 'disabled'] as $target) {
            $output = comet_test_run_php(['-r', 'require ' . var_export(__DIR__ . '/bootstrap.php', true) . ';' . <<<'CODE'
$types = new \CometCMS\Content\ContentTypeRepository();
$types->save(['name' => 'members', 'api_enabled' => true, 'fields' => ['secret' => ['type' => 'text']]]);
$types->save(['name' => 'posts', 'api_enabled' => true, 'fields' => ['related' => ['type' => 'relation', 'target' => 'members']]]);
$content = \CometCMS\Content\ContentRepository::make();
$member = $content->save('members', ['title' => 'Member', 'secret' => 'never-leak-this', 'status' => 'published'], ['id' => 'admin']);
$content->save('posts', ['title' => 'Post', 'slug' => 'post', 'related' => $member['id'], 'status' => 'published'], ['id' => 'admin']);
CODE
                . '$mode = ' . var_export($mode, true) . '; $target = ' . var_export($target, true) . ';' . <<<'CODE'
$store = new \CometCMS\Storage\JsonStore(comet_test_workspace_path() . '/content-types');
$schema = $store->read('members');
if ($target === 'private') $schema['visibility'] = 'private';
if ($target === 'disabled') $schema['api_enabled'] = false;
$store->write($schema, 'members');
if ($mode !== 'anonymous') {
    $resources = ['content:posts:*'];
    if ($mode === 'member') $resources[] = 'content:members:' . $member['uid'];
    $token = (new \CometCMS\Auth\ApiTokenRepository())->create('Reader', '', [['actions' => ['content.read'], 'resources' => $resources]]);
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
}
$_GET = ['include' => 'related'];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/v1/workspaces/default/content/posts/post';
(new \CometCMS\Controllers\ApiController(new \CometCMS\Core\Http()))->useWorkspace('default')->contentShow('posts', 'post');
CODE]);
            $expected = $target !== 'disabled' && ($mode === 'member' || ($mode === 'anonymous' && $target === 'public'));
            assert_same($expected, str_contains($output, 'never-leak-this'), "$mode / $target: $output");
        }
    }
});

test('security delegated managers cannot reset credentials of more privileged accounts', function (): void {
    $seed = <<<'CODE'
$roles->create(['label' => 'Manager', 'permissions' => [['actions' => ['users.update'], 'resources' => ['users:*']]]]);
$roles->create(['label' => 'Privileged', 'permissions' => [['actions' => ['site.manage'], 'resources' => ['site:*']]]]);
$roles->create(['label' => 'Ordinary', 'permissions' => []]);
$users->create('manager', 'secret-password', 'manager');
$users->create('privileged', 'secret-password', 'privileged');
$users->create('ordinary', 'secret-password', 'ordinary');
CODE;
    foreach (['admin' => 'admin', 'privileged' => 'privileged'] as $target => $role) {
        foreach ([['password' => 'replacement-password'], ['password' => 'replacement-password', 'role' => $role], ['password' => 'replacement-password', 'role' => 'ordinary']] as $body) {
            [$status, $result] = comet_admin_request('manager', $seed, 'PUT', '/admin/api/users/' . $target,
                '(new \CometCMS\Controllers\Admin\UsersController(new \CometCMS\Core\Http()))->update(' . var_export($target, true) . ');', $body);
            assert_same(403, $status, $target . " " . json_encode($body) . " " . json_encode($result));
        }
    }
    [$status] = comet_admin_request('manager', $seed, 'PUT', '/admin/api/users/ordinary',
        '(new \CometCMS\Controllers\Admin\UsersController(new \CometCMS\Core\Http()))->update("ordinary");', ['password' => 'replacement-password']);
    assert_same(200, $status);
    [$status] = comet_admin_request('admin', $seed, 'PUT', '/admin/api/users/privileged',
        '(new \CometCMS\Controllers\Admin\UsersController(new \CometCMS\Core\Http()))->update("privileged");', ['password' => 'replacement-password']);
    assert_same(200, $status);
    foreach (['wrong' => 422, 'secret-password' => 200] as $old => $expected) {
        [$status] = comet_admin_request('ordinary', $seed, 'PUT', '/admin/api/profile',
            '(new \CometCMS\Controllers\Admin\UsersController(new \CometCMS\Core\Http()))->profileUpdate();', ['password' => 'replacement-password', 'old_password' => $old]);
        assert_same($expected, $status);
    }
});

function comet_security_restore_request(array $grants, bool $published = false): array
{
    $seed = <<<'CODE'
(new \CometCMS\Localization\LanguageSettings())->save(['locales' => ['en', 'de'], 'default_locale' => 'en']);
(new \CometCMS\Content\ContentTypeRepository())->save(['name' => 'posts', 'fields' => ['body' => ['type' => 'text']]]);
$content = \CometCMS\Content\ContentRepository::make();
$old = $content->save('posts', ['title' => 'Old', 'slug' => 'post', 'locale' => 'en', 'body' => 'Old body', 'status' => 'draft'], ['id' => 'admin']);
$old = $content->save('posts', ['title' => 'Alt', 'slug' => 'beitrag', 'locale' => 'de', 'body' => 'Alter Inhalt', 'status' => 'draft'], ['id' => 'admin'], $old['id']);
CODE;
    if ($published) $seed .= '$old = $content->save("posts", ["status" => "published", "locale" => "en"], ["id" => "admin"], $old["id"]);';
    $seed .= <<<'CODE'
$content->save('posts', ['title' => 'New', 'body' => 'New body', 'status' => 'draft', 'locale' => 'en'], ['id' => 'admin'], $old['id']);
$revision = '';
foreach ($content->revisions('posts', $old['id']) as $candidate) {
    if ($candidate['entry']['revision'] === $old['revision']) $revision = $candidate['id'];
}
CODE;
    $seed .= '$roles->create(["label" => "Restorer", "permissions" => ' . var_export($grants, true) . ']); $users->create("restorer", "secret-password", "restorer");';
    return comet_admin_request('restorer', $seed, 'POST', '/admin/api/content/posts/post/revisions/restore',
        '(new \CometCMS\Controllers\Admin\ContentController(new \CometCMS\Core\Http()))->revisionRestore("posts", "post", $revision);');
}

test('security restoring revisions enforces update field locale and publication grants', function (): void {
    $restore = ['actions' => ['content.revisions.restore'], 'resources' => ['content:posts:*']];
    $update = ['actions' => ['content.update'], 'resources' => ['content:posts:*']];
    $publish = ['actions' => ['content.publish'], 'resources' => ['content:posts:*']];
    foreach ([[$restore], [$restore, $update + ['fields' => ['body']]], [$restore, $update + ['conditions' => ['locales' => ['de']]]]] as $grants) {
        [$status, $result] = comet_security_restore_request($grants);
        assert_same(403, $status, json_encode([$grants, $result]));
    }
    [$status] = comet_security_restore_request([$restore, $update], true);
    assert_same(403, $status);
    [$status, $result] = comet_security_restore_request([$restore, $update, $publish], true);
    assert_same(200, $status, json_encode($result));
    assert_same('published', $result['data']['publication']['en']['status']);
    [$status, $result] = comet_security_restore_request([$restore, $update + ['fields' => ['title', 'body'], 'conditions' => ['locales' => ['en']]]]);
    assert_same(200, $status, json_encode($result));
});

test('security MIME and extension policy rejects executable suffixes and preserves rename type', function (): void {
    foreach (['php', 'PHP', 'phtml', 'php3', 'php5', 'php8', 'phar', 'cgi', 'pl', 'py', 'rb', 'sh', 'asp', 'aspx', 'jsp', 'shtml', 'htaccess', 'ini', 'jpg.php', 'png'] as $suffix) {
        assert_throws(InvalidArgumentException::class, fn() => MimeDetector::validatedExtension('image/gif', 'audit-marker.' . $suffix));
    }
    assert_same('gif', MimeDetector::validatedExtension('image/gif', 'image.gif'));
    assert_same('jpg', MimeDetector::validatedExtension('image/jpeg', 'image.jpeg'));
    assert_same('csv', MimeDetector::validatedExtension('text/plain', 'table.csv'));
    assert_same('pdf', MimeDetector::validatedExtension('application/pdf', 'guide.pdf'));
    $media = new MediaRepository();
    file_put_contents($media->directory() . '/safe.gif', 'GIF89a inert marker');
    foreach (['php', 'phtml', 'phar', 'cgi', 'aspx', 'jpg'] as $extension) {
        assert_throws(InvalidArgumentException::class, fn() => $media->rename('safe.gif', 'audit-marker.' . $extension));
    }
    assert_throws(InvalidArgumentException::class, fn() => $media->rename('safe.gif', 'audit-marker.php.gif'));
    assert_same('renamed.gif', $media->rename('safe.gif', 'renamed')['name']);
});

test('security admin and API multipart uploads reject script suffixes without executing uploaded content', function (): void {
    $root = comet_site_preview_test_install();
    $token = trim(comet_test_run_php(['-r', 'require ' . var_export($root . '/app/bootstrap.php', true) . ';' . <<<'CODE'
(new \CometCMS\Auth\UserRepository())->create('admin', 'secret-password', 'admin');
echo (new \CometCMS\Auth\ApiTokenRepository())->create('Uploader', '', [['actions' => ['media.upload'], 'resources' => ['media:*']]]);
CODE]));
    $port = 28080 + random_int(0, 2000);
    $server = comet_test_start_php_server('127.0.0.1', $port, $root . '/router.php');
    try {
        usleep(300000);
        [$status, $body, $headers] = comet_site_preview_test_request($port, '/admin/api/login', '', 'POST', ['username' => 'admin', 'password' => 'secret-password']);
        assert_same(200, $status, $body);
        $cookie = explode(';', $headers['set-cookie'])[0];
        $csrf = json_decode($body, true)['meta']['csrf_token'] ?? $headers['x-csrf-token'] ?? '';
        foreach (['/admin/api/media', '/api/v1/workspaces/default/media'] as $uri) {
            foreach (['php', 'phtml', 'php3', 'php5', 'php8', 'phar', 'cgi', 'pl', 'py', 'sh', 'asp', 'aspx', 'jsp', 'shtml', 'ini', 'png', 'gif'] as $suffix) {
                $boundary = 'comet-' . bin2hex(random_bytes(12));
                // Inert mixed-format marker: never requested or executed.
                $contents = 'GIF89a' . "\n<?php /* inert audit marker */ ?>";
                $body = '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"media[]\"; filename=\"audit-marker." . $suffix . "\"\r\nContent-Type: image/gif\r\n\r\n" . $contents . "\r\n--" . $boundary . "--\r\n";
                $response = (string) file_get_contents('http://127.0.0.1:' . $port . $uri, false, stream_context_create(['http' => [
                    'ignore_errors' => true, 'method' => 'POST',
                    'header' => 'Content-Type: multipart/form-data; boundary=' . $boundary . "\r\nAuthorization: Bearer " . $token . "\r\nCookie: " . $cookie . "\r\nX-CSRF-Token: " . $csrf . "\r\n",
                    'content' => $body,
                ]]));
                $responseHeaders = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : (${'http_response_header'} ?? []);
                preg_match('#^HTTP/\S+\s+(\d{3})#', $responseHeaders[0] ?? '', $match);
                $expected = $suffix === 'gif' ? 201 : 422;
                assert_same($expected, (int) ($match[1] ?? 0), "$uri / $suffix: $response");
                $files = glob($root . '/storage/workspaces/default/media/*') ?: [];
                foreach ($files as $file) assert_same('gif', pathinfo($file, PATHINFO_EXTENSION));
                if ($expected === 201) {
                    assert_true(count($files) > 0);
                    $filename = json_decode($response, true)['data'][0]['name'];
                    assert_same('gif', pathinfo($filename, PATHINFO_EXTENSION));
                }
            }
        }
    } finally {
        comet_test_stop_process($server);
    }
});

test('security failed revision authorization leaves content and revision history untouched', function (): void {
    (new ContentTypeRepository())->save(['name' => 'posts']);
    $content = ContentRepository::make();
    $user = ['id' => 'admin'];
    $old = $content->save('posts', ['title' => 'Old', 'status' => 'published'], $user);
    $current = $content->save('posts', ['title' => 'New', 'status' => 'draft'], $user, $old['id']);
    $history = $content->revisions('posts', $old['id']);
    assert_throws(InvalidArgumentException::class, function () use ($content, $current, $history, $user): void {
        $content->restoreRevision('posts', $current['id'], $history[0]['id'], $user, function (array $before, array $after, array $changes) use ($current): void {
            assert_same($current['revision'], $before['revision']);
            assert_same('published', $after['publication']['en']['status']);
            assert_true(in_array('title', $changes['en'], true));
            throw new InvalidArgumentException('Denied');
        });
    });
    assert_same($current, $content->find('posts', $current['id']));
    assert_same($history, $content->revisions('posts', $current['id']));
});
