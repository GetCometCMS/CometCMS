<?php

declare(strict_types=1);

namespace CometCMS\Site\Blocks;

use CometCMS\Core\Security;

/**
 * Mustache-style block templates: escaped values, conditional sections and enum
 * comparisons. Templates compile once at install into a small tree of text, values
 * and sections; rendering never parses or evaluates anything.
 */
final class BlockTemplate
{
    public const MAX_DEPTH = 16;

    /**
     * @param array<string, array> $props declared prop specs
     * @param array<string, string> $assets package path => published asset file
     */
    public static function compile(string $source, array $props, array $assets, string $file, array &$errors): array
    {
        $before = count($errors);
        [$html, $tags] = self::tokenize($source, $props, $file, $errors);
        if (count($errors) > $before) {
            return [];
        }
        [$html, $assetFiles] = BlockMarkup::normalize($html, $file, $tags, $props, $assets, $errors);
        if (count($errors) > $before) {
            return [];
        }
        $stack = [[]];
        // Captures alternate with text; a capture is either a tag number or an asset number.
        preg_match_all(BlockMarkup::MARKERS, $html, $markers, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $offset = 0;
        foreach ($markers as $marker) {
            if ($marker[0][1] > $offset) {
                $stack[count($stack) - 1][] = ['text', substr($html, $offset, $marker[0][1] - $offset)];
            }
            $offset = $marker[0][1] + strlen($marker[0][0]);
            if (isset($marker[2]) && $marker[2][1] >= 0) {
                $stack[count($stack) - 1][] = ['asset', $assetFiles[(int) $marker[2][0]]];
                continue;
            }
            $tag = $tags[(int) $marker[1][0]];
            if ($tag['type'] === 'value') {
                $stack[count($stack) - 1][] = ['value', $tag['key']];
            } elseif ($tag['type'] === '/') {
                $children = array_pop($stack);
                $open = array_pop($stack[count($stack) - 1]);
                $stack[count($stack) - 1][] = [$open[0], $open[1], $children, $open[2]];
            } else {
                $stack[count($stack) - 1][] = [$tag['type'], $tag['key'], $tag['option']];
                $stack[] = [];
            }
        }
        if ($offset < strlen($html)) {
            $stack[count($stack) - 1][] = ['text', substr($html, $offset)];
        }

        return $stack[0];
    }

    /** @param callable(string): string $assetUrl published asset file => URL */
    public static function render(array $nodes, array $props, callable $assetUrl): string
    {
        $html = '';
        foreach ($nodes as $node) {
            $html .= match ($node[0]) {
                'text' => $node[1],
                'value' => Security::e(is_scalar($props[$node[1]] ?? null) ? (string) $props[$node[1]] : ''),
                'asset' => Security::e($assetUrl($node[1])),
                '#', '^' => self::matches($node, $props) === ($node[0] === '#') ? self::render($node[2], $props, $assetUrl) : '',
                default => '',
            };
        }

        return $html;
    }

    private static function matches(array $node, array $props): bool
    {
        $value = $props[$node[1]] ?? null;

        return $node[3] !== null ? $value === $node[3] : !empty($value);
    }

    /** @return array{0: string, 1: list<array>} the source with tags swapped for markers, and the tags by marker number */
    private static function tokenize(string $source, array $props, string $file, array &$errors): array
    {
        if (preg_match('/[\x{E000}-\x{E003}]/u', $source) === 1 || str_contains($source, 'data-comet-flag-')) {
            $errors[] = ['file' => $file, 'line' => null, 'message' => 'Templates cannot contain the private-use characters U+E000 to U+E003 or data-comet-flag- attributes.'];
            return ['', []];
        }
        $error = static function (int $line, string $message) use ($file, &$errors): void {
            $errors[] = ['file' => $file, 'line' => $line, 'message' => $message];
        };
        $declared = 'Declared props: ' . ($props === [] ? '(none)' : implode(', ', array_keys($props))) . '.';
        // Whether a tag names a declared prop and, for comparisons, one of its enum options.
        $resolve = static function (string $raw, string $type, string $key, ?string $option, int $line) use ($props, $declared, $error): bool {
            $spec = $props[$key] ?? null;
            if ($spec === null) {
                $error($line, $raw . ': unknown prop "' . $key . '". ' . $declared);
                return false;
            }
            if ($option !== null && ($type === '' || $spec['type'] !== 'enum' || !in_array($option, $spec['options'], true))) {
                $error($line, $raw . ': ' . match (true) {
                    $type === '' => 'comparisons only work in sections, like {{#' . $key . '=' . $option . '}}…{{/' . $key . '}}.',
                    $spec['type'] !== 'enum' => 'only enum props can be compared with =.',
                    default => '"' . $option . '" is not an option of ' . $key . '. Options: ' . implode(', ', $spec['options']) . '.',
                });
                return false;
            }
            return true;
        };
        $tags = [];
        $open = [];
        $html = '';
        $offset = 0;
        $context = ['tag' => false, 'quote' => ''];
        preg_match_all('/\{\{(\{?)(.*?)\}\}(\}?)/s', $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $match) {
            [$raw, $position] = $match[0];
            if ($position < $offset) continue;
            $line = substr_count($source, "\n", 0, $position) + 1;
            $html .= self::text($source, $offset, $position, $error);
            $context = self::context(substr($source, $offset, $position - $offset), $context);
            $offset = $position + strlen($raw);
            $inner = trim($match[2][0]);
            if (str_starts_with($inner, '!')) {
                // Comments vanish; keep their newlines so later line numbers still match the source.
                $html .= str_repeat("\n", substr_count($raw, "\n"));
                continue;
            }
            $hint = match (true) {
                $match[1][0] !== '' || str_starts_with($inner, '&') => 'Raw output with {{{name}}} or {{& name}} is not supported; values are always escaped. Use {{name}}.',
                str_starts_with($inner, '>') => 'Partials ({{> name}}) are not supported.',
                str_starts_with($inner, '=') => 'Changing delimiters ({{= =}}) is not supported.',
                str_contains($raw, "\n") => 'Template tags must stay on one line.',
                default => null,
            };
            if ($hint !== null) {
                $error($line, $raw . ': ' . $hint);
                continue;
            }
            if ($context['tag'] && $context['quote'] === '') {
                // Inside a start tag, a section may only toggle one boolean attribute: <details {{#open}}open{{/open}}>.
                $flag = '/\G\{\{\s*([#^])\s*([a-z][a-z0-9_]{0,63})(?:\s*=\s*([^\s{}]+))?\s*\}\}\s*([a-z][a-z-]{0,39})\s*\{\{\s*\/\s*\2(?:\s*=\s*\3)?\s*\}\}/';
                if (preg_match($flag, $source, $toggle, 0, $position) !== 1) {
                    $error($line, $raw . ': inside an HTML tag, template tags can only toggle a boolean attribute, as in <details {{#open}}open{{/open}}>. Put values in quoted attribute values, as in class="{{name}}".');
                    continue;
                }
                $offset = $position + strlen($toggle[0]);
                $option = isset($toggle[3]) && $toggle[3] !== '' ? $toggle[3] : null;
                if (!$resolve($raw, $toggle[1], $toggle[2], $option, $line)) continue;
                $id = count($tags);
                $tags[] = ['type' => $toggle[1], 'key' => $toggle[2], 'option' => $option, 'line' => $line, 'pair' => $id + 1, 'source' => $raw, 'attribute' => $toggle[4]];
                $tags[] = ['type' => '/', 'key' => $toggle[2], 'option' => $option, 'line' => $line, 'pair' => $id, 'source' => '{{/' . $toggle[2] . '}}'];
                $html .= ' data-comet-flag-' . $id . ' ';
                continue;
            }
            if (!preg_match('/^([#^\/]?)\s*([a-z][a-z0-9_]{0,63})(?:\s*=\s*([^\s{}]+))?$/D', $inner, $tag)) {
                $error($line, $raw . ': ' . (str_contains($inner, '.')
                    ? 'Dotted names are not supported; props are flat, so write {{name}}.'
                    : 'Unrecognized tag. Use {{name}}, {{#name}}…{{/name}}, {{^name}}…{{/name}}, {{#name=option}}…{{/name}} or {{! comment }}.'));
                continue;
            }
            [, $type, $key] = $tag;
            $option = isset($tag[3]) && $tag[3] !== '' ? $tag[3] : null;
            // A rejected opener still opens its section, so its closing tag does not add a second error.
            if (!$resolve($raw, $type, $key, $option, $line) && ($type === '' || $type === '/')) continue;
            if ($type === '/') {
                $last = end($open);
                if ($last === false || $tags[$last]['key'] !== $key || ($option !== null && $tags[$last]['option'] !== $option)) {
                    $error($line, $raw . ($last === false ? ' closes a section that was never opened.' : ' does not match the open section ' . $tags[$last]['source'] . ' (line ' . $tags[$last]['line'] . ').'));
                    continue;
                }
                array_pop($open);
                $tags[$last]['pair'] = count($tags);
                $tags[] = ['type' => '/', 'key' => $key, 'option' => $option, 'line' => $line, 'pair' => $last, 'source' => $raw];
            } else {
                if ($type !== '' && count($open) >= self::MAX_DEPTH) {
                    $error($line, 'Sections can nest at most ' . self::MAX_DEPTH . ' levels deep.');
                    continue;
                }
                if ($type !== '') $open[] = count($tags);
                $tags[] = ['type' => $type === '' ? 'value' : $type, 'key' => $key, 'option' => $option, 'line' => $line, 'pair' => null, 'source' => $raw];
            }
            $html .= sprintf(BlockMarkup::TAG, count($tags) - 1);
        }
        $html .= self::text($source, $offset, strlen($source), $error);
        foreach ($open as $id) {
            $error($tags[$id]['line'], $tags[$id]['source'] . ' is never closed. Add {{/' . $tags[$id]['key'] . '}}.');
        }

        return [$html, $tags];
    }

    /** Track whether the text so far leaves us inside a start tag, and inside a quoted attribute value. */
    private static function context(string $text, array $context): array
    {
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if (!$context['tag']) {
                if ($char === '<' && $i + 1 < $length && (ctype_alpha($text[$i + 1]) || $text[$i + 1] === '/')) $context['tag'] = true;
            } elseif ($context['quote'] !== '') {
                if ($char === $context['quote']) $context['quote'] = '';
            } elseif ($char === '"' || $char === "'") {
                $context['quote'] = $char;
            } elseif ($char === '>') {
                $context['tag'] = false;
            }
        }

        return $context;
    }

    /** The literal text between two tags, which must not contain half a tag. */
    private static function text(string $source, int $from, int $to, callable $error): string
    {
        $text = substr($source, $from, $to - $from);
        foreach (['{{' => 'Unclosed template tag: add the closing }}.', '}}' => 'Stray }} without an opening {{.'] as $needle => $message) {
            $position = strpos($text, $needle);
            if ($position !== false) {
                $error(substr_count($source, "\n", 0, $from + $position) + 1, $message);
            }
        }

        return $text;
    }
}
