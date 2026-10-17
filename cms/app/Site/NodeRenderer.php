<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Core\HtmlSanitizer;
use CometCMS\Core\Security;

/**
 * Turns layout trees into HTML.
 *
 * Every dynamic value is escaped with Security::e() or passed through the
 * HTML allow-list; style values only ever become class names from a fixed
 * vocabulary (see LayoutSchema), so layouts cannot inject markup.
 *
 * Render state ($state) carries what the nodes are bound to:
 *   entry      the entry the layout shows (null for header/footer without one)
 *   type       its content type schema
 *   row        current repeater row inside an item template
 *   row_fields subfield configs of that repeater, keyed by subfield key
 *   depth      nesting of card renders, to stop relation cycles
 *   page       current page number for paginated collections
 *   path       current URL path, for pagination and active menu items
 *   alternates locale => URL of the current page, for language switchers
 *   sizes      "sizes" hint for responsive images inside grids
 */
final class NodeRenderer
{
    private const MAX_CARD_DEPTH = 2;

    private int $annotationsSuspended = 0;

    public function __construct(private readonly SiteContext $ctx) {}

    public function render(array $nodes, array $state = []): string
    {
        $html = '';

        foreach ($nodes as $node) {
            $html .= $this->node($node, $state);
        }

        return $html;
    }

    /** Render without builder annotations (repeated cards would duplicate node ids). */
    public function renderPlain(array $nodes, array $state = []): string
    {
        $this->annotationsSuspended++;

        try {
            return $this->render($nodes, $state);
        } finally {
            $this->annotationsSuspended--;
        }
    }

    private function node(array $node, array $state): string
    {
        return match ($node['kind'] ?? '') {
            'section' => $this->section($node, $state),
            'row' => $this->row($node, $state),
            'column' => $this->column($node, $state),
            'field' => $this->field($node, $state),
            'heading' => $this->heading($node, $state),
            'text' => $this->text($node, $state),
            'image' => $this->image($node, $state),
            'button' => $this->button($node, $state),
            'spacer' => $this->element($node, '', ['c-spacer', 'c-spacer-' . $node['props']['size']]),
            'divider' => $this->element($node, '<hr class="c-divider' . (!empty($node['style']['text_color']) ? ' c-divider-colored' : '') . '">'),
            'collection' => $this->collection($node, $state),
            'menu' => $this->menu($node, $state),
            'site_title' => $this->siteTitle($node, $state),
            'language_switcher' => $this->languageSwitcher($node, $state),
            default => '',
        };
    }

    // ─── Structure ───────────────────────────────────────────────────────────

    private function section(array $node, array $state): string
    {
        $props = $node['props'];
        $inCard = ($state['in_card'] ?? false) === true;
        $classes = ['c-section', 'c-section-' . $props['width']];
        $style = '';

        if ($props['min_height'] !== '') {
            $classes[] = 'c-min-h-' . $props['min_height'];
        }
        if ($props['vertical_align'] !== 'start') {
            $classes[] = 'c-v-' . $props['vertical_align'];
        }
        if ($props['sticky'] && !$inCard) {
            $classes[] = 'c-sticky';
        }

        $background = $props['background_image'] !== '' ? $this->ctx->mediaItem($props['background_image']) : null;
        if ($background !== null && str_starts_with((string) $background['mime'], 'image/')) {
            $classes[] = 'c-has-bg-image';
            $style = 'background-image:url(\'' . $this->imageSource($background, 1920) . '\')';
            $classes[] = 'c-bg-size-' . $props['background_size'];
            $classes[] = 'c-bg-pos-' . $props['background_position'];
            $classes[] = $props['background_repeat'] ? 'c-bg-repeat' : 'c-bg-no-repeat';
            if ($props['overlay'] !== 'none') {
                $classes[] = 'c-overlay-' . $props['overlay'];
            }
        }

        $inner = $this->render($node['children'], $state);
        if ($inner === '' && !$this->annotating()) {
            return '';
        }

        $tag = $inCard ? 'div' : 'section';
        $container = $props['width'] === 'full' ? $inner : '<div class="c-container c-container-' . $props['width'] . '">' . $inner . '</div>';

        return '<' . $tag . $this->attributes($node, [...$classes, ...$this->styleClasses($node['style'])], $style) . '>' . $container . '</' . $tag . '>';
    }

    private function row(array $node, array $state): string
    {
        $props = $node['props'];
        $classes = ['c-row', 'c-gap-' . $props['gap'], 'c-items-' . $props['align'], 'c-justify-' . $props['justify']];

        if ($props['reverse_mobile']) {
            $classes[] = 'c-reverse-mobile';
        }

        $inner = $this->render($node['children'], $state);
        if (trim(strip_tags($inner, '<img><video><audio><hr><svg><input>')) === '' && !$this->annotating()) {
            return '';
        }

        return '<div' . $this->attributes($node, [...$classes, ...$this->styleClasses($node['style'])]) . '>' . $inner . '</div>';
    }

    private function column(array $node, array $state): string
    {
        $span = $node['props']['span'];
        $classes = ['c-col', 'c-col-' . $span['mobile']];

        if ($span['tablet'] > 0) {
            $classes[] = 'c-t-col-' . $span['tablet'];
        }
        if ($span['desktop'] > 0) {
            $classes[] = 'c-d-col-' . $span['desktop'];
        }
        if ($node['props']['vertical_align'] !== 'start') {
            $classes[] = 'c-self-' . $node['props']['vertical_align'];
        }

        $inner = $this->render($node['children'], $state);
        if ($inner === '' && $this->annotating()) {
            $inner = '<div class="c-placeholder c-placeholder-drop">Empty column</div>';
        }

        return '<div' . $this->attributes($node, [...$classes, ...$this->styleClasses($node['style'])]) . '>' . $inner . '</div>';
    }

    // ─── Elements ────────────────────────────────────────────────────────────

    private function heading(array $node, array $state): string
    {
        $level = $node['props']['level'];
        $text = Security::e($this->placeholders($node['props']['text']));
        $link = $this->resolveLink($node['props']['link'], $state);

        if ($link !== null) {
            $text = $this->anchor($link, $text);
        }

        return $this->element($node, '<' . $level . ' class="c-heading">' . $text . '</' . $level . '>');
    }

    private function text(array $node, array $state): string
    {
        $html = HtmlSanitizer::sanitize($node['props']['html']);

        return $this->element($node, '<div class="c-prose">' . $this->placeholders($html, true) . '</div>');
    }

    private function image(array $node, array $state): string
    {
        $props = $node['props'];
        $item = $props['media'] !== '' ? $this->ctx->mediaItem($props['media']) : null;

        if ($item === null) {
            return $this->annotating() ? $this->element($node, '<div class="c-placeholder">Choose an image</div>') : '';
        }

        $html = $this->mediaElement($item, $props, $state, $props['alt']);
        $link = $this->resolveLink($props['link'], $state);
        if ($link !== null) {
            $html = $this->anchor($link, $html, 'c-image-link');
        }

        if ($props['caption'] !== '') {
            $html = '<figure class="c-figure">' . $html . '<figcaption>' . Security::e($props['caption']) . '</figcaption></figure>';
        }

        return $this->element($node, $html);
    }

    private function button(array $node, array $state): string
    {
        $props = $node['props'];
        $link = $this->resolveLink($props['link'], $state);
        $classes = 'c-button c-button-' . $props['variant'] . ' c-button-' . $props['size'] . ($props['full_width'] ? ' c-button-block' : '');
        $label = Security::e($this->placeholders($props['label']));

        if ($link === null) {
            return $this->annotating() ? $this->element($node, '<span class="' . $classes . '">' . $label . '</span>') : '';
        }

        return $this->element($node, $this->anchor($link, $label, $classes));
    }

    private function siteTitle(array $node, array $state): string
    {
        $props = $node['props'];
        $settings = $this->ctx->settings();
        $parts = '';
        $logo = $props['show_logo'] && $settings['logo'] !== '' ? $this->ctx->mediaItem($settings['logo']) : null;

        if ($logo !== null) {
            $parts .= '<img class="c-logo" src="' . Security::e($this->imageSource($logo, 640)) . '" alt="' . ($props['show_name'] ? '' : Security::e($this->ctx->siteTitle())) . '"'
                . $this->dimensions($logo) . '>';
        }

        $text = '';
        if ($props['show_name']) {
            $text .= '<span class="c-site-name">' . Security::e($this->ctx->siteTitle()) . '</span>';
        }
        if ($props['show_tagline'] && $settings['tagline'] !== '') {
            $text .= '<span class="c-site-tagline">' . Security::e($settings['tagline']) . '</span>';
        }
        if ($text !== '') {
            $parts .= '<span class="c-site-text">' . $text . '</span>';
        }

        return $this->element($node, '<a class="c-site-title" href="' . Security::e($this->ctx->homeUrl()) . '" rel="home">' . $parts . '</a>');
    }

    private function languageSwitcher(array $node, array $state): string
    {
        $locales = $this->ctx->locales();

        if (count($locales) < 2) {
            return $this->annotating() ? $this->element($node, '<div class="c-placeholder">Add website languages to show the switcher</div>') : '';
        }

        $alternates = is_array($state['alternates'] ?? null) ? $state['alternates'] : [];
        $items = '';

        foreach ($locales as $locale) {
            $url = $alternates[$locale] ?? $this->ctx->homeUrl($locale);
            $label = $node['props']['format'] === 'names' ? $this->localeName($locale) : strtoupper($locale);
            $current = $locale === ($this->ctx->locale !== '' ? $this->ctx->locale : $this->ctx->defaultLocale());
            $items .= '<li><a href="' . Security::e($url) . '" hreflang="' . Security::e($locale) . '" lang="' . Security::e($locale) . '"'
                . ($current ? ' aria-current="true"' : '') . '>' . Security::e($label) . '</a></li>';
        }

        return $this->element($node, '<nav class="c-languages" aria-label="Language"><ul>' . $items . '</ul></nav>');
    }

    private function menu(array $node, array $state): string
    {
        $props = $node['props'];
        $menu = $this->ctx->menu($props['menu']);

        if ($menu === null || $menu['items'] === []) {
            return $this->annotating() ? $this->element($node, '<div class="c-placeholder">Menu “' . Security::e($props['menu']) . '” has no items</div>') : '';
        }

        $list = $this->menuItems($menu['items'], $state, 'c-menu-list');
        $classes = 'c-menu c-menu-' . $props['orientation'] . ($props['collapse_mobile'] ? ' c-menu-collapsible' : '');
        $toggle = '';

        if ($props['collapse_mobile']) {
            $id = 'menu-' . Security::e($node['id']);
            $toggle = '<input type="checkbox" id="' . $id . '" class="c-menu-toggle" aria-label="Menu">'
                . '<label for="' . $id . '" class="c-menu-button" aria-hidden="true"><span></span><span></span><span></span></label>';
        }

        return $this->element($node, '<nav class="' . $classes . '" aria-label="' . Security::e($menu['label']) . '">' . $toggle . $list . '</nav>');
    }

    private function menuItems(array $items, array $state, string $class): string
    {
        $html = '';
        $currentPath = (string) ($state['path'] ?? '');

        foreach ($items as $item) {
            $link = $this->resolveLink($item['link'], $state);
            $label = (string) (($item['labels'][$this->ctx->locale] ?? '') ?: $item['label']);

            if ($label === '') {
                $label = $this->linkTitle($item['link']);
            }

            $text = Security::e($label);
            $active = $link !== null && $currentPath !== '' && $link['href'] === $currentPath;
            $inner = $link !== null
                ? '<a href="' . Security::e($link['href']) . '"' . ($link['new_tab'] ? ' target="_blank" rel="noopener noreferrer"' : '') . ($active ? ' aria-current="page"' : '') . '>' . $text . '</a>'
                : '<span>' . $text . '</span>';
            $children = $item['children'] !== [] ? $this->menuItems($item['children'], $state, 'c-submenu') : '';

            $html .= '<li' . ($children !== '' ? ' class="c-has-children"' : '') . '>' . $inner . $children . '</li>';
        }

        return '<ul class="' . $class . '">' . $html . '</ul>';
    }

    private function collection(array $node, array $state): string
    {
        $props = $node['props'];
        $type = $props['type'];

        if ($type === '' || !$this->ctx->isPublicType($type) || ($state['depth'] ?? 0) > self::MAX_CARD_DEPTH) {
            return $this->annotating() ? $this->element($node, '<div class="c-placeholder">Choose a public content type</div>') : '';
        }

        $schema = $this->ctx->type($type);
        $page = $props['paginate'] ? max(1, (int) ($state['page'] ?? 1)) : 1;
        $params = [
            'sort' => ($props['sort_order'] === 'desc' ? '-' : '') . $props['sort_field'],
            'limit' => $props['limit'],
            'offset' => ($page - 1) * $props['limit'],
        ];

        if ($props['filter_field'] !== '' && $props['filter_value'] !== '') {
            $params['filter'] = [$props['filter_field'] => $props['filter_value']];
        }

        $current = $state['entry'] ?? null;
        if ($props['exclude_current'] && is_array($current) && ($current['collection'] ?? '') === $type) {
            $params['filter']['slug'] = ['ne' => (string) ($current['slug'] ?? $current['id'] ?? '')];
        }

        $result = $this->ctx->query($type, $params);

        if ($result['data'] === []) {
            $empty = $props['empty_text'] !== '' ? '<p class="c-empty">' . Security::e($props['empty_text']) . '</p>' : '';
            if ($empty === '' && $this->annotating()) {
                $empty = '<div class="c-placeholder">No published ' . Security::e((string) ($schema['label'] ?? $type)) . ' yet</div>';
            }

            return $empty === '' ? '' : $this->element($node, $empty);
        }

        $html = $props['display'] === 'list'
            ? $this->entryList($type, $result['data'], $state)
            : $this->cardGrid($type, $result['data'], $props['columns'], $state);

        if ($props['paginate'] && $result['total'] > $props['limit']) {
            $html .= $this->pagination($page, (int) ceil($result['total'] / $props['limit']), $state);
        }

        return $this->element($node, $html);
    }

    private function cardGrid(string $type, array $entries, int $columns, array $state): string
    {
        $layout = $this->ctx->layout($type, 'card');
        $items = '';
        $childState = [
            'type' => $this->ctx->type($type),
            'depth' => ($state['depth'] ?? 0) + 1,
            'in_card' => true,
            'path' => $state['path'] ?? '',
            'sizes' => $columns > 1 ? '(min-width: 1024px) ' . (int) floor(100 / $columns) . 'vw, (min-width: 640px) 50vw, 100vw' : '100vw',
        ];

        foreach ($entries as $entry) {
            $items .= '<article class="c-card">' . $this->renderPlain($layout, ['entry' => $this->ctx->localize($type, $entry)] + $childState) . '</article>';
        }

        return '<div class="c-grid c-cols-' . max(1, min(6, $columns)) . '">' . $items . '</div>';
    }

    private function entryList(string $type, array $entries, array $state): string
    {
        $items = '';

        foreach ($entries as $entry) {
            $entry = $this->ctx->localize($type, $entry);
            $url = $this->ctx->entryUrl($type, $entry);
            $title = Security::e((string) ($entry['title'] ?? ''));
            $date = !empty($entry['published_at']) ? ' <time class="c-muted" datetime="' . Security::e((string) $entry['published_at']) . '">' . Security::e($this->formatDate((string) $entry['published_at'], 'date')) . '</time>' : '';
            $items .= '<li>' . ($url !== null ? '<a href="' . Security::e($url) . '">' . $title . '</a>' : $title) . $date . '</li>';
        }

        return '<ul class="c-entry-list">' . $items . '</ul>';
    }

    private function pagination(int $page, int $pages, array $state): string
    {
        $path = (string) ($state['path'] ?? '');
        $link = static fn(int $target): string => Security::e($path . ($target > 1 ? '?page=' . $target : ''));
        $html = '<nav class="c-pagination" aria-label="Pagination"><ul>';

        $html .= $page > 1
            ? '<li><a href="' . $link($page - 1) . '" rel="prev">‹</a></li>'
            : '<li><span aria-hidden="true">‹</span></li>';

        for ($i = 1; $i <= $pages; $i++) {
            if ($pages > 9 && $i !== 1 && $i !== $pages && abs($i - $page) > 2) {
                if ($i === 2 || $i === $pages - 1) {
                    $html .= '<li><span>…</span></li>';
                }
                continue;
            }

            $html .= $i === $page
                ? '<li><span aria-current="page">' . $i . '</span></li>'
                : '<li><a href="' . $link($i) . '">' . $i . '</a></li>';
        }

        $html .= $page < $pages
            ? '<li><a href="' . $link($page + 1) . '" rel="next">›</a></li>'
            : '<li><span aria-hidden="true">›</span></li>';

        return $html . '</ul></nav>';
    }

    // ─── Field binding ───────────────────────────────────────────────────────

    private function field(array $node, array $state): string
    {
        $props = $node['props'];
        $name = $props['field'];
        [$value, $config] = $this->fieldValue($name, $state);

        if ($config === null) {
            return $this->annotating()
                ? $this->element($node, '<div class="c-placeholder c-placeholder-warning">' . ($name === '' ? 'Choose a field' : 'Missing field “' . Security::e($name) . '”') . '</div>')
                : '';
        }

        $fieldType = (string) ($config['type'] ?? 'text');
        $displays = LayoutSchema::FIELD_DISPLAYS[$fieldType] ?? ['text'];
        $display = in_array($props['display'], $displays, true) ? $props['display'] : $displays[0];
        $html = $this->isEmpty($value) ? '' : $this->fieldHtml($node, $value, $config, $fieldType, $display, $state);

        if ($html === '') {
            return $this->annotating()
                ? $this->element($node, '<div class="c-placeholder">' . Security::e($this->fieldLabel($name, $config)) . ' (empty)</div>')
                : '';
        }

        if ($props['label']) {
            $html = '<div class="c-field-label">' . Security::e($this->fieldLabel($name, $config)) . '</div>' . $html;
        }

        return $this->element($node, $html, ['c-field', 'c-field-' . $fieldType]);
    }

    /** @return array{0: mixed, 1: ?array} value and field config */
    private function fieldValue(string $name, array $state): array
    {
        if ($name === '') {
            return [null, null];
        }

        if (array_key_exists('row', $state) && is_array($state['row'])) {
            $config = $state['row_fields'][$name] ?? null;

            return [$state['row'][$name] ?? null, is_array($config) ? $config : null];
        }

        $entry = is_array($state['entry'] ?? null) ? $state['entry'] : null;

        if (in_array($name, LayoutSchema::SYSTEM_FIELDS, true)) {
            $config = $name === 'title' ? ['type' => 'text', 'label' => 'Title'] : ['type' => 'datetime', 'label' => ucfirst(str_replace('_', ' ', $name))];

            return [$entry[$name] ?? null, $config];
        }

        $config = $state['type']['fields'][$name] ?? null;

        return [$entry[$name] ?? null, is_array($config) ? $config : null];
    }

    private function fieldHtml(array $node, mixed $value, array $config, string $fieldType, string $display, array $state): string
    {
        $props = $node['props'];

        return match ($fieldType) {
            'markdown', 'html' => $display === 'excerpt'
                ? '<p>' . Security::e(Text::words($this->plainText($value, $fieldType), $props['truncate'] ?: 30)) . '</p>'
                : '<div class="c-prose">' . ($fieldType === 'markdown' ? Markdown::toHtml((string) $value) : HtmlSanitizer::sanitize((string) $value)) . '</div>',
            'textarea' => $display === 'excerpt' || $props['truncate'] > 0
                ? $this->textTag($props, Security::e(Text::words((string) $value, $props['truncate'] ?: 30)), $state, 'p')
                : $this->textTag($props, $this->paragraphs((string) $value), $state, 'div'),
            'number', 'range' => $display === 'meter' && $fieldType === 'range'
                ? $this->meter($value, $config, $props)
                : $this->textTag($props, Security::e($props['prefix'] . $this->formatNumber($value) . $props['suffix']), $state, 'p'),
            'boolean' => $this->booleanHtml((bool) $value, $props, $display),
            'select' => $this->selectHtml($value, $config, $props, $display, $state),
            'date', 'datetime' => '<p><time datetime="' . Security::e((string) $value) . '">'
                . Security::e($this->formatDate((string) $value, $fieldType === 'date' && $props['date_format'] === 'datetime' ? 'date' : $props['date_format'])) . '</time></p>',
            'color' => $this->colorHtml((string) $value, $display),
            'media' => $this->mediaField($value, $props, $display, $state),
            'relation' => $this->relationField($value, $config, $props, $display, $state),
            'repeater' => $this->repeaterField($node, $value, $config, $state),
            'json' => '<pre class="c-code"><code>' . Security::e(is_string($value) ? $value : (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '</code></pre>',
            default => $this->textTag($props, Security::e($props['truncate'] > 0 ? Text::words((string) $value, $props['truncate']) : (string) $value), $state, 'p'),
        };
    }

    /** Wrap text in the chosen tag, linking it to the entry when asked. */
    private function textTag(array $props, string $html, array $state, string $defaultTag): string
    {
        $tag = $props['tag'] !== '' ? $props['tag'] : $defaultTag;

        if ($props['link']) {
            $url = $this->currentEntryUrl($state);
            if ($url !== null && $tag !== 'div') {
                $html = '<a href="' . Security::e($url) . '">' . $html . '</a>';
            }
        }

        $class = str_starts_with($tag, 'h') ? ' class="c-heading"' : '';

        return '<' . $tag . $class . '>' . $html . '</' . $tag . '>';
    }

    private function paragraphs(string $text): string
    {
        $blocks = preg_split('/\R{2,}/', trim($text)) ?: [];

        return implode('', array_map(static fn(string $block): string => '<p>' . nl2br(Security::e(trim($block)), false) . '</p>', $blocks));
    }

    private function booleanHtml(bool $value, array $props, string $display): string
    {
        $text = $value ? ($props['true_text'] !== '' ? $props['true_text'] : 'Yes') : ($props['false_text'] !== '' ? $props['false_text'] : 'No');

        return $display === 'badge'
            ? '<span class="c-badge' . ($value ? ' c-badge-on' : '') . '">' . Security::e($text) . '</span>'
            : '<p>' . Security::e($text) . '</p>';
    }

    private function selectHtml(mixed $value, array $config, array $props, string $display, array $state): string
    {
        $options = is_array($config['options'] ?? null) ? $config['options'] : [];
        $labels = array_map(static function (mixed $item) use ($options): string {
            $item = (string) $item;
            return !array_is_list($options) && isset($options[$item]) ? (string) $options[$item] : $item;
        }, is_array($value) ? $value : [$value]);

        if ($display === 'badges') {
            return '<div class="c-badges">' . implode('', array_map(static fn(string $label): string => '<span class="c-badge">' . Security::e($label) . '</span>', $labels)) . '</div>';
        }

        return $this->textTag($props, Security::e($props['prefix'] . implode(', ', $labels) . $props['suffix']), $state, 'p');
    }

    private function colorHtml(string $value, string $display): string
    {
        if (!preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value)) {
            return '';
        }

        $text = '<code>' . Security::e($value) . '</code>';

        return $display === 'swatch'
            ? '<p class="c-color"><span class="c-swatch" style="background:' . Security::e($value) . '"></span>' . $text . '</p>'
            : '<p>' . $text . '</p>';
    }

    private function meter(mixed $value, array $config, array $props): string
    {
        $min = is_numeric($config['min'] ?? null) ? (float) $config['min'] : 0.0;
        $max = is_numeric($config['max'] ?? null) ? (float) $config['max'] : 100.0;
        $number = (float) $value;

        return '<div class="c-meter"><meter min="' . $min . '" max="' . $max . '" value="' . $number . '"></meter><span>'
            . Security::e($props['prefix'] . $this->formatNumber($value) . $props['suffix']) . '</span></div>';
    }

    private function mediaField(mixed $value, array $props, string $display, array $state): string
    {
        $items = array_values(array_filter(array_map(
            fn(mixed $file): ?array => is_scalar($file) ? $this->ctx->mediaItem((string) $file) : null,
            is_array($value) ? $value : [$value]
        )));

        if ($items === []) {
            return '';
        }

        if ($display === 'files') {
            $links = '';
            foreach ($items as $item) {
                $label = (string) (($item['title'] ?? '') ?: $item['name']);
                $links .= '<li><a href="' . Security::e($this->ctx->mediaUrl($item['name'])) . '" download>' . Security::e($label) . '</a> <span class="c-muted">' . Security::e($this->fileSize((int) $item['size'])) . '</span></li>';
            }

            return '<ul class="c-files">' . $links . '</ul>';
        }

        if ($display === 'gallery' && count($items) > 1) {
            $columns = max(2, $props['columns']);
            $gallery = '';
            $state['sizes'] = '(min-width: 1024px) ' . (int) floor(100 / $columns) . 'vw, 50vw';
            foreach ($items as $item) {
                $gallery .= '<div class="c-gallery-item">' . $this->mediaElement($item, $props, $state) . '</div>';
            }

            return '<div class="c-grid c-gallery c-cols-' . $columns . '">' . $gallery . '</div>';
        }

        $html = $this->mediaElement($items[0], $props, $state);
        if ($props['link']) {
            $url = $this->currentEntryUrl($state);
            if ($url !== null) {
                $html = '<a class="c-image-link" href="' . Security::e($url) . '">' . $html . '</a>';
            }
        }

        return $html;
    }

    private function relationField(mixed $value, array $config, array $props, string $display, array $state): string
    {
        $target = (string) ($config['target'] ?? '');

        if ($target === '' || !$this->ctx->isPublicType($target)) {
            return '';
        }

        $entries = [];
        foreach (is_array($value) ? $value : [$value] as $id) {
            $entry = is_scalar($id) ? $this->ctx->findEntry($target, (string) $id) : null;
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        if ($entries === []) {
            return '';
        }

        if ($display === 'cards' && ($state['depth'] ?? 0) < self::MAX_CARD_DEPTH) {
            return $this->cardGrid($target, $entries, max(1, $props['columns']), $state);
        }

        $titles = array_map(function (array $entry) use ($target, $display): string {
            $title = Security::e((string) ($entry['title'] ?? ''));
            $url = $display === 'text' ? null : $this->ctx->entryUrl($target, $entry);

            return $url !== null ? '<a href="' . Security::e($url) . '">' . $title . '</a>' : $title;
        }, $entries);

        if ($display === 'text' || count($titles) === 1) {
            return '<p>' . implode(', ', $titles) . '</p>';
        }

        return '<ul class="c-links">' . implode('', array_map(static fn(string $title): string => '<li>' . $title . '</li>', $titles)) . '</ul>';
    }

    private function repeaterField(array $node, mixed $rows, array $config, array $state): string
    {
        if (!is_array($rows) || $rows === []) {
            return '';
        }

        $subfields = [];
        foreach (is_array($config['subfields'] ?? null) ? $config['subfields'] : [] as $subfield) {
            if (is_array($subfield) && ($subfield['key'] ?? '') !== '') {
                $subfields[(string) $subfield['key']] = $subfield;
            }
        }

        $template = $node['children'];
        if ($template === []) {
            foreach ($subfields as $key => $subfield) {
                $template[] = LayoutSchema::node('field', ['field' => $key, 'display' => LayoutSchema::defaultDisplay((string) ($subfield['type'] ?? 'text'))]);
            }
        }

        $items = '';
        $first = true;
        foreach (array_values($rows) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowState = ['row' => $row, 'row_fields' => $subfields] + $state;
            // Only the first item carries builder annotations; ids must stay unique.
            $inner = $first ? $this->render($template, $rowState) : $this->renderPlain($template, $rowState);
            $items .= '<div class="c-item">' . $inner . '</div>';
            $first = false;
        }

        $columns = max(1, $node['props']['columns']);

        return '<div class="c-items c-grid c-cols-' . $columns . '">' . $items . '</div>';
    }

    // ─── Media ───────────────────────────────────────────────────────────────

    private function mediaElement(array $item, array $props, array $state, string $alt = ''): string
    {
        $mime = (string) ($item['mime'] ?? '');
        $alt = $alt !== '' ? $alt : (string) ($item['alt'] ?? '');

        if (str_starts_with($mime, 'video/')) {
            return '<video class="c-video" controls preload="metadata" src="' . Security::e($this->ctx->mediaUrl($item['name'])) . '"></video>';
        }

        if (str_starts_with($mime, 'audio/')) {
            return '<audio class="c-audio" controls preload="metadata" src="' . Security::e($this->ctx->mediaUrl($item['name'])) . '"></audio>';
        }

        if (!str_starts_with($mime, 'image/')) {
            $label = (string) (($item['title'] ?? '') ?: $item['name']);

            return '<a class="c-file" href="' . Security::e($this->ctx->mediaUrl($item['name'])) . '" download>' . Security::e($label) . '</a>';
        }

        $srcset = '';
        $descriptor = $this->ctx->variantDescriptor($item['name']);
        if ($descriptor !== null && $descriptor['widths'] !== []) {
            $format = (string) $descriptor['default_format'];
            $candidates = array_map(
                fn(int $width): string => $this->ctx->mediaUrl($item['name'], ['w' => $width, 'format' => $format]) . ' ' . $width . 'w',
                $descriptor['widths']
            );
            if (!empty($item['width'])) {
                $candidates[] = $this->ctx->mediaUrl($item['name']) . ' ' . (int) $item['width'] . 'w';
            }
            $srcset = ' srcset="' . Security::e(implode(', ', $candidates)) . '" sizes="' . Security::e((string) ($state['sizes'] ?? '100vw')) . '"';
        }

        $img = '<img src="' . Security::e($this->imageSource($item, 1280)) . '"' . $srcset . ' alt="' . Security::e($alt) . '"'
            . $this->dimensions($item) . ' loading="lazy" decoding="async">';

        $ratio = $props['ratio'] ?? '';
        if ($ratio !== '') {
            $fit = ($props['fit'] ?? 'cover') === 'contain' ? ' c-fit-contain' : '';
            $position = $props['position'] ?? 'center';
            $fit .= $position !== 'center' && in_array($position, LayoutSchema::POSITIONS, true) ? ' c-obj-pos-' . $position : '';

            return '<div class="c-ratio c-ratio-' . str_replace(':', '-', $ratio) . $fit . '">' . $img . '</div>';
        }

        return $img;
    }

    private function imageSource(array $item, int $preferredWidth): string
    {
        $descriptor = $this->ctx->variantDescriptor($item['name']);

        if ($descriptor === null || $descriptor['widths'] === []) {
            return $this->ctx->mediaUrl($item['name']);
        }

        $width = null;
        foreach ($descriptor['widths'] as $candidate) {
            if ($candidate <= $preferredWidth) {
                $width = $candidate;
            }
        }

        return $width === null
            ? $this->ctx->mediaUrl($item['name'])
            : $this->ctx->mediaUrl($item['name'], ['w' => $width, 'format' => (string) $descriptor['default_format']]);
    }

    private function dimensions(array $item): string
    {
        $width = (int) ($item['width'] ?? 0);
        $height = (int) ($item['height'] ?? 0);

        return $width > 0 && $height > 0 ? ' width="' . $width . '" height="' . $height . '"' : '';
    }

    // ─── Links ───────────────────────────────────────────────────────────────

    /** @return array{href: string, new_tab: bool}|null */
    private function resolveLink(array $link, array $state): ?array
    {
        $href = match ($link['type'] ?? 'none') {
            'url' => $this->siteUrl((string) ($link['url'] ?? '')),
            'current' => $this->currentEntryUrl($state),
            'home' => $this->ctx->homeUrl(),
            'index' => $this->ctx->indexUrl((string) ($link['collection'] ?? '')),
            'entry' => $this->linkedEntryUrl((string) ($link['collection'] ?? ''), (string) ($link['entry'] ?? '')),
            default => null,
        };

        return $href === null || $href === '' ? null : ['href' => $href, 'new_tab' => (bool) ($link['new_tab'] ?? false)];
    }

    private function linkedEntryUrl(string $type, string $slug): ?string
    {
        if ($type === '' || $slug === '') {
            return null;
        }

        $entry = $this->ctx->findEntry($type, $slug);

        return $entry !== null ? $this->ctx->entryUrl($type, $entry) : null;
    }

    private function linkTitle(array $link): string
    {
        return match ($link['type'] ?? 'none') {
            'home' => 'Home',
            'index' => (string) ($this->ctx->route((string) ($link['collection'] ?? ''))['index_title'] ?? '') ?: (string) ($this->ctx->type((string) ($link['collection'] ?? ''))['label'] ?? ''),
            'entry' => (string) ($this->ctx->findEntry((string) ($link['collection'] ?? ''), (string) ($link['entry'] ?? ''))['title'] ?? ''),
            'url' => (string) ($link['url'] ?? ''),
            default => '',
        };
    }

    /** Root-relative URLs typed by editors are relative to the website, not the server. */
    private function siteUrl(string $url): ?string
    {
        if ($url === '' || !HtmlSanitizer::isSafeUrl($url)) {
            return null;
        }

        return str_starts_with($url, '/') && !str_starts_with($url, '//') ? $this->ctx->path($url) : $url;
    }

    private function currentEntryUrl(array $state): ?string
    {
        $entry = $state['entry'] ?? null;

        if (!is_array($entry) || ($entry['collection'] ?? '') === '') {
            return null;
        }

        return $this->ctx->entryUrl((string) $entry['collection'], $entry);
    }

    private function anchor(array $link, string $html, string $class = ''): string
    {
        return '<a href="' . Security::e($link['href']) . '"' . ($class !== '' ? ' class="' . $class . '"' : '')
            . ($link['new_tab'] ? ' target="_blank" rel="noopener noreferrer"' : '') . '>' . $html . '</a>';
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function element(array $node, string $inner, array $extraClasses = []): string
    {
        $classes = ['c-el', 'c-el-' . str_replace('_', '-', $node['kind']), ...$extraClasses, ...$this->styleClasses($node['style'])];

        return '<div' . $this->attributes($node, $classes) . '>' . $inner . '</div>';
    }

    private function attributes(array $node, array $classes, string $style = ''): string
    {
        $attributes = ' class="' . Security::e(implode(' ', array_unique(array_filter($classes)))) . '"';

        if (($node['style']['anchor'] ?? '') !== '') {
            $attributes .= ' id="' . Security::e($node['style']['anchor']) . '"';
        }

        if ($style !== '') {
            $attributes .= ' style="' . Security::e($style) . '"';
        }

        if ($this->annotating()) {
            $attributes .= ' data-node="' . Security::e($node['id']) . '" data-kind="' . Security::e($node['kind']) . '"';
        }

        return $attributes;
    }

    private function styleClasses(array $style): array
    {
        $classes = [];
        $prefixes = [
            'padding_y' => 'c-py-',
            'padding_x' => 'c-px-',
            'margin_bottom' => 'c-mb-',
            'background' => 'c-bg-',
            'text_color' => 'c-fg-',
            'align' => 'c-ta-',
            'font_size' => 'c-fs-',
            'font_weight' => 'c-fw-',
            'font_family' => 'c-ff-',
            'radius' => 'c-r-',
            'shadow' => 'c-sh-',
            'border' => 'c-bd-',
            'max_width' => 'c-mw-',
        ];

        foreach ($prefixes as $key => $prefix) {
            if (($style[$key] ?? '') !== '') {
                $classes[] = $prefix . $style[$key];
            }
        }

        foreach ((array) ($style['hide_on'] ?? []) as $device) {
            $classes[] = 'c-hide-' . $device;
        }

        if (($style['class'] ?? '') !== '') {
            array_push($classes, ...explode(' ', (string) $style['class']));
        }

        return $classes;
    }

    private function annotating(): bool
    {
        return $this->ctx->annotate && $this->annotationsSuspended === 0;
    }

    /** Replace {year}, {site} and {tagline} in editor-written text. */
    private function placeholders(string $text, bool $html = false): string
    {
        $settings = $this->ctx->settings();
        $values = [
            '{year}' => date('Y'),
            '{site}' => $this->ctx->siteTitle(),
            '{tagline}' => (string) ($settings['tagline'] ?? ''),
        ];

        if ($html) {
            $values = array_map(static fn(string $value): string => Security::e($value), $values);
        }

        return strtr($text, $values);
    }

    private function fieldLabel(string $name, array $config): string
    {
        $label = trim((string) ($config['label'] ?? ''));

        return $label !== '' ? $label : ucfirst(str_replace(['_', '-'], ' ', $name));
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private function plainText(mixed $value, string $type): string
    {
        return $type === 'markdown'
            ? Markdown::toText((string) $value)
            : trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</li>'], ' ', (string) $value)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function formatNumber(mixed $value): string
    {
        if (!is_numeric($value)) {
            return (string) $value;
        }

        $string = (string) $value;
        $decimals = str_contains($string, '.') ? strlen(rtrim(substr($string, strpos($string, '.') + 1), '0')) : 0;

        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter($this->ctx->htmlLang(), \NumberFormatter::DECIMAL);
            $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals);

            return (string) $formatter->format((float) $value);
        }

        return number_format((float) $value, $decimals);
    }

    public function formatDate(string $value, string $format): string
    {
        $time = strtotime($value);

        if ($time === false) {
            return $value;
        }

        if (class_exists(\IntlDateFormatter::class)) {
            [$date, $clock] = match ($format) {
                'long' => [\IntlDateFormatter::LONG, \IntlDateFormatter::NONE],
                'datetime' => [\IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT],
                'time' => [\IntlDateFormatter::NONE, \IntlDateFormatter::SHORT],
                default => [\IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE],
            };
            $formatter = new \IntlDateFormatter($this->ctx->htmlLang(), $date, $clock, date_default_timezone_get());
            $formatted = $formatter->format($time);

            if (is_string($formatted)) {
                return $formatted;
            }
        }

        return date(match ($format) {
            'long' => 'F j, Y',
            'datetime' => 'Y-m-d H:i',
            'time' => 'H:i',
            default => 'Y-m-d',
        }, $time);
    }

    private function fileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return ($unit === 0 ? (string) $bytes : number_format($size, 1)) . ' ' . $units[$unit];
    }

    private function localeName(string $locale): string
    {
        if (class_exists(\Locale::class)) {
            $name = \Locale::getDisplayName($locale, $locale);
            if (is_string($name) && $name !== '') {
                return ucfirst($name);
            }
        }

        return strtoupper($locale);
    }
}
