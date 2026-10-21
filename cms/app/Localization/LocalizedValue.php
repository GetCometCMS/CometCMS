<?php

declare(strict_types=1);

namespace CometCMS\Localization;

use CometCMS\Core\ValidationException;

/** Resource-owned values. Absence inherits; null, false, zero and empty values do not. */
final class LocalizedValue
{
    public static function is(mixed $value): bool
    {
        return is_array($value) && ($value['type'] ?? null) === 'localized';
    }

    public static function resolve(mixed $value, string $locale, string $default): mixed
    {
        if (!self::is($value)) return $value;
        $values = $value['values'] ?? [];
        return array_key_exists($locale, $values) ? $values[$locale] : ($values[$default] ?? null);
    }

    public static function tree(mixed $value, string $locale, string $default): mixed
    {
        if (self::is($value)) return self::tree(self::resolve($value, $locale, $default), $locale, $default);
        return is_array($value) ? array_map(static fn(mixed $item): mixed => self::tree($item, $locale, $default), $value) : $value;
    }

    public static function normalize(mixed $value, callable $normalize, array $locales): mixed
    {
        if (!self::is($value)) return $normalize($value);
        if (!is_array($value['values'] ?? null) || count($value['values']) > 100) {
            throw new ValidationException(['locale' => 'Invalid localized value.']);
        }
        $result = ['type' => 'localized', 'values' => [], 'sources' => []];
        foreach ($value['values'] as $locale => $item) {
            if (!in_array($locale, $locales, true) || self::is($item)) {
                throw new ValidationException(['locale' => 'Unsupported locale or nested localized value.']);
            }
            $result['values'][$locale] = $normalize($item);
            if (is_string($value['sources'][$locale] ?? null)) $result['sources'][$locale] = $value['sources'][$locale];
        }
        $default = (new LanguageSettings())->all()['default_locale'];
        foreach ($result['values'] as $locale => $item) {
            if ($locale !== $default && !isset($result['sources'][$locale])) $result['sources'][$locale] = self::sourceHash($result['values'][$default] ?? null);
        }
        return $result;
    }

    public static function set(mixed $current, string $locale, mixed $value, string $default): array
    {
        $result = self::is($current) ? $current : ['type' => 'localized', 'values' => $current === null ? [] : [$default => $current]];
        $result['values'][$locale] = $value;
        if ($locale !== $default) $result['sources'][$locale] = self::sourceHash($result['values'][$default] ?? null);
        return $result;
    }

    public static function remove(mixed $value, string $locale): mixed
    {
        if (self::is($value)) unset($value['values'][$locale], $value['sources'][$locale]);
        return $value;
    }

    public static function state(mixed $value, string $locale, string $default): string
    {
        if (!self::is($value)) return $locale === $default ? 'shared' : 'inherited';
        if (!array_key_exists($locale, $value['values'])) return 'inherited';
        if ($locale !== $default && ($value['sources'][$locale] ?? '') !== self::sourceHash($value['values'][$default] ?? null)) return 'review';
        return 'translated';
    }

    public static function hash(mixed $value): string
    {
        return hash('sha256', self::encode($value));
    }

    private static function sourceHash(mixed $value): string
    {
        return hash('fnv1a32', self::encode($value));
    }

    private static function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR);
    }
}
