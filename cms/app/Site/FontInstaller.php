<?php

declare(strict_types=1);

namespace CometCMS\Site;

final class FontInstaller
{
    public function __construct(private readonly FontRegistry $fonts, private readonly Fontsource $provider = new Fontsource()) {}

    private function cache(): string
    {
        return $this->fonts->workspace->path('cache') . '/fonts';
    }

    public function cleanup(): void
    {
        foreach (glob($this->cache() . '/jobs/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $path = $directory . '/job.json';
            if (!is_link($directory) && is_file($path) && filemtime($path) < time() - 86400) {
                $handle = fopen($directory . '/job.lock', 'c');
                if ($handle && flock($handle, LOCK_EX | LOCK_NB)) {
                    foreach (glob($directory . '/*') ?: [] as $file) {
                        if (basename($file) !== 'job.lock' && is_file($file) && !is_link($file)) {
                            unlink($file);
                        }
                    }
                    flock($handle, LOCK_UN);
                    fclose($handle);
                    @unlink($directory . '/job.lock');
                    @rmdir($directory);
                } elseif ($handle) {
                    fclose($handle);
                }
            }
        }
        foreach (glob($this->cache() . '/previews/*') ?: [] as $file) {
            if (is_file($file) && !is_link($file) && filemtime($file) < time() - 86400) {
                unlink($file);
            }
        }
    }

    public function jobs(): array
    {
        $this->cleanup();
        $jobs = [];
        foreach (glob($this->cache() . '/jobs/*/job.json') ?: [] as $path) {
            $job = json_decode((string) file_get_contents($path), true);
            if (is_array($job) && ($job['status'] ?? '') === 'pending') {
                $jobs[] = $this->summary($job);
            }
        }
        return $jobs;
    }

    public function prepare(string $id, array $subsets, bool $update = false): array
    {
        $this->cleanup();
        $existing = $this->fonts->families()['fontsource-' . $id] ?? null;
        if ($existing !== null && !$update) {
            throw new \InvalidArgumentException('This font is already installed.');
        }
        $family = $this->provider->prepare($id, $subsets);
        $job = ['id' => bin2hex(random_bytes(12)), 'family' => $family, 'cursor' => 0, 'bytes' => 0, 'status' => 'pending', 'previous' => $existing === null ? null : hash('sha256', json_encode($existing, JSON_THROW_ON_ERROR)), 'created_at' => gmdate('c')];
        FontFiles::json($this->jobPath($job['id']) . '/job.json', $job);
        return $this->summary($job);
    }

    private function jobPath(string $id): string
    {
        if (!preg_match('/^[a-f0-9]{24}$/D', $id)) {
            throw new \InvalidArgumentException('Invalid font installation.');
        }
        return $this->cache() . '/jobs/' . $id;
    }

    private function summary(array $job): array
    {
        return ['id' => $job['id'], 'family_id' => $job['family']['id'], 'name' => $job['family']['name'], 'cursor' => $job['cursor'], 'total' => count($job['family']['faces']), 'bytes' => $job['bytes'], 'status' => $job['status']];
    }

    private function lockedJob(string $id, callable $callback): array
    {
        $directory = $this->jobPath($id);
        if (!is_file($directory . '/job.json')) {
            throw new \InvalidArgumentException('Font installation expired. Start it again.');
        }
        $handle = fopen($directory . '/job.lock', 'c');
        if (!$handle || !flock($handle, LOCK_EX)) {
            throw new \RuntimeException('Could not lock the installation.');
        }
        try {
            $job = json_decode((string) file_get_contents($directory . '/job.json'), true);
            if (!is_array($job)) {
                throw new \RuntimeException('The installation data is damaged.');
            }
            return $callback($job, $directory);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** Cursor makes retries after a lost response idempotent. */
    public function step(string $id, int $cursor): array
    {
        return $this->lockedJob($id, function (array $job, string $directory) use ($cursor): array {
            if ($job['status'] === 'cancelled') {
                throw new \InvalidArgumentException('The font installation was cancelled.');
            }
            if ($job['status'] === 'complete' || $cursor < $job['cursor']) {
                return $this->summary($job);
            }
            if ($cursor !== $job['cursor']) {
                throw new \InvalidArgumentException('Invalid installation step.');
            }
            $face = $job['family']['faces'][$cursor] ?? null;
            if ($face !== null) {
                $bytes = $this->provider->get($face['url']);
                $hash = FontFiles::validate($bytes);
                if ($job['bytes'] + strlen($bytes) > FontFiles::MAX_INSTALL) {
                    throw new \InvalidArgumentException('The font installation exceeds 100 MiB. Select fewer character sets.');
                }
                FontFiles::write($directory . '/' . $hash . '.woff2', $bytes);
                $job['family']['faces'][$cursor]['hash'] = $hash;
                $job['bytes'] += strlen($bytes);
                $job['cursor']++;
                // Persist before commit: a failed activation can be retried without downloading again.
                FontFiles::json($directory . '/job.json', $job);
            }
            if ($job['cursor'] === count($job['family']['faces'])) {
                $this->fonts->locked(function () use (&$job, $directory): void {
                    $family = $job['family'];
                    $family['faces'] = array_map(static fn(array $face): array => FontFiles::face($face), $family['faces']);
                    $family['installed_at'] = $job['created_at'];
                    // A stable activation ID recovers a process that stopped after the manifest rename.
                    $family['installation_id'] = $job['id'];
                    $current = $this->fonts->families()[$family['id']] ?? null;
                    $alreadyActivated = ($current['installation_id'] ?? '') === $job['id'];
                    $fingerprint = $current === null ? null : hash('sha256', json_encode($current, JSON_THROW_ON_ERROR));
                    if (!$alreadyActivated && $fingerprint !== $job['previous']) {
                        throw new \InvalidArgumentException('This font changed during installation. Cancel and start again.');
                    }
                    if (!$alreadyActivated) {
                        foreach ($family['faces'] as $face) {
                            $bytes = (string) file_get_contents($directory . '/' . $face['hash'] . '.woff2');
                            if (FontFiles::validate($bytes) !== $face['hash']) {
                                throw new \RuntimeException('Staged font file is damaged.');
                            }
                            $this->storeFile($face['hash'], $bytes);
                        }
                        $this->fonts->save($family);
                    }
                    $job['status'] = 'complete';
                    FontFiles::json($directory . '/job.json', $job);
                    foreach (glob($directory . '/*.woff2') ?: [] as $path) {
                        unlink($path);
                    }
                });
            }
            return $this->summary($job);
        });
    }

    public function cancel(string $id): array
    {
        return $this->lockedJob($id, function (array $job, string $directory): array {
            if ($job['status'] === 'pending') {
                $job['status'] = 'cancelled';
                FontFiles::json($directory . '/job.json', $job);
                foreach (glob($directory . '/*.woff2') ?: [] as $path) {
                    unlink($path);
                }
            }
            return $this->summary($job);
        });
    }

    private function storeFile(string $hash, string $bytes): void
    {
        FontFiles::write($this->fonts->directory() . '/files/' . $hash . '.woff2', $bytes);
        // Keep previously registered assets addressable after deliberate version changes.
        FontFiles::json($this->fonts->directory() . '/files/' . $hash . '.json', ['hash' => $hash]);
    }

    /** Upload bytes are provided only after the controller checks is_uploaded_file. */
    public function upload(string $name, string $category, array $uploads, ?string $id = null): array
    {
        $name = trim($name);
        if ($name === '' || strlen($name) > 160 || !in_array($category, ['serif', 'sans-serif', 'monospace', 'cursive', 'fantasy'], true) || $uploads === []) {
            throw new \InvalidArgumentException('Enter a family name, fallback category and at least one WOFF2 file.');
        }
        return $this->fonts->locked(function () use ($name, $category, $uploads, $id): array {
            $family = $id === null ? ['id' => 'custom-' . bin2hex(random_bytes(12)), 'name' => $name, 'category' => $category, 'source' => 'custom', 'version' => '1', 'faces' => [], 'subsets' => ['custom']] : ($this->fonts->families()[$id] ?? null);
            if ($family === null || $family['source'] !== 'custom') {
                throw new \InvalidArgumentException('Select a custom font family.');
            }
            $files = [];
            $total = 0;
            foreach ($family['faces'] as $face) {
                $path = $this->fonts->asset($face['hash']);
                $total += $path !== null && is_file($path) ? filesize($path) : 0;
            }
            foreach ($uploads as $upload) {
                $bytes = $upload['bytes'];
                $hash = FontFiles::validate($bytes);
                $face = FontFiles::face(['hash' => $hash, 'weight' => $upload['weight'] ?? [], 'style' => $upload['style'] ?? '', 'subset' => 'custom']);
                foreach ($family['faces'] as $existing) {
                    if ($existing['style'] === $face['style'] && $existing['weight'][0] <= $face['weight'][1] && $face['weight'][0] <= $existing['weight'][1]) {
                        throw new \InvalidArgumentException('Overlapping weight ranges for the same font style.');
                    }
                }
                $family['faces'][] = $face;
                $files[$hash] = $bytes;
                $total += strlen($bytes);
                if ($total > FontFiles::MAX_INSTALL) {
                    throw new \InvalidArgumentException('A font family may not exceed 100 MiB.');
                }
            }
            foreach ($files as $hash => $bytes) {
                $this->storeFile($hash, $bytes);
            }
            $family['name'] = $name;
            $family['category'] = $category;
            $family['installed_at'] = gmdate('c');
            $this->fonts->save($family);
            return $family;
        });
    }

    public function preview(string $id): string
    {
        Fontsource::assertId($id);
        $this->cleanup();
        $path = $this->cache() . '/previews/' . $id . '.woff2';
        if (is_file($path) && filemtime($path) > time() - 86400) {
            return $path;
        }
        // Only the default alphabet and one normal face are fetched for a preview.
        $meta = $this->provider->details($id);
        $subset = in_array('latin', (array) ($meta['subsets'] ?? []), true) ? 'latin' : ($meta['defSubset'] ?? '');
        $bytes = $this->quickPreview($id, $meta, (string) $subset);
        if ($bytes === null) {
            $family = $this->provider->prepare($id, [$subset]);
            $face = null;
            foreach ($family['faces'] as $candidate) {
                if ($candidate['style'] === 'normal' && ($face === null || abs($candidate['weight'][0] - 400) < abs($face['weight'][0] - 400))) {
                    $face = $candidate;
                }
            }
            if ($face === null) {
                throw new \RuntimeException('No preview is available for this font.');
            }
            $bytes = $this->provider->get($face['url']);
        }
        FontFiles::validate($bytes);
        FontFiles::write($path, $bytes);
        return $path;
    }

    /** Fetch the static package file directly when the provider reports its version: one download instead of a manifest build. */
    private function quickPreview(string $id, array $meta, string $subset): ?string
    {
        $version = (string) ($meta['npmVersion'] ?? '');
        $weights = array_values(array_filter((array) ($meta['weights'] ?? []), 'is_int'));
        if (!preg_match('/^\d+\.\d+\.\d+$/D', $version) || $weights === [] || !preg_match('/^[a-z0-9-]{1,80}$/D', $subset)) {
            return null;
        }
        usort($weights, static fn(int $a, int $b): int => abs($a - 400) <=> abs($b - 400));
        $style = in_array('normal', (array) ($meta['styles'] ?? ['normal']), true) ? 'normal' : 'italic';
        try {
            return $this->provider->get('https://cdn.jsdelivr.net/npm/@fontsource/' . $id . '@' . $version . '/files/' . $id . '-' . $subset . '-' . $weights[0] . '-' . $style . '.woff2');
        } catch (\RuntimeException) {
            return null;
        }
    }
}
