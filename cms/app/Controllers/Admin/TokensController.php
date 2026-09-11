<?php

declare(strict_types=1);

namespace CometCMS\Controllers\Admin;

final class TokensController extends BaseController
{
    public function index(): never
    {
        $this->requirePermission('tokens.read', ['type' => 'token']);
        $this->json(['data' => array_map([$this->tokens, 'safeToken'], $this->tokens->all())]);
    }

    public function store(): never
    {
        $actor = $this->requirePermission('tokens.create', ['type' => 'token']);
        $this->verifyCsrf();
        $body = $this->requestJson();
        $permissions = is_array($body['permissions'] ?? null) ? $body['permissions'] : null;
        $tokenName = (string) ($body['name'] ?? 'Access token');
        $description = (string) ($body['description'] ?? '');
        $token = $this->tokens->create($tokenName, $description, $permissions);
        $this->logger->info('token.created', ['name' => $tokenName, 'actor_id' => $actor['id'] ?? null]);
        $this->json(['data' => ['token' => $token]], 201);
    }

    public function destroy(string $tokenId): never
    {
        $actor = $this->requirePermission('tokens.revoke', ['type' => 'token', 'token_id' => $tokenId]);
        $this->verifyCsrf();
        $token = $this->tokens->find($tokenId);
        $action = !empty($token['revoked_at']) ? 'deleted' : 'revoked';

        if ($action === 'deleted') {
            $this->tokens->delete($tokenId);
        } else {
            $this->tokens->revoke($tokenId);
        }

        $this->logger->info('token.' . $action, ['token_id' => $tokenId, 'actor_id' => $actor['id'] ?? null]);
        $this->json(['data' => ['ok' => true, 'action' => $action]]);
    }
}
