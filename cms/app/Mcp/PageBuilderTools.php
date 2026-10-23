<?php

declare(strict_types=1);

namespace CometCMS\Mcp;

use CometCMS\Content\ContentTypeRepository;
use CometCMS\Core\ConflictException;
use CometCMS\Core\ValidationException;
use CometCMS\Localization\LocalizedValue;
use CometCMS\Site\LayoutSchema;
use CometCMS\Site\SiteRepository;
use CometCMS\Site\SmartValue;
use CometCMS\Site\StyleVariants;
use CometCMS\Workspaces\WorkspaceContext;

/** Shared-layout tools; transport and authorization belong to McpServer. */
final class PageBuilderTools
{
    public const NAMES = ['get_builder_schema', 'get_layout', 'get_section', 'get_element', 'set_layout', 'update_section', 'update_element_props'];

    public static function schemas(): array
    {
        $string = ['type' => 'string', 'minLength' => 1];
        $map = ['type' => 'object', 'additionalProperties' => true];
        $target = ['collection' => $string, 'variant' => ['type' => 'string', 'enum' => LayoutSchema::VARIANTS]];
        $revision = ['revision' => $string + ['description' => 'Required revision from the latest layout/section/element read. A stale revision fails with 409.']];
        $nodes = ['type' => 'array', 'maxItems' => LayoutSchema::MAX_NODES, 'items' => ['$ref' => '#/$defs/node']];
        $overrides = ['responsive' => $map + ['description' => '{tablet?, mobile?: {props?, style?}}: values that differ on smaller screens; mobile follows tablet. Replaces the whole map.'], 'states' => $map + ['description' => '{hover?, focus?: {props?, style?}}: values while hovered or focused. Replaces the whole map.']];
        $patch = self::object(['name' => ['type' => 'string'], 'props' => $map, 'style' => $map, ...$overrides, 'children' => $nodes]);
        $definitions = ['node' => self::object([
            'id' => $string + ['pattern' => '^[A-Za-z0-9_-]+$'],
            'kind' => ['type' => 'string', 'enum' => array_keys(LayoutSchema::spec(true)['kinds'])],
            'name' => ['type' => 'string', 'maxLength' => LayoutSchema::MAX_NODE_NAME_LENGTH],
            'props' => $map, 'style' => $map, ...$overrides, 'children' => $nodes,
        ], ['kind'])];
        $contracts = [
            'get_builder_schema' => ['Discover builder vocabulary', 'Read allowed kinds/children and limits, or pass kind for its props/style defaults and bindings. Start here before creating layouts.', ['kind' => $string], []],
            'get_layout' => ['Read layout overview', 'Read ordered section IDs, kinds, counts and revision without a raw tree. Layouts are shared by all entries of the collection/variant.', $target, array_keys($target)],
            'get_section' => ['Read one section', 'Read one top-level section subtree and layout revision. Default props are omitted; use get_builder_schema(kind) for defaults.', $target + ['section_id' => $string], ['collection', 'variant', 'section_id']],
            'get_element' => ['Read one element', 'Read a node by ID, its props/style and immediate child summaries, enclosing section and layout revision.', $target + ['element_id' => $string], ['collection', 'variant', 'element_id']],
            'set_layout' => ['Replace layout', 'Replace all sections, including empty layouts. Requires revision. Changes affect published pages immediately. Discover valid props with get_builder_schema.', $target + $revision + ['sections' => $nodes], ['collection', 'variant', 'revision', 'sections']],
            'update_section' => ['Patch one section', 'Merge supplied props/style keys and name into one top-level section; children explicitly replaces its subtree. Omitted keys and other sections are preserved. Requires revision; saves live.', $target + $revision + ['section_id' => $string, 'patch' => $patch], ['collection', 'variant', 'revision', 'section_id', 'patch']],
            'update_element_props' => ['Patch element properties', 'Merge props/style keys on any node by ID; preserve children. Object-valued properties (including localized maps) replace the whole property; responsive and states replace their whole maps. Requires revision; saves live.', $target + $revision + ['element_id' => $string, 'props' => $map, 'style' => $map, ...$overrides], ['collection', 'variant', 'revision', 'element_id', 'props']],
        ];
        $result = [];
        foreach ($contracts as $name => [$title, $description, $properties, $required]) {
            $write = in_array($name, ['set_layout', 'update_section', 'update_element_props'], true);
            $schema = self::object($properties, $required);
            if (in_array($name, ['set_layout', 'update_section'], true)) $schema['$defs'] = $definitions;
            $result[$name] = [
                'title' => $title, 'description' => $description, 'inputSchema' => $schema,
                'annotations' => ['readOnlyHint' => !$write, 'destructiveHint' => $write, 'idempotentHint' => !$write, 'openWorldHint' => false],
            ];
        }
        return $result;
    }

    private static function object(array $properties, array $required = []): array
    {
        return ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false];
    }

    public function __construct(private readonly WorkspaceContext $workspace, private readonly array $principal) {}

    public function call(string $tool, array $args): array
    {
        $contract = self::schemas()[$tool]['inputSchema'];
        $this->keys($args, array_keys($contract['properties']), 'arguments');
        foreach ($contract['required'] as $key) {
            if (!array_key_exists($key, $args)) $this->invalid($key, 'Required argument is missing.');
        }
        if ($tool === 'get_builder_schema') return ['data' => $this->vocabulary($args)];

        $collection = $this->identifier($args['collection'], 'collection');
        $variant = $args['variant'];
        if (!is_string($variant) || !in_array($variant, LayoutSchema::VARIANTS, true)) $this->invalid('variant', 'Use detail or index.');
        if (!(new ContentTypeRepository($this->workspace))->exists($collection)) {
            throw new McpError('Content type not found.', 404, ['code' => 'not_found', 'collection' => $collection], [], ['Use list_content_types to find a collection, or create_content_type to create one first.']);
        }
        $site = new SiteRepository($this->workspace);
        if (!in_array($variant, $site->enabledVariantsFor($site->types()[$collection] ?? ['name' => $collection]), true)) $this->invalid('variant', 'This collection has no list page. Turn it on in the content type settings, or use detail.');
        $nodes = $site->layout($collection, $variant);
        $revision = LocalizedValue::hash($nodes);
        $target = ['collection' => $collection, 'variant' => $variant];
        if ($tool === 'get_layout') return ['data' => $this->overview($target, $nodes)];
        if ($tool === 'get_section') {
            $index = $this->sectionIndex($nodes, $this->identifier($args['section_id'], 'section_id'));
            return ['data' => $target + ['revision' => $revision, 'section' => $this->compact($nodes[$index])]];
        }
        if ($tool === 'get_element') {
            [$node, $section] = $this->element($nodes, $this->identifier($args['element_id'], 'element_id'));
            return ['data' => $target + ['revision' => $revision, 'section_id' => $section, 'element' => $this->elementView($node)]];
        }

        if (!is_string($args['revision']) || $args['revision'] === '') $this->invalid('revision', 'Read get_layout or get_section and include its revision.');
        if ($args['revision'] !== $revision) $this->conflict();
        if ($tool === 'set_layout') {
            $candidate = $args['sections'];
        } elseif ($tool === 'update_section') {
            $index = $this->sectionIndex($nodes, $this->identifier($args['section_id'], 'section_id'));
            $nodes[$index] = $this->patch($nodes[$index], $args['patch'], true);
            $candidate = $nodes;
        } else {
            $id = $this->identifier($args['element_id'], 'element_id');
            $this->element($nodes, $id); // Fail before mutation if the ID does not exist.
            $candidate = $this->patchElement($nodes, $id, ['props' => $args['props']] + array_intersect_key($args, array_flip(['style', 'responsive', 'states'])));
        }
        $seen = [];
        $count = 0;
        $candidate = $this->prepare($candidate, 'sections', $seen, $count);
        try {
            $normalized = LayoutSchema::normalize($candidate, $variant === 'detail');
        } catch (ValidationException $e) {
            $this->invalid('sections', $e->getMessage() . ' Check localized values and workspace locales.');
        }
        $this->compareNodes($candidate, $normalized, 'sections');
        try {
            $saved = $site->saveLayout($collection, $variant, $normalized, $this->principal, 'mcp.saved', $args['revision']);
        } catch (ConflictException) {
            $this->conflict();
        }
        if ($tool === 'update_element_props') {
            [$node, $section] = $this->element($saved, $args['element_id']);
            return ['data' => $target + ['revision' => LocalizedValue::hash($saved), 'section_id' => $section, 'element' => $this->elementView($node), 'effect' => 'Saved live; shared by entries using this layout.']];
        }
        return ['data' => $this->overview($target, $saved)];
    }

    private function vocabulary(array $args): array
    {
        $spec = LayoutSchema::spec(true);
        if (array_key_exists('kind', $args)) {
            $kind = $this->identifier($args['kind'], 'kind');
            if (!isset($spec['kinds'][$kind])) $this->invalid('kind', 'Unknown kind. Call get_builder_schema without kind to list available kinds.');
            $node = $spec['kinds'][$kind];
            return ['kind' => $kind, 'props' => $node['props'], 'style' => array_intersect_key($spec['style'], array_flip($node['style'])), 'children' => $node['children'], 'responsive' => ['devices' => $spec['devices']] + $node['responsive'], 'states' => ['states' => $spec['states']] + $node['states'], 'bindings' => array_intersect_key($spec['bindings'], array_flip(['sources', 'date_formats'])), 'localized_values' => 'Use {type: localized, values: {locale: value}}. Patches replace the entire property, so preserve other locales.'];
        }
        return [
            'format_version' => $spec['format_version'], 'variants' => $spec['variants'], 'root' => $spec['root'],
            'kinds' => array_map(static fn(array $kind): array => ['children' => $kind['children']], $spec['kinds']),
            'max_nodes' => LayoutSchema::MAX_NODES, 'max_row_depth' => $spec['max_row_depth'],
            'next' => 'Call get_builder_schema with kind for props/styles, then get_layout for section IDs and revision. Layouts are shared and writes take effect immediately.',
        ];
    }

    private function overview(array $target, array $nodes): array
    {
        return $target + [
            'revision' => LocalizedValue::hash($nodes),
            'sections' => array_map(fn(array $node): array => $this->summary($node) + ['node_count' => $this->countNodes([$node])], $nodes),
            'effect' => 'Shared by entries using this layout; saves take effect on published pages immediately.',
            'next' => 'Use get_section(section_id) for a subtree or get_element(element_id) for props. Include revision in every write.',
        ];
    }

    private function summary(array $node): array
    {
        return array_intersect_key($node, array_flip(['id', 'kind', 'name']));
    }

    private function countNodes(array $nodes): int
    {
        $count = count($nodes);
        foreach ($nodes as $node) $count += $this->countNodes($node['children'] ?? []);
        return $count;
    }

    private function compact(array $node): array
    {
        $result = $this->summary($node);
        $defaults = array_map(static fn(array $spec): mixed => $spec['default'], LayoutSchema::propSpec($node['kind']));
        $props = array_filter($node['props'] ?? [], static fn(mixed $value, string $key): bool => $value !== ($defaults[$key] ?? null), ARRAY_FILTER_USE_BOTH);
        if ($props !== []) $result['props'] = $props;
        if (($node['style'] ?? []) !== []) $result['style'] = $node['style'];
        foreach (['responsive', 'states'] as $key) if (($node[$key] ?? []) !== []) $result[$key] = $node[$key];
        if (($node['children'] ?? []) !== []) $result['children'] = array_map($this->compact(...), $node['children']);
        return $result;
    }

    private function elementView(array $node): array
    {
        $result = $this->compact(['children' => []] + $node);
        $result['children'] = array_map($this->summary(...), $node['children'] ?? []);
        return $result;
    }

    private function sectionIndex(array $nodes, string $id): int
    {
        foreach ($nodes as $index => $node) if ($node['id'] === $id) return $index;
        throw new McpError('Section ID not found at the layout root.', 404, ['code' => 'section_not_found', 'section_id' => $id], [], ['Call get_layout to list top-level section IDs. For nested nodes use get_element and update_element_props.']);
    }

    private function element(array $nodes, string $id): array
    {
        foreach ($nodes as $section) {
            $node = $this->find([$section], $id);
            if ($node !== null) return [$node, $section['id']];
        }
        throw new McpError('Element ID not found in this layout.', 404, ['code' => 'element_not_found', 'element_id' => $id], [], ['Read get_layout and get_section to find current node IDs. Component definition children are not part of the layout.']);
    }

    private function find(array $nodes, string $id): ?array
    {
        foreach ($nodes as $node) {
            if ($node['id'] === $id) return $node;
            $found = $this->find($node['children'] ?? [], $id);
            if ($found !== null) return $found;
        }
        return null;
    }

    private function patch(array $node, mixed $patch, bool $section = false): array
    {
        $this->map($patch, 'patch');
        $this->keys($patch, $section ? ['name', 'props', 'style', 'responsive', 'states', 'children'] : ['props', 'style', 'responsive', 'states'], 'patch');
        if ($patch === []) $this->invalid('patch', 'Supply props, style, responsive, states, name or children to change.');
        foreach (['props', 'style'] as $key) {
            if (array_key_exists($key, $patch)) {
                $this->map($patch[$key], 'patch.' . $key);
                $node[$key] = array_replace($node[$key] ?? [], $patch[$key]);
            }
        }
        foreach (['name', 'responsive', 'states', 'children'] as $key) if (array_key_exists($key, $patch)) $node[$key] = $patch[$key];
        return $node;
    }

    private function patchElement(array $nodes, string $id, array $patch): array
    {
        foreach ($nodes as &$node) {
            if ($node['id'] === $id) $node = $this->patch($node, $patch);
            else $node['children'] = $this->patchElement($node['children'] ?? [], $id, $patch);
        }
        return $nodes;
    }

    /** Check shapes and supplied IDs before using the lenient editor normalizer. */
    private function prepare(mixed $nodes, string $path, array &$seen, int &$count, int $depth = 0): array
    {
        if (!is_array($nodes) || !array_is_list($nodes)) $this->invalid($path, 'Expected an array of nodes. Use [] for an empty layout or children.');
        if ($depth > 32) $this->invalid($path, 'Tree is too deep. Reduce nested rows/templates.');
        foreach ($nodes as $index => &$node) {
            $nodePath = $path . '.' . $index;
            if (++$count > LayoutSchema::MAX_NODES) $this->invalid($nodePath, 'Layout exceeds the ' . LayoutSchema::MAX_NODES . ' node limit.');
            $this->map($node, $nodePath);
            $this->keys($node, ['id', 'kind', 'name', 'props', 'style', 'responsive', 'states', 'children'], $nodePath);
            if (!is_string($node['kind'] ?? null) || !in_array($node['kind'], [...LayoutSchema::STRUCTURE, ...LayoutSchema::ELEMENTS, 'tab', LayoutSchema::COMPONENT, LayoutSchema::PAGE_CONTENT], true)) $this->invalid($nodePath . '.kind', 'Unknown or missing kind.');
            if (!array_key_exists('id', $node)) $node['id'] = LayoutSchema::newId();
            $id = $this->identifier($node['id'], $nodePath . '.id');
            if (isset($seen[$id])) $this->invalid($nodePath . '.id', 'Duplicate node ID. Use a unique ID or omit IDs on new nodes.');
            $seen[$id] = true;
            if (array_key_exists('name', $node) && !is_string($node['name'])) $this->invalid($nodePath . '.name', 'Expected a string.');
            foreach (['props', 'style'] as $key) {
                if (!array_key_exists($key, $node)) continue;
                $this->map($node[$key], $nodePath . '.' . $key);
                $specs = $key === 'props' ? LayoutSchema::propSpec($node['kind']) : array_intersect_key(LayoutSchema::styleSpec(), array_flip(LayoutSchema::styleKeys($node['kind'])));
                foreach ($node[$key] as $property => $value) {
                    $valuePath = $nodePath . '.' . $key . '.' . $property;
                    if (!isset($specs[$property])) $this->invalid($valuePath, 'Unknown ' . $key . ' key for ' . $node['kind'] . '.');
                    $localized = $key === 'props' ? in_array($property, LayoutSchema::LOCALIZED_PROPS[$node['kind']] ?? [], true) : !empty($specs[$property]['localized']);
                    $binding = $key === 'props' ? SmartValue::PROPERTIES[$node['kind']][$property] ?? null : SmartValue::STYLE_PROPERTIES[$property] ?? null;
                    $this->valueShape($value, $specs[$property]['type'], $valuePath, $localized, $binding !== null);
                }
            }
            foreach (['responsive' => array_keys(StyleVariants::DEVICES), 'states' => StyleVariants::STATES] as $group => $variants) {
                if (!array_key_exists($group, $node)) continue;
                $this->map($node[$group], $nodePath . '.' . $group);
                $this->keys($node[$group], $variants, $nodePath . '.' . $group);
                foreach ($node[$group] as $variant => $values) {
                    $this->map($values, $nodePath . '.' . $group . '.' . $variant);
                    $this->keys($values, ['props', 'style'], $nodePath . '.' . $group . '.' . $variant);
                    foreach ($values as $part => $items) $this->map($items, $nodePath . '.' . $group . '.' . $variant . '.' . $part);
                }
            }
            $node['children'] = $this->prepare($node['children'] ?? [], $nodePath . '.children', $seen, $count, $depth + 1);
        }
        return $nodes;
    }

    /** Reject malformed compound values before lenient normalizers can cast them. */
    private function valueShape(mixed $value, string $type, string $path, bool $localized = false, bool $binding = false): void
    {
        if ($localized && LocalizedValue::is($value)) {
            $this->map($value['values'] ?? null, $path . '.values');
            foreach ($value['values'] as $locale => $item) $this->valueShape($item, $type, $path . '.values.' . $locale, false, $binding);
            return;
        }
        if ($binding && SmartValue::isDynamic($value)) return; // SmartValue validates its own structure.
        if (in_array($type, ['set', 'media_list', 'content_types', 'search_fields', 'sort_fields'], true)) {
            if (!is_array($value) || !array_is_list($value)) $this->invalid($path, 'Expected an array of strings.');
            foreach ($value as $index => $item) if (!is_string($item)) $this->invalid($path . '.' . $index, 'Expected a string.');
            return;
        }
        if ($type === 'list_filters') {
            if (!is_array($value) || !array_is_list($value)) $this->invalid($path, 'Expected an array of {field, label?, multiple?} objects.');
            foreach ($value as $index => $row) {
                $this->map($row, $path . '.' . $index);
                if (!is_string($row['field'] ?? null)) $this->invalid($path . '.' . $index . '.field', 'Expected a field name.');
                if (array_key_exists('label', $row) && !is_string($row['label'])) $this->invalid($path . '.' . $index . '.label', 'Expected a string.');
                if (array_key_exists('multiple', $row) && !is_bool($row['multiple'])) $this->invalid($path . '.' . $index . '.multiple', 'Expected a boolean.');
            }
            return;
        }
        if (in_array($type, ['link', 'gradient', 'spans'], true)) {
            $this->map($value, $path);
            if ($type === 'link') {
                foreach (['type', 'url', 'anchor', 'collection', 'entry'] as $key) if (array_key_exists($key, $value) && !is_string($value[$key])) $this->invalid($path . '.' . $key, 'Expected a string.');
                if (isset($value['new_tab']) && !is_bool($value['new_tab'])) $this->invalid($path . '.new_tab', 'Expected a boolean.');
            }
            return;
        }
        $valid = match ($type) {
            'bool' => is_bool($value),
            'int' => is_int($value),
            default => is_string($value),
        };
        if (!$valid) $this->invalid($path, 'Wrong value type; inspect get_builder_schema(kind).');
    }

    /** Defaults can be added, but no submitted nodes or values may disappear. */
    private function compareNodes(array $input, array $output, string $path): void
    {
        if (count($input) !== count($output)) $this->invalid($path, 'Invalid child placement, nesting limit, duplicate page placeholder or collection card count. Inspect allowed children with get_builder_schema.');
        $styles = LayoutSchema::styleSpec();
        foreach ($input as $index => $node) {
            $actual = $output[$index];
            $nodePath = $path . '.' . $index;
            foreach (['id', 'kind', 'name'] as $key) {
                if (array_key_exists($key, $node) && $node[$key] !== ($actual[$key] ?? ($key === 'name' ? '' : null))) $this->invalid($nodePath . '.' . $key, 'Value would be changed or removed by the layout schema.');
            }
            foreach ($node['props'] ?? [] as $key => $value) {
                if (!array_key_exists($key, $actual['props'])) $this->invalid($nodePath . '.props.' . $key, 'Unknown property for ' . $node['kind'] . '.');
                $this->compareValue($value, $actual['props'][$key], $nodePath . '.props.' . $key);
            }
            $allowedStyle = array_flip(LayoutSchema::styleKeys($node['kind']));
            foreach ($node['style'] ?? [] as $key => $value) {
                if (!isset($allowedStyle[$key])) $this->invalid($nodePath . '.style.' . $key, 'Unknown style for ' . $node['kind'] . '.');
                $this->compareValue($value, $actual['style'][$key] ?? $styles[$key]['default'], $nodePath . '.style.' . $key);
            }
            foreach (['responsive', 'states'] as $group) {
                if (array_key_exists($group, $node)) $this->compareValue($node[$group], $actual[$group] ?? [], $nodePath . '.' . $group);
            }
            $this->compareNodes($node['children'], $actual['children'], $nodePath . '.children');
        }
    }

    private function compareValue(mixed $input, mixed $output, string $path): void
    {
        if (is_array($input) && is_array($output)) {
            if (array_is_list($input) && $input !== [] && count($input) !== count($output)) $this->invalid($path, 'Some supplied values would be discarded.');
            foreach ($input as $key => $value) {
                if (!array_key_exists($key, $output)) $this->invalid($path . '.' . $key, 'Unknown or invalid value would be discarded.');
                $this->compareValue($value, $output[$key], $path . '.' . $key);
            }
            return;
        }
        if ($input !== $output) $this->invalid($path, 'Value would be sanitized, clamped or replaced by a default. Use the allowed type/value from get_builder_schema(kind).');
    }

    private function map(mixed $value, string $path): void
    {
        if (!is_array($value) || ($value !== [] && array_is_list($value))) $this->invalid($path, 'Expected an object with named keys.');
    }

    private function keys(array $value, array $allowed, string $path): void
    {
        foreach (array_keys($value) as $key) if (!in_array($key, $allowed, true)) $this->invalid($path . '.' . $key, 'Unknown key. Allowed: ' . implode(', ', $allowed) . '.');
    }

    private function identifier(mixed $value, string $path): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_-]+$/D', $value)) $this->invalid($path, 'Use a nonempty ID containing letters, numbers, underscores and dashes.');
        return $value;
    }

    private function invalid(string $path, string $message): never
    {
        throw new McpError('Invalid builder input at ' . $path . ': ' . $message, 422, ['code' => 'validation_failed', 'path' => $path], [], ['Read get_builder_schema(kind) for allowed properties, styles, values and children. No layout changes were saved.']);
    }

    private function conflict(): never
    {
        throw new McpError('Layout changed since it was read. No changes were saved.', 409, ['code' => 'conflict'], [], ['Read get_layout or get_section again, reconcile your changes, then retry with the new revision. Do not blindly replay a stale full replacement.']);
    }
}
