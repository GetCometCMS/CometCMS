<?php

declare(strict_types=1);

namespace CometCMS\Mcp;

/** Validates the JSON Schema vocabulary used by the builder tool contracts. */
final class ToolArguments
{
    public static function validate(string $tool, mixed $value, array $schema): void
    {
        self::check($tool, $value, $schema, $schema, 'arguments');
    }

    private static function check(string $tool, mixed $value, array $schema, array $root, string $path): void
    {
        if (isset($schema['$ref'])) $schema = $root['$defs'][basename($schema['$ref'])];
        if (isset($schema['anyOf'])) {
            foreach ($schema['anyOf'] as $option) {
                try {
                    self::check($tool, $value, $option, $root, $path);
                    return;
                } catch (McpError) {
                    // Report the accepted alternatives together if none match.
                }
            }
            self::invalid($tool, $path, $value, $schema);
        }
        $type = $schema['type'] ?? null;
        $matches = match ($type) {
            'object' => is_array($value) && ($value === [] || !array_is_list($value)),
            'array' => is_array($value) && array_is_list($value),
            'string' => is_string($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            default => true,
        };
        if (!$matches) self::invalid($tool, $path, $value, $schema);
        if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) self::invalid($tool, $path, $value, $schema);
        if ($type === 'object') {
            $properties = $schema['properties'] ?? [];
            foreach ($schema['required'] ?? [] as $key) {
                if (!array_key_exists($key, $value)) self::invalid($tool, self::path($path, $key), null, $properties[$key], 'Required argument is missing.');
            }
            foreach ($value as $key => $child) {
                if (isset($properties[$key])) {
                    self::check($tool, $child, $properties[$key], $root, self::path($path, (string) $key));
                } elseif (($schema['additionalProperties'] ?? true) === false) {
                    self::invalid($tool, self::path($path, (string) $key), $child, $schema, 'Unknown key. Allowed keys: ' . implode(', ', array_keys($properties)) . '.');
                }
            }
        } elseif ($type === 'array') {
            if (count($value) < ($schema['minItems'] ?? 0) || count($value) > ($schema['maxItems'] ?? PHP_INT_MAX)) self::invalid($tool, $path, $value, $schema);
            if (($schema['uniqueItems'] ?? false) && count(array_unique($value, SORT_REGULAR)) !== count($value)) self::invalid($tool, $path, $value, $schema, 'Items must be unique.');
            foreach ($value as $index => $child) self::check($tool, $child, $schema['items'], $root, $path . '.' . $index);
        } elseif ($type === 'string') {
            $length = mb_strlen($value);
            if ($length < ($schema['minLength'] ?? 0) || $length > ($schema['maxLength'] ?? PHP_INT_MAX)) self::invalid($tool, $path, $value, $schema);
            if (isset($schema['pattern']) && !preg_match('~' . $schema['pattern'] . '~D', $value)) self::invalid($tool, $path, $value, $schema);
        } elseif ($type === 'integer') {
            if ($value < ($schema['minimum'] ?? PHP_INT_MIN) || $value > ($schema['maximum'] ?? PHP_INT_MAX)) self::invalid($tool, $path, $value, $schema);
        }
    }

    private static function path(string $parent, string $key): string
    {
        return $parent === 'arguments' ? $key : $parent . '.' . $key;
    }

    private static function shape(array $schema): string
    {
        if (isset($schema['anyOf'])) return implode(' or ', array_map(self::shape(...), $schema['anyOf']));
        if (isset($schema['enum'])) return 'one of: ' . implode(', ', array_map(static fn(mixed $value): string => json_encode($value), $schema['enum']));
        $shape = $schema['type'] ?? 'value';
        if ($shape === 'object' && isset($schema['properties'])) {
            $keys = [];
            foreach ($schema['properties'] as $key => $property) $keys[] = $key . (in_array($key, $schema['required'] ?? [], true) ? ' (required, ' : ' (optional, ') . ($property['type'] ?? 'value') . ')';
            $shape .= ' with keys: ' . implode(', ', $keys);
        }
        if ($shape === 'array') {
            $shape .= ' of ' . ($schema['items']['type'] ?? 'node') . ' values';
            if (isset($schema['minItems'])) $shape .= ', at least ' . $schema['minItems'] . ' items';
            if (isset($schema['maxItems'])) $shape .= ', at most ' . $schema['maxItems'] . ' items';
        }
        if (isset($schema['minimum'])) $shape .= ' >= ' . $schema['minimum'];
        if (isset($schema['maximum'])) $shape .= ' <= ' . $schema['maximum'];
        if (isset($schema['minLength'])) $shape .= ', at least ' . $schema['minLength'] . ' characters';
        if (isset($schema['maxLength'])) $shape .= ', at most ' . $schema['maxLength'] . ' characters';
        if (isset($schema['pattern'])) $shape .= ' matching ' . $schema['pattern'];
        return $shape;
    }

    private static function invalid(string $tool, string $path, mixed $value, array $schema, string $reason = ''): never
    {
        $expected = self::shape($schema);
        $message = 'Invalid tool arguments for ' . $tool . ' at ' . $path . ': ' . ($reason !== '' ? $reason . ' ' : '') . 'Expected ' . $expected . '.';
        if (isset($schema['description'])) $message .= ' ' . $schema['description'];
        $details = ['code' => 'validation_failed', 'tool' => $tool, 'path' => $path, 'expected' => $schema, 'received_type' => get_debug_type($value)];
        if (isset($schema['examples'][0])) $details['example'] = $schema['examples'][0];
        throw new McpError($message, 422, $details, [], [
            'Correct ' . $path . ' to match error.details.expected' . (isset($details['example']) ? ' (see error.details.example)' : '') . '. Use tools/list for the inputSchema of ' . $tool . (in_array($tool, PageBuilderTools::NAMES, true) ? ', and get_builder_schema(kind) for builder properties, styles and children' : '') . '. No changes were saved.',
        ]);
    }
}
