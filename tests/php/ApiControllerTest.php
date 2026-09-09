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

test('media endpoint serves configured image variants', function (): void {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
        return;
    }

    $output = comet_test_run_php(['-r',
        comet_api_controller_test_bootstrap_require_snippet() .
        'comet_test_reset_storage();' .
        '$image = imagecreatetruecolor(1200, 600);' .
        'imagejpeg($image, comet_test_workspace_path() . "/media/hero.jpg", 90);' .
        'imagedestroy($image);' .
        '$_GET = ["w" => "320", "format" => "jpeg"];' .
        '$_SERVER["REQUEST_METHOD"] = "GET";' .
        '$_SERVER["REQUEST_URI"] = "/media/default/hero.jpg?w=320&format=jpeg";' .
        '$_SERVER["SCRIPT_NAME"] = "/index.php";' .
        '(new \\CometCMS\\Controllers\\ApiController(new \\CometCMS\\Core\\Http()))->useWorkspace("default")->mediaShow("hero.jpg");'
    ]);

    $dimensions = getimagesizefromstring($output);
    assert_same(320, $dimensions[0] ?? null);
    assert_same(160, $dimensions[1] ?? null);
});

test('media listing advertises responsive variant urls', function (): void {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
        return;
    }

    $output = comet_test_run_php(['-r',
        comet_api_controller_test_bootstrap_require_snippet() .
        'comet_test_reset_storage();' .
        '$image = imagecreatetruecolor(1200, 600);' .
        'imagejpeg($image, comet_test_workspace_path() . "/media/hero.jpg", 90);' .
        'imagedestroy($image);' .
        '$_SERVER["REQUEST_METHOD"] = "GET";' .
        '$_SERVER["REQUEST_URI"] = "/api/v1/workspaces/default/media";' .
        '$_SERVER["SCRIPT_NAME"] = "/index.php";' .
        '(new \\CometCMS\\Controllers\\ApiController(new \\CometCMS\\Core\\Http()))->useWorkspace("default", false, true)->mediaIndex();'
    ]);

    assert_true(str_contains($output, '"variants": {'));
    assert_true(str_contains($output, 'hero.jpg?w=320&format='));
    assert_false(str_contains($output, 'hero.jpg?w=1280&format='));
});
