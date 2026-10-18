<?php

declare(strict_types=1);

namespace CometCMS\Core;

/**
 * Fixed-window attempt counter for public endpoints, one small file per
 * bucket and visitor. Visitors are stored as hashes only.
 */
final class RateLimiter
{
    public function __construct(private readonly string $directory) {}

    /** Count one attempt; true when it is over $limit within the window. */
    public function hit(string $bucket, string $identity, int $limit, int $windowSeconds): bool
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Could not create the rate-limit directory.');
        }

        $now = time();
        $handle = fopen($this->directory . '/' . hash('sha256', $bucket . '|' . $identity) . '.json', 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            throw new \RuntimeException('Could not access rate-limit storage.');
        }

        try {
            $record = json_decode((string) stream_get_contents($handle), true);
            $resetAt = (int) ($record['reset_at'] ?? 0);
            $attempts = $resetAt > $now ? (int) ($record['attempts'] ?? 0) : 0;
            if ($resetAt <= $now) {
                $resetAt = $now + max(1, $windowSeconds);
            }
            $attempts++;

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode(['attempts' => $attempts, 'reset_at' => $resetAt]));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        if (random_int(1, 50) === 1) {
            $this->prune($now);
        }

        return $attempts > max(1, $limit);
    }

    /** Drop records whose window has passed. */
    public function prune(?int $now = null): void
    {
        $now ??= time();

        foreach (glob($this->directory . '/*.json') ?: [] as $file) {
            $record = json_decode((string) @file_get_contents($file), true);
            if ((int) ($record['reset_at'] ?? 0) <= $now) {
                @unlink($file);
            }
        }
    }
}
