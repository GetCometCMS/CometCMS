<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Storage\JsonStore;
use CometCMS\Workspaces\WorkspaceContext;

/**
 * Turns single page content types into ordinary collections (see LEGACY.md).
 *
 * A single page was a collection with one entry, served at a fixed URL such
 * as /about. It now gets the default collection URL /{type}/{slug}, without a
 * list page. A single page used as the homepage gets its entry selected
 * explicitly, and `single.*` smart references in the theme and its revisions
 * become `entry.*` references.
 *
 * Runs on the first request after an update and after every backup restore.
 */
final class SingletonMigration
{
    private WorkspaceContext $workspace;
    private string $types;

    public function __construct(WorkspaceContext $workspace)
    {
        $this->workspace = $workspace;
        $this->types = $workspace->path('content-types');
    }

    /** Whether a stored content type is still marked as a single page, or a configured one is not migrated yet. */
    public function needed(): bool
    {
        if ($this->storedSingletons() !== []) {
            return true;
        }
        $configured = $this->configuredSingletons();
        if ($configured === []) {
            return false;
        }
        // Configuration cannot be rewritten, so look for what run() would change.
        $settings = (new JsonStore($this->workspace->path('site')))->read('settings') ?? [];
        foreach ($configured as $name) {
            $paths = self::paths($settings['routes'][$name]['pattern'] ?? '');
            if ($paths !== [] && array_filter($paths, static fn(string $path): bool => str_contains($path, '{slug}')) === []) {
                return true;
            }
            if (($settings['homepage']['type'] ?? '') === $name && ($settings['homepage']['entry'] ?? '') === '' && $this->entry($name) !== null) {
                return true;
            }
        }

        return false;
    }

    /** @return bool whether any file changed */
    public function run(): bool
    {
        $singletons = $this->storedSingletons();
        foreach ($this->configuredSingletons() as $name) {
            $singletons[$name] ??= null;
        }
        if ($singletons === []) {
            return false;
        }

        $lock = fopen(sys_get_temp_dir() . '/cometcms-singleton-migration-' . sha1($this->types) . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new \RuntimeException('Could not lock the content types for migration.');
        }

        try {
            $entries = [];
            foreach (array_keys($singletons) as $name) {
                $entries[$name] = $this->entry($name);
            }
            $changed = $this->migrateSettings($entries);
            $changed = $this->rewriteReferences($entries) || $changed;
            // Last, so an interrupted run starts over on the next request.
            foreach ($singletons as $name => $file) {
                if ($file === null) {
                    continue;
                }
                $schema = json_decode((string) file_get_contents($file), true);
                unset($schema['singleton']);
                (new JsonStore($this->types))->write($schema, (string) $name);
                $changed = true;
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $changed;
    }

    /** @return array<string, string> stored single page types, name → file */
    private function storedSingletons(): array
    {
        $found = [];
        foreach (glob($this->types . '/*.json') ?: [] as $file) {
            $json = (string) file_get_contents($file);
            if (!str_contains($json, '"singleton"')) {
                continue;
            }
            $schema = json_decode($json, true);
            if (is_array($schema) && !empty($schema['singleton'])) {
                $found[(string) ($schema['name'] ?? basename($file, '.json'))] = $file;
            }
        }

        return $found;
    }

    /** @return list<string> single page types defined in the configuration, which stays as it is */
    private function configuredSingletons(): array
    {
        $names = [];
        foreach ((array) comet_config('content_types', []) as $name => $schema) {
            if (is_array($schema) && !empty($schema['singleton'])) {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    /** The one active entry a single page had, preferring the one stored under the type's name. */
    private function entry(string $name): ?array
    {
        $store = new JsonStore($this->workspace->path('content'));
        $entry = $store->read($name, $name);
        if (is_array($entry) && empty($entry['deleted_at'])) {
            return $entry;
        }
        foreach ($store->all($name) as $candidate) {
            if (empty($candidate['deleted_at'])) {
                return $candidate;
            }
        }

        return null;
    }

    /** Give single pages the default collection URL and select the homepage entry. */
    private function migrateSettings(array $entries): bool
    {
        $store = new JsonStore($this->workspace->path('site'));
        $settings = $store->read('settings');
        if (!is_array($settings)) {
            return false;
        }

        $changed = false;
        foreach (array_keys($entries) as $name) {
            $route = $settings['routes'][$name] ?? null;
            $paths = is_array($route) ? self::paths($route['pattern'] ?? '') : [];
            if ($paths === [] || array_filter($paths, static fn(string $path): bool => str_contains($path, '{slug}')) !== []) {
                continue;
            }
            $route['pattern'] = '/' . $name . '/{slug}';
            $route['index_path'] = '';
            $settings['routes'][$name] = $route;
            $changed = true;
        }

        $home = is_array($settings['homepage'] ?? null) ? $settings['homepage'] : [];
        $type = (string) ($home['type'] ?? '');
        if (array_key_exists($type, $entries) && (string) ($home['entry'] ?? '') === '' && $entries[$type] !== null) {
            $settings['homepage']['entry'] = (string) ($entries[$type]['uid'] ?? $entries[$type]['id']);
            $changed = true;
        }

        if ($changed) {
            $store->write($settings, 'settings');
        }

        return $changed;
    }

    /** Point `single.*` smart references at the single page's entry. */
    private function rewriteReferences(array $entries): bool
    {
        $changed = false;
        $roots = [$this->workspace->path('site') . '/theme', $this->workspace->path('revisions') . '/site'];

        foreach ($roots as $root) {
            foreach ($this->files($root) as $file) {
                $json = (string) file_get_contents($file);
                // Skip the many files without a single page reference.
                if (!preg_match('/"source":\s*"single"/', $json)) {
                    continue;
                }
                $data = json_decode($json, true);
                if (!is_array($data)) {
                    continue;
                }
                $rewritten = self::rewriteValue($data, $entries);
                if ($rewritten !== $data) {
                    (new JsonStore(dirname($file)))->write($rewritten, basename($file, '.json'));
                    $changed = true;
                }
            }
        }

        return $changed;
    }

    private static function rewriteValue(array $value, array $entries): array
    {
        if (($value['type'] ?? '') === 'reference' && ($value['source'] ?? '') === 'single') {
            $collection = (string) ($value['collection'] ?? '');
            $entry = $entries[$collection] ?? null;
            $value['source'] = 'entry';
            // A single page without content keeps a reference that resolves to nothing, as before.
            $value['entry'] = (string) ($entry['uid'] ?? $entry['id'] ?? $collection);

            return $value;
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::rewriteValue($item, $entries);
            }
        }

        return $value;
    }

    /** @return array<string, string> a stored path, keyed by language ('' when not localized) */
    private static function paths(mixed $value): array
    {
        if (is_string($value)) {
            return $value === '' ? [] : ['' => $value];
        }
        if (is_array($value) && is_array($value['values'] ?? null)) {
            return array_filter(array_map('strval', $value['values']), static fn(string $path): bool => $path !== '');
        }

        return [];
    }

    /** @return list<string> */
    private function files(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), '.json')) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }
}
