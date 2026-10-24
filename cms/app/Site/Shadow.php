<?php

declare(strict_types=1);

namespace CometCMS\Site;

/**
 * Box shadows are a fixed soft scale plus the theme's own presets (see
 * Theme::shadows); text shadows keep a fixed list. Custom shadows use bounded
 * values, so no shadow can inject CSS.
 */
final class Shadow
{
    /** Fixed box shadows from site.css. */
    public const FIXED = ['', 'none', 'xs', 'sm', 'md', 'lg', 'xl'];

    public const TEXT_PRESETS = ['', 'none', 'sm', 'md', 'lg', 'hard', 'glow'];

    /** Not a theme color: shadows are often plain black, whatever the palette. */
    public const BLACK = 'black';

    /** Theme preset tokens; the shipped presets keep the names layouts already use. */
    public static function isPresetKey(mixed $key): bool
    {
        return is_string($key) && preg_match('/^(?:hard|glow|inset|shadow-[a-z0-9][a-z0-9-]{0,47})$/', $key) === 1;
    }

    public static function normalize(mixed $value, bool $text = false): string|array
    {
        if (is_string($value)) {
            $valid = $text ? in_array($value, self::TEXT_PRESETS, true) : in_array($value, self::FIXED, true) || self::isPresetKey($value);
            return $valid ? $value : '';
        }
        if (!is_array($value) || $value === []) return '';
        $color = $value['color'] ?? 'primary';
        if (!is_string($color) || ($color !== self::BLACK && !in_array($color, Theme::COLOR_KEYS, true) && !Theme::isCustomColorKey($color))) return '';
        $result = ['color' => $color];
        foreach (['x' => [-100, 100, $text ? 3 : 6], 'y' => [-100, 100, $text ? 3 : 6], 'blur' => [0, 200, 0], 'spread' => [-100, 100, 0], 'opacity' => [0, 100, 100]] as $key => [$min, $max, $default]) {
            if ($text && $key === 'spread') continue;
            $result[$key] = is_numeric($value[$key] ?? null) ? max($min, min($max, (int) $value[$key])) : $default;
        }
        if (!$text) $result['inset'] = ($value['inset'] ?? false) === true;
        return $result;
    }

    public static function css(array $value, bool $text = false): string
    {
        $shadow = self::normalize($value, $text);
        if (!is_array($shadow)) return '';
        $base = $shadow['color'] === self::BLACK ? '#000' : 'var(--c-' . $shadow['color'] . ',var(--c-primary))';
        $color = 'color-mix(in srgb,' . $base . ' ' . $shadow['opacity'] . '%,transparent)';
        return (!$text && $shadow['inset'] ? 'inset ' : '') . $shadow['x'] . 'px ' . $shadow['y'] . 'px ' . $shadow['blur'] . 'px '
            . (!$text ? $shadow['spread'] . 'px ' : '') . $color;
    }
}
