<?php

declare(strict_types=1);

use CometCMS\Core\ConflictException;
use CometCMS\Core\ValidationException;
use CometCMS\Site\FormCaptcha;
use CometCMS\Site\FormCaptchaSettings;
use CometCMS\Site\SiteRepository;
use CometCMS\Workspaces\WorkspaceContext;

test('captcha UI settings mask secrets and override server keys only within their workspace', function (): void {
    comet_captcha_test_config(function (): void {
        $settings = new FormCaptchaSettings(new WorkspaceContext());
        $original = $settings->admin();
        assert_true($original['providers']['turnstile']['server_configured']);
        assert_same('', $original['providers']['turnstile']['secret_key']);
        assert_false(str_contains(json_encode($original), 'turnstile-private'));
        $saved = $settings->save(['revision' => '', 'providers' => ['turnstile' => [
            'mode' => 'workspace', 'site_key' => 'ui-public', 'secret_key' => 'ui-secret', 'hostnames' => "EXAMPLE.test\nwww.example.test,example.test",
        ]]]);
        assert_same('workspace', $saved['providers']['turnstile']['mode']);
        assert_true($saved['providers']['turnstile']['configured']);
        assert_true($saved['providers']['turnstile']['secret_configured']);
        assert_same('', $saved['providers']['turnstile']['secret_key']);
        assert_false(str_contains(json_encode($saved), 'ui-secret'));
        assert_same(['example.test', 'www.example.test'], $settings->effective('turnstile')['hostnames']);
        assert_same('ui-secret', $settings->effective('turnstile')['secret_key']);
        assert_same('turnstile-private', (new FormCaptchaSettings(new WorkspaceContext('other')))->effective('turnstile')['secret_key']);
        $form = ['id' => 'type-contact', 'captcha' => 'turnstile'];
        $captcha = new FormCaptcha(new SiteRepository(), static function (string $url, array $fields): string {
            assert_same('ui-secret', $fields['secret']);
            return '{"success":true,"hostname":"example.test","action":"type-contact"}';
        });
        assert_true($captcha->verify($form, ['cf-turnstile-response' => 'token'], 'visitor'));
        assert_true(str_contains($captcha->markup($form, 'en', false), 'ui-public'));
        assert_false(str_contains($captcha->markup($form, 'en', false), 'ui-secret'));
    });
});

test('captcha UI settings preserve blank secrets support rotation and reject stale revisions', function (): void {
    $settings = new FormCaptchaSettings(new WorkspaceContext());
    $saved = $settings->save(['providers' => ['recaptcha_v3' => ['mode' => 'workspace', 'site_key' => 'v3-public', 'secret_key' => 'original-secret', 'min_score' => 0.6]]]);
    $provider = $saved['providers']['recaptcha_v3'];
    $provider['min_score'] = 0.8;
    $updated = $settings->save(['revision' => $saved['revision'], 'providers' => ['recaptcha_v3' => $provider]]);
    assert_same('original-secret', $settings->effective('recaptcha_v3')['secret_key']);
    assert_same(0.8, $settings->effective('recaptcha_v3')['min_score']);
    assert_throws(ConflictException::class, fn() => $settings->save(['revision' => $saved['revision'], 'providers' => ['recaptcha_v3' => $provider]]));
    $provider['secret_key'] = 'rotated-secret';
    $settings->save(['revision' => $updated['revision'], 'providers' => ['recaptcha_v3' => $provider]]);
    assert_same('rotated-secret', $settings->effective('recaptcha_v3')['secret_key']);
});

test('captcha UI settings validate keys hostnames and scores before changing any stored configuration', function (): void {
    $settings = new FormCaptchaSettings(new WorkspaceContext());
    foreach ([
        ['mode' => 'workspace', 'site_key' => 'public', 'secret_key' => ''],
        ['mode' => 'workspace', 'site_key' => [], 'secret_key' => 'secret'],
        ['mode' => 'workspace', 'site_key' => 'public', 'secret_key' => "secret\n"],
        ['mode' => 'workspace', 'site_key' => 'public', 'secret_key' => 'secret', 'hostnames' => 'https://example.test'],
        ['mode' => 'workspace', 'site_key' => 'public', 'secret_key' => 'secret', 'min_score' => 2],
        ['mode' => 'workspace', 'site_key' => 'public', 'secret_key' => 'secret', 'min_score' => '0.7'],
    ] as $invalid) {
        assert_throws(ValidationException::class, fn() => $settings->save(['providers' => ['recaptcha_v3' => $invalid]]));
        assert_same('', $settings->admin()['revision']);
    }
    assert_throws(ValidationException::class, fn() => $settings->save(['providers' => ['unknown' => ['mode' => 'workspace']]]));
    assert_throws(ValidationException::class, fn() => $settings->save(['providers' => 'bad']));
});

test('captcha UI settings can return to server configuration and stay out of website exports', function (): void {
    comet_captcha_test_config(function (): void {
        $settings = new FormCaptchaSettings(new WorkspaceContext());
        $settings->save(['providers' => ['recaptcha' => ['mode' => 'workspace', 'site_key' => 'ui-public', 'secret_key' => 'ui-secret']]]);
        assert_file_exists_at(comet_test_workspace_path() . '/form-captcha.json');
        assert_false(str_contains(json_encode((new SiteRepository())->settings()), 'ui-secret'));
        $backup = (new \CometCMS\Backups\BackupService())->create(['id' => 'admin'], ['site']);
        $zip = new ZipArchive();
        assert_true($zip->open(COMET_STORAGE . '/backups/' . $backup['name']));
        for ($i = 0; $i < $zip->numFiles; $i++) assert_false(str_contains((string) $zip->getFromIndex($i), 'ui-secret'));
        $zip->close();
        $saved = $settings->save(['providers' => ['recaptcha' => ['mode' => 'server']]]);
        assert_same('server', $saved['providers']['recaptcha']['mode']);
        assert_false($saved['providers']['recaptcha']['secret_configured']);
        assert_same('recaptcha-private', $settings->effective('recaptcha')['secret_key']);
        assert_throws(ValidationException::class, fn() => $settings->save(['providers' => ['recaptcha' => ['mode' => 'workspace', 'site_key' => 'ui-public', 'secret_key' => '']]]));
    });
});

test('captcha UI settings endpoints enforce website management and return only masked metadata', function (): void {
    $action = '(new \\CometCMS\\Controllers\\Admin\\SiteController(new \\CometCMS\\Core\\Http()))->updateFormSettings();';
    $body = ['providers' => ['turnstile' => ['mode' => 'workspace', 'site_key' => 'api-public', 'secret_key' => 'api-secret']]];
    [$status, $json] = comet_admin_request('admin', '', 'PUT', '/admin/api/site/form-settings', $action, $body);
    assert_same(200, $status);
    assert_true($json['data']['providers']['turnstile']['secret_configured']);
    assert_false(str_contains(json_encode($json), 'api-secret'));
    [$status] = comet_admin_request('wes', COMET_WRITER_SEED, 'PUT', '/admin/api/site/form-settings', $action, $body);
    assert_same(403, $status);
    [$status] = comet_admin_request('admin', '', 'PUT', '/admin/api/site/form-settings', 'unset($_SESSION["user_id"]); ' . $action, $body);
    assert_same(401, $status);
    [$status] = comet_admin_request('admin', '', 'PUT', '/admin/api/site/form-settings', '$_SESSION["cometcms_csrf"] = "different-token"; ' . $action, $body);
    assert_same(419, $status);
});
