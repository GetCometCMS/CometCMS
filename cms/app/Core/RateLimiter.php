<?php

declare(strict_types=1);

namespace CometCMS\Core;

/**
 * Small file-backed fixed-window rate limiter for public request gateways.
 */
final class RateLimiter
{
    public function __construct(
        private readonly string $directory,
        private readonly int $maxRecords = 10000,
    ) {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }
    }

    /**
     * @return array{limited:bool,remaining:int,retry_after:int,reset_at:int}
     */
    public function consume(string $bucket, string $identity, int $limit, int $windowSeconds): array
    {
        $limit = max(1, $limit);
        $windowSeconds = max(1, $windowSeconds);
        $now = time();
        $key = hash('sha256', $bucket . '|' . $identity);
        $path = $this->directory . '/' . $key . '.json';
        $stream = fopen($path, 'c+');

        if ($stream === false || !flock($stream, LOCK_EX)) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw new \RuntimeException('Could not access rate-limit storage.');
        }

        $raw = stream_get_contents($stream);
        $record = json_decode(is_string($raw) ? $raw : '', true);
        $record = is_array($record) ? $record : [];
        $resetAt = (int) ($record['reset_at'] ?? 0);
        $attempts = (int) ($record['attempts'] ?? 0);

        if ($resetAt <= $now) {
            $attempts = 0;
            $resetAt = $now + $windowSeconds;
        }

        $attempts++;
        $payload = json_encode([
            'id' => $key,
            'bucket_hash' => hash('sha256', $bucket),
            'identity_hash' => hash('sha256', $identity),
            'attempts' => $attempts,
            'reset_at' => $resetAt,
            'updated_at' => Security::now(),
        ], JSON_UNESCAPED_SLASHES);

        rewind($stream);
        ftruncate($stream, 0);
        fwrite($stream, $payload === false ? '{}' : $payload);
        fflush($stream);
        flock($stream, LOCK_UN);
        fclose($stream);

        if (random_int(1, 100) <= 2) {
            $this->prune($now);
        }

        return [
            'limited' => $attempts > $limit,
            'remaining' => max(0, $limit - $attempts),
            'retry_after' => max(0, $resetAt - $now),
            'reset_at' => $resetAt,
        ];
    }

    public function prune(?int $now = null): void
    {
        $now ??= time();
        $files = glob($this->directory . '/*.json') ?: [];

        foreach ($files as $index => $path) {
            $record = json_decode((string) file_get_contents($path), true);
            if (!is_array($record) || (int) ($record['reset_at'] ?? 0) <= $now) {
                @unlink($path);
                unset($files[$index]);
            }
        }

        $files = array_values($files);
        if (count($files) <= max(1, $this->maxRecords)) {
            return;
        }

        usort($files, static fn(string $a, string $b): int => (filemtime($a) ?: 0) <=> (filemtime($b) ?: 0));
        foreach (array_slice($files, 0, count($files) - max(1, $this->maxRecords)) as $path) {
            @unlink($path);
        }
    }
}
