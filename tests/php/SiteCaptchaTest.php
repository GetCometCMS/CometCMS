<?php

declare(strict_types=1);

use CometCMS\Content\ContentRepository;
use CometCMS\Content\ContentTypeRepository;
use CometCMS\Core\RateLimiter;
use CometCMS\Site\FormCaptcha;
use CometCMS\Site\FormRepository;
use CometCMS\Site\FormSubmission;
use CometCMS\Site\LayoutSchema;
use CometCMS\Site\PageRenderer;
use CometCMS\Site\SiteRepository;
use CometCMS\Site\SiteScripts;
use CometCMS\Workspaces\WorkspaceContext;

function comet_captcha_test_config(Closure $test): void
{
    global $cometConfig;
    $original = $cometConfig['site'];
    try {
        foreach (['turnstile', 'recaptcha', 'recaptcha_v3'] as $provider) {
            $cometConfig['site']['forms']['captcha'][$provider] = ['site_key' => $provider . '-public', 'secret_key' => $provider . '-private', 'hostnames' => ['example.test']];
        }
        $cometConfig['site']['forms']['captcha']['workspaces'] = [];
        $test();
    } finally {
        $cometConfig['site'] = $original;
    }
}

function comet_captcha_test_setup(string $provider = 'turnstile'): array
{
    comet_test_reset_storage();
    [, , $form] = comet_form_test_setup();
    $types = new ContentTypeRepository();
    $types->save([...$types->find('inquiries'), 'submission_settings' => ['captcha' => $provider]]);
    $site = new SiteRepository();
    $forms = new FormRepository($site);

    return [$site, $forms, $forms->find($form['id'])];
}

function comet_captcha_test_response(array $form): array
{
    return ['success' => true, 'hostname' => 'example.test', 'action' => FormCaptcha::action($form)]
        + ($form['captcha'] === 'recaptcha_v3' ? ['score' => 0.9] : []);
}

test('captcha settings persist supported providers and discard credentials from schemas', function (): void {
    foreach (['none', 'turnstile', 'recaptcha', 'recaptcha_v3'] as $provider) {
        $settings = FormRepository::settings(['captcha' => $provider, 'secret_key' => 'must-not-be-stored']);
        assert_same($provider, $settings['captcha']);
        assert_false(isset($settings['secret_key']));
    }
    assert_same('none', FormRepository::settings(['captcha' => 'unknown'])['captcha']);
});

test('captcha verification posts the correct token and credentials to each fixed provider endpoint', function (): void {
    comet_captcha_test_config(function (): void {
        foreach ([
            ['turnstile', 'cf-turnstile-response', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'],
            ['recaptcha', 'g-recaptcha-response', 'https://www.google.com/recaptcha/api/siteverify'],
            ['recaptcha_v3', 'g-recaptcha-response', 'https://www.google.com/recaptcha/api/siteverify'],
        ] as [$provider, $field, $endpoint]) {
            [$site, , $form] = comet_captcha_test_setup($provider);
            $calls = [];
            $captcha = new FormCaptcha($site, static function (string $url, array $fields) use (&$calls, $form): string {
                $calls[] = [$url, $fields];
                return json_encode([...comet_captcha_test_response($form), 'hostname' => 'EXAMPLE.test']);
            });
            assert_true($captcha->verify($form, [$field => 'visitor-token'], '203.0.113.5'));
            assert_same([[$endpoint, ['secret' => $provider . '-private', 'response' => 'visitor-token', 'remoteip' => '203.0.113.5']]], $calls);
            assert_true($captcha->verify($form, [$field => 'visitor-token'], 'unknown'));
            assert_false(isset($calls[1][1]['remoteip']));
            foreach ([[], [$field => []], [$field => 123], [$field => '  '], [$field => str_repeat('x', 8193)], ['other-provider-response' => 'token']] as $input) {
                assert_false($captcha->verify($form, $input, 'visitor'));
            }
            assert_same(2, count($calls), 'Invalid tokens must not trigger outbound requests.');
        }
    });
});

test('captcha verification fails closed for provider rejection malformed responses outages and mismatches', function (): void {
    comet_captcha_test_config(function (): void {
        foreach (['turnstile', 'recaptcha', 'recaptcha_v3'] as $provider) {
            [$site, , $form] = comet_captcha_test_setup($provider);
            $field = $provider === 'turnstile' ? 'cf-turnstile-response' : 'g-recaptcha-response';
            $success = comet_captcha_test_response($form);
            $responses = [
                '', '<html>unavailable</html>', 'null', '[]', str_repeat('x', 16385),
                json_encode(['success' => false, 'error-codes' => ['timeout-or-duplicate']]),
                json_encode([...$success, 'success' => 'true']),
                json_encode([...$success, 'hostname' => 'evil.example']),
                json_encode([...$success, 'hostname' => ['example.test']]),
                json_encode(['success' => true]),
                json_encode([...$success, 'error-codes' => ['Over free quota.']]),
            ];
            $responses[] = json_encode($provider !== 'recaptcha' ? [...$success, 'action' => 'other-form'] : [...$success, 'score' => 0.9]);
            foreach ($responses as $response) {
                assert_false((new FormCaptcha($site, static fn(): string => $response))->verify($form, [$field => 'token'], 'visitor'));
            }
            $unavailable = new FormCaptcha($site, static fn() => throw new RuntimeException('Network timeout'));
            assert_false($unavailable->verify($form, [$field => 'token'], 'visitor'));
        }
    });
});

test('captcha protection rejects submissions before uploads and stores entries only after verification', function (): void {
    comet_captcha_test_config(function (): void {
        foreach (['turnstile', 'recaptcha', 'recaptcha_v3'] as $provider) {
            [$site, $forms, $form] = comet_captcha_test_setup($provider);
            $content = ContentRepository::make($site->workspace());
            $field = $provider === 'turnstile' ? 'cf-turnstile-response' : 'g-recaptcha-response';
            $input = ['fields' => ['name' => 'Ada', 'email' => 'ada@example.com', 'consent' => '1']];
            $calls = 0;
            $captcha = new FormCaptcha($site, static function () use (&$calls, $form): string {
                $calls++;
                return json_encode(comet_captcha_test_response($form));
            });
            $submission = new FormSubmission($site, $forms, $content, null, $captcha);
            $result = $submission->submit($form, $input, 'visitor', '', ['fields' => 'malformed']);
            assert_same(FormSubmission::ERROR, $result['status']);
            assert_true(isset($result['errors']['captcha']));
            assert_same(0, $calls);
            assert_same([], $content->all('inquiries'));
            assert_same([], (new \CometCMS\Media\MediaRepository($site->workspace()))->files());
            $result = $submission->submit($form, $input + [$field => 'token'], 'visitor');
            assert_same(FormSubmission::SENT, $result['status']);
            assert_same(1, $calls);
            assert_same(1, count($content->all('inquiries')));
            assert_false(isset($result['entry'][$field]));
        }
    });
});

test('captcha verification runs after honeypot and rate limits and cannot be disabled by posted fields', function (): void {
    comet_captcha_test_config(function (): void {
        [$site, $forms, $form] = comet_captcha_test_setup();
        $calls = 0;
        $captcha = new FormCaptcha($site, static function () use (&$calls): string { $calls++; return '{"success":false}'; });
        $limiter = new RateLimiter($site->workspace()->path('cache') . '/form-limits');
        $submission = new FormSubmission($site, $forms, ContentRepository::make(), $limiter, $captcha);
        assert_same(FormSubmission::SENT, $submission->submit($form, [FormSubmission::HONEYPOT => 'bot', 'cf-turnstile-response' => 'token'], 'visitor')['status']);
        assert_same(0, $calls);
        $result = $submission->submit($form, ['captcha' => 'none'], 'visitor');
        assert_true(isset($result['errors']['captcha']));
        for ($i = 0; $i < 5; $i++) {
            $limiter->hit('form:' . $form['id'], 'visitor', 5, 600);
        }
        $result = $submission->submit($form, ['cf-turnstile-response' => 'token'], 'visitor');
        assert_true(isset($result['errors']['form']));
        assert_same(0, $calls);
    });
});

test('captcha v3 requires the expected action and a numeric score at or above the configured threshold', function (): void {
    comet_captcha_test_config(function (): void {
        global $cometConfig;
        [$site, $forms, $form] = comet_captcha_test_setup('recaptcha_v3');
        assert_same('recaptcha_v3', $form['captcha']);
        assert_matches('/^[A-Za-z0-9_\/]+$/D', FormCaptcha::action($form));
        $response = comet_captcha_test_response($form);
        $captcha = new FormCaptcha($site, static function () use (&$response): string { return json_encode($response); });
        $input = ['g-recaptcha-response' => 'token', 'fields' => ['name' => 'Ada', 'email' => 'ada@example.com', 'consent' => '1']];
        foreach ([0, 0.49, '0.9', true, null, [], -1, 1.1] as $score) {
            $response['score'] = $score;
            assert_false($captcha->verify($form, $input, 'visitor'), 'Invalid or insufficient score: ' . json_encode($score));
        }
        unset($response['score']);
        assert_false($captcha->verify($form, $input, 'visitor'), 'A v2 response must never pass a v3 form.');
        foreach ([0.5, 0.9, 1] as $score) {
            $response['score'] = $score;
            assert_true($captcha->verify($form, $input, 'visitor'));
        }
        $response['action'] = $form['id'];
        assert_false($captcha->verify($form, $input + ['action' => $form['id']], 'visitor'), 'The raw form ID is not the normalized Google action.');
        $response = [...comet_captcha_test_response($form), 'score' => 0.6];
        $cometConfig['site']['forms']['captcha']['recaptcha_v3']['min_score'] = 0.7;
        $result = (new FormSubmission($site, $forms, ContentRepository::make(), null, $captcha))->submit($form, $input + ['min_score' => 0], 'visitor');
        assert_same(FormSubmission::ERROR, $result['status']);
        assert_true(isset($result['errors']['captcha']));
        assert_same([], ContentRepository::make()->all('inquiries'), 'Low-score responses must not create an entry.');
        $response['score'] = 0.7;
        assert_true($captcha->verify($form, $input, 'visitor'));
        foreach ([-0.1, 1.1, '0.5', false, [], INF, NAN] as $threshold) {
            $cometConfig['site']['forms']['captcha']['recaptcha_v3']['min_score'] = $threshold;
            assert_false($captcha->configured('recaptcha_v3'));
            assert_false($captcha->verify($form, $input, 'visitor'));
        }
    });
});

test('captcha v3 workspace thresholds and keys are independent from shared v2 and v3 settings', function (): void {
    comet_captcha_test_config(function (): void {
        global $cometConfig;
        [$site, , $form] = comet_captcha_test_setup('recaptcha_v3');
        $cometConfig['site']['forms']['captcha']['workspaces']['default']['recaptcha_v3'] = [
            'site_key' => 'workspace-public', 'secret_key' => 'workspace-secret', 'min_score' => 0.95, 'hostnames' => ['workspace.test'],
        ];
        $captcha = new FormCaptcha($site, static function (string $url, array $fields) use ($form): string {
            assert_same('workspace-secret', $fields['secret']);
            return json_encode([...comet_captcha_test_response($form), 'hostname' => 'workspace.test']);
        });
        assert_false($captcha->verify($form, ['g-recaptcha-response' => 'token'], 'visitor'));
        $markup = $captcha->markup($form, 'en', false);
        assert_true(str_contains($markup, 'data-form-captcha="recaptcha_v3"'));
        assert_true(str_contains($markup, 'data-sitekey="workspace-public"'));
        assert_true(str_contains($markup, 'data-action="' . FormCaptcha::action($form) . '"'));
        assert_true(str_contains($markup, 'name="g-recaptcha-response" data-captcha-token'));
        assert_false(str_contains($markup, 'workspace-secret'));
        assert_false(str_contains($captcha->markup($form, 'de', true), 'data-form-captcha='));
        assert_same("script-src 'none'", SiteScripts::cspDirective($markup));
    });
});

test('captcha workspace overrides keep key pairs isolated and incomplete configuration blocks sending', function (): void {
    comet_captcha_test_config(function (): void {
        global $cometConfig;
        [$site, , $form] = comet_captcha_test_setup();
        $other = new SiteRepository(new WorkspaceContext('other'));
        $settings = &$cometConfig['site']['forms']['captcha'];
        $settings['workspaces']['other']['turnstile'] = ['site_key' => 'other-public', 'secret_key' => 'other-private', 'hostnames' => ['other.test']];
        $captcha = new FormCaptcha($other, static function (string $url, array $fields) use ($form): string {
            assert_same('other-private', $fields['secret']);
            return json_encode(['success' => true, 'hostname' => 'other.test', 'action' => $form['id']]);
        });
        assert_true($captcha->verify($form, ['cf-turnstile-response' => 'token'], 'visitor'));
        assert_true(str_contains($captcha->markup($form, 'en', false), 'other-public'));
        assert_false(str_contains($captcha->markup($form, 'en', false), 'other-private'));
        assert_true(str_contains((new FormCaptcha($site))->markup($form, 'en', false), 'turnstile-public'));
        foreach ([[], ['site_key' => 'only-public'], ['site_key' => [], 'secret_key' => 'secret'], ['site_key' => 'key', 'secret_key' => 'secret', 'hostnames' => 'bad']] as $invalid) {
            $settings['workspaces']['other']['turnstile'] = $invalid;
            assert_false($captcha->configured('turnstile'));
            assert_false($captcha->verify($form, ['cf-turnstile-response' => 'token'], 'visitor'));
            assert_false(str_contains($captcha->markup($form, 'en', false), 'data-form-captcha='));
        }
        assert_false($captcha->verify([...$form, 'captcha' => 'unknown'], [], 'visitor'));
        assert_true($captcha->verify([...$form, 'captcha' => 'none'], [], 'visitor'));
    });
});

test('captcha pages load one initializer for multiple forms and previews never contact providers', function (): void {
    comet_captcha_test_config(function (): void {
        [$site, , $form] = comet_captcha_test_setup();
        $site->saveLayout('blog', 'detail', [comet_component_test_section([
            LayoutSchema::node('form', ['form' => $form['id']]), LayoutSchema::node('form', ['form' => $form['id']]),
        ])]);
        $html = comet_site_test_get('/blog/hello')['body'];
        assert_same(2, substr_count($html, 'data-form-captcha="turnstile"'));
        assert_same(1, substr_count($html, '<script type="module">' . FormCaptcha::script() . '</script>'));
        assert_same(2, substr_count($html, '<noscript>'));
        assert_false(str_contains($html, 'turnstile-private'));
        $ctx = comet_site_test_context(new SiteRepository());
        $ctx->preview = true;
        $preview = (new PageRenderer($ctx))->layoutPreview($ctx->type('blog'), 'detail', $ctx->findEntry('blog', 'hello'));
        assert_false(str_contains($preview, 'data-form-captcha='));
        assert_false(str_contains($preview, 'challenges.cloudflare.com'));
        assert_true(str_contains($preview, 'Spam verification appears on the live website.'));
    });
});

test('captcha CSP allowances require the exact initializer and include only providers present on the page', function (): void {
    $script = '<script type="module">' . FormCaptcha::script() . '</script>';
    assert_same("script-src 'none'", SiteScripts::cspDirective('<div data-form-captcha="turnstile"></div>'));
    $turnstile = '<div data-form-captcha="turnstile"></div>' . $script;
    $directive = SiteScripts::cspDirective($turnstile);
    assert_true(str_contains($directive, FormCaptcha::cspSource()));
    assert_true(str_contains($directive, 'https://challenges.cloudflare.com'));
    assert_false(str_contains($directive, 'google.com'));
    $base = "default-src 'self'; frame-src 'self' https://player.vimeo.com; " . $directive;
    $policy = FormCaptcha::extendPolicy($base, $turnstile);
    assert_true(str_contains($policy, "frame-src 'self' https://player.vimeo.com https://challenges.cloudflare.com"));
    assert_true(str_contains($policy, "connect-src 'self' https://challenges.cloudflare.com"));
    $google = '<div data-form-captcha="recaptcha"></div>' . $script;
    $directive = SiteScripts::cspDirective($google);
    assert_true(str_contains($directive, 'https://www.gstatic.com/recaptcha/'));
    assert_false(str_contains($directive, 'cloudflare.com'));
    $policy = FormCaptcha::extendPolicy("default-src 'self'; frame-src 'self'; " . $directive, $google);
    assert_true(str_contains($policy, 'https://recaptcha.google.com/recaptcha/'));
    assert_true(str_contains($policy, "connect-src 'self' https://www.google.com/recaptcha/"));
    $v3 = '<div data-form-captcha="recaptcha_v3"></div>' . $script;
    assert_same(SiteScripts::cspDirective($google), SiteScripts::cspDirective($v3));
    assert_same(SiteScripts::cspDirective($google), SiteScripts::cspDirective($v3 . $google));
    $both = $turnstile . '<div data-form-captcha="recaptcha"></div>';
    assert_true(str_contains(SiteScripts::cspDirective($both), 'https://www.google.com/recaptcha/'));
    assert_same($base, FormCaptcha::extendPolicy($base, 'no captcha'));
});

test('captcha page caches refresh when credentials become ready or public keys change', function (): void {
    comet_captcha_test_config(function (): void {
        global $cometConfig;
        [$site, , $form] = comet_captcha_test_setup();
        $site->saveLayout('blog', 'detail', [comet_component_test_section([LayoutSchema::node('form', ['form' => $form['id']])])]);
        $settings = &$cometConfig['site']['forms']['captcha']['turnstile'];
        $settings['secret_key'] = '';
        $first = comet_site_cache_test_get('/blog/hello');
        assert_same('MISS', $first['cache_status']);
        assert_false(str_contains($first['body'], 'data-form-captcha="turnstile"'));
        assert_same('HIT', comet_site_cache_test_get('/blog/hello')['cache_status']);
        $settings['secret_key'] = 'turnstile-private';
        $ready = comet_site_cache_test_get('/blog/hello');
        assert_same('MISS', $ready['cache_status']);
        assert_true(str_contains($ready['body'], 'data-sitekey="turnstile-public"'));
        $settings['site_key'] = 'rotated-public';
        $rotated = comet_site_cache_test_get('/blog/hello');
        assert_same('MISS', $rotated['cache_status']);
        assert_true(str_contains($rotated['body'], 'data-sitekey="rotated-public"'));
    });
});
