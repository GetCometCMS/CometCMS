<?php

declare(strict_types=1);

function comet_api_controller_test_bootstrap_require_snippet(): string
{
    return 'require ' . var_export(__DIR__ . '/bootstrap.php', true) . ';';
}

test('singleton content collection route returns the fixed entry', function (): void {
    $output = comet_test_run_php(['-r',
        comet_api_controller_test_bootstrap_require_snippet() .
        'comet_test_reset_storage();' .
        '(new \\CometCMS\\Content\\ContentTypeRepository())->save(["name" => "homepage", "singleton" => true]);' .
        '\\CometCMS\\Content\\ContentRepository::make()->save("homepage", ["title" => "Home", "status" => "published"], ["id" => "admin"]);' .
        '$_SERVER["REQUEST_METHOD"] = "GET";' .
        '$_SERVER["REQUEST_URI"] = "/api/v1/workspaces/default/content/homepage";' .
        '$_SERVER["SCRIPT_NAME"] = "/index.php";' .
        '(new \\CometCMS\\Controllers\\ApiController(new \\CometCMS\\Core\\Http()))->useWorkspace("default")->contentIndex("homepage");'
    ]);

    assert_true(str_contains($output, '"data": {'));
    assert_true(str_contains($output, '"slug": "homepage"'));
    assert_false(str_contains($output, '"meta": {'));
});

test('private content collection rejects unauthenticated reads', function (): void {
    $output = comet_test_run_php(['-r',
        comet_api_controller_test_bootstrap_require_snippet() .
        'comet_test_reset_storage();' .
        '(new \\CometCMS\\Content\\ContentTypeRepository())->save(["name" => "members", "visibility" => "private"]);' .
        '\\CometCMS\\Content\\ContentRepository::make()->save("members", ["title" => "Member", "status" => "published"], ["id" => "admin"]);' .
        '$_SERVER["REQUEST_METHOD"] = "GET";' .
        '$_SERVER["REQUEST_URI"] = "/api/v1/workspaces/default/content/members";' .
        '$_SERVER["SCRIPT_NAME"] = "/index.php";' .
        '(new \\CometCMS\\Controllers\\ApiController(new \\CometCMS\\Core\\Http()))->useWorkspace("default")->contentIndex("members");'
    ]);

    assert_true(str_contains($output, '"code": "unauthorized"'));
    assert_true(str_contains($output, 'Missing bearer token'));
});

test('private content collection allows a token with collection read permission', function (): void {
    $output = comet_test_run_php(['-r',
        comet_api_controller_test_bootstrap_require_snippet() .
        'comet_test_reset_storage();' .
        '(new \\CometCMS\\Content\\ContentTypeRepository())->save(["name" => "members", "visibility" => "private"]);' .
        '\\CometCMS\\Content\\ContentRepository::make()->save("members", ["title" => "Member", "status" => "published"], ["id" => "admin"]);' .
        '$plain = (new \\CometCMS\\Auth\\ApiTokenRepository())->create("reader", "", [["effect" => "allow", "actions" => ["content.read"], "resources" => ["content:members:*"]]]);' .
        '$_SERVER["HTTP_AUTHORIZATION"] = "Bearer " . $plain;' .
        '$_SERVER["REQUEST_METHOD"] = "GET";' .
        '$_SERVER["REQUEST_URI"] = "/api/v1/workspaces/default/content/members";' .
        '$_SERVER["SCRIPT_NAME"] = "/index.php";' .
        '(new \\CometCMS\\Controllers\\ApiController(new \\CometCMS\\Core\\Http()))->useWorkspace("default")->contentIndex("members");'
    ]);

    assert_true(str_contains($output, '"title": "Member"'));
});

test('unauthenticated content type listing omits private schemas', function (): void {
    $output = comet_test_run_php(['-r',
        comet_api_controller_test_bootstrap_require_snippet() .
        'comet_test_reset_storage();' .
        '$types = new \\CometCMS\\Content\\ContentTypeRepository();' .
        '$types->save(["name" => "pages"]);' .
        '$types->save(["name" => "members", "visibility" => "private"]);' .
        '$_SERVER["REQUEST_METHOD"] = "GET";' .
        '$_SERVER["REQUEST_URI"] = "/api/v1/workspaces/default/content-types";' .
        '$_SERVER["SCRIPT_NAME"] = "/index.php";' .
        '(new \\CometCMS\\Controllers\\ApiController(new \\CometCMS\\Core\\Http()))->useWorkspace("default")->contentTypes();'
    ]);

    assert_true(str_contains($output, '"name": "pages"'));
    assert_false(str_contains($output, '"name": "members"'));
});
