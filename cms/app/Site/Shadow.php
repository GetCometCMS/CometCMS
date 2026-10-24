<?php

declare(strict_types=1);

namespace CometCMS\Site;

/** Presets stay compatible with existing layouts; custom shadows use bounded values. */
final class Shadow
{
    public const PRESETS = ['', 'none', 'sm', 'md', 'lg', 'hard', 'glow', 'inset'];

    public static function normalize(mixed $value, bool $text = false): string|array
    {
        if (is_string($value)) return in_array($value, self::PRESETS, true) && (!$text || $value !== 'inset') ? $value : '';
        if (!is_array($value) || $value === []) return '';
        $color = $value['color'] ?? 'primary';
        if (!is_string($color) || (!in_array($color, Theme::COLOR_KEYS, true) && !Theme::isCustomColorKey($color))) return '';
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
        $color = 'color-mix(in srgb,var(--c-' . $shadow['color'] . ',var(--c-primary)) ' . $shadow['opacity'] . '%,transparent)';
        return (!$text && $shadow['inset'] ? 'inset ' : '') . $shadow['x'] . 'px ' . $shadow['y'] . 'px ' . $shadow['blur'] . 'px '
            . (!$text ? $shadow['spread'] . 'px ' : '') . $color;
    }
}
