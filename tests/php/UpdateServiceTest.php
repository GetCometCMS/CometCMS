<?php

declare(strict_types=1);

use CometCMS\Updates\UpdateService;

test('update service builds configured fallback release sources', function (): void {
    global $cometConfig;

    $originalUpdates = $cometConfig['updates'];

    try {
        $cometConfig['updates']['repository_url'] = 'https://github.com/GetCometCMS/CometCMS';
        $cometConfig['updates']['releases_api_url'] = '';
        $cometConfig['updates']['fallback_repository_urls'] = [
            'https://github.com/example/cometcms-mirror',
        ];

        $method = new ReflectionMethod(UpdateService::class, 'config');
        $config = $method->invoke(new UpdateService());

        assert_same(
            [
                'https://api.github.com/repos/GetCometCMS/CometCMS/releases/latest',
                'https://api.github.com/repos/example/cometcms-mirror/releases/latest',
            ],
            array_column($config['release_sources'], 'api_url')
        );
    } finally {
        $cometConfig['updates'] = $originalUpdates;
    }
});

test('update service has no implicit fallback when config key is missing', function (): void {
    global $cometConfig;

    $originalUpdates = $cometConfig['updates'];

    try {
        unset($cometConfig['updates']['fallback_repository_urls']);
        $cometConfig['updates']['repository_url'] = 'https://github.com/GetCometCMS/CometCMS';
        $cometConfig['updates']['releases_api_url'] = '';

        $method = new ReflectionMethod(UpdateService::class, 'config');
        $config = $method->invoke(new UpdateService());

        assert_same(
            ['https://api.github.com/repos/GetCometCMS/CometCMS/releases/latest'],
            array_column($config['release_sources'], 'api_url')
        );
    } finally {
        $cometConfig['updates'] = $originalUpdates;
    }
});
