<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Storage\JsonStore;
use CometCMS\Workspaces\WorkspaceContext;

/**
 * Brings older website files into the theme folder (see LEGACY.md):
 *
 *   site/{regions,layouts,components}/   →  site/theme/{regions,layouts,components}/
 *   components/c3f9a1b2c4d.json           →  components/{name-slug}.json
 *
 * Renamed components are recorded in site/component-aliases.json, so
 * placements in revisions and older backups restored later still find them.
 * Revisions stay in revisions/site/ and keep their paths.
 *
 * Runs on the first request after an update and after every backup restore.
 */
final class ThemeMigration
{
    /** Component ids generated before components were named after their name. */
    public const LEGACY_COMPONENT_ID = '/^c[0-9a-f]{10}$/';

    /** Directories that moved from site/ into site/theme/. */
    public const MOVED = ['regions', 'layouts', 'components'];

    public const ALIASES = 'component-aliases';

    private string $site;
    private string $theme;
    private string $revisions;

    public function __construct(WorkspaceContext $workspace)
    {
        $this->site = $workspace->path('site');
        $this->theme = $this->site . '/theme';
        $this->revisions = $workspace->path('revisions') . '/site';
    }

    /** Whether the workspace still has website files outside the theme folder. */
    public function needed(): bool
    {
        foreach (self::MOVED as $directory) {
            if (is_dir($this->site . '/' . $directory)) {
                return true;
            }
        }

        return false;
    }

    /** @return bool whether any file changed */
    public function run(): bool
    {
        if (!is_dir($this->site)) {
            return false;
        }

        // Outside the workspace, so the lock never shows up next to the theme files.
        $lock = fopen(sys_get_temp_dir() . '/cometcms-theme-migration-' . sha1($this->site) . '.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new \RuntimeException('Could not lock the website files for migration.');
        }

        try {
            $changed = $this->moveLegacyDirectories();
            $store = new JsonStore($this->site);
            $aliases = $store->read(self::ALIASES) ?? [];
            $known = $aliases;
            $changed = $this->renameLegacyComponents($aliases) || $changed;
            if ($aliases !== $known) {
                ksort($aliases);
                $store->write($aliases, self::ALIASES);
            }
            if ($aliases !== []) {
                $changed = $this->moveComponentRevisions($aliases) || $changed;
                $changed = $this->rewriteReferences($aliases) || $changed;
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $changed;
    }

    /** Move files into the theme folder. Like a restored legacy theme.json, the older location wins. */
    private function moveLegacyDirectories(): bool
    {
        $changed = false;

        foreach (self::MOVED as $directory) {
            $source = $this->site . '/' . $directory;
            if (!is_dir($source)) {
                continue;
            }

            foreach ($this->files($source, '') as $file) {
                $target = $this->theme . '/' . $directory . substr($file, strlen($source));
                if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true) && !is_dir(dirname($target))) {
                    throw new \RuntimeException('Could not create the theme directory.');
                }
                if (!rename($file, $target)) {
                    throw new \RuntimeException('Could not move ' . $file . ' into the theme folder.');
                }
                $changed = true;
            }
            $this->removeEmptyDirectories($source);
        }

        return $changed;
    }

    /** Give components with generated ids a readable id and file name. */
    private function renameLegacyComponents(array &$aliases): bool
    {
        $directory = $this->theme . '/components';
        $changed = false;

        foreach (glob($directory . '/*.json') ?: [] as $file) {
            $stored = json_decode((string) file_get_contents($file), true);
            $legacy = is_array($stored) ? (string) ($stored['id'] ?? '') : '';
            if (!preg_match(self::LEGACY_COMPONENT_ID, $legacy)) {
                continue;
            }

            $id = $aliases[$legacy] ?? SiteRepository::componentId(
                (string) ($stored['name'] ?? ''),
                static fn(string $id): bool => is_file($directory . '/' . $id . '.json'),
            );
            $stored['id'] = $id;
            // The save time now comes from the file itself.
            $updated = strtotime((string) ($stored['updated_at'] ?? ''));
            unset($stored['updated_at']);
            (new JsonStore($directory))->write($stored, $id);
            if ($updated !== false) {
                touch($directory . '/' . $id . '.json', $updated);
            }
            if (basename($file, '.json') !== $id) {
                unlink($file);
            }
            $aliases[$legacy] = $id;
            $changed = true;
        }

        return $changed;
    }

    private function moveComponentRevisions(array $aliases): bool
    {
        $changed = false;

        foreach ($aliases as $legacy => $id) {
            $source = $this->revisions . '/components/' . $legacy;
            if (!is_dir($source)) {
                continue;
            }

            $target = $this->revisions . '/components/' . $id;
            if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) {
                throw new \RuntimeException('Could not create the component revision directory.');
            }
            foreach (glob($source . '/*.json') ?: [] as $file) {
                // Revision ids are unique, so an existing file is the same revision.
                is_file($target . '/' . basename($file)) ? unlink($file) : rename($file, $target . '/' . basename($file));
            }
            $this->removeEmptyDirectories($source);
            $changed = true;
        }

        return $changed;
    }

    /** Point placements in the theme and in revisions at the renamed components. */
    private function rewriteReferences(array $aliases): bool
    {
        $changed = false;
        $roots = [];
        foreach (self::MOVED as $directory) {
            $roots[] = [$this->theme . '/' . $directory, false];
            $roots[] = [$this->revisions . '/' . $directory, true];
        }

        foreach ($roots as [$root, $revision]) {
            foreach ($this->files($root, '.json') as $file) {
                $json = (string) file_get_contents($file);
                // Skip the many files that cannot mention a renamed component.
                if (!preg_match('/"c[0-9a-f]{10}"/', $json)) {
                    continue;
                }
                $data = json_decode($json, true);
                $nodes = $revision ? $data['snapshot']['nodes'] ?? null : $data['nodes'] ?? null;
                if (!is_array($nodes)) {
                    continue;
                }

                $rewritten = self::rewriteNodes($nodes, $aliases);
                if ($rewritten === $nodes) {
                    continue;
                }
                if ($revision) {
                    $data['snapshot']['nodes'] = $rewritten;
                } else {
                    $data['nodes'] = $rewritten;
                }
                (new JsonStore(dirname($file)))->write($data, basename($file, '.json'));
                $changed = true;
            }
        }

        return $changed;
    }

    private static function rewriteNodes(array $nodes, array $aliases): array
    {
        foreach ($nodes as &$node) {
            if (!is_array($node)) {
                continue;
            }
            $ref = $node['props']['component'] ?? null;
            if (($node['kind'] ?? '') === LayoutSchema::COMPONENT && is_string($ref) && isset($aliases[$ref])) {
                $node['props']['component'] = $aliases[$ref];
            }
            if (is_array($node['children'] ?? null)) {
                $node['children'] = self::rewriteNodes($node['children'], $aliases);
            }
        }

        return $nodes;
    }

    /** @return list<string> files below $directory, optionally only those ending in $suffix */
    private function files(string $directory, string $suffix): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), $suffix)) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    private function removeEmptyDirectories(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $path) {
            if ($path instanceof \SplFileInfo && $path->isDir()) {
                @rmdir($path->getPathname());
            }
        }
        @rmdir($directory);
    }
}
