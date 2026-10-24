<?php

declare(strict_types=1);

namespace CometCMS\Localization;

use CometCMS\Core\ValidationException;
use CometCMS\Storage\JsonStore;
use CometCMS\Workspaces\WorkspaceContext;

final class LanguageSettings
{
    private static array $cache = [];

    public function __construct(private readonly ?WorkspaceContext $workspace = null) {}

    public function all(): array
    {
        return array_intersect_key($this->stored(), array_flip(['locales', 'default_locale']));
    }

    /** Include inactive languages when normalizing saved translations, never in language switchers. */
    public function knownLocales(): array
    {
        $settings = $this->stored();
        return array_values(array_unique([...$settings['locales'], ...($settings['inactive_locales'] ?? [])]));
    }

    public function inactiveLocales(): array
    {
        return $this->stored()['inactive_locales'] ?? [];
    }

    private function stored(): array
    {
        $root = ($this->workspace ?? WorkspaceContext::active())->root();
        if (!is_file($root . '/languages.json')) return $this->existingLanguages($root);
        return self::$cache[$root] ??= (new JsonStore($root))->read('languages') ?? ['locales' => ['en'], 'default_locale' => 'en'];
    }

    /** Before workspace settings existed, language choices lived in the site and schemas. */
    private function existingLanguages(string $root): array
    {
        $settings = [];
        foreach ([$root . '/site/settings.json', ...(glob($root . '/content-types/*.json') ?: [])] as $file) {
            $value = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            if (is_array($value)) $settings[] = $value;
        }
        $settings = [...$settings, ...array_values((array) comet_config('content_types', []))];
        $locales = [];
        $default = '';
        foreach ($settings as $setting) {
            if (!is_array($setting)) continue;
            $code = self::code($setting['default_locale'] ?? '');
            if ($code !== '') {
                $default = $default ?: $code;
                $locales[] = $code;
            }
            foreach ((array) ($setting['locales'] ?? []) as $locale) {
                if (($code = self::code($locale)) !== '') $locales[] = $code;
            }
        }
        $locales = array_values(array_unique($locales));
        $languages = ['locales' => $locales ?: ['en'], 'default_locale' => $default ?: ($locales[0] ?? 'en')];
        if ($locales !== []) {
            // Freeze the old choices once, before later schema saves drop redundant lists.
            $store = new JsonStore($root);
            return $store->synchronized(function () use ($store, $root, $languages): array {
                $existing = $store->read('languages');
                if ($existing === null) $store->write($languages, 'languages');
                return self::$cache[$root] = $existing ?? $languages;
            }, 'languages');
        }
        return $languages;
    }

    public function save(array $input): array
    {
        $settings = $this->validate($input);
        $workspace = $this->workspace ?? WorkspaceContext::active();
        // Removing a language only deactivates it. Keep recognizing its stored
        // values so editing active languages cannot discard or invalidate them.
        $inactive = array_values(array_diff($this->knownLocales(), $settings['locales']));
        $stored = $settings;
        if ($inactive !== []) $stored['inactive_locales'] = $inactive;
        (new JsonStore($workspace->root()))->write($stored, 'languages');
        self::$cache[$workspace->root()] = $stored;
        \CometCMS\Cache\ApiCache::fromConfig($workspace)->clear();
        return $settings;
    }

    public function validate(array $input): array
    {
        $locales = array_values(array_unique(array_map(self::code(...), (array) ($input['locales'] ?? []))));
        $default = self::code((string) ($input['default_locale'] ?? ''));
        if ($locales === [] || in_array('', $locales, true) || !in_array($default, $locales, true) || count($locales) > 100) {
            throw new ValidationException(['locales' => 'Choose at least one valid language and a default language.']);
        }
        return ['locales' => $locales, 'default_locale' => $default];
    }

    public static function code(mixed $value): string
    {
        $value = strtolower(str_replace(['_', ' '], '-', trim((string) $value)));
        return preg_match('/^[a-z]{2,8}(?:-[a-z0-9]{2,8})*$/', $value) ? $value : '';
    }

    public function requireLocale(string $locale): string
    {
        $settings = $this->all();
        $locale = $locale !== '' ? $locale : $settings['default_locale'];
        if (!in_array($locale, $settings['locales'], true)) {
            throw new ValidationException(['locale' => 'Unsupported locale: ' . $locale]);
        }
        return $locale;
    }
}
