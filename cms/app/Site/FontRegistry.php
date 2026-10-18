<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Workspaces\WorkspaceContext;

final class FontRegistry
{
    public function __construct(public readonly WorkspaceContext $workspace) {}

    public static function active(): self
    {
        return new self(WorkspaceContext::active());
    }

    public function directory(): string
    {
        return $this->workspace->path('site') . '/theme/fonts';
    }

    public static function validId(mixed $id): bool
    {
        return is_string($id) && (isset(Theme::FONT_STACKS[$id]) || preg_match('/^(?:fontsource-[a-z0-9][a-z0-9-]{0,79}|custom-[a-f0-9]{24})$/D', $id));
    }

    /** Includes unavailable families so revisions never silently lose a selection. */
    public function families(): array
    {
        $families = [];
        foreach (glob($this->directory() . '/*.json') ?: [] as $path) {
            $family = json_decode((string) file_get_contents($path), true);
            if (!is_array($family) || !self::validId($family['id'] ?? null) || isset($families[$family['id']])
                || basename($path, '.json') !== $family['id'] || !in_array($family['source'] ?? '', ['fontsource', 'custom'], true)) {
                continue;
            }
            $families[$family['id']] = $family;
        }
        foreach ($families as $id => &$family) {
            $faces = [];
            foreach ((array) ($family['faces'] ?? []) as $face) {
                try {
                    $faces[] = FontFiles::face(is_array($face) ? $face : []);
                } catch (\InvalidArgumentException) {
                    // A damaged manifest cannot inject CSS or filesystem paths.
                }
            }
            $family['faces'] = $faces;
            $family['category'] = in_array($family['category'] ?? '', ['serif', 'sans-serif', 'monospace', 'cursive', 'fantasy'], true) ? $family['category'] : 'sans-serif';
            $family['name'] = (string) ($family['name'] ?? $id);
            $family['available'] = $faces !== [] && count(array_filter($faces, fn(array $face): bool => is_file($this->facePath($face['hash'])))) === count($faces);
        }
        unset($family);
        return $families;
    }

    private function facePath(string $hash): string
    {
        return $this->directory() . '/files/' . $hash . '.woff2';
    }

    public function asset(string $hash): ?string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $hash)) {
            return null;
        }
        $retained = $this->directory() . '/files/' . $hash;
        if (is_file($retained . '.json') && is_file($retained . '.woff2')
            && (json_decode((string) file_get_contents($retained . '.json'), true)['hash'] ?? '') === $hash) {
            return $retained . '.woff2';
        }
        foreach ($this->families() as $family) {
            foreach ($family['faces'] as $face) {
                if ($face['hash'] === $hash && is_file($path = $this->facePath($hash))) {
                    return $path;
                }
            }
        }
        return null;
    }

    public function stacks(): array
    {
        $stacks = Theme::FONT_STACKS;
        foreach ($this->families() as $id => $family) {
            $stacks[$id] = '"comet-' . $id . '", ' . $family['category'];
        }
        return $stacks;
    }

    public function fingerprint(): string
    {
        return substr(hash('sha256', json_encode($this->families(), JSON_THROW_ON_ERROR)), 0, 16);
    }

    public function css(string $basePath = ''): string
    {
        $css = '';
        foreach ($this->families() as $id => $family) {
            foreach ($family['faces'] as $face) {
                if (!is_file($this->facePath($face['hash']))) {
                    continue;
                }
                $url = rtrim($basePath, '/') . '/_site/' . rawurlencode($this->workspace->slug()) . '/fonts/' . $face['hash'] . '.woff2';
                // URL input comes only from the installation base path and fixed registry identifiers.
                $url = str_replace(["'", "\n", "\r", '<', '\\'], ['%27', '', '', '%3C', '%5C'], $url);
                $weight = $face['weight'][0] === $face['weight'][1] ? (string) $face['weight'][0] : implode(' ', $face['weight']);
                $css .= '@font-face{font-family:"comet-' . $id . '";font-style:' . $face['style'] . ';font-weight:' . $weight . ';font-display:swap;src:url(\'' . $url . '\') format(\'woff2\');'
                    . ($face['unicode_range'] !== '' ? 'unicode-range:' . $face['unicode_range'] . ';' : '') . '}';
            }
            $css .= '.c-ff-' . $id . ',.c-ff-' . $id . ' :is(h1,h2,h3,h4,h5,h6,.c-site-name){font-family:"comet-' . $id . '",' . $family['category'] . ';}';
        }
        foreach (Theme::FONT_STACKS as $id => $stack) {
            $css .= '.c-ff-' . $id . ',.c-ff-' . $id . ' :is(h1,h2,h3,h4,h5,h6,.c-site-name){font-family:' . $stack . ';}';
        }
        return $css;
    }

    public function metadata(string $basePath = ''): array
    {
        $families = array_values($this->families());
        foreach ($families as &$family) {
            unset($family['license']);
            $family['usages'] = $this->usages($family['id']);
        }
        return ['fonts' => array_keys($this->stacks()), 'font_stacks' => $this->stacks(), 'font_families' => $families, 'font_css' => $this->css($basePath), 'font_version' => $this->fingerprint()];
    }

    public function usages(string $id): array
    {
        $root = $this->workspace->path('site');
        if (!is_dir($root)) {
            return [];
        }
        $uses = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen($root) + 1);
            if ($file->isLink() || $file->getExtension() !== 'json' || str_starts_with($relative, 'theme/fonts/')) {
                continue;
            }
            $data = json_decode((string) file_get_contents($file->getPathname()), true);
            if (!is_array($data)) {
                continue;
            }
            $contains = false;
            array_walk_recursive($data, static function ($value) use ($id, &$contains): void {
                $contains = $contains || $value === $id;
            });
            if ($contains) {
                $uses[] = $relative;
            }
        }
        return $uses;
    }

    public function locked(callable $callback): mixed
    {
        if (!is_dir($this->directory())) {
            mkdir($this->directory(), 0775, true);
        }
        $lockDirectory = $this->workspace->path('cache') . '/fonts';
        if (!is_dir($lockDirectory)) {
            mkdir($lockDirectory, 0775, true);
        }
        $handle = fopen($lockDirectory . '/registry.lock', 'c');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            throw new \RuntimeException('Could not lock font storage.');
        }
        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function save(array $family): void
    {
        if (!self::validId($family['id'] ?? null) || isset(Theme::FONT_STACKS[$family['id']])) {
            throw new \InvalidArgumentException('Invalid font identifier.');
        }
        unset($family['available'], $family['usages']);
        FontFiles::json($this->directory() . '/' . $family['id'] . '.json', $family);
        (new SiteRepository($this->workspace))->clearCache();
    }

    public function delete(string $id): void
    {
        $this->locked(function () use ($id): void {
            $family = $this->families()[$id] ?? null;
            if ($family === null) {
                throw new \InvalidArgumentException('This font cannot be removed.');
            }
            if ($this->usages($id) !== []) {
                throw new \InvalidArgumentException('The font is used by: ' . implode(', ', $this->usages($id)));
            }
            unlink($this->directory() . '/' . $id . '.json');
            // Retain immutable files for cached pages and old theme revisions.
            (new SiteRepository($this->workspace))->clearCache();
        });
    }
}
