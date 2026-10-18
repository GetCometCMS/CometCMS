<?php

declare(strict_types=1);

use CometCMS\Cache\ApiCache;

test('api cache get returns null when disabled', function (): void {
    $cache = new ApiCache(false, 300, COMET_STORAGE . '/cache/api-disabled');

    $cache->put('a', ['ok' => true]);
    assert_null($cache->get('a'));
});

test('api cache put and get roundtrip', function (): void {
    $cache = new ApiCache(true, 300, COMET_STORAGE . '/cache/api-roundtrip');

    $cache->put('k1', ['items' => [1, 2, 3]]);
    assert_same(['items' => [1, 2, 3]], $cache->get('k1'));
});

test('api cache expiration invalidates stale entries', function (): void {
    $cache = new ApiCache(true, -1, COMET_STORAGE . '/cache/api-expired');

    $cache->put('k2', ['ok' => true]);
    assert_null($cache->get('k2'));
});

test('api cache clear removes stored cache files', function (): void {
    $path = COMET_STORAGE . '/cache/api-clear';
    $cache = new ApiCache(true, 300, $path);

    $cache->put('k1', ['a' => 1]);
    $cache->put('k2', ['b' => 2]);
    assert_true(count(glob($path . '/*.json') ?: []) >= 2);

    $cache->clear();
    assert_same(0, count(glob($path . '/*.json') ?: []));
});

test('api cache deadlines shorten but never extend the configured lifetime', function (): void {
    $path = COMET_STORAGE . '/cache/api-deadlines';
    $cache = new ApiCache(true, 300, $path);
    $deadline = time() + 60;
    $cache->put('scheduled', ['ok' => true], $deadline);
    assert_same($deadline, json_decode(file_get_contents($path . '/scheduled.json'), true)['expires_at']);
    assert_same(['ok' => true], $cache->get('scheduled'));

    $cache->put('later', ['ok' => true], time() + 600);
    assert_true(json_decode(file_get_contents($path . '/later.json'), true)['expires_at'] <= time() + 300);

    $cache->put('now', ['ok' => true], time());
    assert_null($cache->get('now'), 'A deadline expires at its exact second.');
});

test('api cache key generation is deterministic', function (): void {
    $cache = new ApiCache(true, 300, COMET_STORAGE . '/cache/api-key');

    $a = $cache->key('/api/v1/posts', 'page=1');
    $b = $cache->key('/api/v1/posts', 'page=1');
    $c = $cache->key('/api/v1/posts', 'page=2');

    assert_same($a, $b);
    assert_false($a === $c);
});
