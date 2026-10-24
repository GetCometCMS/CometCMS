<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Core\ConflictException;
use CometCMS\Core\ValidationException;
use CometCMS\Storage\JsonStore;
use CometCMS\Workspaces\WorkspaceContext;

/** Workspace credentials live outside exported site files. Admin responses never contain secrets. */
final class FormCaptchaSettings
{
    public const PROVIDERS = ['turnstile', 'recaptcha', 'recaptcha_v3'];

    private JsonStore $store;

    public function __construct(private readonly WorkspaceContext $workspace)
    {
        $this->store = new JsonStore($workspace->root());
    }

    private function stored(): array
    {
        return $this->store->read('form-captcha') ?? [];
    }

    private function server(string $provider): array
    {
        $config = comet_config('site.forms.captcha', []);
        if (!is_array($config)) return [];
        $workspace = $config['workspaces'][$this->workspace->slug()] ?? [];
        $settings = is_array($workspace) && array_key_exists($provider, $workspace) ? $workspace[$provider] : ($config[$provider] ?? []);

        return is_array($settings) ? $settings : [];
    }

    public function effective(string $provider): array
    {
        $providers = $this->stored()['providers'] ?? [];
        if (array_key_exists($provider, $providers)) return is_array($providers[$provider]) ? $providers[$provider] : [];

        return $this->server($provider);
    }

    public function admin(): array
    {
        $stored = $this->stored();
        $providers = [];
        foreach (self::PROVIDERS as $provider) {
            $local = $stored['providers'][$provider] ?? null;
            $server = $this->server($provider);
            $config = is_array($local) ? $local : $server;
            $providers[$provider] = [
                'mode' => $local !== null ? 'workspace' : 'server',
                'site_key' => is_string($config['site_key'] ?? null) ? $config['site_key'] : '',
                'secret_key' => '',
                'secret_configured' => is_string($local['secret_key'] ?? null) && trim($local['secret_key']) !== '',
                'hostnames' => is_array($config['hostnames'] ?? null) ? implode("\n", array_filter($config['hostnames'], 'is_string')) : '',
                'configured' => FormCaptcha::validConfiguration($provider, $config),
                'server_configured' => FormCaptcha::validConfiguration($provider, $server),
                ...($provider === 'recaptcha_v3' ? ['min_score' => $config['min_score'] ?? 0.5] : []),
            ];
        }

        return ['providers' => $providers, 'revision' => $stored['revision'] ?? ''];
    }

    /** Blank secrets retain a saved workspace secret; switching to server mode removes the override. */
    public function save(array $input): array
    {
        return $this->store->synchronized(function () use ($input): array {
            $stored = $this->stored();
            if (isset($input['revision']) && $input['revision'] !== ($stored['revision'] ?? '')) throw new ConflictException();
            if (!is_array($input['providers'] ?? null)) throw new ValidationException(['providers' => 'Choose CAPTCHA settings.']);
            $providers = $stored['providers'] ?? [];
            $errors = [];
            foreach ($input['providers'] as $provider => $settings) {
                $path = 'providers.' . $provider;
                if (!in_array($provider, self::PROVIDERS, true) || !is_array($settings) || !in_array($settings['mode'] ?? '', ['server', 'workspace'], true)) {
                    $errors[$path] = 'Choose a supported provider and configuration source.';
                    continue;
                }
                if ($settings['mode'] === 'server') {
                    unset($providers[$provider]);
                    continue;
                }
                $config = [];
                foreach (['site_key', 'secret_key'] as $key) {
                    $value = $settings[$key] ?? '';
                    if (!is_string($value) || strlen($value) > 4096 || preg_match('/[\x00-\x1f\x7f]/', $value)) {
                        $errors[$path . '.' . $key] = 'Enter a valid key.';
                        continue;
                    }
                    $value = trim($value);
                    if ($key === 'secret_key' && $value === '') $value = $providers[$provider]['secret_key'] ?? '';
                    if ($value === '') $errors[$path . '.' . $key] = 'This key is required.';
                    $config[$key] = $value;
                }
                $hostnames = $settings['hostnames'] ?? [];
                if (is_string($hostnames)) $hostnames = preg_split('/[\s,]+/', trim($hostnames), -1, PREG_SPLIT_NO_EMPTY);
                if (!is_array($hostnames) || count($hostnames) > 100) {
                    $errors[$path . '.hostnames'] = 'Enter up to 100 hostnames.';
                } else {
                    $config['hostnames'] = [];
                    foreach ($hostnames as $hostname) {
                        if (!is_string($hostname) || strlen($hostname) > 253 || !preg_match('/^[a-z0-9.-]+$/iD', trim($hostname))) {
                            $errors[$path . '.hostnames'] = 'Use hostnames without a scheme, port or wildcard.';
                            break;
                        }
                        $config['hostnames'][] = strtolower(trim($hostname));
                    }
                    $config['hostnames'] = array_values(array_unique($config['hostnames']));
                }
                if ($provider === 'recaptcha_v3') {
                    $config['min_score'] = $settings['min_score'] ?? 0.5;
                    $score = $config['min_score'];
                    if ((!is_int($score) && !is_float($score)) || !is_finite((float) $score) || $score < 0 || $score > 1) $errors[$path . '.min_score'] = 'Choose a score from 0 to 1.';
                }
                $providers[$provider] = $config;
            }
            if ($errors !== []) throw new ValidationException($errors);
            $this->store->write(['providers' => $providers, 'revision' => bin2hex(random_bytes(12))], 'form-captcha');
            chmod($this->store->path('form-captcha') . '.json', 0600);

            return $this->admin();
        }, 'form-captcha');
    }
}
