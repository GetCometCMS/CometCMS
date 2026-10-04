<?php

declare(strict_types=1);

namespace CometCMS\Auth;

final class PermissionService
{
    private RoleRepository $roles;

    public function __construct(?RoleRepository $roles = null)
    {
        $this->roles = $roles ?? new RoleRepository();
    }

    public function allows(array $principal, string $action, array $context = []): bool
    {
        $fields = $this->changedFields($context);
        $allow = false;

        foreach ($this->grants($principal) as $grant) {
            if (!$this->grantApplies($grant, $action, $context, $fields)) {
                continue;
            }

            if (($grant['effect'] ?? 'allow') === 'deny') {
                return false;
            }

            $allow = true;
        }

        return $allow;
    }

    /** Whether any allow grant names $action, regardless of the resources it is limited to. */
    public function grantsAction(array $principal, string $action): bool
    {
        foreach ($this->grants($principal) as $grant) {
            if (($grant['effect'] ?? 'allow') === 'allow' && $this->matchesAny((array) ($grant['actions'] ?? []), $action)) {
                return true;
            }
        }

        return false;
    }

    public function capabilities(array $principal): array
    {
        return [
            'permissions' => $this->grants($principal),
            'actions' => array_values(array_unique(array_merge(...array_map(
                fn(array $grant): array => array_map('strval', (array) ($grant['actions'] ?? $grant['action'] ?? [])),
                $this->grants($principal),
            )))),
        ];
    }

    /**
     * Find the first permission in $grants that $principal could not exercise itself.
     *
     * Used before delegating access (assigning a role, editing a role, minting an
     * access token) so nobody can hand out — or hand themselves — more than they
     * hold. The check is deliberately conservative: a delegated allow grant is
     * covered only when, for each of its actions and resources, the principal has
     * an allow grant at least as broad (no narrower field list, no extra
     * conditions) and no deny grant that could overlap it. Delegated deny grants
     * only ever restrict access and are always acceptable.
     *
     * @return array{action: string, resource: string}|null null when everything is covered
     */
    public function firstUncovered(array $principal, array $grants): ?array
    {
        $own = $this->grants($principal);

        foreach ($grants as $grant) {
            if (!is_array($grant)) {
                continue;
            }

            $grant = $this->normalizeGrant($grant);
            if ($grant['effect'] === 'deny') {
                continue;
            }

            foreach ($this->expandActions($grant['actions']) as $action) {
                foreach ($grant['resources'] ?: ['*'] as $resource) {
                    if (!$this->coversPair($own, $action, $resource, $grant)) {
                        return ['action' => $action, 'resource' => $resource];
                    }
                }
            }
        }

        return null;
    }

    private function coversPair(array $own, string $action, string $resource, array $delegated): bool
    {
        $candidates = [$resource];

        // A bare "*" resource on e.g. content.read means "every content entry",
        // which is what a grant on content:* already covers.
        $domain = self::ACTION_DOMAINS[strstr($action, '.', true) ?: $action] ?? null;
        if ($resource === '*' && $domain !== null) {
            $candidates[] = $domain . ':*';
        }

        // allows() also checks unscoped resources for workspace-scoped requests,
        // so an unscoped grant covers the same resource inside any workspace.
        if (preg_match('/^workspace:[^:]+:(.+)$/', $resource, $match)) {
            $candidates[] = $match[1];
        }

        $allowed = false;

        foreach ($own as $grant) {
            if (!$this->matchesAny((array) ($grant['actions'] ?? []), $action)) {
                continue;
            }

            $patterns = (array) ($grant['resources'] ?? []);

            if (($grant['effect'] ?? 'allow') === 'deny') {
                foreach ($patterns as $pattern) {
                    foreach ($candidates as $candidate) {
                        if ($this->matches((string) $pattern, $candidate) || $this->matches($candidate, (string) $pattern)) {
                            return false;
                        }
                    }
                }

                continue;
            }

            if ($allowed || !$this->grantAtLeastAsBroad($grant, $delegated)) {
                continue;
            }

            foreach ($patterns as $pattern) {
                foreach ($candidates as $candidate) {
                    if ($this->matches((string) $pattern, $candidate)) {
                        $allowed = true;
                        break 2;
                    }
                }
            }
        }

        return $allowed;
    }

    private function grantAtLeastAsBroad(array $own, array $delegated): bool
    {
        if (array_key_exists('fields', $own)) {
            if (!array_key_exists('fields', $delegated)) {
                return false;
            }

            foreach ((array) $delegated['fields'] as $field) {
                if (!$this->matchesAny((array) $own['fields'], (string) $field)) {
                    return false;
                }
            }
        }

        $ownConditions = (array) ($own['conditions'] ?? []);

        return $ownConditions === [] || $ownConditions == (array) ($delegated['conditions'] ?? []);
    }

    /** Expand wildcard actions such as "content.*" or "*" to the concrete actions they grant. */
    private function expandActions(array $actions): array
    {
        $catalog = [];
        foreach (RoleRepository::defaultPermissions('admin') as $grant) {
            foreach ((array) ($grant['actions'] ?? []) as $known) {
                $catalog[] = (string) $known;
            }
        }

        $expanded = [];
        foreach ($actions as $action) {
            $action = (string) $action;
            $matches = str_contains($action, '*')
                ? array_values(array_filter($catalog, fn(string $known): bool => $this->matches($action, $known)))
                : [];
            array_push($expanded, ...($matches !== [] ? $matches : [$action]));
        }

        return array_values(array_unique($expanded));
    }

    private const ACTION_DOMAINS = [
        'content' => 'content',
        'schema' => 'schema',
        'media' => 'media',
        'users' => 'users',
        'tokens' => 'tokens',
        'roles' => 'roles',
        'workspaces' => 'workspaces',
    ];

    public static function preset(string $role): array
    {
        return RoleRepository::defaultPermissions($role);
    }

    public static function defaultPermissions(string $role): array
    {
        return self::preset($role);
    }

    private function grants(array $principal): array
    {
        $grants = [];

        if (($principal['_principal_type'] ?? 'user') !== 'token') {
            $role = (string) ($principal['role'] ?? 'viewer');
            foreach ($this->roles->permissions($role) as $grant) {
                $grants[] = $this->normalizeGrant($grant);
            }

            return $grants;
        }

        foreach ((array) ($principal['permissions'] ?? []) as $grant) {
            if (is_array($grant)) {
                $grants[] = $this->normalizeGrant($grant);
            }
        }

        return $grants;
    }

    private function normalizeGrant(array $grant): array
    {
        $actions = $grant['actions'] ?? $grant['action'] ?? [];
        $resources = $grant['resources'] ?? $grant['resource'] ?? [];

        return array_filter([
            'effect' => ($grant['effect'] ?? 'allow') === 'deny' ? 'deny' : 'allow',
            'actions' => array_values(array_filter(array_map('strval', (array) $actions))),
            'resources' => array_values(array_filter(array_map('strval', (array) $resources))),
            'fields' => array_key_exists('fields', $grant) ? array_values(array_filter(array_map('strval', (array) $grant['fields']))) : null,
            'conditions' => is_array($grant['conditions'] ?? null) ? $grant['conditions'] : null,
        ], static fn(mixed $value): bool => $value !== null);
    }

    private function grantApplies(array $grant, string $action, array $context, array $fields): bool
    {
        if (!$this->matchesAny((array) ($grant['actions'] ?? []), $action)) {
            return false;
        }

        if (!$this->matchesResource((array) ($grant['resources'] ?? []), $context)) {
            return false;
        }

        if (!$this->fieldsAllowed($grant, $fields)) {
            return false;
        }

        return $this->conditionsAllowed((array) ($grant['conditions'] ?? []), $context);
    }

    private function matchesResource(array $patterns, array $context): bool
    {
        $resources = $this->resourceCandidates($context);

        foreach ($patterns as $pattern) {
            foreach ($resources as $resource) {
                if ($this->matches((string) $pattern, $resource)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function resourceCandidates(array $context): array
    {
        $type = (string) ($context['type'] ?? '');

        if ($type === 'content') {
            $collection = (string) ($context['collection'] ?? '*');
            $workspace = (string) ($context['workspace'] ?? '');
            $entry = is_array($context['entry'] ?? null) ? $context['entry'] : [];
            $id = (string) ($context['id'] ?? $entry['id'] ?? $entry['slug'] ?? '*');
            $slug = (string) ($entry['slug'] ?? '');
            $candidates = [
                'content:' . $collection . ':' . $id,
            ];

            if ($slug !== '' && $slug !== $id) {
                $candidates[] = 'content:' . $collection . ':' . $slug;
            }

            $resources = array_values(array_unique(array_merge($candidates, [
                'content:' . $collection . ':*',
                'content:' . $collection,
                'content:*',
                '*',
            ])));

            if ($workspace !== '') {
                $workspaceResources = [];
                foreach ($resources as $resource) {
                    if ($resource !== '*') {
                        $workspaceResources[] = 'workspace:' . $workspace . ':' . $resource;
                    }
                }

                return array_values(array_unique(array_merge($workspaceResources, $resources)));
            }

            return $resources;
        }

        if ($type === 'schema') {
            $name = (string) ($context['name'] ?? '*');
            $workspace = (string) ($context['workspace'] ?? '');
            $resources = ['schema:' . $name, 'schema:*', '*'];

            if ($workspace !== '') {
                return array_values(array_unique(array_merge([
                    'workspace:' . $workspace . ':schema:' . $name,
                    'workspace:' . $workspace . ':schema:*',
                ], $resources)));
            }

            return $resources;
        }

        if ($type === 'media') {
            $file = (string) ($context['file'] ?? '*');
            $category = trim((string) ($context['category'] ?? ''));
            $workspace = (string) ($context['workspace'] ?? '');
            $resources = ['media:' . $file, 'media:*', '*'];
            if ($category !== '') {
                array_unshift($resources, 'media:category:' . $category);
            }

            if ($workspace !== '') {
                $workspaceResources = [];
                foreach ($resources as $resource) {
                    if ($resource !== '*') {
                        $workspaceResources[] = 'workspace:' . $workspace . ':' . $resource;
                    }
                }

                return array_values(array_unique(array_merge($workspaceResources, $resources)));
            }
            return $resources;
        }

        if ($type === 'user') {
            $id = (string) ($context['user_id'] ?? '*');
            return ['users:' . $id, 'users:*', '*'];
        }

        if ($type === 'token') {
            $id = (string) ($context['token_id'] ?? $context['user_id'] ?? '*');
            return ['tokens:' . $id, 'tokens:*', '*'];
        }

        if ($type === 'workspace') {
            $slug = (string) ($context['slug'] ?? '*');
            return ['workspaces:' . $slug, 'workspaces:*', '*'];
        }

        $resource = (string) ($context['resource'] ?? $type . ':*');
        return [$resource, '*'];
    }

    private function fieldsAllowed(array $grant, array $fields): bool
    {
        if ($fields === [] || !array_key_exists('fields', $grant)) {
            return true;
        }

        $allowed = (array) $grant['fields'];
        foreach ($fields as $field) {
            if (!$this->matchesAny($allowed, $field)) {
                return false;
            }
        }

        return true;
    }

    private function conditionsAllowed(array $conditions, array $context): bool
    {
        if ($conditions === []) {
            return true;
        }

        $entry = is_array($context['entry'] ?? null) ? $context['entry'] : [];
        $principal = is_array($context['principal'] ?? null) ? $context['principal'] : [];

        if (($conditions['own'] ?? false) === true && ($entry['author_id'] ?? null) !== ($principal['id'] ?? null)) {
            return false;
        }

        if (isset($conditions['status'])) {
            $statuses = array_map('strval', (array) $conditions['status']);
            $status = (string) ($context['status'] ?? $entry['status'] ?? '');
            if (!in_array($status, $statuses, true)) {
                return false;
            }
        }

        if (isset($conditions['locales'])) {
            $locales = array_map('strval', (array) $conditions['locales']);
            $locale = (string) ($context['locale'] ?? '');
            if ($locale !== '' && !in_array($locale, $locales, true)) {
                return false;
            }
        }

        return true;
    }

    private function changedFields(array $context): array
    {
        return array_values(array_filter(array_map('strval', (array) ($context['fields'] ?? []))));
    }

    private function matchesAny(array $patterns, string $value): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->matches((string) $pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    private function matches(string $pattern, string $value): bool
    {
        if ($pattern === '*' || $pattern === $value) {
            return true;
        }

        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';

        return preg_match($regex, $value) === 1;
    }
}
