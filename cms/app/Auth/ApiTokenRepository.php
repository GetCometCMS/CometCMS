<?php

declare(strict_types=1);

namespace CometCMS\Auth;

use CometCMS\Core\Security;
use CometCMS\Storage\JsonStore;

final class ApiTokenRepository
{
    private JsonStore $store;
    private UserRepository $users;

    public function __construct()
    {
        $this->store = new JsonStore(COMET_STORAGE . '/api-tokens');
        $this->users = new UserRepository();
    }

    public function all(): array
    {
        $this->migrateLegacyUserTokens();

        return $this->store->all();
    }

    public function find(string $id): ?array
    {
        Security::assertSafeName($id);
        $this->migrateLegacyUserTokens();

        return $this->store->read($id);
    }

    public function create(string $name, string $description = '', ?array $permissions = null): string
    {
        $tokenId = bin2hex(random_bytes(5));
        $secret = bin2hex(random_bytes(24));
        $plain = 'ctcms_' . $tokenId . '_' . $secret;
        $token = [
            'id' => 'tok_' . $tokenId,
            'name' => trim($name) !== '' ? trim($name) : 'Access token',
            'description' => trim($description),
            'hash' => password_hash($plain, PASSWORD_DEFAULT),
            'permissions' => $this->normalizePermissions($permissions ?? []),
            'created_at' => Security::now(),
            'last_used_at' => null,
            'revoked_at' => null,
        ];

        $this->save($token);

        return $plain;
    }

    public function revoke(string $id): void
    {
        $token = $this->find($id);

        if ($token === null) {
            return;
        }

        $token['revoked_at'] = Security::now();
        $token['updated_at'] = Security::now();
        $this->save($token);
    }

    public function delete(string $id): void
    {
        Security::assertSafeName($id);
        $this->store->delete($id);
    }

    public function findByToken(string $plainToken): ?array
    {
        $this->migrateLegacyUserTokens();

        foreach ($this->store->all() as $token) {
            if (!is_array($token) || !empty($token['revoked_at']) || empty($token['hash']) || !password_verify($plainToken, (string) $token['hash'])) {
                continue;
            }

            $token['last_used_at'] = Security::now();
            $token['updated_at'] = Security::now();
            $this->save($token);

            return [
                'id' => $token['id'],
                'username' => $token['name'],
                '_principal_type' => 'token',
                '_token' => $this->safeToken($token),
                'permissions' => is_array($token['permissions'] ?? null)
                    ? $this->normalizePermissions($token['permissions'])
                    : [],
            ];
        }

        return null;
    }

    public function safeToken(array $token): array
    {
        return [
            'id' => $token['id'],
            'name' => $token['name'],
            'description' => $token['description'] ?? '',
            'permissions' => $token['permissions'] ?? [],
            'created_at' => $token['created_at'] ?? null,
            'last_used_at' => $token['last_used_at'] ?? null,
            'revoked_at' => $token['revoked_at'] ?? null,
        ];
    }

    public function save(array $token): void
    {
        Security::assertSafeName((string) $token['id']);
        $token['permissions'] = $this->normalizePermissions($token['permissions'] ?? []);
        $this->store->write($token, (string) $token['id']);
    }

    private function migrateLegacyUserTokens(): void
    {
        foreach ($this->users->all() as $user) {
            $tokens = is_array($user['api_tokens'] ?? null) ? $user['api_tokens'] : [];

            if ($tokens === []) {
                continue;
            }

            foreach ($tokens as $token) {
                if (!is_array($token) || empty($token['id']) || $this->store->read((string) $token['id']) !== null) {
                    continue;
                }

                $token['description'] = (string) ($token['description'] ?? '');
                $token['legacy_user_id'] = (string) ($user['id'] ?? '');
                $token['permissions'] = $this->normalizePermissions($token['permissions'] ?? []);
                $this->save($token);
            }

            unset($user['api_tokens']);
            $user['updated_at'] = Security::now();
            $this->users->save($user);
        }
    }

    private function normalizePermissions(mixed $permissions): array
    {
        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($permissions)) {
            return [];
        }

        $normalized = [];

        foreach ($permissions as $grant) {
            if (!is_array($grant)) {
                continue;
            }

            $actions = $grant['actions'] ?? $grant['action'] ?? [];
            $resources = $grant['resources'] ?? $grant['resource'] ?? [];

            $item = [
                'effect' => ($grant['effect'] ?? 'allow') === 'deny' ? 'deny' : 'allow',
                'actions' => array_values(array_filter(array_map('strval', (array) $actions))),
                'resources' => array_values(array_filter(array_map('strval', (array) $resources))),
            ];

            if (array_key_exists('fields', $grant)) {
                $item['fields'] = array_values(array_filter(array_map('strval', (array) $grant['fields'])));
            }

            if (is_array($grant['conditions'] ?? null)) {
                $item['conditions'] = $grant['conditions'];
            }

            if ($item['actions'] !== [] && $item['resources'] !== []) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }
}
