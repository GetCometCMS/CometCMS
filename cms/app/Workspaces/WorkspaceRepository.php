<?php

declare(strict_types=1);

namespace CometCMS\Workspaces;

use CometCMS\Core\Security;
use CometCMS\Storage\SettingsStore;

final class WorkspaceRepository
{
    public function __construct(private readonly SettingsStore $settings = new SettingsStore()) {}

    public function all(bool $includeArchived = false): array
    {
        $settings = $this->settings->all();
        $workspaces = is_array($settings['workspaces'] ?? null) ? $settings['workspaces'] : [];
        $workspaceDirs = $this->workspaceDirectorySlugs();

        if ($workspaceDirs !== []) {
            $indexed = [];
            foreach ($workspaces as $workspace) {
                if (!is_array($workspace) || !is_string($workspace['slug'] ?? null)) {
                    continue;
                }

                $indexed[Security::slug((string) $workspace['slug'])] = $workspace;
            }

            $missingSlugs = array_values(array_diff(array_keys($indexed), $workspaceDirs));
            $newSlugs = array_values(array_diff($workspaceDirs, array_keys($indexed)));
            $renamedFrom = null;
            $renamedTo = null;

            // If exactly one configured slug disappeared and exactly one new folder appeared,
            // treat it as a manual folder rename and carry metadata/default over.
            if (count($missingSlugs) === 1 && count($newSlugs) === 1) {
                $renamedFrom = $missingSlugs[0];
                $renamedTo = $newSlugs[0];
                if (isset($indexed[$renamedFrom])) {
                    $indexed[$renamedTo] = $indexed[$renamedFrom];
                    $indexed[$renamedTo]['slug'] = $renamedTo;
                    unset($indexed[$renamedFrom]);
                }
            }

            $now = Security::now();
            $reconciled = [];

            foreach ($workspaceDirs as $slug) {
                $existing = $indexed[$slug] ?? [];
                $createdAt = (string) ($existing['created_at'] ?? $now);

                $reconciled[] = [
                    'slug' => $slug,
                    'label' => trim((string) ($existing['label'] ?? $slug)),
                    'archived' => (bool) ($existing['archived'] ?? false),
                    'created_at' => $createdAt,
                    'updated_at' => (string) ($existing['updated_at'] ?? $createdAt),
                ];
            }

            $default = Security::slug((string) ($settings['default_workspace'] ?? WorkspaceContext::DEFAULT));
            if ($renamedFrom !== null && $renamedTo !== null && $default === $renamedFrom) {
                $default = $renamedTo;
            }
            if (!in_array($default, $workspaceDirs, true)) {
                $default = $workspaceDirs[0];
            }

            $settings['workspaces'] = $reconciled;
            $settings['default_workspace'] = $default;
            $this->settings->save($settings);
            $workspaces = $reconciled;
        }

        if ($workspaces === []) {
            $workspaces = [$this->defaultWorkspace()];
            $settings['workspaces'] = $workspaces;
            $settings['default_workspace'] = WorkspaceContext::DEFAULT;
            $this->settings->save($settings);
        }

        $default = (string) ($settings['default_workspace'] ?? WorkspaceContext::DEFAULT);
        $items = [];
        foreach ($workspaces as $workspace) {
            if (!is_array($workspace)) {
                continue;
            }

            $item = $this->normalize($workspace, $default);
            if (!$includeArchived && $item['archived']) {
                continue;
            }

            $items[] = $item;
        }

        usort($items, static fn(array $a, array $b): int => strcmp((string) $a['label'], (string) $b['label']));

        return $items;
    }

    public function find(string $slug, bool $includeArchived = false): ?array
    {
        $slug = Security::slug($slug);

        foreach ($this->all(true) as $workspace) {
            if (($workspace['slug'] ?? '') === $slug) {
                if (!$includeArchived && !empty($workspace['archived'])) {
                    return null;
                }

                return $workspace;
            }
        }

        return null;
    }

    public function exists(string $slug, bool $includeArchived = false): bool
    {
        return $this->find($slug, $includeArchived) !== null;
    }

    public function save(array $data, ?string $existingSlug = null): array
    {
        $settings = $this->settings->all();
        $workspaces = is_array($settings['workspaces'] ?? null) ? $settings['workspaces'] : [$this->defaultWorkspace()];
        $slug = Security::slug((string) ($data['slug'] ?? $existingSlug ?? ''));
        $now = Security::now();
        $found = false;

        foreach ($workspaces as $index => $workspace) {
            if (!is_array($workspace)) {
                continue;
            }

            if (($workspace['slug'] ?? '') !== ($existingSlug ?? $slug)) {
                continue;
            }

            $workspaces[$index] = $this->normalize(array_replace($workspace, [
                'slug' => $slug,
                'label' => trim((string) ($data['label'] ?? $workspace['label'] ?? $slug)),
                'archived' => (bool) ($data['archived'] ?? $workspace['archived'] ?? false),
                'updated_at' => $now,
            ]));
            $found = true;
            break;
        }

        if (!$found) {
            foreach ($workspaces as $workspace) {
                if (is_array($workspace) && ($workspace['slug'] ?? '') === $slug) {
                    throw new \InvalidArgumentException('Workspace already exists.');
                }
            }

            $workspaces[] = $this->normalize([
                'slug' => $slug,
                'label' => trim((string) ($data['label'] ?? $slug)),
                'archived' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $settings['workspaces'] = array_values(array_map(
            static fn(array $w): array => array_diff_key($w, ['default' => true]),
            $workspaces,
        ));
        $this->settings->save($settings);

        $context = new WorkspaceContext($slug);
        $context->ensure();

        return $this->find($slug, true) ?? $this->normalize(['slug' => $slug]);
    }

    public function archive(string $slug): array
    {
        $slug = Security::slug($slug);
        $activeWorkspaces = array_values(array_filter(
            $this->all(),
            static fn(array $workspace): bool => empty($workspace['archived']),
        ));

        if (count($activeWorkspaces) <= 1) {
            throw new \InvalidArgumentException('The last active workspace cannot be archived.');
        }

        if ($slug === $this->getDefault()) {
            $fallback = null;

            foreach ($activeWorkspaces as $workspace) {
                if (($workspace['slug'] ?? '') !== $slug) {
                    $fallback = (string) $workspace['slug'];
                    break;
                }
            }

            if ($fallback === null) {
                throw new \InvalidArgumentException('The last active workspace cannot be archived.');
            }

            $this->setDefault($fallback);
        }

        return $this->save(['archived' => true], $slug);
    }

    public function getDefault(): string
    {
        $settings = $this->settings->all();
        $slug = (string) ($settings['default_workspace'] ?? WorkspaceContext::DEFAULT);

        if ($this->exists($slug)) {
            return $slug;
        }

        foreach ($this->all() as $workspace) {
            if (is_array($workspace) && is_string($workspace['slug'] ?? null) && $workspace['slug'] !== '') {
                return (string) $workspace['slug'];
            }
        }

        return WorkspaceContext::DEFAULT;
    }

    public function setDefault(string $slug): void
    {
        $slug = Security::slug($slug);

        if (!$this->exists($slug)) {
            throw new \InvalidArgumentException('Workspace not found.');
        }

        $settings = $this->settings->all();
        $settings['default_workspace'] = $slug;
        $this->settings->save($settings);
    }

    private function normalize(array $workspace, ?string $defaultSlug = null): array
    {
        $slug = Security::slug((string) ($workspace['slug'] ?? WorkspaceContext::DEFAULT));
        $label = trim((string) ($workspace['label'] ?? ''));
        $createdAt = (string) ($workspace['created_at'] ?? Security::now());
        $resolvedDefault = $defaultSlug ?? (string) (($this->settings->all())['default_workspace'] ?? WorkspaceContext::DEFAULT);
        $hasIcon = $this->iconPath($slug) !== null;

        return [
            'slug' => $slug,
            'label' => $label !== '' ? $label : ucfirst(str_replace(['-', '_'], ' ', $slug)),
            'archived' => (bool) ($workspace['archived'] ?? false),
            'default' => $slug === $resolvedDefault,
            'has_icon' => $hasIcon,
            'created_at' => $createdAt,
            'updated_at' => (string) ($workspace['updated_at'] ?? $createdAt),
        ];
    }

    public function iconPath(string $slug): ?string
    {
        $slug = Security::slug($slug);
        $target = (new WorkspaceContext($slug))->root() . '/icon.png';

        if (is_file($target)) {
            return $target;
        }

        // Transparently migrate icons written by releases before icons lived
        // inside their workspace. Re-encode them so icon.png is always a PNG.
        foreach (['png', 'jpg', 'jpeg', 'webp', 'gif'] as $extension) {
            $legacy = COMET_STORAGE . '/workspaces/icons/' . $slug . '.' . $extension;
            if (!is_file($legacy)) {
                continue;
            }

            $contents = file_get_contents($legacy);
            $image = is_string($contents) && function_exists('imagecreatefromstring')
                ? @imagecreatefromstring($contents)
                : false;

            if ($image instanceof \GdImage) {
                if (@imagepng($image, $target)) {
                    @unlink($legacy);
                }
                imagedestroy($image);
            } elseif ($extension === 'png' && @rename($legacy, $target)) {
                // PNG icons can still be migrated on installations without GD.
            }

            if (is_file($target)) {
                $legacyDirectory = dirname($legacy);
                if ((glob($legacyDirectory . '/*') ?: []) === []) {
                    @rmdir($legacyDirectory);
                }
                return $target;
            }

            return null;
        }

        return null;
    }

    private function defaultWorkspace(): array
    {
        $now = Security::now();

        return [
            'slug' => WorkspaceContext::DEFAULT,
            'label' => 'Default',
            'archived' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function workspaceDirectorySlugs(): array
    {
        $root = COMET_STORAGE . '/workspaces';

        if (!is_dir($root)) {
            return [];
        }

        $slugs = [];
        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $name = basename($directory);

            if ($name === 'icons') {
                continue;
            }

            $slug = Security::slug($name);
            if ($slug !== '') {
                $slugs[] = $slug;
            }
        }

        sort($slugs);

        return array_values(array_unique($slugs));
    }
}
