<?php

declare(strict_types=1);

namespace CometCMS\Content;

use CometCMS\Auth\PermissionService;
use CometCMS\Workspaces\WorkspaceContext;

/** Exposure and target authorization shared by external read transports. */
final class ExternalReadPolicy
{
    public static function apiEnabled(array $schema): bool
    {
        return !empty($schema['api_enabled']);
    }

    public static function allowsEntry(array $entry, ContentRepository $content, ContentTypeRepository $types, WorkspaceContext $workspace, ?array $principal, bool $management = false): bool
    {
        $collection = (string) ($entry['collection'] ?? '');
        if (!$types->exists($collection)) return false;
        $schema = $types->find($collection);
        // Authenticated MCP is a management interface; anonymous MCP and REST
        // share the API exposure boundary, including expanded targets.
        if ((!$management || $principal === null) && !self::apiEnabled($schema)) return false;
        $locale = (string) ($entry['locale'] ?? $content->locale());
        if ($principal === null) {
            return ($schema['visibility'] ?? 'public') !== 'private' && $content->isPubliclyVisible($entry, $locale);
        }

        return (new PermissionService())->allows($principal, 'content.read', [
            'type' => 'content', 'collection' => $collection, 'entry' => $entry,
            'locale' => $locale, 'workspace' => $workspace->slug(), 'principal' => $principal,
        ]);
    }
}
