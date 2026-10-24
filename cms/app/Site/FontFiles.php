<?php

declare(strict_types=1);

namespace CometCMS\Site;

/** The small, validated subset of font CSS that CometCMS needs. */
final class FontFiles
{
    public const MAX_FILE = 10485760;
    public const MAX_INSTALL = 104857600;

    public static function validate(string $bytes): string
    {
        $size = strlen($bytes);
        if ($size < 48 || $size > self::MAX_FILE || substr($bytes, 0, 4) !== 'wOF2') {
            throw new \InvalidArgumentException('Upload a valid WOFF2 file (maximum 10 MiB).');
        }
        $header = unpack('Nflavor/Nlength/ntables/nreserved/Nsfnt/Ncompressed', substr($bytes, 4, 20));
        if ($header['length'] !== $size || $header['tables'] < 1 || $header['tables'] > 4096
            || $header['reserved'] !== 0 || $header['compressed'] < 1 || $header['compressed'] > $size - 48
            || $header['sfnt'] < 12 || $header['sfnt'] > self::MAX_INSTALL) {
            throw new \InvalidArgumentException('The WOFF2 header is invalid.');
        }
        return hash('sha256', $bytes);
    }

    public static function write(string $path, string $bytes): void
    {
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) {
            throw new \RuntimeException('Could not create font storage.');
        }
        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        try {
            if (file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes) || !rename($temporary, $path)) {
                throw new \RuntimeException('Could not save font data.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    public static function json(string $path, array $data): void
    {
        self::write($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    }

    public static function face(array $face): array
    {
        $weight = $face['weight'] ?? [400, 400];
        if (!is_array($weight) || count($weight) !== 2 || !is_numeric($weight[0]) || !is_numeric($weight[1])
            || (int) $weight[0] < 1 || (int) $weight[1] > 1000 || (int) $weight[0] > (int) $weight[1]
            || !in_array($face['style'] ?? '', ['normal', 'italic'], true)
            || !preg_match('/^[a-f0-9]{64}$/D', (string) ($face['hash'] ?? ''))) {
            throw new \InvalidArgumentException('Invalid font face metadata.');
        }
        $unicode = (string) ($face['unicode_range'] ?? '');
        if (strlen($unicode) > 30000 || ($unicode !== '' && !preg_match('/^U\+[A-Fa-f0-9?]{1,6}(?:-[A-Fa-f0-9]{1,6})?(?:\s*,\s*U\+[A-Fa-f0-9?]{1,6}(?:-[A-Fa-f0-9]{1,6})?)*$/D', $unicode))) {
            throw new \InvalidArgumentException('Invalid Unicode range.');
        }
        $subset = (string) ($face['subset'] ?? 'custom');
        if (!preg_match('/^[a-z0-9-]{1,80}$/D', $subset)) {
            throw new \InvalidArgumentException('Invalid font subset.');
        }
        return ['hash' => $face['hash'], 'weight' => [(int) $weight[0], (int) $weight[1]], 'style' => $face['style'], 'subset' => $subset, 'unicode_range' => $unicode];
    }

    /** Parse declarations, never execute or reuse provider CSS. */
    public static function parse(string $css, array $subsets): array
    {
        preg_match_all('/@font-face\s*\{([^{}]+)\}/i', $css, $blocks);
        $faces = [];
        foreach ($blocks[1] as $block) {
            $properties = [];
            foreach (explode(';', $block) as $declaration) {
                $pair = explode(':', $declaration, 2);
                if (count($pair) === 2) {
                    $properties[strtolower(trim($pair[0]))] = trim($pair[1]);
                }
            }
            if (!preg_match('~url\([\s\'"]*(?:\./)?files/([a-z0-9-]+\.woff2)[\s\'"]*\)~i', $properties['src'] ?? '', $match)) {
                throw new \RuntimeException('Unsupported font file in provider stylesheet.');
            }
            $filename = $match[1];
            $subset = null;
            // Longest first: latin-ext must not be mistaken for latin.
            usort($subsets, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
            foreach ($subsets as $candidate) {
                if (preg_match('~-' . preg_quote($candidate, '~') . '-(?:[0-9]{1,4}|[a-z]{4}(?:-[a-z]{4})*)-(?:normal|italic)\.woff2$~D', $filename)) {
                    $subset = $candidate;
                    break;
                }
            }
            if ($subset === null) {
                continue;
            }
            if (!preg_match('/^(\d{1,4})(?:\s+(\d{1,4}))?$/D', $properties['font-weight'] ?? '', $weight)) {
                throw new \RuntimeException('Unsupported font weight in provider stylesheet.');
            }
            $face = self::face(['hash' => str_repeat('0', 64), 'weight' => [(int) $weight[1], (int) ($weight[2] ?? $weight[1])], 'style' => $properties['font-style'] ?? '', 'subset' => $subset, 'unicode_range' => $properties['unicode-range'] ?? '']);
            unset($face['hash']);
            $faces[$filename] = $face + ['filename' => $filename];
        }
        return array_values($faces);
    }
}
