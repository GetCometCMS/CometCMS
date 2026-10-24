<?php

declare(strict_types=1);

namespace CometCMS\Auth;

use CometCMS\Core\Security;
use CometCMS\Storage\JsonStore;

final class ApiTokenRepository
{
    private JsonStore $store;

    public function __construct()
    {
        $this->store = new JsonStore(COMET_STORAGE . '/api-tokens');
    }

    public function all(): array
    {
        return $this->store->all();
    }

    public function find(string $id): ?array
    {
        Security::assertSafeName($id);
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

    private function normalizePermissions(mixed $permissions): array
    {
        return PermissionService::normalizeGrants($permissions);
    }
}
