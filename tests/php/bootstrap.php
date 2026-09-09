<?php

declare(strict_types=1);

define('COMET_TEST_ROOT', dirname(__DIR__, 2));
define('COMET_ROOT', COMET_TEST_ROOT . '/cms');
define('COMET_APP', COMET_ROOT . '/app');
define('COMET_STORAGE', sys_get_temp_dir() . '/cometcms-tests-' . getmypid());

$cometConfig = require COMET_ROOT . '/config/config.php';
$cometConfig['app']['debug'] = true;
$cometConfig['cache']['path'] = COMET_STORAGE . '/cache/api';

spl_autoload_register(static function (string $class): void {
    $prefix = 'CometCMS\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = COMET_APP . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

function comet_config(string $key, mixed $default = null): mixed
{
    global $cometConfig;

    $value = $cometConfig;

    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function comet_test_reset_storage(): void
{
    comet_test_remove_directory(COMET_STORAGE);

    foreach (
        [
            COMET_STORAGE,
            COMET_STORAGE . '/users',
            COMET_STORAGE . '/api-tokens',
            COMET_STORAGE . '/content',
            COMET_STORAGE . '/media',
            COMET_STORAGE . '/media-meta',
            COMET_STORAGE . '/sessions',
            COMET_STORAGE . '/cache',
            COMET_STORAGE . '/cache/api',
            COMET_STORAGE . '/cache/login-throttle',
            COMET_STORAGE . '/content-types',
            COMET_STORAGE . '/revisions',
            COMET_STORAGE . '/revisions/content',
            COMET_STORAGE . '/trash',
            COMET_STORAGE . '/trash/content',
            COMET_STORAGE . '/trash/media',
            COMET_STORAGE . '/logs',
            COMET_STORAGE . '/backups',
            COMET_STORAGE . '/updates',
            COMET_STORAGE . '/workspaces',
            COMET_STORAGE . '/workspaces/default',
            COMET_STORAGE . '/workspaces/default/content',
            COMET_STORAGE . '/workspaces/default/content-types',
            COMET_STORAGE . '/workspaces/default/media',
            COMET_STORAGE . '/workspaces/default/media-thumbs',
            COMET_STORAGE . '/workspaces/default/media-variants',
            COMET_STORAGE . '/workspaces/default/media-meta',
            COMET_STORAGE . '/workspaces/default/revisions',
            COMET_STORAGE . '/workspaces/default/revisions/content',
            COMET_STORAGE . '/workspaces/default/trash',
            COMET_STORAGE . '/workspaces/default/trash/content',
            COMET_STORAGE . '/workspaces/default/trash/media',
            COMET_STORAGE . '/workspaces/default/cache',
            COMET_STORAGE . '/workspaces/default/cache/api',
        ] as $directory
    ) {
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
    }

    $_SESSION = [];
    \CometCMS\Workspaces\WorkspaceContext::reset();
}

function comet_test_workspace_path(string $workspace = 'default'): string
{
    return COMET_STORAGE . '/workspaces/' . $workspace;
}

function comet_test_remove_directory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    foreach (glob($path . '/*', GLOB_MARK) ?: [] as $item) {
        if (is_dir($item)) {
            comet_test_remove_directory(rtrim($item, '/'));
        } else {
            unlink($item);
        }
    }

    rmdir($path);
}

function comet_test_run_php(array $args, string $stdin = ''): string
{
    $command = array_merge([PHP_BINARY], $args);
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);

    if (!is_resource($process)) {
        throw new RuntimeException('Failed to start PHP subprocess.');
    }

    fwrite($pipes[0], $stdin);
    fclose($pipes[0]);

    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    if ($exitCode !== 0) {
        throw new RuntimeException(trim((string) $error) !== '' ? trim((string) $error) : 'PHP subprocess failed.');
    }

    return (string) $output;
}

function comet_test_start_php_server(string $host, int $port, string $routerPath): mixed
{
    $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $process = proc_open([PHP_BINARY, '-S', $host . ':' . $port, $routerPath], [
        0 => ['file', $nullDevice, 'r'],
        1 => ['file', $nullDevice, 'w'],
        2 => ['file', $nullDevice, 'w'],
    ], $pipes);

    if (!is_resource($process)) {
        throw new RuntimeException('Failed to start temporary PHP server.');
    }

    return $process;
}

function comet_test_stop_process(mixed $process): void
{
    if (!is_resource($process)) {
        return;
    }

    proc_terminate($process);
    proc_close($process);
}

register_shutdown_function(static function (): void {
    comet_test_remove_directory(COMET_STORAGE);
});
