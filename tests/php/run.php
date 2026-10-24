<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$cometTests = [];

function test(string $name, callable $callback): void
{
    global $cometTests;

    $cometTests[] = [$name, $callback];
}

function assert_true(mixed $actual, string $message = ''): void
{
    if ($actual !== true) {
        throw new RuntimeException($message !== '' ? $message : 'Expected true.');
    }
}

function assert_false(mixed $actual, string $message = ''): void
{
    if ($actual !== false) {
        throw new RuntimeException($message !== '' ? $message : 'Expected false.');
    }
}

function assert_null(mixed $actual, string $message = ''): void
{
    if ($actual !== null) {
        throw new RuntimeException($message !== '' ? $message : 'Expected null.');
    }
}

function assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message !== '' ? $message : sprintf(
            "Expected:\n%s\nActual:\n%s",
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assert_matches(string $pattern, string $actual, string $message = ''): void
{
    if (preg_match($pattern, $actual) !== 1) {
        throw new RuntimeException($message !== '' ? $message : sprintf('Expected %s to match %s.', $actual, $pattern));
    }
}

function assert_file_exists_at(string $path, string $message = ''): void
{
    if (!is_file($path)) {
        throw new RuntimeException($message !== '' ? $message : sprintf('Expected file to exist: %s', $path));
    }
}

function assert_throws(string $class, callable $callback, string $message = ''): void
{
    try {
        $callback();
    } catch (Throwable $throwable) {
        if ($throwable instanceof $class) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Expected %s, got %s: %s',
            $class,
            $throwable::class,
            $throwable->getMessage()
        ), 0, $throwable);
    }

    throw new RuntimeException($message !== '' ? $message : sprintf('Expected %s to be thrown.', $class));
}

foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    require $file;
}

$failures = [];

if (getenv('COMET_TEST_FILTER')) $cometTests = array_values(array_filter($cometTests, static fn(array $test): bool => str_contains($test[0], getenv('COMET_TEST_FILTER'))));

foreach ($cometTests as [$name, $callback]) {
    comet_test_reset_storage();

    try {
        $callback();
        fwrite(STDOUT, '.');
    } catch (Throwable $throwable) {
        $failures[] = [$name, $throwable];
        fwrite(STDOUT, 'F');
    }
}

fwrite(STDOUT, PHP_EOL);

if ($failures !== []) {
    foreach ($failures as [$name, $throwable]) {
        fwrite(STDERR, PHP_EOL . "FAIL: {$name}" . PHP_EOL);
        fwrite(STDERR, $throwable->getMessage() . PHP_EOL);
        fwrite(STDERR, $throwable->getFile() . ':' . $throwable->getLine() . PHP_EOL);
    }

    fwrite(STDERR, PHP_EOL . sprintf('%d of %d backend tests failed.', count($failures), count($cometTests)) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, sprintf('%d backend tests passed.', count($cometTests)) . PHP_EOL);
