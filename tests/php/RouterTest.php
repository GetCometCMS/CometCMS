<?php

declare(strict_types=1);

function comet_router_test_get(int $port, string $path): array
{
    $body = @file_get_contents('http://127.0.0.1:' . $port . $path, false, stream_context_create([
        'http' => ['ignore_errors' => true],
    ]));
    $status = 0;

    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match)) {
            $status = (int) $match[1];
        }
    }

    return [$status, (string) $body];
}

test('built-in server router only serves compiled admin assets as static files', function (): void {
    $root = COMET_STORAGE . '/router-test';
    foreach (['admin/assets', 'storage/sessions', 'storage/users', 'app', 'config'] as $directory) {
        mkdir($root . '/' . $directory, 0775, true);
    }

    copy(COMET_ROOT . '/router.php', $root . '/router.php');
    file_put_contents($root . '/index.php', "<?php echo 'INDEX ' . \$_SERVER['SCRIPT_NAME'];");
    file_put_contents($root . '/admin/assets/main.js', 'console.log("asset");');
    file_put_contents($root . '/admin/hidden.php', "<?php echo 'EXECUTED';");
    file_put_contents($root . '/storage/sessions/sess_abc', 'secret-session');
    file_put_contents($root . '/storage/users/admin.json', '{"password_hash":"secret"}');
    file_put_contents($root . '/app/version.php', "<?php echo 'EXECUTED';");
    file_put_contents($root . '/config/config.php', "<?php echo 'EXECUTED';");

    $port = 20080 + random_int(0, 2000);
    $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root, $root . '/router.php'], [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', '/dev/null', 'w'],
        2 => ['file', '/dev/null', 'w'],
    ], $pipes);

    try {
        usleep(300000);

        assert_same([200, 'console.log("asset");'], comet_router_test_get($port, '/admin/assets/main.js'));

        foreach ([
            '/storage/sessions/sess_abc',
            '/storage/users/admin.json',
            '/app/version.php',
            '/config/config.php',
            '/admin/hidden.php',
            '/admin/../storage/users/admin.json',
        ] as $path) {
            [, $body] = comet_router_test_get($port, $path);
            assert_same('INDEX /index.php', $body, 'Expected ' . $path . ' to be routed through index.php.');
        }
    } finally {
        comet_test_stop_process($process);
    }
});
