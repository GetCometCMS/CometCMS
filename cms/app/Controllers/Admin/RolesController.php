<?php

declare(strict_types=1);

namespace CometCMS\Controllers\Admin;

use CometCMS\Auth\RoleRepository;

final class RolesController extends BaseController
{
    private RoleRepository $roles;

    public function __construct(\CometCMS\Core\Http $http)
    {
        parent::__construct($http);
        $this->roles = new RoleRepository();
    }

    public function index(): never
    {
        $this->requirePermission('roles.read', ['resource' => 'roles:*']);
        $this->json(['data' => $this->roles->all()]);
    }

    public function store(): never
    {
        $actor = $this->requirePermission('roles.create', ['resource' => 'roles:*']);
        $this->verifyCsrf();
        $body = $this->requestJson();
        $this->requireDelegable($actor, is_array($body['permissions'] ?? null) ? $body['permissions'] : [], 'You cannot create a role with permissions you do not have yourself.');

        try {
            $role = $this->roles->create($body);
        } catch (\Throwable $e) {
            $this->json(['error' => ['code' => 'validation_failed', 'message' => $e->getMessage()]], 422);
        }

        $this->logger->info('role.created', ['role' => $role['id'] ?? null, 'user_id' => $actor['id'] ?? null]);
        $this->json(['data' => $role], 201);
    }

    public function update(string $id): never
    {
        $actor = $this->requirePermission('roles.update', ['resource' => 'roles:' . $id]);
        $this->verifyCsrf();
        $body = $this->requestJson();
        if (is_array($body['permissions'] ?? null)) {
            $this->requireDelegable($actor, $body['permissions'], 'You cannot give a role permissions you do not have yourself.');
        }

        try {
            $role = $this->roles->update($id, $body);
        } catch (\RuntimeException $e) {
            $this->json(['error' => ['code' => 'not_found', 'message' => $e->getMessage()]], 404);
        } catch (\Throwable $e) {
            $this->json(['error' => ['code' => 'validation_failed', 'message' => $e->getMessage()]], 422);
        }

        $this->logger->info('role.updated', ['role' => $role['id'] ?? $id, 'user_id' => $actor['id'] ?? null]);
        $this->json(['data' => $role]);
    }

    public function destroy(string $id): never
    {
        $actor = $this->requirePermission('roles.delete', ['resource' => 'roles:' . $id]);
        $this->verifyCsrf();

        if ($this->roleInUse($id)) {
            $this->json(['error' => ['code' => 'validation_failed', 'message' => 'This role is assigned to one or more users.']], 422);
        }

        try {
            $this->roles->delete($id);
        } catch (\RuntimeException $e) {
            $this->json(['error' => ['code' => 'not_found', 'message' => $e->getMessage()]], 404);
        } catch (\Throwable $e) {
            $this->json(['error' => ['code' => 'validation_failed', 'message' => $e->getMessage()]], 422);
        }

        $this->logger->info('role.deleted', ['role' => $id, 'user_id' => $actor['id'] ?? null]);
        $this->json(['data' => ['ok' => true]]);
    }

    private function roleInUse(string $id): bool
    {
        foreach ($this->users->all() as $user) {
            if (($user['role'] ?? '') === $id) {
                return true;
            }
        }

        return false;
    }
}
