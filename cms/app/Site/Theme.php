<?php

declare(strict_types=1);

namespace CometCMS\Site;

/**
 * Website design tokens. Themes are data only: every value is checked against
 * a fixed vocabulary before it reaches the generated stylesheet, so a theme
 * can never inject markup or script. Custom CSS is the single free-form value
 * and is restricted to administrators by the controller.
 */
final class Theme
{
    public const COLOR_KEYS = ['primary', 'secondary', 'accent', 'background', 'surface', 'text', 'muted', 'border'];

    public const MODES = ['light', 'dark', 'auto'];

    /** Default corner for buttons, images and cards: none, a radius token, or fully round. */
    public const RADII = ['none', 'sm', 'md', 'lg', 'full'];

    /** Corner radius tokens in px. */
    public const RADIUS_TOKENS = ['sm', 'md', 'lg'];

    /** Spacing scale tokens in px, used by padding, space below, gaps and spacers. */
    public const SPACE_TOKENS = ['xs', 'sm', 'md', 'lg', 'xl', '2xl'];

    /** Density presets of earlier themes, kept to convert them into a spacing scale. */
    public const SPACING = ['compact' => 0.8, 'normal' => 1.0, 'relaxed' => 1.25];

    public const TYPE_SCALES = ['1.125', '1.2', '1.25', '1.333'];

    public const MAX_CUSTOM_CSS = 100000;

    public const MAX_CUSTOM_COLORS = 64;

    /**
     * Local font stacks only: nothing is fetched from third-party font CDNs,
     * so visitors' IP addresses never leave the site.
     */
    public const FONT_STACKS = [
        'system' => 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
        'neo-grotesque' => 'Inter, Roboto, "Helvetica Neue", "Arial Nova", "Nimbus Sans", Arial, sans-serif',
        'humanist' => 'Seravek, "Gill Sans Nova", Ubuntu, Calibri, "DejaVu Sans", source-sans-pro, sans-serif',
        'geometric' => 'Avenir, Montserrat, Corbel, "URW Gothic", source-sans-pro, sans-serif',
        'rounded' => 'ui-rounded, "Hiragino Maru Gothic ProN", Quicksand, Comfortaa, Manjari, "Arial Rounded MT", "Arial Rounded MT Bold", Calibri, source-sans-pro, sans-serif',
        'transitional' => 'Charter, "Bitstream Charter", "Sitka Text", Cambria, serif',
        'old-style' => '"Iowan Old Style", "Palatino Linotype", "URW Palladio L", P052, serif',
        'didone' => 'Didot, "Bodoni MT", "Noto Serif Display", "URW Palladio L", P052, Sylfaen, serif',
        'slab' => 'Rockwell, "Rockwell Nova", "Roboto Slab", "DejaVu Serif", "Sitka Small", serif',
        'monospace' => 'ui-monospace, "Cascadia Code", "Source Code Pro", Menlo, Consolas, "DejaVu Sans Mono", monospace',
    ];

    public static function defaults(): array
    {
        return [
            'mode' => 'light',
            'colors' => [
                'primary' => '#2563eb',
                'secondary' => '#0f172a',
                'accent' => '#f59e0b',
                'background' => '#ffffff',
                'surface' => '#f1f5f9',
                'text' => '#0f172a',
                'muted' => '#64748b',
                'border' => '#e2e8f0',
            ],
            'dark_colors' => [
                'primary' => '#60a5fa',
                'secondary' => '#e2e8f0',
                'accent' => '#fbbf24',
                'background' => '#0b1120',
                'surface' => '#162033',
                'text' => '#e2e8f0',
                'muted' => '#94a3b8',
                'border' => '#1e293b',
            ],
            'custom_colors' => [],
            'fonts' => [
                'body' => 'system',
                'heading' => 'system',
            ],
            'base_size' => 17,
            'line_height' => 1.65,
            'type_scale' => '1.25',
            'heading_weight' => 700,
            'radius' => 'md',
            'radii' => ['sm' => 4, 'md' => 8, 'lg' => 14],
            'container_width' => 1200,
            'narrow_width' => 760,
            'space' => ['xs' => 4, 'sm' => 8, 'md' => 16, 'lg' => 32, 'xl' => 56, '2xl' => 96],
            'link_underline' => true,
            'custom_css' => '',
        ];
    }

    public static function normalize(array $theme): array
    {
        $defaults = self::defaults();

        return [
            'mode' => self::oneOf($theme['mode'] ?? null, self::MODES, $defaults['mode']),
            'colors' => self::colors($theme['colors'] ?? [], $defaults['colors']),
            'dark_colors' => self::colors($theme['dark_colors'] ?? [], $defaults['dark_colors']),
            'custom_colors' => self::customColors($theme['custom_colors'] ?? []),
            'fonts' => [
                'body' => self::oneOf($theme['fonts']['body'] ?? null, array_keys(self::FONT_STACKS), $defaults['fonts']['body']),
                'heading' => self::oneOf($theme['fonts']['heading'] ?? null, array_keys(self::FONT_STACKS), $defaults['fonts']['heading']),
            ],
            'base_size' => self::clampInt($theme['base_size'] ?? null, 12, 24, $defaults['base_size']),
            'line_height' => round(self::clampFloat($theme['line_height'] ?? null, 1.1, 2.2, $defaults['line_height']), 2),
            'type_scale' => self::oneOf(isset($theme['type_scale']) ? (string) $theme['type_scale'] : null, self::TYPE_SCALES, $defaults['type_scale']),
            'heading_weight' => self::clampInt($theme['heading_weight'] ?? null, 300, 900, $defaults['heading_weight'], 100),
            // "xl" was a fixed size before radius tokens existed.
            'radius' => self::oneOf(($theme['radius'] ?? null) === 'xl' ? 'lg' : ($theme['radius'] ?? null), self::RADII, $defaults['radius']),
            'radii' => self::radii($theme, $defaults['radii']),
            'container_width' => self::clampInt($theme['container_width'] ?? null, 720, 1920, $defaults['container_width']),
            'narrow_width' => self::clampInt($theme['narrow_width'] ?? null, 480, 1200, $defaults['narrow_width']),
            'space' => self::space($theme, $defaults['space']),
            'link_underline' => (bool) ($theme['link_underline'] ?? $defaults['link_underline']),
            'custom_css' => self::customCss((string) ($theme['custom_css'] ?? '')),
        ];
    }

    /**
     * CSS custom properties for a normalized theme, followed by the shipped
     * base stylesheet and the administrator's custom CSS.
     */
    public static function stylesheet(array $theme): string
    {
        $theme = self::normalize($theme);
        $scale = (float) $theme['type_scale'];
        $radius = match ($theme['radius']) {
            'none' => '0',
            'full' => '9999px',
            default => 'var(--radius-' . $theme['radius'] . ')',
        };

        $root = [
            '--font-body' => self::FONT_STACKS[$theme['fonts']['body']],
            '--font-heading' => self::FONT_STACKS[$theme['fonts']['heading']],
            '--font-size' => $theme['base_size'] . 'px',
            '--line-height' => (string) $theme['line_height'],
            '--heading-weight' => (string) $theme['heading_weight'],
            '--radius' => $radius,
            // Surfaces (images, cards, sections) never become pills: "round"
            // only rounds buttons and other small controls fully.
            '--radius-box' => $theme['radius'] === 'full' ? 'var(--radius-lg)' : $radius,
            '--container' => $theme['container_width'] . 'px',
            '--narrow' => $theme['narrow_width'] . 'px',
            '--link-decoration' => $theme['link_underline'] ? 'underline' : 'none',
        ];

        foreach ($theme['radii'] as $token => $px) {
            $root['--radius-' . $token] = $px . 'px';
        }

        foreach ($theme['space'] as $token => $px) {
            $root['--space-' . $token] = self::number($px / 16) . 'rem';
        }

        foreach (['sm' => -1, 'base' => 0, 'lg' => 1, 'xl' => 2, '2xl' => 3, '3xl' => 4, '4xl' => 5, '5xl' => 6] as $step => $power) {
            $root['--fs-' . $step] = self::number(pow($scale, $power)) . 'rem';
        }

        $light = self::colorVariables($theme['colors']);
        $dark = self::colorVariables($theme['dark_colors']);
        foreach ($theme['custom_colors'] as $key => $color) {
            $light['--c-' . $key] = $color['color'];
            $light['--c-on-' . $key] = self::contrastColor($color['color']);
            $dark['--c-' . $key] = $color['dark_color'];
            $dark['--c-on-' . $key] = self::contrastColor($color['dark_color']);
        }
        $css = ':root{' . self::declarations($root) . '}';

        if ($theme['mode'] === 'dark') {
            $css .= ':root{color-scheme:dark;' . self::declarations($dark) . '}';
        } else {
            $css .= ':root{color-scheme:light;' . self::declarations($light) . '}';
        }

        if ($theme['mode'] === 'auto') {
            $css .= '@media (prefers-color-scheme: dark){:root{color-scheme:dark;' . self::declarations($dark) . '}}';
        }

        $css .= "\n" . self::baseStylesheet();

        foreach ($theme['custom_colors'] as $key => $_color) {
            $css .= "\n.c-bg-" . $key . '{background-color:var(--c-' . $key . ');color:var(--c-on-' . $key . ');}';
            $css .= '.c-fg-' . $key . '{color:var(--c-' . $key . ');}';
            $css .= '.c-bg-' . $key . ' a:not(.c-button){color:inherit;}';
        }

        if ($theme['custom_css'] !== '') {
            $css .= "\n/* Custom CSS */\n" . $theme['custom_css'] . "\n";
        }

        return $css;
    }

    public static function baseStylesheet(): string
    {
        static $css = null;

        return $css ??= (string) file_get_contents(__DIR__ . '/assets/site.css');
    }

    /** Black or white, whichever reads better on the given background. */
    public static function contrastColor(string $hex): string
    {
        [$r, $g, $b] = self::rgb($hex);
        $channel = static function (int $value): float {
            $c = $value / 255;
            return $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
        };
        $luminance = 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);

        return $luminance > 0.4 ? '#0f172a' : '#ffffff';
    }

    private static function colorVariables(array $colors): array
    {
        $variables = [];

        foreach (self::COLOR_KEYS as $key) {
            $variables['--c-' . $key] = $colors[$key];
        }

        foreach (self::COLOR_KEYS as $key) {
            $variables['--c-on-' . $key] = self::contrastColor($colors[$key]);
            [$r, $g, $b] = self::rgb($colors[$key]);
            $variables['--c-' . $key . '-rgb'] = $r . ' ' . $g . ' ' . $b;
        }

        return $variables;
    }

    private static function declarations(array $values): string
    {
        $css = '';

        foreach ($values as $name => $value) {
            $css .= $name . ':' . $value . ';';
        }

        return $css;
    }

    private static function colors(mixed $colors, array $defaults): array
    {
        $colors = is_array($colors) ? $colors : [];
        $normalized = [];

        foreach (self::COLOR_KEYS as $key) {
            $normalized[$key] = self::hexColor($colors[$key] ?? null) ?? $defaults[$key];
        }

        return $normalized;
    }

    /** Stable tokens let color names and values change without rewriting layouts. */
    public static function isCustomColorKey(mixed $key): bool
    {
        return is_string($key) && preg_match('/^custom-[a-z0-9][a-z0-9-]{0,47}$/', $key) === 1;
    }

    private static function customColors(mixed $colors): array
    {
        $normalized = [];
        foreach (is_array($colors) ? $colors : [] as $key => $color) {
            if (count($normalized) >= self::MAX_CUSTOM_COLORS) {
                break;
            }
            if (!self::isCustomColorKey($key) || !is_array($color)) {
                continue;
            }
            $value = self::hexColor($color['color'] ?? null);
            if ($value === null) {
                continue;
            }
            $label = is_string($color['label'] ?? null) ? trim(strip_tags($color['label'])) : '';
            $normalized[$key] = [
                'label' => Text::limit($label !== '' ? $label : $key, 80),
                'color' => $value,
                'dark_color' => self::hexColor($color['dark_color'] ?? null) ?? $value,
            ];
        }

        return $normalized;
    }

    private static function hexColor(mixed $value): ?string
    {
        $value = is_string($value) ? strtolower(trim($value)) : '';
        if (preg_match('/^#([0-9a-f]{3})$/', $value, $match)) {
            $value = '#' . $match[1][0] . $match[1][0] . $match[1][1] . $match[1][1] . $match[1][2] . $match[1][2];
        }

        return preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : null;
    }

    /** @return array{0:int,1:int,2:int} */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    private static function customCss(string $css): string
    {
        $css = substr(str_replace("\0", '', $css), 0, self::MAX_CUSTOM_CSS);

        // The stylesheet is also inlined into <style> for previews; a closing
        // tag sequence must never be able to end that element early.
        return trim(str_ireplace(['</style', '<style', '<!--', '-->'], '', $css));
    }

    private static function radii(array $theme, array $defaults): array
    {
        $radii = [];

        foreach (self::RADIUS_TOKENS as $token) {
            $radii[$token] = self::clampInt($theme['radii'][$token] ?? null, 0, 64, $defaults[$token]);
        }

        return $radii;
    }

    /** The spacing scale; themes saved with a density preset are converted. */
    private static function space(array $theme, array $defaults): array
    {
        $factor = self::SPACING[$theme['spacing'] ?? 'normal'] ?? 1.0;
        $space = [];

        foreach (self::SPACE_TOKENS as $token) {
            $fallback = (int) round($defaults[$token] * $factor);
            $space[$token] = self::clampInt($theme['space'][$token] ?? null, 0, 400, $fallback);
        }

        return $space;
    }

    private static function oneOf(mixed $value, array $allowed, string $default): string
    {
        $value = is_scalar($value) ? (string) $value : '';

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private static function clampInt(mixed $value, int $min, int $max, int $default, int $step = 1): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        $number = (int) round((float) $value / $step) * $step;

        return max($min, min($max, $number));
    }

    private static function clampFloat(mixed $value, float $min, float $max, float $default): float
    {
        return is_numeric($value) ? max($min, min($max, (float) $value)) : $default;
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
}
