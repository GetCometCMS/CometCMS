<?php

declare(strict_types=1);

use CometCMS\Core\Http;

function comet_http_test_run_inline_php(string $code): string
{
    return comet_test_run_php(['-r', $code]);
}

function comet_http_test_run_inline_php_with_stdin(string $code, string $stdin): string
{
    return comet_test_run_php(['-r', $code], $stdin);
}

function comet_http_test_bootstrap_path(): string
{
    return __DIR__ . '/bootstrap.php';
}

function comet_http_test_bootstrap_require_snippet(): string
{
    return 'require ' . var_export(comet_http_test_bootstrap_path(), true) . ';';
}

test('http path strips base dir and normalizes trailing slash', function (): void {
    $http = new Http();

    $_SERVER['SCRIPT_NAME'] = '/cms/index.php';
    $_SERVER['REQUEST_URI'] = '/cms/admin/users/';
    assert_same('/admin/users', $http->path());

    $_SERVER['REQUEST_URI'] = '/cms';
    assert_same('/', $http->path());
});

test('http url prefixes path with script base directory', function (): void {
    $http = new Http();

    $_SERVER['SCRIPT_NAME'] = '/cms/index.php';
    assert_same('/cms/api/v1/content', $http->url('api/v1/content'));
});

test('http requestJson parses body and falls back to empty object for invalid payload', function (): void {
    $routerPath = COMET_STORAGE . '/request-json-test-router.php';
    file_put_contents(
        $routerPath,
        "<?php\nrequire " . var_export(comet_http_test_bootstrap_path(), true) . ";\nheader('Content-Type: application/json');\necho json_encode((new \\CometCMS\\Core\\Http())->requestJson());\n"
    );

    $port = 18080 + random_int(0, 2000);
    $server = comet_test_start_php_server('127.0.0.1', $port, $routerPath);

    try {
        usleep(300000);

        $validResponse = (string) file_get_contents(
            'http://127.0.0.1:' . $port,
            false,
            stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => '{"ok":true}',
                ],
            ])
        );

        $invalidResponse = (string) file_get_contents(
            'http://127.0.0.1:' . $port,
            false,
            stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => 'not-json',
                ],
            ])
        );

        assert_same('{"ok":true}', trim($validResponse));
        assert_same('[]', trim($invalidResponse));
    } finally {
        comet_test_stop_process($server);
    }
});

test('http redirect emits location header and status before exit', function (): void {
    $bootstrapRequire = comet_http_test_bootstrap_require_snippet();

    $output = comet_http_test_run_inline_php(
        $bootstrapRequire .
            'register_shutdown_function(static function (): void {' .
            'echo "\n__CODE__" . http_response_code();' .
            'echo "\n__HEADERS__" . json_encode(headers_list());' .
            '});' .
            '(new \\CometCMS\\Core\\Http())->redirect("/admin", 307);'
    );

    assert_true(str_contains($output, '__CODE__307'));
});

test('http flash supports set get and consume behavior', function (): void {
    $http = new Http();

    $_SESSION = [];
    assert_null($http->flash('notice'));

    $http->flash('notice', 'Saved');
    assert_same('Saved', $http->flash('notice'));
    assert_null($http->flash('notice'));
});

test('http json outputs payload and response metadata before exit', function (): void {
    $bootstrapRequire = comet_http_test_bootstrap_require_snippet();

    $output = comet_http_test_run_inline_php(
        $bootstrapRequire .
            'register_shutdown_function(static function (): void {' .
            'echo "\n__CODE__" . http_response_code();' .
            'echo "\n__HEADERS__" . json_encode(headers_list());' .
            '});' .
            '(new \\CometCMS\\Core\\Http())->json(["ok" => true], 201);'
    );

    assert_true(str_contains($output, '"ok": true'));
    assert_true(str_contains($output, '__CODE__201'));
});

test('http streamFile bypasses all output buffers', function (): void {
    $bootstrapRequire = comet_http_test_bootstrap_require_snippet();
    $file = COMET_STORAGE . '/stream-file.txt';
    file_put_contents($file, 'streamed-content');

    $output = comet_http_test_run_inline_php(
        $bootstrapRequire .
            'ob_start(static fn(string $body): string => "outer[" . $body . "]");' .
            'ob_start(static fn(string $body): string => "inner[" . $body . "]");' .
            'register_shutdown_function(static function (): void {' .
            'echo "\n__BUFFER_LEVEL__" . ob_get_level();' .
            '});' .
            '(new \\CometCMS\\Core\\Http())->streamFile(' . var_export($file, true) . ', "text/plain");'
    );

    assert_same("streamed-content\n__BUFFER_LEVEL__0", $output);
});
