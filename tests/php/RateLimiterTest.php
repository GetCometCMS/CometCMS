<?php

declare(strict_types=1);

use CometCMS\Core\RateLimiter;

test('rate limiter allows a bounded number of attempts and reports retry timing', function (): void {
    $limiter = new RateLimiter(COMET_STORAGE . '/cache/request-rate-test', 100);

    assert_false($limiter->consume('contact', '127.0.0.1', 2, 60)['limited']);
    assert_false($limiter->consume('contact', '127.0.0.1', 2, 60)['limited']);
    $limited = $limiter->consume('contact', '127.0.0.1', 2, 60);

    assert_true($limited['limited']);
    assert_same(0, $limited['remaining']);
    assert_true($limited['retry_after'] > 0);
    assert_false($limiter->consume('contact', '127.0.0.2', 2, 60)['limited']);
});

test('rate limiter pruning removes expired records', function (): void {
    $path = COMET_STORAGE . '/cache/request-rate-prune';
    $limiter = new RateLimiter($path, 100);
    $limiter->consume('contact', 'visitor', 2, 60);
    $files = glob($path . '/*.json') ?: [];
    assert_same(1, count($files));

    $record = json_decode((string) file_get_contents($files[0]), true);
    $record['reset_at'] = time() - 1;
    file_put_contents($files[0], json_encode($record));
    $limiter->prune();

    assert_same([], glob($path . '/*.json') ?: []);
});
