<?php

declare(strict_types=1);

namespace CometCMS\Site;

use CometCMS\Storage\JsonStore;

/** Short-lived, user-owned previews. Never writes content or website configuration. */
final class PreviewRepository
{
    private JsonStore $store;

    public function __construct(SiteRepository $site)
    {
        $this->store = new JsonStore($site->workspace()->path('cache') . '/site-previews');
    }

    public function create(string $user, array $body): string
    {
        $owned = [];
        foreach ($this->store->all() as $item) {
            $token = (string) ($item['token'] ?? '');
            if (!preg_match('/^[a-f0-9]{48}$/', $token)) continue;
            if ((int) ($item['expires'] ?? 0) <= time()) {
                $this->store->delete($token);
            } elseif (($item['user'] ?? '') === $user) {
                $owned[] = $item;
            }
        }
        // Bound disk use when repeatedly opening previews from an editor.
        usort($owned, static fn(array $a, array $b): int => $b['expires'] <=> $a['expires']);
        foreach (array_slice($owned, 19) as $item) $this->store->delete($item['token']);
        $token = bin2hex(random_bytes(24));
        $this->store->write(['token' => $token, 'user' => $user, 'expires' => time() + 3600, 'body' => $body], $token);
        return $token;
    }

    public function find(string $token, string $user): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) return null;
        $item = $this->store->read($token);
        if ($item === null || ($item['user'] ?? '') !== $user) return null;
        if ((int) ($item['expires'] ?? 0) <= time()) {
            $this->store->delete($token);
            return null;
        }
        return is_array($item['body'] ?? null) ? $item['body'] : null;
    }
}
