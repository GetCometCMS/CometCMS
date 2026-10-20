<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Core\HtmlSanitizer;

/**
 * The vocabulary of website layouts.
 *
 * A layout is a tree of nodes: section > row > column > element. Columns
 * may hold rows of their own (up to MAX_ROW_DEPTH deep), and a collection
 * holds one column as the card it repeats for each entry. Every node
 * has the same shape ({id, kind, props, style, children}) and an optional
 * editor-only name. Props and style values are checked against this schema
 * whenever a layout is saved or
 * previewed, so the renderer only ever sees known keys and values. The admin
 * builder reads the same schema (see spec()) to draw its inspector.
 *
 * Reusable components are stored separately (see SiteRepository); a layout
 * only holds {kind: "component", props: {component, root, span}} instances.
 */
final class LayoutSchema
{
    public const FORMAT_VERSION = 2;

    public const VARIANTS = ['detail', 'index'];

    public const REGIONS = ['header', 'footer'];

    public const STRUCTURE = ['section', 'row', 'column'];

    /**
     * A placed reusable component. It stands in for a node of its component's
     * root kind (props.root) and is placed wherever such a node may go.
     */
    public const COMPONENT = 'component';

    public const ELEMENTS = [
        'field',
        'heading',
        'text',
        'image',
        'gallery',
        'button',
        'accordion',
        'tabs',
        'icon',
        'spacer',
        'divider',
        'collection',
        'form',
        'menu',
        'site_title',
        'language_switcher',
    ];

    /** Elements allowed inside a repeater field's item template. */
    public const ITEM_ELEMENTS = ['field', 'heading', 'text', 'image', 'gallery', 'button', 'accordion', 'icon', 'spacer', 'divider'];

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

    public const MAX_NODE_NAME_LENGTH = 120;

    /** How deep rows may nest inside columns (a section's own rows count as 0). */
    public const MAX_ROW_DEPTH = 3;

    /** Upper bound of an image element's width options, in pixels. */
    public const MAX_IMAGE_WIDTH = 2400;

    /** Focal points for background images and cropped images. */
    public const POSITIONS = ['top-left', 'top', 'top-right', 'left', 'center', 'right', 'bottom-left', 'bottom', 'bottom-right'];

    private const SPACE = ['none', 'xs', 'sm', 'md', 'lg', 'xl', '2xl'];

    private const SPACING_KEYS = ['padding_y', 'padding_x', 'padding_top', 'padding_right', 'padding_bottom', 'padding_left', 'margin_top', 'margin_right', 'margin_bottom', 'margin_left'];

    private const LINK_TYPES = ['none', 'url', 'current', 'entry', 'index', 'home', 'anchor'];

    /** Background image options; older layouts stored them as section props. */
    private const BACKGROUND_IMAGE_KEYS = ['background_image', 'background_size', 'background_position', 'background_repeat', 'background_blur', 'overlay'];

    /** Border sides and how they are drawn; the look only applies once a side is picked. */
    private const BORDER_KEYS = ['border', 'border_width', 'border_style', 'border_color'];

    /** Entrance animation and its timing; the timing only applies to an animation. */
    private const ANIMATION_KEYS = ['animation', 'animation_trigger', 'animation_duration', 'animation_delay'];

    /** Borders used to be a single choice of sides. */
    private const LEGACY_BORDERS = ['all' => ['left', 'top', 'right', 'bottom'], 'top' => ['top'], 'bottom' => ['bottom']];

    public static function styleSpec(): array
    {
        return [
            'padding_y' => ['type' => 'enum', 'options' => ['', ...self::SPACE], 'default' => ''],
            'padding_x' => ['type' => 'enum', 'options' => ['', 'none', 'xs', 'sm', 'md', 'lg', 'xl'], 'default' => ''],
            'margin_bottom' => ['type' => 'enum', 'options' => ['', ...self::SPACE], 'default' => ''],
            'padding_top' => ['type' => 'enum', 'options' => ['', ...self::SPACE], 'default' => ''],
            'padding_right' => ['type' => 'enum', 'options' => ['', ...self::SPACE], 'default' => ''],
            'padding_bottom' => ['type' => 'enum', 'options' => ['', ...self::SPACE], 'default' => ''],
            'padding_left' => ['type' => 'enum', 'options' => ['', ...self::SPACE], 'default' => ''],
            'margin_top' => ['type' => 'enum', 'options' => ['', ...self::SPACE], 'default' => ''],
            'margin_right' => ['type' => 'enum', 'options' => ['', ...self::SPACE], 'default' => ''],
            'margin_left' => ['type' => 'enum', 'options' => ['', ...self::SPACE], 'default' => ''],
            'background' => ['type' => 'color', 'options' => ['', ...Theme::COLOR_KEYS], 'default' => ''],
            'background_gradient' => ['type' => 'gradient', 'options' => Theme::COLOR_KEYS, 'default' => []],
            // An image is layered over the background color, which shows
            // through transparent images and while the image loads.
            'background_image' => ['type' => 'media', 'default' => ''],
            'background_size' => ['type' => 'enum', 'options' => ['cover', 'contain', 'original'], 'default' => 'cover'],
            'background_position' => ['type' => 'enum', 'options' => self::POSITIONS, 'default' => 'center'],
            'background_repeat' => ['type' => 'bool', 'default' => false],
            'background_blur' => ['type' => 'int', 'min' => 0, 'max' => 40, 'default' => 0],
            'overlay' => ['type' => 'enum', 'options' => ['none', 'light', 'dark'], 'default' => 'none'],
            'text_color' => ['type' => 'color', 'options' => ['', ...Theme::COLOR_KEYS, 'inverse'], 'default' => ''],
            'text_gradient' => ['type' => 'gradient', 'options' => Theme::COLOR_KEYS, 'default' => []],
            'align' => ['type' => 'enum', 'options' => ['', 'left', 'center', 'right'], 'default' => ''],
            'font_size' => ['type' => 'enum', 'options' => ['', 'sm', 'base', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl'], 'default' => ''],
            'font_weight' => ['type' => 'enum', 'options' => ['', 'normal', 'medium', 'semibold', 'bold'], 'default' => ''],
            'font_family' => ['type' => 'font', 'options' => ['', 'body', 'heading'], 'default' => ''],
            'text_shadow' => ['type' => 'enum', 'options' => ['', 'none', 'sm', 'md', 'lg'], 'default' => ''],
            'radius' => ['type' => 'enum', 'options' => ['', 'none', 'theme', 'sm', 'md', 'lg', 'full'], 'default' => ''],
            'shadow' => ['type' => 'enum', 'options' => ['', 'none', 'sm', 'md', 'lg'], 'default' => ''],
            'border' => ['type' => 'set', 'options' => ['left', 'top', 'right', 'bottom'], 'default' => []],
            'border_width' => ['type' => 'int', 'min' => 1, 'max' => 6, 'default' => 1],
            'border_style' => ['type' => 'enum', 'options' => ['solid', 'dashed', 'dotted', 'double'], 'default' => 'solid'],
            'border_color' => ['type' => 'color', 'options' => ['', ...Theme::COLOR_KEYS], 'default' => ''],
            'max_width' => ['type' => 'enum', 'options' => ['', 'narrow', 'prose'], 'default' => ''],
            'animation' => ['type' => 'enum', 'options' => ['none', 'fade', 'fade-up', 'fade-down', 'fade-left', 'fade-right', 'zoom-in', 'zoom-out', 'blur'], 'default' => 'none'],
            // Scroll plays as the element comes into view; load on page open; hover on pointer entry.
            'animation_trigger' => ['type' => 'enum', 'options' => ['scroll', 'load', 'hover'], 'default' => 'scroll'],
            'animation_duration' => ['type' => 'enum', 'options' => ['fast', 'normal', 'slow'], 'default' => 'normal'],
            // Stagger step: later steps start a little after earlier ones.
            'animation_delay' => ['type' => 'int', 'min' => 0, 'max' => 6, 'default' => 0],
            'hide_on' => ['type' => 'set', 'options' => ['mobile', 'tablet', 'desktop'], 'default' => []],
            'class' => ['type' => 'class', 'default' => ''],
            'anchor' => ['type' => 'key', 'default' => ''],
        ];
    }

    /** Style keys each kind offers in the inspector. */
    public static function styleKeys(string $kind): array
    {
        return match ($kind) {
            'section' => [...self::SPACING_KEYS, 'background', 'background_gradient', ...self::BACKGROUND_IMAGE_KEYS, 'text_color', 'align', ...self::BORDER_KEYS, 'radius', 'shadow', ...self::ANIMATION_KEYS, 'class', 'anchor', 'hide_on'],
            'row' => [...self::SPACING_KEYS, 'background', 'background_gradient', ...self::BACKGROUND_IMAGE_KEYS, 'text_color', 'radius', 'shadow', ...self::BORDER_KEYS, ...self::ANIMATION_KEYS, 'class', 'anchor', 'hide_on'],
            self::COMPONENT => [],
            'column' => [...self::SPACING_KEYS, 'background', 'background_gradient', ...self::BACKGROUND_IMAGE_KEYS, 'text_color', 'align', 'radius', 'shadow', ...self::BORDER_KEYS, ...self::ANIMATION_KEYS, 'class', 'hide_on'],
            default => [...self::SPACING_KEYS, 'background', 'background_gradient', ...self::BACKGROUND_IMAGE_KEYS, 'text_color', 'align', 'font_size', 'font_weight', 'font_family', ...(in_array($kind, ['text', 'heading'], true) ? ['text_gradient', 'text_shadow'] : []), 'radius', 'shadow', ...self::BORDER_KEYS, 'max_width', ...self::ANIMATION_KEYS, 'class', 'anchor', 'hide_on'],
        };
    }

    public static function propSpec(string $kind): array
    {
        $ratio = ['type' => 'enum', 'options' => ['', '1:1', '4:3', '3:2', '16:9', '21:9', '3:4'], 'default' => ''];
        $fit = ['type' => 'enum', 'options' => ['cover', 'contain'], 'default' => 'cover'];
        $position = ['type' => 'enum', 'options' => self::POSITIONS, 'default' => 'center'];

        return match ($kind) {
            'section' => [
                'width' => ['type' => 'enum', 'options' => ['contained', 'narrow', 'full'], 'default' => 'contained'],
                'min_height' => ['type' => 'enum', 'options' => ['', 'half', 'screen'], 'default' => ''],
                'vertical_align' => ['type' => 'enum', 'options' => ['start', 'center', 'end'], 'default' => 'start'],
                // Always stays at the top; reveal hides it while scrolling down and brings it back on the way up.
                'sticky' => ['type' => 'enum', 'options' => ['none', 'always', 'reveal'], 'default' => 'none'],
                // Takes no room: the content below starts underneath, as behind a transparent header.
                'overlap' => ['type' => 'bool', 'default' => false],
                // How a sticky or floating section changes once the page is scrolled.
                'scroll_effect' => ['type' => 'set', 'options' => ['shadow', 'glass', 'compact'], 'default' => []],
            ],
            'row' => [
                'gap' => ['type' => 'enum', 'options' => ['none', 'xs', 'sm', 'md', 'lg', 'xl'], 'default' => 'md'],
                'align' => ['type' => 'enum', 'options' => ['stretch', 'start', 'center', 'end'], 'default' => 'stretch'],
                'justify' => ['type' => 'enum', 'options' => ['start', 'center', 'end', 'between'], 'default' => 'start'],
                'reverse_mobile' => ['type' => 'bool', 'default' => false],
                'reverse_tablet' => ['type' => 'bool', 'default' => false],
            ],
            'column' => [
                'span' => ['type' => 'spans', 'default' => ['mobile' => 12, 'tablet' => 0, 'desktop' => 0]],
                'vertical_align' => ['type' => 'enum', 'options' => ['start', 'center', 'end'], 'default' => 'start'],
                // Space between the column's elements; empty keeps the theme's flow.
                'spacing' => ['type' => 'enum', 'options' => ['', 'none', 'xs', 'sm', 'md', 'lg', 'xl'], 'default' => ''],
                // Elements underneath each other, or side by side (wrapping when they run out of room).
                'direction' => ['type' => 'enum', 'options' => ['column', 'row'], 'default' => 'column'],
                'content_justify' => ['type' => 'enum', 'options' => ['start', 'center', 'end', 'between'], 'default' => 'start'],
                'content_align' => ['type' => 'enum', 'options' => ['start', 'center', 'end', 'stretch'], 'default' => 'center'],
            ],
            // Only a column component's instances use their own span.
            self::COMPONENT => [
                'component' => ['type' => 'key', 'default' => ''],
                'root' => ['type' => 'enum', 'options' => self::STRUCTURE, 'default' => 'section'],
                'span' => ['type' => 'spans', 'default' => ['mobile' => 12, 'tablet' => 0, 'desktop' => 0]],
            ],
            'field' => [
                'source' => ['type' => 'reference', 'default' => ''],
                'display' => ['type' => 'display', 'default' => ''],
                'tag' => ['type' => 'enum', 'options' => ['', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'div'], 'default' => ''],
                'label' => ['type' => 'bool', 'default' => false],
                'link' => ['type' => 'link', 'default' => ['type' => 'none']],
                'truncate' => ['type' => 'int', 'min' => 0, 'max' => 500, 'default' => 0],
                'date_format' => ['type' => 'enum', 'options' => ['date', 'long', 'datetime', 'time'], 'default' => 'date'],
                'ratio' => $ratio,
                'fit' => $fit,
                'position' => $position,
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
                'ratio' => $ratio,
                'fit' => $fit,
                'position' => $position,
                // Rendered width bounds in pixels; 0 leaves that side to the image and its column.
                'min_width' => ['type' => 'int', 'min' => 0, 'max' => self::MAX_IMAGE_WIDTH, 'slider_max' => 1000, 'unit' => 'px', 'default' => 0],
                'max_width' => ['type' => 'int', 'min' => 0, 'max' => self::MAX_IMAGE_WIDTH, 'slider_max' => 1000, 'unit' => 'px', 'default' => 0],
                'link' => ['type' => 'link', 'default' => ['type' => 'none']],
            ],
            'gallery' => [
                'media' => ['type' => 'media_list', 'default' => []],
                'label' => ['type' => 'bool', 'default' => false],
                'masonry' => ['type' => 'bool', 'default' => false],
                'ratio' => ['default' => '16:9'] + $ratio,
                'fit' => $fit,
                'position' => $position,
                'columns' => ['type' => 'int', 'min' => 1, 'max' => 6, 'default' => 3],
                'gap' => ['type' => 'enum', 'options' => ['none', 'xs', 'sm', 'md', 'lg', 'xl'], 'default' => 'sm'],
                'captions' => ['type' => 'bool', 'default' => false],
                'lightbox' => ['type' => 'bool', 'default' => false],
                'lightbox_navigation' => ['type' => 'bool', 'default' => true],
                'link' => ['type' => 'link', 'default' => ['type' => 'none']],
            ],
            'button' => [
                'label' => ['type' => 'string', 'max' => 120, 'default' => 'Learn more'],
                'link' => ['type' => 'link', 'default' => ['type' => 'current']],
                'variant' => ['type' => 'enum', 'options' => ['primary', 'secondary', 'outline', 'link'], 'default' => 'primary'],
                'size' => ['type' => 'enum', 'options' => ['sm', 'md', 'lg'], 'default' => 'md'],
                'full_width' => ['type' => 'bool', 'default' => false],
            ],
            'accordion' => [
                'title' => ['type' => 'string', 'max' => 300, 'default' => 'Accordion title'],
                'html' => ['type' => 'html', 'default' => '<p>Accordion content</p>'],
                'open' => ['type' => 'bool', 'default' => false],
            ],
            'tabs' => [
                'title' => ['type' => 'string', 'max' => 300, 'default' => 'Tabs'],
                'initial_tab' => ['type' => 'int', 'min' => 1, 'max' => self::MAX_NODES, 'default' => 1],
            ],
            'tab' => [
                'title' => ['type' => 'string', 'max' => 300, 'default' => 'Tab'],
            ],
            'icon' => [
                'icon' => ['type' => 'icon', 'options' => Icons::names(), 'default' => 'mdi:star-outline'],
                'size' => ['type' => 'int', 'min' => 12, 'max' => 240, 'unit' => 'px', 'default' => 48],
                'label' => ['type' => 'string', 'max' => 120, 'default' => ''],
                'link' => ['type' => 'link', 'default' => ['type' => 'none']],
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
            // A content type's form; retain form IDs for existing placements (see FormRepository).
            'form' => [
                'form' => ['type' => 'form', 'default' => ''],
                'labels' => ['type' => 'enum', 'options' => ['above', 'hidden'], 'default' => 'above'],
                'button_variant' => ['type' => 'enum', 'options' => ['primary', 'secondary', 'outline'], 'default' => 'primary'],
                'button_full_width' => ['type' => 'bool', 'default' => false],
            ],
            'menu' => [
                'menu' => ['type' => 'menu', 'default' => 'main'],
                'always_button' => ['type' => 'bool', 'default' => false],
                'orientation' => ['type' => 'enum', 'options' => ['horizontal', 'vertical'], 'default' => 'horizontal'],
                'collapse_mobile' => ['type' => 'bool', 'default' => true],
                // How the menu button shows the list.
                'panel' => ['type' => 'enum', 'options' => ['dropdown', 'fullscreen', 'drawer'], 'default' => 'dropdown'],
                'panel_side' => ['type' => 'enum', 'options' => ['left', 'right'], 'default' => 'right'],
                'panel_background' => ['type' => 'color', 'options' => ['', ...Theme::COLOR_KEYS], 'default' => ''],
                'panel_text_color' => ['type' => 'color', 'options' => ['', ...Theme::COLOR_KEYS, 'inverse'], 'default' => ''],
            ],
            'site_title' => [
                'show_logo' => ['type' => 'bool', 'default' => true],
                'show_name' => ['type' => 'bool', 'default' => true],
                'show_tagline' => ['type' => 'bool', 'default' => false],
            ],
            'language_switcher' => [
                // Flags beside the text, or instead of it.
                'flag' => ['type' => 'enum', 'options' => ['none', 'with_text', 'only'], 'default' => 'none'],
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
            'column', 'tab' => [...self::ELEMENTS, 'row'],
            'tabs' => ['tab'],
            'field' => self::ITEM_ELEMENTS,
            // The card a collection repeats for each entry.
            'collection' => ['column'],
            // The top of a component's own tree holds just its root kind.
            'component:section' => ['section'],
            'component:row' => ['row'],
            'component:column' => ['column'],
            default => [],
        };
    }

    /** Everything the admin builder needs to draw palettes and inspectors. */
    public static function spec(): array
    {
        $kinds = [];

        foreach ([...self::STRUCTURE, ...self::ELEMENTS, 'tab', self::COMPONENT] as $kind) {
            $props = self::propSpec($kind);
            foreach (SmartValue::PROPERTIES[$kind] ?? [] as $key => $binding) {
                $props[$key]['binding'] = $binding;
            }
            $kinds[$kind] = [
                'props' => $props,
                'style' => self::styleKeys($kind),
                'children' => self::allowedChildren($kind),
            ];
        }

        $style = self::styleSpec();
        foreach (SmartValue::STYLE_PROPERTIES as $key => $binding) {
            $style[$key]['binding'] = $binding;
        }

        return [
            'format_version' => self::FORMAT_VERSION,
            'bindings' => ['sources' => SmartValue::SOURCES, 'date_formats' => SmartValue::DATE_FORMATS, 'properties' => SmartValue::PROPERTIES, 'style_properties' => SmartValue::STYLE_PROPERTIES],
            'kinds' => $kinds,
            'root' => self::allowedChildren('root'),
            'style' => $style,
            'elements' => self::ELEMENTS,
            'item_elements' => self::ITEM_ELEMENTS,
            'field_displays' => self::FIELD_DISPLAYS,
            'system_fields' => self::SYSTEM_FIELDS,
            'variants' => self::VARIANTS,
            'max_row_depth' => self::MAX_ROW_DEPTH,
            'max_node_name_length' => self::MAX_NODE_NAME_LENGTH,
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

    /** The kind a node is placed as: a component instance counts as its root kind. */
    public static function placementKind(array $node): string
    {
        $kind = (string) ($node['kind'] ?? '');
        if ($kind !== self::COMPONENT) {
            return $kind;
        }
        $root = (string) ($node['props']['root'] ?? '');

        return in_array($root, self::STRUCTURE, true) ? $root : '';
    }

    /** Whether any bound value in the tree reads from $source (e.g. "current"). */
    public static function usesSource(array $nodes, string $source): bool
    {
        $scan = static function (mixed $value) use (&$scan, $source): bool {
            if (!is_array($value)) {
                return false;
            }
            if (($value['type'] ?? null) === 'reference' && ($value['source'] ?? null) === $source) {
                return true;
            }
            foreach ($value as $item) {
                if ($scan($item)) {
                    return true;
                }
            }
            return false;
        };

        foreach ($nodes as $node) {
            if ($scan($node['props'] ?? []) || $scan($node['style'] ?? []) || self::usesSource($node['children'] ?? [], $source)) {
                return true;
            }
        }

        return false;
    }

    /** Ids of the components placed anywhere in the tree. */
    public static function componentRefs(array $nodes): array
    {
        $refs = [];

        foreach ($nodes as $node) {
            if (($node['kind'] ?? '') === self::COMPONENT && ($node['props']['component'] ?? '') !== '') {
                $refs[(string) $node['props']['component']] = true;
            }
            foreach (self::componentRefs($node['children'] ?? []) as $ref) {
                $refs[$ref] = true;
            }
        }

        return array_keys($refs);
    }

    /** Validate a component's tree: its first node of kind $root, nothing else. */
    public static function normalizeComponent(mixed $nodes, string $root): array
    {
        $count = 0;
        $seen = [];
        $first = array_values(array_filter(is_array($nodes) ? $nodes : [], static fn(mixed $node): bool => is_array($node) && ($node['kind'] ?? '') === $root));

        return self::normalizeNodes(array_slice($first, 0, 1), 'component:' . $root, $count, $seen, false);
    }

    /** A copy of $nodes with fresh ids throughout. */
    public static function withNewIds(array $nodes): array
    {
        return array_map(static fn(array $node): array => ['id' => self::newId(), 'children' => self::withNewIds($node['children'] ?? [])] + $node, $nodes);
    }

    public static function newId(): string
    {
        return 'n' . bin2hex(random_bytes(5));
    }

    public static function node(string $kind, array $props = [], array $style = [], array $children = []): array
    {
        if ($kind === 'field' && isset($props['field'])) {
            $props['source'] = SmartValue::reference((string) $props['field']);
            unset($props['field']);
        }
        $defaults = [];

        foreach (self::propSpec($kind) as $key => $spec) {
            $defaults[$key] = $spec['default'];
            if ($key === 'link' && $spec['type'] === 'link' && is_bool($props[$key] ?? null)) {
                $props[$key] = self::normalizeLink($props[$key]);
            }
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
        return self::bindDefaultNodes(self::buildDefaultLayout($type, $variant), self::defaultFields($type));
    }

    /** The standard card of a content type: one column, as a collection repeats it. */
    public static function defaultCardTemplate(array $type): array
    {
        return self::bindDefaultNodes(self::buildDefaultLayout($type, 'card'), self::defaultFields($type));
    }

    /**
     * Turn a card layout from before cards lived in collections (sections of
     * rows) into a card template: one column holding all the rows, styled
     * like the first section as far as columns allow.
     */
    public static function cardTemplate(array $layout): array
    {
        $sections = array_values(array_filter($layout, static fn(mixed $node): bool => is_array($node) && ($node['kind'] ?? '') === 'section'));
        $style = array_intersect_key($sections[0]['style'] ?? [], array_flip(self::styleKeys('column')));
        $rows = array_merge(...array_map(static fn(array $section): array => $section['children'] ?? [], $sections ?: [[]]));

        return [self::node('column', [], $style, $rows)];
    }

    private static function defaultFields(array $type): array
    {
        $fields = ($type['fields'] ?? []) + array_fill_keys(self::SYSTEM_FIELDS, ['type' => 'datetime']);
        $fields['title'] = ['type' => 'text'];

        return $fields;
    }

    private static function buildDefaultLayout(array $type, string $variant): array
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
                self::node('column', [], ['border' => ['left', 'top', 'right', 'bottom'], 'radius' => 'theme', 'background' => 'background'], $top),
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

    private static function bindDefaultNodes(array $nodes, array $fields, bool $item = false): array
    {
        foreach ($nodes as &$node) {
            if ($node['kind'] !== 'field') {
                $node['children'] = self::bindDefaultNodes($node['children'], $fields, $item);
                continue;
            }
            $props = $node['props'];
            $key = $props['source']['path'][0] ?? '';
            $config = $fields[$key] ?? ['type' => 'text'];
            $source = SmartValue::reference($key, $item ? 'item' : 'current');
            if (!empty($props['truncate'])) $source['filters'][] = ['name' => 'truncate', 'value' => $props['truncate']];
            if (in_array($config['type'], ['date', 'datetime'], true)) $source['filters'][] = ['name' => 'date', 'value' => $props['date_format']];
            $simple = in_array($config['type'], ['text', 'slug', 'textarea', 'markdown', 'html', 'number', 'date', 'datetime'], true)
                || ($config['type'] === 'media' && empty($config['multiple']));
            if ($simple) {
                if ($config['type'] === 'media') {
                    $next = self::node('image', ['media' => $source, 'ratio' => $props['ratio'], 'fit' => $props['fit'], 'position' => $props['position']]);
                } elseif (str_starts_with($props['tag'], 'h')) {
                    $next = self::node('heading', ['text' => $source, 'level' => $props['tag']]);
                } else {
                    $content = $props['label']
                        ? ['type' => 'template', 'parts' => ['<p><span class="c-field-label">' . \CometCMS\Core\Security::e((string) ($config['label'] ?? $key)) . '</span>', $source, '</p>']]
                        : $source;
                    $next = self::node('text', ['html' => $content]);
                }
                if (isset(self::propSpec($next['kind'])['link'])) $next['props']['link'] = self::normalizeLink($props['link']);
                $next['id'] = $node['id'];
                $next['style'] = $node['style'];
                $node = $next;
            } elseif ($config['type'] === 'media' && !empty($config['multiple'])) {
                $node['props']['source'] = $source;
                $node = self::galleryFromField($node);
            } else {
                $node['props']['source'] = SmartValue::reference($key, $item ? 'item' : 'current');
                $subfields = [];
                foreach ($config['subfields'] ?? [] as $sub) $subfields[$sub['key']] = $sub;
                $node['children'] = self::bindDefaultNodes($node['children'], $subfields, true);
            }
        }
        return $nodes;
    }

    public static function defaultRegion(string $region): array
    {
        if ($region === 'header') {
            return [
                self::node('section', ['width' => 'contained'], ['padding_y' => 'sm', 'border' => ['bottom'], 'background' => 'background'], [
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
            self::node('section', ['width' => 'contained'], ['padding_y' => 'lg', 'background' => 'surface', 'border' => ['top']], [
                self::node('row', ['align' => 'center'], [], [
                    self::node('column', ['span' => ['mobile' => 12, 'tablet' => 6, 'desktop' => 0]], [], [
                        self::node('text', ['html' => ['type' => 'template', 'parts' => ['<p>© ', SmartValue::reference('year', 'site'), ' ', SmartValue::reference('title', 'site'), '</p>']]], ['text_color' => 'muted', 'font_size' => 'sm', 'margin_bottom' => 'none']),
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
        // Older fields and galleries stored only a "link to current entry" toggle.
        if (is_bool($link)) $link = ['type' => $link ? 'current' : 'none'];
        $link = is_array($link) ? $link : [];
        if (($link['type'] ?? '') === 'dynamic') {
            return ['type' => 'dynamic', 'value' => SmartValue::normalize($link['value'] ?? null, 'link'), 'new_tab' => (bool) ($link['new_tab'] ?? false)];
        }
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

        if (in_array($type, ['anchor', 'home', 'entry', 'index'], true)) {
            $anchor = self::key(ltrim(trim((string) ($link['anchor'] ?? '')), '#'));
            if ($anchor !== '' || $type === 'anchor') {
                $normalized['anchor'] = $anchor;
            }
        }

        if ($type !== 'none') {
            $normalized['new_tab'] = $type !== 'anchor' && (bool) ($link['new_tab'] ?? false);
        }

        return $normalized;
    }

    public static function key(mixed $value): string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return preg_match('/^[A-Za-z0-9_-]{1,80}$/', $value) ? $value : '';
    }

    /** $rows counts the rows around $nodes, to bound how deep rows nest in columns. */
    private static function normalizeNodes(array $nodes, string $parent, int &$count, array &$seen, bool $insideItem, int $rows = 0): array
    {
        $allowed = self::allowedChildren($parent);
        $normalized = [];

        foreach (array_values($nodes) as $node) {
            if (!is_array($node) || $count >= self::MAX_NODES) {
                continue;
            }

            // Promote saved gallery displays while preserving identity and styling.
            if (($node['kind'] ?? '') === 'field' && ($node['props']['display'] ?? '') === 'gallery') {
                $node = self::galleryFromField($node);
            }
            $kind = (string) ($node['kind'] ?? '');
            $placed = self::placementKind($node);
            if ($placed === '' || !in_array($placed, $allowed, true) || ($placed === 'row' && $rows > self::MAX_ROW_DEPTH)) {
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
            $children = in_array($kind, [...self::STRUCTURE, 'field', 'collection', 'tabs', 'tab'], true) && is_array($node['children'] ?? null)
                ? self::normalizeNodes($node['children'], $childParent, $count, $seen, $insideItem || $kind === 'field', $rows + ($kind === 'row' ? 1 : 0))
                : [];
            // A collection repeats a single card.
            if ($kind === 'collection') {
                $children = array_slice($children, 0, 1);
            }

            [$props, $style] = self::liftBackgroundImage(
                is_array($node['props'] ?? null) ? $node['props'] : [],
                is_array($node['style'] ?? null) ? $node['style'] : [],
            );

            $entry = [
                'id' => $id,
                'kind' => $kind,
                'props' => self::normalizeProps($kind, $props),
                'style' => self::normalizeStyle($kind, $style),
                'children' => $children,
            ];
            $name = is_string($node['name'] ?? null)
                ? trim(Text::limit(str_replace(["\r", "\n"], ' ', $node['name']), self::MAX_NODE_NAME_LENGTH))
                : '';
            if ($name !== '') {
                $entry['name'] = $name;
            }
            $normalized[] = $entry;
        }

        return $normalized;
    }

    private static function normalizeProps(string $kind, array $props): array
    {
        // Sections used to be either sticky or not.
        if ($kind === 'section' && is_bool($props['sticky'] ?? null)) {
            $props['sticky'] = $props['sticky'] ? 'always' : 'none';
        }
        $normalized = [];

        foreach (self::propSpec($kind) as $key => $spec) {
            $value = $props[$key] ?? $spec['default'];
            $binding = SmartValue::PROPERTIES[$kind][$key] ?? null;
            $normalized[$key] = $binding !== null && SmartValue::isDynamic($value)
                ? SmartValue::normalize($value, $binding)
                : self::normalizeValue($spec, $value);
        }

        return $normalized;
    }

    private static function galleryFromField(array $node): array
    {
        $props = $node['props'];
        $node['kind'] = 'gallery';
        $node['props'] = self::node('gallery', [
            'media' => $props['source'] ?? '',
            'columns' => max(2, (int) ($props['columns'] ?? 1)),
            'ratio' => $props['ratio'] ?? '',
        ] + array_intersect_key($props, array_flip(['fit', 'position', 'label', 'link'])))['props'];
        $node['children'] = [];
        return $node;
    }

    /** Only values that differ from their default are kept. */
    private static function normalizeStyle(string $kind, array $style): array
    {
        $specs = self::styleSpec();
        $normalized = [];
        if (is_string($style['border'] ?? null)) {
            $style['border'] = self::LEGACY_BORDERS[$style['border']] ?? [];
        }

        foreach (self::styleKeys($kind) as $key) {
            if (!array_key_exists($key, $style)) {
                continue;
            }

            $binding = SmartValue::STYLE_PROPERTIES[$key] ?? null;
            $value = $binding !== null && SmartValue::isDynamic($style[$key])
                ? SmartValue::normalize($style[$key], $binding)
                : self::normalizeValue($specs[$key], $style[$key]);
            if ($value !== $specs[$key]['default']) {
                $normalized[$key] = $value;
            }
        }

        if (!isset($normalized['border'])) {
            unset($normalized['border_width'], $normalized['border_style'], $normalized['border_color']);
        }
        if (!isset($normalized['animation'])) {
            unset($normalized['animation_trigger'], $normalized['animation_duration'], $normalized['animation_delay']);
        }

        return $normalized;
    }

    /** Move background image props of older sections into their style. */
    private static function liftBackgroundImage(array $props, array $style): array
    {
        foreach (self::BACKGROUND_IMAGE_KEYS as $key) {
            if (array_key_exists($key, $props)) {
                $style[$key] ??= $props[$key];
                unset($props[$key]);
            }
        }

        return [$props, $style];
    }

    private static function normalizeValue(array $spec, mixed $value): mixed
    {
        $default = $spec['default'];

        return match ($spec['type']) {
            'gradient' => Gradient::normalize($value),
            'color' => is_string($value) && (in_array($value, $spec['options'], true) || Theme::isCustomColorKey($value)) ? $value : $default,
            'enum', 'icon' => is_scalar($value) && in_array((string) $value, $spec['options'], true) ? (string) $value : $default,
            'set' => array_values(array_intersect($spec['options'], array_map('strval', is_array($value) ? $value : []))),
            'bool' => is_bool($value) ? $value : (bool) $default,
            'int' => is_numeric($value) ? max($spec['min'], min($spec['max'], (int) $value)) : $default,
            'font' => in_array($value, ['', 'body', 'heading'], true) || FontRegistry::validId($value) ? $value : $default,
            'string' => is_scalar($value) ? trim(Text::limit(str_replace(["\r", "\n"], ' ', (string) $value), $spec['max'] ?? 200)) : $default,
            'html' => is_scalar($value) ? HtmlSanitizer::sanitize(Text::limit((string) $value, 50000)) : $default,
            'media' => is_scalar($value) && preg_match('/^[^\/\\\\\0]{1,255}$/', basename((string) $value)) ? basename((string) $value) : '',
            'media_list' => self::normalizeMediaList($value),
            'link' => self::normalizeLink($value),
            'reference' => SmartValue::isDynamic($value) ? SmartValue::normalize($value, 'value') : '',
            'spans' => self::normalizeSpans($value),
            'class' => self::cssClasses($value),
            'sort_field' => self::key($value) !== '' ? self::key($value) : $default,
            'field', 'display', 'content_type', 'menu', 'form', 'key' => self::key($value),
            default => $default,
        };
    }

    private static function normalizeMediaList(mixed $value): array
    {
        $items = [];
        foreach (array_slice(is_array($value) ? $value : [], 0, 500) as $file) {
            $name = self::normalizeValue(['type' => 'media', 'default' => ''], $file);
            if ($name !== '') $items[] = $name;
        }
        return array_values(array_unique($items));
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
