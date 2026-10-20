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
        $root = ($this->workspace ?? WorkspaceContext::active())->root();
        if (!is_file($root . '/languages.json')) return ['locales' => ['en'], 'default_locale' => 'en'];
        return self::$cache[$root] ??= (new JsonStore($root))->read('languages') ?? ['locales' => ['en'], 'default_locale' => 'en'];
    }

    public function save(array $input): array
    {
        $settings = $this->validate($input);
        $workspace = $this->workspace ?? WorkspaceContext::active();
        (new JsonStore($workspace->root()))->write($settings, 'languages');
        self::$cache[$workspace->root()] = $settings;
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
        $workspace = $this->workspace ?? WorkspaceContext::active();
        $removed = array_diff($this->all()['locales'], $locales);
        if ($removed !== []) {
            $check = static function (mixed $value) use (&$check, $removed): bool {
                if (!is_array($value)) return false;
                if (LocalizedValue::is($value) && array_intersect(array_keys($value['values']), $removed)) return true;
                if (isset($value['publication']) && array_intersect(array_keys($value['publication']), $removed)) return true;
                foreach ($value as $item) if ($check($item)) return true;
                return false;
            };
            foreach (['content', 'content-types', 'site', 'media-meta'] as $directory) {
                $path = $workspace->path($directory);
                if (!is_dir($path)) continue;
                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
                foreach ($files as $file) {
                    if ($file->getExtension() === 'json' && $check(json_decode((string) file_get_contents($file->getPathname()), true))) {
                        throw new ValidationException(['locales' => 'Remove saved translations before removing a workspace language, or disable it in the website and content type language selectors.']);
                    }
                }
            }
        }
        return ['locales' => $locales, 'default_locale' => $default];
    }

    public static function code(mixed $value): string
    {
        $value = strtolower(str_replace(['_', ' '], '-', trim((string) $value)));
        return preg_match('/^[a-z]{2,8}(?:-[a-z0-9]{2,8})*$/', $value) ? $value : '';
    }

    public function requireLocale(string $locale, ?array $subset = null): string
    {
        $settings = $this->all();
        $locale = $locale !== '' ? $locale : $settings['default_locale'];
        if (!in_array($locale, $subset ?? $settings['locales'], true)) {
            throw new ValidationException(['locale' => 'Unsupported locale: ' . $locale]);
        }
        return $locale;
    }
}
