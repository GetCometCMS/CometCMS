<?php

declare(strict_types=1);

namespace CometCMS\Site;

/** Two-color gradients use palette tokens, so they follow the site's color mode. */
final class Gradient
{
    public static function normalize(mixed $value): array
    {
        if (!is_array($value) || !in_array($value['type'] ?? null, ['linear', 'radial'], true)) {
            return [];
        }
        foreach (['from', 'to'] as $key) {
            $color = $value[$key] ?? null;
            if (!is_string($color) || (!in_array($color, Theme::COLOR_KEYS, true) && !Theme::isCustomColorKey($color))) {
                return [];
            }
        }

        $gradient = ['type' => $value['type'], 'from' => $value['from'], 'to' => $value['to']];
        if ($value['type'] === 'linear') {
            $gradient['angle'] = is_numeric($value['angle'] ?? null) ? max(0, min(360, (int) $value['angle'])) : 135;
        }

        return $gradient;
    }

    public static function css(mixed $value): string
    {
        $gradient = self::normalize($value);
        if ($gradient === []) return '';

        // Deleted custom colors fall back to the corresponding built-in color.
        $colors = 'var(--c-' . $gradient['from'] . ', var(--c-primary)),var(--c-' . $gradient['to'] . ', var(--c-accent))';

        return $gradient['type'] === 'radial'
            ? 'radial-gradient(ellipse at center,' . $colors . ')'
            : 'linear-gradient(' . $gradient['angle'] . 'deg,' . $colors . ')';
    }
}
