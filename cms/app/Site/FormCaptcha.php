<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Core\Security;
use CometCMS\Localization\Messages;

/** Public form widgets and server-only verification. The transport is injectable for offline tests. */
final class FormCaptcha
{
    private const PROVIDERS = [
        'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'recaptcha' => 'https://www.google.com/recaptcha/api/siteverify',
        'recaptcha_v3' => 'https://www.google.com/recaptcha/api/siteverify',
    ];

    private const MAX_RESPONSE_BYTES = 16384;

    public function __construct(private readonly SiteRepository $site, private readonly ?\Closure $transport = null) {}

    /** A workspace override replaces the entire provider configuration, keeping key pairs together. */
    private function configuration(string $provider): array
    {
        return (new FormCaptchaSettings($this->site->workspace()))->effective($provider);
    }

    public function configured(string $provider): bool
    {
        return self::validConfiguration($provider, $this->configuration($provider));
    }

    public static function validConfiguration(string $provider, array $config): bool
    {
        if (!isset(self::PROVIDERS[$provider])) {
            return false;
        }
        foreach (['site_key', 'secret_key'] as $key) {
            if (!is_string($config[$key] ?? null) || trim($config[$key]) === '') {
                return false;
            }
        }
        if (!is_array($config['hostnames'] ?? [])) {
            return false;
        }
        foreach ($config['hostnames'] ?? [] as $hostname) {
            if (!is_string($hostname) || !preg_match('/^[a-z0-9.-]+$/iD', $hostname)) {
                return false;
            }
        }
        if ($provider === 'recaptcha_v3' && !self::validScore($config['min_score'] ?? 0.5)) {
            return false;
        }

        return true;
    }

    public function markup(array $form, string $locale, bool $preview): string
    {
        $provider = $form['captcha'] ?? 'none';
        if ($provider === 'none') {
            return '';
        }
        if ($preview || !$this->configured($provider)) {
            return '<p class="c-form-help" role="status">' . Security::e(Messages::get($preview ? 'captcha_preview' : 'captcha_unavailable', $locale)) . '</p>';
        }

        return '<div class="c-form-captcha" data-form-captcha="' . Security::e($provider) . '"'
            . ' data-sitekey="' . Security::e($this->configuration($provider)['site_key']) . '"'
            . ' data-action="' . Security::e(self::action($form)) . '"'
            . ' data-prompt="' . Security::e(Messages::get('captcha_required', $locale)) . '"'
            . ' data-checking="' . Security::e(Messages::get('captcha_checking', $locale)) . '"'
            . ' data-error="' . Security::e(Messages::get('captcha_unavailable', $locale)) . '">'
            . '<div data-captcha-widget></div>'
            . ($provider === 'recaptcha_v3' ? '<input type="hidden" name="g-recaptcha-response" data-captcha-token value="">' : '')
            . '<p class="c-form-help" data-captcha-status role="status" aria-live="polite"></p>'
            . '<noscript><p class="c-form-help">' . Security::e(Messages::get('captcha_javascript', $locale)) . '</p></noscript></div>';
    }

    /** Invalid, expired, reused, missing and unverifiable tokens all fail closed before any files or entries are saved. */
    public function verify(array $form, array $input, string $visitor): bool
    {
        $provider = $form['captcha'] ?? 'none';
        if ($provider === 'none') {
            return true;
        }
        if (!$this->configured($provider)) {
            return false;
        }
        $token = $input[$provider === 'turnstile' ? 'cf-turnstile-response' : 'g-recaptcha-response'] ?? null;
        if (!is_string($token) || trim($token) === '' || strlen($token) > ($provider === 'turnstile' ? 2048 : 8192)) {
            return false;
        }
        $config = $this->configuration($provider);
        $fields = ['secret' => $config['secret_key'], 'response' => $token];
        if (filter_var($visitor, FILTER_VALIDATE_IP)) {
            $fields['remoteip'] = $visitor;
        }
        try {
            $body = $this->transport !== null ? ($this->transport)(self::PROVIDERS[$provider], $fields) : $this->post(self::PROVIDERS[$provider], $fields);
            if (!is_string($body) || strlen($body) > self::MAX_RESPONSE_BYTES) {
                return false;
            }
            $response = json_decode($body, true);
        } catch (\Throwable) {
            // Never return or log the request: it contains the secret and the visitor's token.
            return false;
        }
        if (!is_array($response) || ($response['success'] ?? null) !== true || ($response['error-codes'] ?? []) !== [] || !is_string($response['hostname'] ?? null) || $response['hostname'] === '') {
            return false;
        }
        $hostnames = array_map('strtolower', $config['hostnames'] ?? []);
        if ($hostnames !== [] && !in_array(strtolower($response['hostname']), $hostnames, true)) {
            return false;
        }

        if ($provider === 'recaptcha_v3') {
            return ($response['action'] ?? null) === self::action($form)
                && self::validScore($response['score'] ?? null)
                && $response['score'] >= ($config['min_score'] ?? 0.5);
        }

        // Turnstile tokens are bound to their form; v2 keys must not accept a v3 score response.
        return $provider === 'turnstile' ? ($response['action'] ?? null) === self::action($form) : !isset($response['score']);
    }

    /** Google action names allow letters, numbers, slashes and underscores, without hyphens. */
    public static function action(array $form): string
    {
        return ($form['captcha'] ?? '') === 'recaptcha_v3' ? str_replace('-', '_', $form['id']) : $form['id'];
    }

    private static function validScore(mixed $score): bool
    {
        return (is_int($score) || is_float($score)) && is_finite((float) $score) && $score >= 0 && $score <= 1;
    }

    private function post(string $url, array $fields): string|false
    {
        $content = http_build_query($fields);
        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            $body = '';
            curl_setopt_array($handle, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => $content,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                    if (strlen($body) + strlen($chunk) > self::MAX_RESPONSE_BYTES) {
                        return 0;
                    }
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            $ok = curl_exec($handle);
            $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

            return $ok !== false && $status === 200 ? $body : false;
        }

        $context = stream_context_create([
            'http' => ['method' => 'POST', 'content' => $content, 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'timeout' => 10, 'follow_location' => 0, 'ignore_errors' => true],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $context, 0, self::MAX_RESPONSE_BYTES + 1);
        $headers = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : (${'http_response_header'} ?? []);

        return preg_match('~^HTTP/\S+ 200(?: |$)~', $headers[0] ?? '') ? $body : false;
    }

    /** Keys or configuration readiness can change without a content edit. No secret enters the cache key. */
    public function cacheVersion(): string
    {
        $settings = [];
        foreach (array_keys(self::PROVIDERS) as $provider) {
            $settings[$provider] = [$this->configured($provider), $this->configuration($provider)['site_key'] ?? ''];
        }

        return hash('sha256', (string) json_encode($settings) . self::cspSource());
    }

    public static function script(): string
    {
        return (string) file_get_contents(__DIR__ . '/assets/form-captcha.js') . "\ninitFormCaptchas(document);";
    }

    public static function cspSource(): string
    {
        return "'sha256-" . base64_encode(hash('sha256', self::script(), true)) . "'";
    }

    /** Only pages with the bundled initializer and a provider widget receive its CSP allowances. */
    public static function cspSources(string $html): array
    {
        $sources = ['script-src' => [], 'frame-src' => [], 'connect-src' => []];
        if (!str_contains($html, '>' . self::script() . '</script>')) {
            return $sources;
        }
        // The initializer itself contains selectors for providers that may be absent from the page.
        $html = str_replace(self::script(), '', $html);
        if (str_contains($html, 'data-form-captcha="turnstile"')) {
            foreach ($sources as &$values) {
                $values[] = 'https://challenges.cloudflare.com';
            }
            unset($values);
        }
        if (str_contains($html, 'data-form-captcha="recaptcha"') || str_contains($html, 'data-form-captcha="recaptcha_v3"')) {
            $sources['script-src'] = [...$sources['script-src'], 'https://www.google.com/recaptcha/', 'https://www.gstatic.com/recaptcha/'];
            $sources['frame-src'] = [...$sources['frame-src'], 'https://www.google.com/recaptcha/', 'https://recaptcha.google.com/recaptcha/'];
            $sources['connect-src'][] = 'https://www.google.com/recaptcha/';
        }

        return $sources;
    }

    public static function extendPolicy(string $policy, string $html): string
    {
        foreach (self::cspSources($html) as $directive => $sources) {
            if ($directive === 'script-src' || $sources === []) {
                continue;
            }
            $value = implode(' ', $sources);
            $policy = str_contains($policy, $directive . ' ')
                ? preg_replace('/(' . $directive . ' [^;]*)/', '$1 ' . $value, $policy)
                : $policy . '; ' . $directive . " 'self' " . $value;
        }

        return $policy;
    }
}
