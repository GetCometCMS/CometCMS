<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Core\HtmlSanitizer;
use CometCMS\Localization\LocalizedValue;
use CometCMS\Localization\LanguageSettings;

/**
 * The vocabulary of website layouts.
 *
 * A layout is a tree of nodes: section > row > column > element. Columns
 * may hold rows of their own (up to MAX_ROW_DEPTH deep), and a collection
 * holds one column as the card it repeats for each entry. Every node
 * has the same shape ({id, kind, props, style, children}) and an optional
 * editor-only name. Optional `responsive` ({tablet, mobile}) and `states`
 * ({hover, focus}) hold {props, style} values that differ on smaller screens
 * or while hovered or focused (see StyleVariants). Props and style values are checked against this schema
 * whenever a layout is saved or
 * previewed, so the renderer only ever sees known keys and values. The admin
 * builder reads the same schema (see spec()) to draw its inspector.
 *
 * Reusable components are stored separately (see SiteRepository); a layout
 * only holds {kind: "component", props: {component, root, span}} instances.
 */
final class LayoutSchema
{
    public const FORMAT_VERSION = 3;

    public const VARIANTS = ['detail', 'index'];

    public const REGIONS = ['header', 'footer'];

    /** Standalone pages edited like regions, but rendered as the main content of their own response. */
    public const SPECIAL_PAGES = ['not_found'];

    /** Everything stored and edited as a region document. */
    public const REGION_DOCUMENTS = [...self::REGIONS, ...self::SPECIAL_PAGES];

    public const STRUCTURE = ['section', 'row', 'column'];

    /**
     * A placed reusable component. It stands in for a node of its component's
     * root kind (props.root) and is placed wherever such a node may go.
     */
    public const COMPONENT = 'component';

    /**
     * Where an entry's own page content goes in its type's detail layout.
     * Its children (sections) are the layout's default content, shown while
     * the entry has none of its own in the page field named by props.field.
     * It sits between sections or in a column, never inside another one or
     * in a collection's card, and each page field has one place.
     */
    public const PAGE_CONTENT = 'page_content';

    public const ELEMENTS = [
        'field',
        'heading',
        'text',
        'image',
        'video',
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
        'search',
        'site_title',
        'language_switcher',
        'theme_toggle',
    ];

    /** Elements allowed inside a repeater field's item template. */
    public const ITEM_ELEMENTS = ['field', 'heading', 'text', 'image', 'video', 'gallery', 'button', 'accordion', 'icon', 'spacer', 'divider'];

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

    /** Background image style options. */
    private const BACKGROUND_IMAGE_KEYS = ['background_image', 'background_size', 'background_position', 'background_repeat', 'background_blur', 'overlay'];

    /** Border sides and how they are drawn; the look only applies once a side is picked. */
    private const BORDER_KEYS = ['border', 'border_width', 'border_style', 'border_color'];

    /** Entrance animation and its timing; the timing only applies to an animation. */
    private const ANIMATION_KEYS = ['animation', 'animation_trigger', 'animation_duration', 'animation_delay'];

    /** Taking an element out of the flow, pinned to a point of its section. */
    private const POSITION_KEYS = ['position', 'pin', 'offset_x', 'offset_y'];

    /** Style a tablet or mobile override may change: values that render as a class, and the offsets (see NodeRenderer::offsetStyle). */
    public const RESPONSIVE_STYLE = ['padding_top', 'padding_right', 'padding_bottom', 'padding_left', 'margin_top', 'margin_right', 'margin_bottom', 'margin_left', 'background', 'text_color', 'link_color', 'align', 'font_size', 'font_weight', 'font_family', 'text_shadow', 'radius', 'shadow', 'max_width', ...self::POSITION_KEYS, 'z_index', 'opacity'];

    /** Props a tablet or mobile override may change, per kind. */
    public const RESPONSIVE_PROPS = [
        'section' => ['vertical_align'],
        'row' => ['gap', 'align'],
        'column' => ['vertical_align', 'spacing', 'content_justify', 'content_align'],
        'button' => ['size', 'full_width'],
        'spacer' => ['size'],
    ];

    /** Style a hover or focus state may change. */
    public const STATE_STYLE = ['background', 'text_color', 'link_color', 'border_color', 'shadow', 'text_shadow', 'opacity'];

    /** Additional style options available only in Hover. */
    public const HOVER_ANIMATION_KEYS = ['animation', 'animation_duration', 'animation_delay'];

    /** Props a hover or focus state may change, per kind. */
    public const STATE_PROPS = [
        'button' => ['button_color', 'button_text_color', 'button_border_color'],
    ];

    public const LOCALIZED_PROPS = [
        'heading' => ['text', 'link'], 'text' => ['html'],
        'video' => ['media', 'poster', 'video_url', 'title'],
        'image' => ['media', 'link', 'alt', 'caption'], 'gallery' => ['media', 'link'],
        'button' => ['label', 'link'], 'accordion' => ['title', 'html'],
        'tabs' => ['title'], 'tab' => ['title'], 'icon' => ['label', 'link'],
        'collection' => ['empty_text'], 'field' => ['prefix', 'suffix', 'true_text', 'false_text', 'link'],
        'search' => ['placeholder', 'no_results_text'],
        'theme_toggle' => ['label'],
    ];

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
            'background_image' => ['type' => 'media', 'default' => '', 'localized' => true],
            'background_size' => ['type' => 'enum', 'options' => ['cover', 'contain', 'original'], 'default' => 'cover'],
            'background_position' => ['type' => 'enum', 'options' => self::POSITIONS, 'default' => 'center'],
            'background_repeat' => ['type' => 'bool', 'default' => false],
            'background_blur' => ['type' => 'int', 'min' => 0, 'max' => 40, 'default' => 0],
            'overlay' => ['type' => 'enum', 'options' => ['none', 'light', 'dark'], 'default' => 'none'],
            'text_color' => ['type' => 'color', 'options' => ['', ...Theme::COLOR_KEYS, 'inverse'], 'default' => ''],
            // Links inside the node; buttons keep their own colors.
            'link_color' => ['type' => 'color', 'options' => ['', ...Theme::COLOR_KEYS, 'inverse'], 'default' => ''],
            'text_gradient' => ['type' => 'gradient', 'options' => Theme::COLOR_KEYS, 'default' => []],
            'align' => ['type' => 'enum', 'options' => ['', 'left', 'center', 'right'], 'default' => ''],
            'font_size' => ['type' => 'enum', 'options' => ['', 'sm', 'base', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl', '6xl', '7xl', '8xl', '9xl'], 'default' => ''],
            'font_weight' => ['type' => 'enum', 'options' => ['', 'normal', 'medium', 'semibold', 'bold'], 'default' => ''],
            'font_family' => ['type' => 'font', 'options' => ['', 'body', 'heading'], 'default' => ''],
            'text_shadow' => ['type' => 'shadow', 'text' => true, 'options' => Theme::COLOR_KEYS, 'default' => ''],
            'radius' => ['type' => 'enum', 'options' => ['', 'none', 'theme', 'sm', 'md', 'lg', 'full'], 'default' => ''],
            'shadow' => ['type' => 'shadow', 'options' => Theme::COLOR_KEYS, 'default' => ''],
            'border' => ['type' => 'set', 'options' => ['left', 'top', 'right', 'bottom'], 'default' => []],
            'border_width' => ['type' => 'int', 'min' => 1, 'max' => 6, 'default' => 1],
            'border_style' => ['type' => 'enum', 'options' => ['solid', 'dashed', 'dotted', 'double'], 'default' => 'solid'],
            'border_color' => ['type' => 'color', 'options' => ['', ...Theme::COLOR_KEYS], 'default' => ''],
            'max_width' => ['type' => 'enum', 'options' => ['', 'narrow', 'prose'], 'default' => ''],
            'animation' => ['type' => 'enum', 'options' => ['none', 'fade', 'fade-up', 'fade-down', 'fade-left', 'fade-right', 'zoom-in', 'zoom-out', 'blur'], 'default' => 'none'],
            // Entrance animations play on scroll or load; hover effects live in states.hover.style.
            'animation_trigger' => ['type' => 'enum', 'options' => ['scroll', 'load'], 'default' => 'scroll'],
            'animation_duration' => ['type' => 'enum', 'options' => ['fast', 'normal', 'slow'], 'default' => 'normal'],
            // Stagger step: later steps start a little after earlier ones.
            'animation_delay' => ['type' => 'int', 'min' => 0, 'max' => 6, 'default' => 0],
            // Absolute takes the element out of the flow, pinned to a point of its section.
            // Static only matters as a device override back to the flow.
            'position' => ['type' => 'enum', 'options' => ['', 'static', 'absolute'], 'default' => ''],
            'pin' => ['type' => 'enum', 'options' => self::POSITIONS, 'default' => 'top-left'],
            // Tenths of a percent of the page width on both axes, so the layout scales with it (see site.css).
            'offset_x' => ['type' => 'int', 'min' => -1000, 'max' => 1000, 'unit' => '%', 'scale' => 10, 'default' => 0],
            'offset_y' => ['type' => 'int', 'min' => -1000, 'max' => 1000, 'unit' => '%', 'scale' => 10, 'default' => 0],
            'z_index' => ['type' => 'enum', 'options' => ['', 'behind', '1', '2', '3', '4', '5'], 'default' => ''],
            'opacity' => ['type' => 'enum', 'options' => ['', '0', '10', '25', '50', '75', '90'], 'default' => ''],
            'hide_on' => ['type' => 'set', 'options' => ['mobile', 'tablet', 'desktop'], 'default' => []],
            'class' => ['type' => 'class', 'default' => ''],
            'anchor' => ['type' => 'key', 'default' => ''],
        ];
    }

    /** Style keys each kind offers in the inspector. */
    public static function styleKeys(string $kind): array
    {
        return match ($kind) {
            'section' => [...self::SPACING_KEYS, 'background', 'background_gradient', ...self::BACKGROUND_IMAGE_KEYS, 'text_color', 'link_color', 'align', ...self::BORDER_KEYS, 'radius', 'shadow', ...self::ANIMATION_KEYS, 'z_index', 'opacity', 'class', 'anchor', 'hide_on'],
            'row' => [...self::SPACING_KEYS, 'background', 'background_gradient', ...self::BACKGROUND_IMAGE_KEYS, 'text_color', 'link_color', 'radius', 'shadow', ...self::BORDER_KEYS, ...self::ANIMATION_KEYS, 'z_index', 'opacity', 'class', 'anchor', 'hide_on'],
            self::COMPONENT, self::PAGE_CONTENT => [],
            'column' => [...self::SPACING_KEYS, 'background', 'background_gradient', ...self::BACKGROUND_IMAGE_KEYS, 'text_color', 'link_color', 'align', 'radius', 'shadow', ...self::BORDER_KEYS, ...self::ANIMATION_KEYS, 'z_index', 'opacity', 'class', 'hide_on'],
            default => [...self::SPACING_KEYS, 'background', 'background_gradient', ...self::BACKGROUND_IMAGE_KEYS, 'text_color', ...($kind === 'button' ? [] : ['link_color']), 'align', 'font_size', 'font_weight', 'font_family', ...(in_array($kind, ['text', 'heading'], true) ? ['text_gradient', 'text_shadow'] : []), 'radius', 'shadow', ...self::BORDER_KEYS, 'max_width', ...self::POSITION_KEYS, 'z_index', 'opacity', ...self::ANIMATION_KEYS, 'class', 'anchor', 'hide_on'],
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
                'min_height' => ['type' => 'enum', 'options' => ['', 'quarter', 'half', 'three-quarter', 'screen'], 'default' => ''],
                'vertical_align' => ['type' => 'enum', 'options' => ['start', 'center', 'end'], 'default' => 'start'],
                // Always stays at the top; reveal hides it while scrolling down and brings it back on the way up.
                'sticky' => ['type' => 'enum', 'options' => ['none', 'always', 'reveal'], 'default' => 'none'],
                // Takes no room: the content below starts underneath, as behind a transparent header.
                'overlap' => ['type' => 'bool', 'default' => false],
                // A floating section is transparent at the top of the page, or keeps its background throughout.
                'overlap_background' => ['type' => 'enum', 'options' => ['transparent', 'background'], 'default' => 'transparent'],
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
            self::PAGE_CONTENT => [
                'field' => ['type' => 'page_field', 'default' => ''],
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
                'html' => ['type' => 'html', 'default' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>'],
            ],
            'image' => [
                'alt' => ['type' => 'string', 'max' => 1000, 'default' => ''],
                'caption' => ['type' => 'string', 'max' => 2000, 'default' => ''],
                'media' => ['type' => 'media', 'default' => ''],
                'ratio' => $ratio,
                'fit' => $fit,
                'position' => $position,
                // Rendered width bounds in pixels; 0 leaves that side to the image and its column.
                'min_width' => ['type' => 'int', 'min' => 0, 'max' => self::MAX_IMAGE_WIDTH, 'slider_max' => 1000, 'unit' => 'px', 'default' => 0],
                'max_width' => ['type' => 'int', 'min' => 0, 'max' => self::MAX_IMAGE_WIDTH, 'slider_max' => 1000, 'unit' => 'px', 'default' => 0],
                'link' => ['type' => 'link', 'default' => ['type' => 'none']],
            ],
            'video' => [
                // A library video takes precedence over the URL; the poster only applies to file playback.
                'media' => ['type' => 'media', 'default' => ''],
                'poster' => ['type' => 'media', 'default' => ''],
                'video_url' => ['type' => 'string', 'max' => 2000, 'default' => ''],
                'title' => ['type' => 'string', 'max' => 300, 'default' => 'Video'],
                'ratio' => ['type' => 'enum', 'options' => ['16:9', '4:3', '1:1', '3:4', '9:16', '21:9'], 'default' => '16:9'],
                'autoplay' => ['type' => 'bool', 'default' => false],
                'muted' => ['type' => 'bool', 'default' => false],
                'loop' => ['type' => 'bool', 'default' => false],
                'controls' => ['type' => 'bool', 'default' => true],
                'fullscreen' => ['type' => 'bool', 'default' => true],
                'start_time' => ['type' => 'int', 'min' => 0, 'max' => 86400, 'unit' => 's', 'default' => 0],
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
                // Colors of the button itself, in place of its variant's.
                'button_color' => ['type' => 'color', 'options' => ['', ...Theme::COLOR_KEYS], 'default' => ''],
                'button_text_color' => ['type' => 'color', 'options' => ['', ...Theme::COLOR_KEYS, 'inverse'], 'default' => ''],
                'button_border_color' => ['type' => 'color', 'options' => ['', ...Theme::COLOR_KEYS], 'default' => ''],
                // An icon before or after the label, sized with its text.
                'icon' => ['type' => 'icon', 'options' => ['', ...Icons::names()], 'default' => ''],
                'icon_position' => ['type' => 'enum', 'options' => ['start', 'end'], 'default' => 'start'],
                'icon_gap' => ['type' => 'enum', 'options' => ['xs', 'sm', 'md', 'lg'], 'default' => 'sm'],
            ],
            'accordion' => [
                'title' => ['type' => 'string', 'max' => 300, 'default' => 'Accordion title'],
                'html' => ['type' => 'html', 'default' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>'],
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
                'columns' => ['type' => 'int', 'min' => 1, 'max' => 6, 'default' => 3],
                'limit' => ['type' => 'int', 'min' => 1, 'max' => 100, 'default' => 6],
                'sort_field' => ['type' => 'sort_field', 'default' => 'published_at'],
                'sort_order' => ['type' => 'enum', 'options' => ['desc', 'asc'], 'default' => 'desc'],
                'filter_field' => ['type' => 'key', 'default' => ''],
                'filter_value' => ['type' => 'string', 'max' => 200, 'default' => ''],
                'exclude_current' => ['type' => 'bool', 'default' => true],
                // Controls visitors filter and sort the list with (see ListFilters).
                'filters' => ['type' => 'list_filters', 'default' => []],
                'sort_choices' => ['type' => 'sort_fields', 'default' => []],
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
            // Empty texts use the visitor's language; empty lists search every page type by its text fields.
            'search' => [
                'placeholder' => ['type' => 'string', 'max' => 120, 'default' => ''],
                'collections' => ['type' => 'content_types', 'default' => []],
                'fields' => ['type' => 'search_fields', 'default' => []],
                'max_results' => ['type' => 'int', 'min' => 1, 'max' => SiteSearch::MAX_RESULTS, 'default' => SiteSearch::MAX_RESULTS],
                'min_chars' => ['type' => 'int', 'min' => 1, 'max' => 5, 'default' => 2],
                'icon_position' => ['type' => 'enum', 'options' => ['left', 'right', 'none'], 'default' => 'left'],
                'icon' => ['type' => 'icon', 'options' => Icons::names(), 'default' => 'mdi:magnify'],
                'variant' => ['type' => 'enum', 'options' => ['outline', 'filled', 'underline'], 'default' => 'outline'],
                'size' => ['type' => 'enum', 'options' => ['sm', 'md', 'lg'], 'default' => 'md'],
                'show_type' => ['type' => 'bool', 'default' => true],
                'no_results_text' => ['type' => 'string', 'max' => 200, 'default' => ''],
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
            // Each icon shows while its mode is on; an empty label uses the visitor's language.
            'theme_toggle' => [
                'light_icon' => ['type' => 'icon', 'options' => Icons::names(), 'default' => 'mdi:white-balance-sunny'],
                'dark_icon' => ['type' => 'icon', 'options' => Icons::names(), 'default' => 'mdi:weather-night'],
                'size' => ['type' => 'int', 'min' => 12, 'max' => 96, 'unit' => 'px', 'default' => 20],
                'variant' => ['type' => 'enum', 'options' => ['plain', 'outline', 'filled'], 'default' => 'plain'],
                'show_label' => ['type' => 'bool', 'default' => false],
                'label' => ['type' => 'string', 'max' => 120, 'default' => ''],
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
            self::PAGE_CONTENT => ['section'],
            // The top of a component's own tree holds just its root kind.
            'component:section' => ['section'],
            'component:row' => ['row'],
            'component:column' => ['column'],
            default => [],
        };
    }

    /**
     * Everything the admin builder needs to draw palettes and inspectors.
     * With $pageContent (detail layouts) the page content place is offered.
     */
    public static function spec(bool $pageContent = false): array
    {
        $kinds = [];

        foreach ([...self::STRUCTURE, ...self::ELEMENTS, 'tab', self::COMPONENT, self::PAGE_CONTENT] as $kind) {
            $props = self::propSpec($kind);
            foreach (self::LOCALIZED_PROPS[$kind] ?? [] as $key) $props[$key]['localized'] = true;
            foreach (SmartValue::PROPERTIES[$kind] ?? [] as $key => $binding) {
                $props[$key]['binding'] = $binding;
            }
            $kinds[$kind] = [
                'props' => $props,
                'style' => self::styleKeys($kind),
                'children' => self::childrenFor($kind, $pageContent),
                'responsive' => ['props' => self::RESPONSIVE_PROPS[$kind] ?? [], 'style' => array_values(array_intersect(self::RESPONSIVE_STYLE, self::styleKeys($kind)))],
                'states' => [
                    'props' => self::STATE_PROPS[$kind] ?? [],
                    'style' => array_values(array_intersect(self::STATE_STYLE, self::styleKeys($kind))),
                    'variants' => ['hover' => ['style' => array_values(array_intersect(self::HOVER_ANIMATION_KEYS, self::styleKeys($kind)))]],
                ],
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
            'root' => self::childrenFor('root', $pageContent),
            'style' => $style,
            'elements' => $pageContent ? [...self::ELEMENTS, self::PAGE_CONTENT] : self::ELEMENTS,
            'item_elements' => self::ITEM_ELEMENTS,
            'field_displays' => self::FIELD_DISPLAYS,
            'system_fields' => self::SYSTEM_FIELDS,
            'variants' => self::VARIANTS,
            'max_row_depth' => self::MAX_ROW_DEPTH,
            'max_node_name_length' => self::MAX_NODE_NAME_LENGTH,
            'regions' => self::REGIONS,
            'special_pages' => self::SPECIAL_PAGES,
            'link_types' => self::LINK_TYPES,
            'devices' => array_keys(StyleVariants::DEVICES),
            'states' => StyleVariants::STATES,
        ];
    }

    /**
     * Validate a whole layout. Unknown kinds, misplaced nodes, props and style
     * values are dropped rather than rejected so an older or hand-edited
     * layout keeps rendering what is still valid.
     */
    public static function normalize(mixed $nodes, bool $pageContent = false): array
    {
        $count = 0;
        $seen = [];
        $places = $pageContent ? [] : null;

        return self::normalizeNodes(is_array($nodes) ? $nodes : [], 'root', $count, $seen, false, 0, $places);
    }

    /** Kinds a node of $parent may contain, with the page content place where it is allowed. */
    private static function childrenFor(string $parent, bool $pageContent): array
    {
        $children = self::allowedChildren($parent);

        return $pageContent && in_array($parent, ['root', 'column', 'tab'], true) ? [...$children, self::PAGE_CONTENT] : $children;
    }

    /**
     * An entry's page content as stored in its page field: sections, with
     * the format they were saved in. Content saved in another format stays
     * as it is (see pageNodes()) instead of being dropped.
     */
    public static function pageValue(mixed $nodes): array
    {
        return ['format_version' => self::FORMAT_VERSION, 'nodes' => self::normalize($nodes)];
    }

    /** The sections of a stored page field value; empty when the entry has none. */
    public static function pageNodes(mixed $value): array
    {
        return is_array($value) && is_array($value['nodes'] ?? null) ? self::normalize($value['nodes']) : [];
    }

    /** Whether a stored page field value holds content of the entry's own. */
    public static function hasPageContent(mixed $value): bool
    {
        return is_array($value) && is_array($value['nodes'] ?? null) && $value['nodes'] !== [];
    }

    /** Whether a stored page field value was saved in an older format and needs saving again. */
    public static function pageNeedsUpgrade(mixed $value): bool
    {
        return self::hasPageContent($value) && ($value['format_version'] ?? null) !== self::FORMAT_VERSION;
    }

    /** The page content places of a detail layout: field => node. */
    public static function pagePlaces(array $nodes): array
    {
        $places = [];
        foreach ($nodes as $node) {
            if (($node['kind'] ?? '') === self::PAGE_CONTENT) {
                $field = (string) ($node['props']['field'] ?? '');
                if ($field !== '' && !isset($places[$field])) $places[$field] = $node;
                continue;
            }
            $places += self::pagePlaces(is_array($node['children'] ?? null) ? $node['children'] : []);
        }

        return $places;
    }

    /**
     * The words visitors read in a tree, for search and descriptions:
     * literal texts in $locale. Texts bound to fields are left out; the
     * fields are read on their own.
     */
    public static function text(array $nodes, string $locale, string $defaultLocale): string
    {
        $parts = [];
        $visit = static function (array $nodes) use (&$visit, &$parts, $locale, $defaultLocale): void {
            foreach ($nodes as $node) {
                if (!is_array($node)) {
                    continue;
                }
                $props = LocalizedValue::tree(is_array($node['props'] ?? null) ? $node['props'] : [], $locale, $defaultLocale);
                foreach (['text', 'html', 'label', 'title', 'alt', 'caption'] as $key) {
                    $value = $props[$key] ?? null;
                    if (is_string($value) && $value !== '' && in_array($key, self::LOCALIZED_PROPS[$node['kind'] ?? ''] ?? [], true)) {
                        $parts[] = $key === 'html' ? html_entity_decode(strip_tags(str_replace(['</p>', '<br>'], ' ', $value)), ENT_QUOTES | ENT_HTML5, 'UTF-8') : $value;
                    }
                }
                $visit(is_array($node['children'] ?? null) ? $node['children'] : []);
            }
        };
        $visit($nodes);

        return trim((string) preg_replace('/\s+/u', ' ', implode(' ', $parts)));
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

    /**
     * An instance's own values for props its component marks editable:
     * {inner node id: {prop: value}}. Only their shape is checked here; the
     * values are checked against the inner node when they are applied, as
     * the component may have changed since.
     */
    private static function instanceOverrides(mixed $value): array
    {
        $normalized = [];
        foreach (array_slice(is_array($value) ? $value : [], 0, self::MAX_NODES, true) as $id => $props) {
            $id = self::key($id);
            if ($id === '' || !is_array($props)) {
                continue;
            }
            foreach (array_slice($props, 0, 50, true) as $key => $item) {
                $key = self::key($key);
                if ($key !== '' && $item !== null) {
                    $normalized[$id][$key] = $item;
                }
            }
        }

        return $normalized;
    }

    /**
     * A component's tree as one instance shows it: each of its own values
     * replaces the value of a prop the component marks editable. Values for
     * props that are no longer editable are kept on the instance but ignored.
     */
    public static function applyOverrides(array $nodes, array $overrides): array
    {
        if ($overrides === []) {
            return $nodes;
        }

        foreach ($nodes as &$node) {
            $own = array_intersect_key($overrides[$node['id'] ?? ''] ?? [], array_flip($node['editable'] ?? []));
            if ($own !== [] && ($node['kind'] ?? '') !== self::COMPONENT) {
                $node['props'] = self::normalizeProps((string) $node['kind'], array_replace($node['props'] ?? [], $own));
            }
            $node['children'] = self::applyOverrides($node['children'] ?? [], $overrides);
        }

        return $nodes;
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
        $nodes = self::bindDefaultNodes(self::buildDefaultLayout($type, $variant), self::defaultFields($type));
        // Entries of a type with a page field design their page; the generated layout is where they start.
        $field = $variant === 'detail' ? self::firstFieldOfType(is_array($type['fields'] ?? null) ? $type['fields'] : [], ['page']) : null;
        if ($field !== null) {
            $nodes = [self::node(self::PAGE_CONTENT, ['field' => $field], [], $nodes)];
        }

        return self::withDefaultIds($nodes, 'layout:' . ($type['name'] ?? '') . ':' . $variant);
    }

    /** Generated defaults must keep their revision hash across reads and requests. */
    private static function withDefaultIds(array $nodes, string $path): array
    {
        foreach ($nodes as $index => &$node) {
            $nodePath = $path . ':' . $index;
            $node['id'] = 'n' . substr(hash('sha256', $nodePath), 0, 10);
            $node['children'] = self::withDefaultIds($node['children'] ?? [], $nodePath);
        }

        return $nodes;
    }

    /** The standard card of a content type: one column, as a collection repeats it. */
    public static function defaultCardTemplate(array $type): array
    {
        return self::bindDefaultNodes(self::buildDefaultLayout($type, 'card'), self::defaultFields($type));
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

            $body = [
                self::node('field', ['field' => 'title', 'tag' => 'h3', 'link' => true], ['margin_bottom' => 'xs', 'font_size' => 'xl']),
                self::node('field', ['field' => 'published_at', 'display' => 'date'], ['text_color' => 'muted', 'font_size' => 'sm', 'margin_bottom' => 'sm']),
            ];

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

        $elements = [
            self::node('field', ['field' => 'title', 'tag' => 'h1']),
            self::node('field', ['field' => 'published_at', 'display' => 'date', 'date_format' => 'long'], ['text_color' => 'muted', 'margin_bottom' => 'lg']),
        ];

        foreach ($fields as $name => $config) {
            $name = (string) $name;
            if (!is_array($config) || in_array($name, ['title', 'slug', $slugField], true)) {
                continue;
            }

            $fieldType = (string) ($config['type'] ?? 'text');
            if (in_array($fieldType, ['json', 'page'], true)) {
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
        return self::withDefaultIds(self::buildDefaultRegion($region), 'region:' . $region);
    }

    private static function buildDefaultRegion(string $region): array
    {
        if ($region === 'not_found') {
            return [
                self::node('section', ['width' => 'narrow'], ['padding_y' => '2xl'], [
                    self::node('row', [], [], [
                        self::node('column', [], [], [
                            self::node('heading', ['text' => 'Page not found', 'level' => 'h1']),
                            self::node('text', ['html' => '<p>The page you are looking for does not exist or has moved.</p>']),
                            self::node('button', ['label' => 'Back to the homepage', 'link' => ['type' => 'home']]),
                        ]),
                    ]),
                ]),
            ];
        }

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

    /**
     * $rows counts the rows around $nodes, to bound how deep rows nest in
     * columns. $places collects the page fields that already have a place
     * (null where no page content place may go).
     */
    private static function normalizeNodes(array $nodes, string $parent, int &$count, array &$seen, bool $insideItem, int $rows = 0, ?array &$places = null): array
    {
        $allowed = self::childrenFor($parent, $places !== null);
        $normalized = [];

        foreach (array_values($nodes) as $node) {
            if (!is_array($node) || $count >= self::MAX_NODES) {
                continue;
            }

            // Promote saved gallery displays while preserving identity and styling.
            if (($node['kind'] ?? '') === 'field' && ($node['props']['display'] ?? '') === 'gallery') {
                $node = self::galleryFromField($node);
            }
            // Entry lists used to offer a plain list; it became cards in a single column.
            if (($node['kind'] ?? '') === 'collection' && ($node['props']['display'] ?? '') === 'list') {
                $node['props']['columns'] = 1;
            }
            $kind = (string) ($node['kind'] ?? '');
            $placed = self::placementKind($node);
            if ($placed === '' || !in_array($placed, $allowed, true) || ($placed === 'row' && $rows > self::MAX_ROW_DEPTH)) {
                continue;
            }

            // Each page field has one place, which needs a field to show.
            if ($kind === self::PAGE_CONTENT) {
                $field = self::key($node['props']['field'] ?? '');
                if ($field !== '' && isset($places[$field])) {
                    continue;
                }
                if ($field !== '') {
                    $places[$field] = true;
                }
            }

            $count++;
            $id = self::key($node['id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                $id = self::newId();
            }
            $seen[$id] = true;

            // A repeater's item template may not contain another template.
            $childParent = $kind === 'field' ? ($insideItem ? '' : 'field') : $kind;
            // Cards, item templates and the default content hold no page content place.
            $childPlaces = in_array($kind, ['field', 'collection', self::PAGE_CONTENT], true) ? null : $places;
            $children = in_array($kind, [...self::STRUCTURE, 'field', 'collection', 'tabs', 'tab', self::PAGE_CONTENT], true) && is_array($node['children'] ?? null)
                ? self::normalizeNodes($node['children'], $childParent, $count, $seen, $insideItem || $kind === 'field', $kind === self::PAGE_CONTENT ? 0 : $rows + ($kind === 'row' ? 1 : 0), $childPlaces)
                : [];
            if ($childPlaces !== null) {
                $places = $childPlaces;
            }
            // A collection repeats a single card.
            if ($kind === 'collection') {
                $children = array_slice($children, 0, 1);
            }

            $node = self::migrateHoverAnimation($node);
            $props = is_array($node['props'] ?? null) ? $node['props'] : [];
            $style = is_array($node['style'] ?? null) ? $node['style'] : [];

            $entry = [
                'id' => $id,
                'kind' => $kind,
                'props' => self::normalizeProps($kind, $props),
                'style' => self::normalizeStyle($kind, $style),
                'children' => $children,
            ];
            foreach (['responsive' => array_keys(StyleVariants::DEVICES), 'states' => StyleVariants::STATES] as $group => $variants) {
                $overrides = self::normalizeOverrides($kind, $node[$group] ?? null, $group, $variants);
                if ($overrides !== []) {
                    $entry[$group] = $overrides;
                }
            }
            $name = is_string($node['name'] ?? null)
                ? trim(Text::limit(str_replace(["\r", "\n"], ' ', $node['name']), self::MAX_NODE_NAME_LENGTH))
                : '';
            if ($name !== '') {
                $entry['name'] = $name;
            }
            // Inside a component: props each placement may set (see applyOverrides()).
            $editable = $kind === self::COMPONENT ? [] : array_values(array_intersect(self::keyList($node['editable'] ?? null), array_keys(self::propSpec($kind))));
            if ($editable !== []) {
                $entry['editable'] = $editable;
            }
            $overrides = $kind === self::COMPONENT ? self::instanceOverrides($node['overrides'] ?? null) : [];
            if ($overrides !== []) {
                $entry['overrides'] = $overrides;
            }
            $normalized[] = $entry;
        }

        return $normalized;
    }

    private static function normalizeProps(string $kind, array $props): array
    {
        $normalized = [];

        foreach (self::propSpec($kind) as $key => $spec) {
            $value = $props[$key] ?? $spec['default'];
            $binding = SmartValue::PROPERTIES[$kind][$key] ?? null;
            $normalize = static fn(mixed $item): mixed => $binding !== null && SmartValue::isDynamic($item)
                ? SmartValue::normalize($item, $binding) : self::normalizeValue($spec, $item);
            $normalized[$key] = in_array($key, self::LOCALIZED_PROPS[$kind] ?? [], true)
                ? LocalizedValue::normalize($value, $normalize, (new LanguageSettings())->all()['locales'])
                : $normalize($value);
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

        foreach (self::styleKeys($kind) as $key) {
            if (!array_key_exists($key, $style)) {
                continue;
            }

            $binding = SmartValue::STYLE_PROPERTIES[$key] ?? null;
            $normalize = static fn(mixed $item): mixed => $binding !== null && SmartValue::isDynamic($item)
                ? SmartValue::normalize($item, $binding) : self::normalizeValue($specs[$key], $item);
            $value = !empty($specs[$key]['localized'])
                ? LocalizedValue::normalize($style[$key], $normalize, (new LanguageSettings())->all()['locales'])
                : $normalize($style[$key]);
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

    /** Upgrade the former single hover animation without rewriting stored layouts. */
    public static function migrateHoverAnimation(array $node): array
    {
        if (($node['style']['animation_trigger'] ?? '') !== 'hover') return $node;
        $states = is_array($node['states'] ?? null) ? $node['states'] : [];
        $hover = is_array($states['hover'] ?? null) ? $states['hover'] : [];
        $style = is_array($hover['style'] ?? null) ? $hover['style'] : [];
        if (!array_key_exists('animation', $style)) {
            $node['states'] = $states;
            $node['states']['hover'] = $hover;
            $node['states']['hover']['style'] = $style;
            foreach (self::HOVER_ANIMATION_KEYS as $key) {
                if (array_key_exists($key, $node['style'])) {
                    $node['states']['hover']['style'][$key] ??= $node['style'][$key];
                }
            }
        }
        foreach (self::ANIMATION_KEYS as $key) unset($node['style'][$key]);
        return $node;
    }

    /**
     * Tablet and mobile, or hover and focus values of a node: only keys the
     * kind offers for them, and only set values. An empty value follows the
     * normal one (or the larger screen), so it is not kept.
     */
    private static function normalizeOverrides(string $kind, mixed $value, string $group, array $variants): array
    {
        if (!is_array($value)) {
            return [];
        }
        $keys = $group === 'responsive'
            ? ['props' => self::RESPONSIVE_PROPS[$kind] ?? [], 'style' => self::RESPONSIVE_STYLE]
            : ['props' => self::STATE_PROPS[$kind] ?? [], 'style' => self::STATE_STYLE];
        $specs = ['props' => self::propSpec($kind), 'style' => self::styleSpec()];
        $styleKeys = self::styleKeys($kind);
        $normalized = [];

        foreach ($variants as $variant) {
            $variantKeys = $keys;
            if ($group === 'states' && $variant === 'hover') {
                $variantKeys['style'] = [...$variantKeys['style'], ...self::HOVER_ANIMATION_KEYS];
            }
            foreach (['props', 'style'] as $part) {
                $values = $value[$variant][$part] ?? null;
                if (!is_array($values)) {
                    continue;
                }
                foreach ($variantKeys[$part] as $key) {
                    if (!array_key_exists($key, $values) || ($part === 'style' && !in_array($key, $styleKeys, true))) {
                        continue;
                    }
                    $item = self::normalizeValue($specs[$part][$key], $values[$key]);
                    if ($item !== '') {
                        $normalized[$variant][$part][$key] = $item;
                    }
                }
            }
        }

        if ($group === 'states' && isset($normalized['hover']['style'])) {
            $hover = &$normalized['hover']['style'];
            if (($hover['animation'] ?? 'none') === 'none') {
                foreach (self::HOVER_ANIMATION_KEYS as $key) unset($hover[$key]);
            } else {
                foreach (['animation_duration', 'animation_delay'] as $key) {
                    if (($hover[$key] ?? null) === $specs['style'][$key]['default']) unset($hover[$key]);
                }
            }
            unset($hover);
            if ($normalized['hover']['style'] === []) unset($normalized['hover']['style']);
            if ($normalized['hover'] === []) unset($normalized['hover']);
        }
        return $normalized;
    }

    private static function normalizeValue(array $spec, mixed $value): mixed
    {
        $default = $spec['default'];

        return match ($spec['type']) {
            'gradient' => Gradient::normalize($value),
            'shadow' => Shadow::normalize($value, $spec['text'] ?? false),
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
            'field', 'display', 'content_type', 'menu', 'form', 'key', 'page_field' => self::key($value),
            'content_types', 'search_fields' => self::keyList($value),
            'sort_fields' => array_slice(self::keyList($value), 0, ListFilters::MAX_SORTS),
            'list_filters' => ListFilters::normalize($value),
            default => $default,
        };
    }

    /** Distinct keys in their saved order, e.g. the content types a search looks through. */
    private static function keyList(mixed $value): array
    {
        $keys = array_map(self::key(...), array_slice(is_array($value) ? array_values($value) : [], 0, 100));

        return array_values(array_unique(array_filter($keys, static fn(string $key): bool => $key !== '')));
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
