<?php

declare(strict_types=1);

namespace CometCMS\Site;

/** Provider adapter. The injectable transport keeps tests offline. */
final class Fontsource
{
    public function __construct(private readonly ?\Closure $transport = null) {}

    public function get(string $url, int $limit = FontFiles::MAX_FILE): string
    {
        if (!preg_match('~^https://(?:api\.fontsource\.org/v1/(?:fonts(?:/[a-z0-9-]+)?|version/[a-z0-9-]+)|cdn\.jsdelivr\.net/npm/@fontsource(?:-variable)?/[a-z0-9-]+@\d+\.\d+\.\d+/(?:(?:metadata|unicode)\.json|LICENSE|[a-z0-9-]+\.css|files/[a-z0-9-]+\.woff2))$~D', $url)) {
            throw new \InvalidArgumentException('Unapproved font download URL.');
        }
        if ($this->transport !== null) {
            $body = ($this->transport)($url);
        } elseif (function_exists('curl_init')) {
            $handle = curl_init($url);
            $body = '';
            curl_setopt_array($handle, [CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 10, CURLOPT_USERAGENT => 'CometCMS-Fonts', CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, $limit): int {
                if (strlen($body) + strlen($chunk) > $limit) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            }]);
            $ok = curl_exec($handle);
            $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);
            if ($ok === false || $status !== 200) {
                throw new \RuntimeException('Could not download font data. Check outbound HTTPS access and retry.');
            }
        } else {
            $context = stream_context_create(['http' => ['timeout' => 10, 'follow_location' => 0, 'ignore_errors' => true, 'header' => "User-Agent: CometCMS-Fonts\r\n"], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
            $body = @file_get_contents($url, false, $context, 0, $limit + 1);
            if ($body === false || !preg_match('~^HTTP/\S+ 200(?: |$)~', ($http_response_header ?? [])[0] ?? '')) {
                throw new \RuntimeException('Could not download font data. Check outbound HTTPS access and retry, or upload WOFF2 files.');
            }
        }
        if (!is_string($body) || strlen($body) > $limit || $body === '') {
            throw new \RuntimeException('Font provider returned empty or oversized data.');
        }
        return $body;
    }

    public function json(string $url): array
    {
        $data = json_decode($this->get($url, 8388608), true);
        if (!is_array($data)) {
            throw new \RuntimeException('Font provider returned invalid metadata.');
        }
        return $data;
    }

    public function catalogue(): array
    {
        $path = COMET_STORAGE . '/cache/fonts/catalogue.json';
        $cached = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (is_array($cached) && filemtime($path) > time() - 86400) {
            return ['families' => $cached, 'stale' => false];
        }
        try {
            $data = $this->json('https://api.fontsource.org/v1/fonts');
            $data = array_values(array_filter($data, static fn($font): bool => is_array($font) && ($font['type'] ?? '') === 'google' && preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/D', (string) ($font['id'] ?? ''))));
            if ($data === []) {
                throw new \RuntimeException('The font catalogue is unavailable.');
            }
            FontFiles::json($path, $data);
            return ['families' => $data, 'stale' => false];
        } catch (\RuntimeException $e) {
            if (is_array($cached)) {
                return ['families' => $cached, 'stale' => true];
            }
            throw $e;
        }
    }

    public function details(string $id): array
    {
        self::assertId($id);
        return $this->json('https://api.fontsource.org/v1/fonts/' . $id);
    }

    public static function assertId(string $id): void
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/D', $id)) {
            throw new \InvalidArgumentException('Invalid catalogue identifier.');
        }
    }

    /** Build a pinned download manifest. No font bytes are downloaded here. */
    public function prepare(string $id, array $subsets = [], ?array $pin = null): array
    {
        self::assertId($id);
        $versions = $pin ?? $this->json('https://api.fontsource.org/v1/version/' . $id);
        $variable = isset($versions['latestVariable']);
        $version = (string) ($variable ? $versions['latestVariable'] : ($versions['latest'] ?? ''));
        if (!preg_match('/^\d+\.\d+\.\d+$/D', $version)) {
            throw new \RuntimeException('Invalid font package version.');
        }
        $package = 'https://cdn.jsdelivr.net/npm/@fontsource' . ($variable ? '-variable' : '') . '/' . $id . '@' . $version . '/';
        $meta = $this->json($package . 'metadata.json');
        // Fonts without a weight axis use their static package.
        if ($variable && !isset($meta['variable']['wght'])) {
            return $this->prepare($id, $subsets, ['latest' => $versions['latest'] ?? $version]);
        }
        $available = array_values(array_filter((array) ($meta['subsets'] ?? []), static fn($s): bool => is_string($s) && preg_match('/^[a-z0-9-]{1,80}$/D', $s)));
        $defaults = array_values(array_intersect(['latin', 'latin-ext'], $available));
        $selected = $subsets === [] ? ($defaults ?: [(string) ($meta['defSubset'] ?? '')]) : $subsets;
        if ($selected === [] || array_diff($selected, $available) !== []) {
            throw new \InvalidArgumentException('Select supported character sets.');
        }
        $files = [];
        $unicode = $variable ? [] : $this->json($package . 'unicode.json');
        $sheets = $variable ? ['wght.css'] : array_map(static fn(string $subset): string => $subset . '.css', $selected);
        if (in_array('italic', (array) ($meta['styles'] ?? []), true)) {
            $sheets = [...$sheets, ...($variable ? ['wght-italic.css'] : array_map(static fn(string $subset): string => $subset . '-italic.css', $selected))];
        }
        foreach ($sheets as $sheet) {
            foreach (FontFiles::parse($this->get($package . $sheet, 2097152), $selected) as $face) {
                if ($face['unicode_range'] === '') {
                    $face['unicode_range'] = (string) ($unicode[$face['subset']] ?? '');
                    if ($face['unicode_range'] === '') {
                        throw new \RuntimeException('Missing character coverage for a font subset.');
                    }
                    FontFiles::face($face + ['hash' => str_repeat('0', 64)]);
                }
                $face['url'] = $package . 'files/' . $face['filename'];
                $files[$face['filename']] = $face;
            }
        }
        if ($files === [] || count($files) > 2048) {
            throw new \RuntimeException('No supported WOFF2 faces were found for this family.');
        }
        return ['id' => 'fontsource-' . $id, 'provider_id' => $id, 'name' => (string) ($meta['family'] ?? $id), 'source' => 'fontsource', 'category' => match ($meta['category'] ?? '') { 'serif', 'monospace', 'cursive', 'fantasy' => $meta['category'], 'handwriting' => 'cursive', 'display' => 'fantasy', default => 'sans-serif' }, 'version' => $version, 'variable' => $variable, 'subsets' => array_values(array_unique($selected)), 'license' => $this->get($package . 'LICENSE', 262144), 'faces' => array_values($files)];
    }
}
