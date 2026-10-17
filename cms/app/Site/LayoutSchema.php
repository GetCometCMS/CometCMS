<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Core\HtmlSanitizer;

/**
 * The vocabulary of website layouts.
 *
 * A layout is a tree of nodes: section > row > column > element. Every node
 * has the same shape ({id, kind, props, style, children}); props and style
 * values are checked against this schema whenever a layout is saved or
 * previewed, so the renderer only ever sees known keys and values. The admin
 * builder reads the same schema (see spec()) to draw its inspector.
 */
final class LayoutSchema
{
    public const VARIANTS = ['detail', 'card', 'index'];

    public const REGIONS = ['header', 'footer'];

    public const STRUCTURE = ['section', 'row', 'column'];

    public const ELEMENTS = [
        'field',
        'heading',
        'text',
        'image',
        'button',
        'spacer',
        'divider',
        'collection',
        'menu',
        'site_title',
        'language_switcher',
    ];

    /** Elements allowed inside a repeater field's item template. */
    public const ITEM_ELEMENTS = ['field', 'heading', 'text', 'image', 'button', 'spacer', 'divider'];

    public const SYSTEM_FIELDS = ['title', 'published_at', 'created_at', 'updated_at'];

    /** Display modes per field type; the first one is the default. */
    public const FIELD_DISPLAYS = [
        'text' => ['text'],
        'slug' => ['text'],
        'textarea' => ['text', 'excerpt'],
        'markdown' => ['rich', 'excerpt'],
        'html' => ['rich', 'excerpt'],
        'number' => ['number'],
        'range' => ['number', 'meter'],
        'boolean' => ['text', 'badge'],
        'select' => ['text', 'badges'],
        'date' => ['date'],
        'datetime' => ['date'],
        'color' => ['swatch', 'text'],
        'media' => ['image', 'gallery', 'files'],
        'relation' => ['cards', 'links', 'text'],
        'repeater' => ['items'],
        'json' => ['code'],
    ];

    public const MAX_NODES = 1500;

    private const SPACE = ['none', 'xs', 'sm', 'md', 'lg', 'xl', '2xl'];

    private const LINK_TYPES = ['none', 'url', 'current', 'entry', 'index', 'home'];

    public static function styleSpec(): array
    {
        return [
            'padding_y' => ['type' => 'enum', 'options' => ['', ...self::SPACE], 'default' => ''],
            'padding_x' => ['type' => 'enum', 'options' => ['', 'none', 'xs', 'sm', 'md', 'lg', 'xl'], 'default' => ''],
            'margin_bottom' => ['type' => 'enum', 'options' => ['', ...self::SPACE], 'default' => ''],
            'background' => ['type' => 'enum', 'options' => ['', 'background', 'surface', 'primary', 'secondary', 'accent'], 'default' => ''],
            'text_color' => ['type' => 'enum', 'options' => ['', 'text', 'muted', 'primary', 'secondary', 'accent', 'inverse'], 'default' => ''],
            'align' => ['type' => 'enum', 'options' => ['', 'left', 'center', 'right'], 'default' => ''],
            'font_size' => ['type' => 'enum', 'options' => ['', 'sm', 'base', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl'], 'default' => ''],
            'font_weight' => ['type' => 'enum', 'options' => ['', 'normal', 'medium', 'semibold', 'bold'], 'default' => ''],
            'font_family' => ['type' => 'enum', 'options' => ['', 'body', 'heading'], 'default' => ''],
            'radius' => ['type' => 'enum', 'options' => ['', 'none', 'theme', 'full'], 'default' => ''],
            'shadow' => ['type' => 'enum', 'options' => ['', 'none', 'sm', 'md', 'lg'], 'default' => ''],
            'border' => ['type' => 'enum', 'options' => ['', 'none', 'all', 'top', 'bottom'], 'default' => ''],
            'max_width' => ['type' => 'enum', 'options' => ['', 'narrow', 'prose'], 'default' => ''],
            'hide_on' => ['type' => 'set', 'options' => ['mobile', 'tablet', 'desktop'], 'default' => []],
            'class' => ['type' => 'class', 'default' => ''],
            'anchor' => ['type' => 'key', 'default' => ''],
        ];
    }

    /** Style keys each kind offers in the inspector. */
    public static function styleKeys(string $kind): array
    {
        return match ($kind) {
            'section' => ['padding_y', 'padding_x', 'background', 'text_color', 'align', 'border', 'radius', 'shadow', 'class', 'anchor', 'hide_on'],
            'row' => ['margin_bottom', 'class', 'hide_on'],
            'column' => ['padding_y', 'padding_x', 'background', 'text_color', 'align', 'radius', 'shadow', 'border', 'class', 'hide_on'],
            default => ['margin_bottom', 'padding_y', 'padding_x', 'background', 'text_color', 'align', 'font_size', 'font_weight', 'font_family', 'radius', 'shadow', 'border', 'max_width', 'class', 'anchor', 'hide_on'],
        };
    }

    public static function propSpec(string $kind): array
    {
        $ratio = ['type' => 'enum', 'options' => ['', '1:1', '4:3', '3:2', '16:9', '21:9', '3:4'], 'default' => ''];
        $fit = ['type' => 'enum', 'options' => ['cover', 'contain'], 'default' => 'cover'];

        return match ($kind) {
            'section' => [
                'width' => ['type' => 'enum', 'options' => ['contained', 'narrow', 'full'], 'default' => 'contained'],
                'min_height' => ['type' => 'enum', 'options' => ['', 'half', 'screen'], 'default' => ''],
                'vertical_align' => ['type' => 'enum', 'options' => ['start', 'center', 'end'], 'default' => 'start'],
                'background_image' => ['type' => 'media', 'default' => ''],
                'overlay' => ['type' => 'enum', 'options' => ['none', 'light', 'dark'], 'default' => 'none'],
                'sticky' => ['type' => 'bool', 'default' => false],
            ],
            'row' => [
                'gap' => ['type' => 'enum', 'options' => ['none', 'xs', 'sm', 'md', 'lg', 'xl'], 'default' => 'md'],
                'align' => ['type' => 'enum', 'options' => ['stretch', 'start', 'center', 'end'], 'default' => 'stretch'],
                'justify' => ['type' => 'enum', 'options' => ['start', 'center', 'end', 'between'], 'default' => 'start'],
                'reverse_mobile' => ['type' => 'bool', 'default' => false],
            ],
            'column' => [
                'span' => ['type' => 'spans', 'default' => ['mobile' => 12, 'tablet' => 0, 'desktop' => 0]],
                'vertical_align' => ['type' => 'enum', 'options' => ['start', 'center', 'end'], 'default' => 'start'],
            ],
            'field' => [
                'field' => ['type' => 'field', 'default' => ''],
                'display' => ['type' => 'display', 'default' => ''],
                'tag' => ['type' => 'enum', 'options' => ['', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'div'], 'default' => ''],
                'label' => ['type' => 'bool', 'default' => false],
                'link' => ['type' => 'bool', 'default' => false],
                'truncate' => ['type' => 'int', 'min' => 0, 'max' => 500, 'default' => 0],
                'date_format' => ['type' => 'enum', 'options' => ['date', 'long', 'datetime', 'time'], 'default' => 'date'],
                'ratio' => $ratio,
                'fit' => $fit,
                'columns' => ['type' => 'int', 'min' => 1, 'max' => 6, 'default' => 1],
                'prefix' => ['type' => 'string', 'max' => 60, 'default' => ''],
                'suffix' => ['type' => 'string', 'max' => 60, 'default' => ''],
                'true_text' => ['type' => 'string', 'max' => 120, 'default' => ''],
                'false_text' => ['type' => 'string', 'max' => 120, 'default' => ''],
            ],
            'heading' => [
                'text' => ['type' => 'string', 'max' => 300, 'default' => 'Heading'],
                'level' => ['type' => 'enum', 'options' => ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], 'default' => 'h2'],
                'link' => ['type' => 'link', 'default' => ['type' => 'none']],
            ],
            'text' => [
                'html' => ['type' => 'html', 'default' => '<p>Text</p>'],
            ],
            'image' => [
                'media' => ['type' => 'media', 'default' => ''],
                'alt' => ['type' => 'string', 'max' => 300, 'default' => ''],
                'caption' => ['type' => 'string', 'max' => 300, 'default' => ''],
                'ratio' => $ratio,
                'fit' => $fit,
                'link' => ['type' => 'link', 'default' => ['type' => 'none']],
            ],
            'button' => [
                'label' => ['type' => 'string', 'max' => 120, 'default' => 'Learn more'],
                'link' => ['type' => 'link', 'default' => ['type' => 'current']],
                'variant' => ['type' => 'enum', 'options' => ['primary', 'secondary', 'outline', 'link'], 'default' => 'primary'],
                'size' => ['type' => 'enum', 'options' => ['sm', 'md', 'lg'], 'default' => 'md'],
                'full_width' => ['type' => 'bool', 'default' => false],
            ],
            'spacer' => [
                'size' => ['type' => 'enum', 'options' => ['xs', 'sm', 'md', 'lg', 'xl', '2xl'], 'default' => 'md'],
            ],
            'divider' => [],
            'collection' => [
                'type' => ['type' => 'content_type', 'default' => ''],
                'display' => ['type' => 'enum', 'options' => ['cards', 'list'], 'default' => 'cards'],
                'columns' => ['type' => 'int', 'min' => 1, 'max' => 6, 'default' => 3],
                'limit' => ['type' => 'int', 'min' => 1, 'max' => 100, 'default' => 6],
                'sort_field' => ['type' => 'sort_field', 'default' => 'published_at'],
                'sort_order' => ['type' => 'enum', 'options' => ['desc', 'asc'], 'default' => 'desc'],
                'filter_field' => ['type' => 'key', 'default' => ''],
                'filter_value' => ['type' => 'string', 'max' => 200, 'default' => ''],
                'exclude_current' => ['type' => 'bool', 'default' => true],
                'paginate' => ['type' => 'bool', 'default' => false],
                'empty_text' => ['type' => 'string', 'max' => 300, 'default' => ''],
            ],
            'menu' => [
                'menu' => ['type' => 'menu', 'default' => 'main'],
                'orientation' => ['type' => 'enum', 'options' => ['horizontal', 'vertical'], 'default' => 'horizontal'],
                'collapse_mobile' => ['type' => 'bool', 'default' => true],
            ],
            'site_title' => [
                'show_logo' => ['type' => 'bool', 'default' => true],
                'show_name' => ['type' => 'bool', 'default' => true],
                'show_tagline' => ['type' => 'bool', 'default' => false],
            ],
            'language_switcher' => [
                'format' => ['type' => 'enum', 'options' => ['codes', 'names'], 'default' => 'codes'],
            ],
            default => [],
        };
    }

    /** Kinds a node of $parent may contain; "root" is the layout itself. */
    public static function allowedChildren(string $parent): array
    {
        return match ($parent) {
            'root' => ['section'],
            'section' => ['row'],
            'row' => ['column'],
            'column' => self::ELEMENTS,
            'field' => self::ITEM_ELEMENTS,
            default => [],
        };
    }

    /** Everything the admin builder needs to draw palettes and inspectors. */
    public static function spec(): array
    {
        $kinds = [];

        foreach ([...self::STRUCTURE, ...self::ELEMENTS] as $kind) {
            $kinds[$kind] = [
                'props' => self::propSpec($kind),
                'style' => self::styleKeys($kind),
                'children' => self::allowedChildren($kind),
            ];
        }

        return [
            'kinds' => $kinds,
            'root' => self::allowedChildren('root'),
            'style' => self::styleSpec(),
            'elements' => self::ELEMENTS,
            'item_elements' => self::ITEM_ELEMENTS,
            'field_displays' => self::FIELD_DISPLAYS,
            'system_fields' => self::SYSTEM_FIELDS,
            'variants' => self::VARIANTS,
            'regions' => self::REGIONS,
            'link_types' => self::LINK_TYPES,
        ];
    }

    /**
     * Validate a whole layout. Unknown kinds, misplaced nodes, props and style
     * values are dropped rather than rejected so an older or hand-edited
     * layout keeps rendering what is still valid.
     */
    public static function normalize(mixed $nodes): array
    {
        $count = 0;
        $seen = [];

        return self::normalizeNodes(is_array($nodes) ? $nodes : [], 'root', $count, $seen, false);
    }

    public static function newId(): string
    {
        return 'n' . bin2hex(random_bytes(5));
    }

    public static function node(string $kind, array $props = [], array $style = [], array $children = []): array
    {
        $defaults = [];

        foreach (self::propSpec($kind) as $key => $spec) {
            $defaults[$key] = $spec['default'];
        }

        return [
            'id' => self::newId(),
            'kind' => $kind,
            'props' => array_replace($defaults, $props),
            'style' => $style,
            'children' => $children,
        ];
    }

    public static function defaultDisplay(string $fieldType): string
    {
        return self::FIELD_DISPLAYS[$fieldType][0] ?? 'text';
    }

    /** A sensible starting layout built from a content type's fields. */
    public static function defaultLayout(array $type, string $variant): array
    {
        $fields = is_array($type['fields'] ?? null) ? $type['fields'] : [];
        $slugField = (string) ($type['slug_field'] ?? 'slug');
        $singleton = !empty($type['singleton']);

        if ($variant === 'index') {
            return [
                self::node('section', ['width' => 'contained'], ['padding_y' => 'xl'], [
                    self::node('row', [], [], [
                        self::node('column', [], [], [
                            self::node('heading', ['text' => (string) ($type['label'] ?? $type['name'] ?? ''), 'level' => 'h1']),
                            self::node('collection', [
                                'type' => (string) ($type['name'] ?? ''),
                                'paginate' => true,
                                'limit' => 12,
                                'columns' => 3,
                            ]),
                        ]),
                    ]),
                ]),
            ];
        }

        if ($variant === 'card') {
            $media = self::firstFieldOfType($fields, ['media']);
            $excerpt = self::firstFieldOfType($fields, ['textarea', 'markdown', 'html']);
            $top = [];

            if ($media !== null) {
                $top[] = self::node('row', ['gap' => 'none'], [], [
                    self::node('column', [], [], [
                        self::node('field', ['field' => $media, 'display' => 'image', 'ratio' => '16:9', 'link' => true], ['margin_bottom' => 'none']),
                    ]),
                ]);
            }

            $body = [self::node('field', ['field' => 'title', 'tag' => 'h3', 'link' => true], ['margin_bottom' => 'xs', 'font_size' => 'xl'])];

            if (!$singleton) {
                $body[] = self::node('field', ['field' => 'published_at', 'display' => 'date'], ['text_color' => 'muted', 'font_size' => 'sm', 'margin_bottom' => 'sm']);
            }

            if ($excerpt !== null) {
                $body[] = self::node('field', ['field' => $excerpt, 'display' => 'excerpt', 'truncate' => 28], ['margin_bottom' => 'none']);
            }

            $top[] = self::node('row', ['gap' => 'none'], [], [
                self::node('column', [], ['padding_y' => 'md', 'padding_x' => 'md'], $body),
            ]);

            return [
                self::node('section', ['width' => 'full'], ['border' => 'all', 'radius' => 'theme', 'background' => 'background'], $top),
            ];
        }

        $elements = [self::node('field', ['field' => 'title', 'tag' => 'h1'])];

        if (!$singleton) {
            $elements[] = self::node('field', ['field' => 'published_at', 'display' => 'date', 'date_format' => 'long'], ['text_color' => 'muted', 'margin_bottom' => 'lg']);
        }

        foreach ($fields as $name => $config) {
            $name = (string) $name;
            if (!is_array($config) || in_array($name, ['title', 'slug', $slugField], true)) {
                continue;
            }

            $fieldType = (string) ($config['type'] ?? 'text');
            if ($fieldType === 'json') {
                continue;
            }

            $props = ['field' => $name, 'display' => self::defaultDisplay($fieldType)];
            if ($fieldType === 'media') {
                $props += ['ratio' => '16:9'];
            }
            if (in_array($fieldType, ['relation', 'repeater'], true)) {
                $props += ['label' => true, 'columns' => $fieldType === 'relation' ? 3 : 1];
            }
            if (in_array($fieldType, ['number', 'range', 'boolean', 'select', 'date', 'datetime', 'color'], true)) {
                $props += ['label' => true];
            }

            $children = [];
            if ($fieldType === 'repeater') {
                foreach (is_array($config['subfields'] ?? null) ? $config['subfields'] : [] as $subfield) {
                    $key = is_array($subfield) ? (string) ($subfield['key'] ?? '') : '';
                    if ($key !== '' && ($subfield['type'] ?? '') !== 'json') {
                        $subProps = ['field' => $key, 'display' => self::defaultDisplay((string) ($subfield['type'] ?? 'text'))];
                        // The first text subfield reads as the item's heading.
                        if ($children === [] && ($subfield['type'] ?? 'text') === 'text') {
                            $subProps['tag'] = 'h4';
                        }
                        $children[] = self::node('field', $subProps, ['margin_bottom' => 'xs']);
                    }
                }
            }

            $elements[] = self::node('field', $props, [], $children);
        }

        return [
            self::node('section', ['width' => 'narrow'], ['padding_y' => 'xl'], [
                self::node('row', [], [], [self::node('column', [], [], $elements)]),
            ]),
        ];
    }

    public static function defaultRegion(string $region): array
    {
        if ($region === 'header') {
            return [
                self::node('section', ['width' => 'contained'], ['padding_y' => 'sm', 'border' => 'bottom', 'background' => 'background'], [
                    self::node('row', ['align' => 'center', 'justify' => 'between'], [], [
                        self::node('column', ['span' => ['mobile' => 8, 'tablet' => 4, 'desktop' => 0]], [], [
                            self::node('site_title', [], ['margin_bottom' => 'none']),
                        ]),
                        self::node('column', ['span' => ['mobile' => 4, 'tablet' => 8, 'desktop' => 0]], ['align' => 'right'], [
                            self::node('menu', ['menu' => 'main'], ['margin_bottom' => 'none']),
                        ]),
                    ]),
                ]),
            ];
        }

        return [
            self::node('section', ['width' => 'contained'], ['padding_y' => 'lg', 'background' => 'surface', 'border' => 'top'], [
                self::node('row', ['align' => 'center'], [], [
                    self::node('column', ['span' => ['mobile' => 12, 'tablet' => 6, 'desktop' => 0]], [], [
                        self::node('text', ['html' => '<p>© {year} {site}</p>'], ['text_color' => 'muted', 'font_size' => 'sm', 'margin_bottom' => 'none']),
                    ]),
                    self::node('column', ['span' => ['mobile' => 12, 'tablet' => 6, 'desktop' => 0]], ['align' => 'right'], [
                        self::node('menu', ['menu' => 'footer', 'collapse_mobile' => false], ['font_size' => 'sm', 'margin_bottom' => 'none']),
                    ]),
                ]),
            ]),
        ];
    }

    public static function normalizeLink(mixed $link): array
    {
        $link = is_array($link) ? $link : [];
        $type = in_array($link['type'] ?? null, self::LINK_TYPES, true) ? (string) $link['type'] : 'none';
        $normalized = ['type' => $type];

        if ($type === 'url') {
            $url = trim(substr((string) ($link['url'] ?? ''), 0, 2000));
            $normalized['url'] = HtmlSanitizer::isSafeUrl($url) ? $url : '';
        }

        if ($type === 'entry' || $type === 'index') {
            $normalized['collection'] = self::key($link['collection'] ?? '');
        }

        if ($type === 'entry') {
            $normalized['entry'] = self::key($link['entry'] ?? '');
        }

        if ($type !== 'none') {
            $normalized['new_tab'] = (bool) ($link['new_tab'] ?? false);
        }

        return $normalized;
    }

    public static function key(mixed $value): string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return preg_match('/^[A-Za-z0-9_-]{1,80}$/', $value) ? $value : '';
    }

    private static function normalizeNodes(array $nodes, string $parent, int &$count, array &$seen, bool $insideItem): array
    {
        $allowed = self::allowedChildren($parent);
        $normalized = [];

        foreach (array_values($nodes) as $node) {
            if (!is_array($node) || $count >= self::MAX_NODES) {
                continue;
            }

            $kind = (string) ($node['kind'] ?? '');
            if (!in_array($kind, $allowed, true)) {
                continue;
            }

            $count++;
            $id = self::key($node['id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                $id = self::newId();
            }
            $seen[$id] = true;

            // A repeater's item template may not contain another template.
            $childParent = $kind === 'field' ? ($insideItem ? '' : 'field') : $kind;
            $children = in_array($kind, [...self::STRUCTURE, 'field'], true) && is_array($node['children'] ?? null)
                ? self::normalizeNodes($node['children'], $childParent, $count, $seen, $insideItem || $kind === 'field')
                : [];

            $normalized[] = [
                'id' => $id,
                'kind' => $kind,
                'props' => self::normalizeProps($kind, is_array($node['props'] ?? null) ? $node['props'] : []),
                'style' => self::normalizeStyle($kind, is_array($node['style'] ?? null) ? $node['style'] : []),
                'children' => $children,
            ];
        }

        return $normalized;
    }

    private static function normalizeProps(string $kind, array $props): array
    {
        $normalized = [];

        foreach (self::propSpec($kind) as $key => $spec) {
            $normalized[$key] = self::normalizeValue($spec, $props[$key] ?? $spec['default']);
        }

        return $normalized;
    }

    private static function normalizeStyle(string $kind, array $style): array
    {
        $specs = self::styleSpec();
        $normalized = [];

        foreach (self::styleKeys($kind) as $key) {
            if (!array_key_exists($key, $style)) {
                continue;
            }

            $value = self::normalizeValue($specs[$key], $style[$key]);
            if ($value !== '' && $value !== []) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private static function normalizeValue(array $spec, mixed $value): mixed
    {
        $default = $spec['default'];

        return match ($spec['type']) {
            'enum' => is_scalar($value) && in_array((string) $value, $spec['options'], true) ? (string) $value : $default,
            'set' => array_values(array_intersect($spec['options'], array_map('strval', is_array($value) ? $value : []))),
            'bool' => is_bool($value) ? $value : (bool) $default,
            'int' => is_numeric($value) ? max($spec['min'], min($spec['max'], (int) $value)) : $default,
            'string' => is_scalar($value) ? trim(Text::limit(str_replace(["\r", "\n"], ' ', (string) $value), $spec['max'] ?? 200)) : $default,
            'html' => is_scalar($value) ? HtmlSanitizer::sanitize(Text::limit((string) $value, 50000)) : $default,
            'media' => is_scalar($value) && preg_match('/^[^\/\\\\\0]{1,255}$/', basename((string) $value)) ? basename((string) $value) : '',
            'link' => self::normalizeLink($value),
            'spans' => self::normalizeSpans($value),
            'class' => self::cssClasses($value),
            'sort_field' => self::key($value) !== '' ? self::key($value) : $default,
            'field', 'display', 'content_type', 'menu', 'key' => self::key($value),
            default => $default,
        };
    }

    private static function normalizeSpans(mixed $value): array
    {
        $value = is_array($value) ? $value : [];
        $span = static fn(mixed $span, int $min): int => is_numeric($span) ? max($min, min(12, (int) $span)) : $min;

        return [
            'mobile' => isset($value['mobile']) ? $span($value['mobile'], 1) : 12,
            'tablet' => $span($value['tablet'] ?? 0, 0),
            'desktop' => $span($value['desktop'] ?? 0, 0),
        ];
    }

    private static function cssClasses(mixed $value): string
    {
        $classes = preg_split('/\s+/', is_scalar($value) ? trim((string) $value) : '') ?: [];
        $classes = array_filter($classes, static fn(string $class): bool => preg_match('/^[A-Za-z_][A-Za-z0-9_-]{0,60}$/', $class) === 1);

        return implode(' ', array_slice(array_values(array_unique($classes)), 0, 8));
    }

    private static function firstFieldOfType(array $fields, array $types): ?string
    {
        foreach ($fields as $name => $config) {
            if (is_array($config) && in_array((string) ($config['type'] ?? ''), $types, true)) {
                return (string) $name;
            }
        }

        return null;
    }
}
