<?php

declare(strict_types=1);

namespace CometCMS\Controllers\Admin;

use CometCMS\Content\ContentRepository;
use CometCMS\Content\ContentTypeRepository;
use CometCMS\Core\Http;
use CometCMS\Core\ValidationException;

final class ContentController extends BaseController
{
    private ContentRepository $content;
    private ContentTypeRepository $types;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->content = ContentRepository::make();
        $this->types = new ContentTypeRepository();
    }

    public function index(string $collection): never
    {
        $this->requireCollection($collection);
        $this->requirePermission('content.read', ['type' => 'content', 'collection' => $collection]);
        $result = $this->content->query($collection, $_GET, true);
        $this->json(['data' => array_values($result['data']), 'meta' => $result['meta']]);
    }

    public function show(string $collection, string $id): never
    {
        $this->requireCollection($collection);
        $entry = $this->content->findByIdentifier($collection, $id);

        if ($entry === null) {
            $this->json(['error' => ['code' => 'not_found', 'message' => 'Entry not found.']], 404);
        }

        $this->requirePermission('content.read', ['type' => 'content', 'collection' => $collection, 'entry' => $entry]);
        $this->json(['data' => $entry]);
    }

    public function store(string $collection): never
    {
        $this->requireCollection($collection);
        $body = $this->requestJson();
        $user = $this->requirePermission('content.create', ['type' => 'content', 'collection' => $collection, 'fields' => $this->payloadFields($body), 'locale' => $body['locale'] ?? null]);
        $this->requirePublishPermission($user, $collection, null, $body);
        $this->verifyCsrf();

        try {
            $entry = $this->content->save($collection, $body, $user, null, true);
        } catch (ValidationException $e) {
            $this->json(['error' => ['code' => 'validation_failed', 'message' => $e->getMessage(), 'fields' => $e->fields()]], 422);
        }

        $this->json(['data' => $entry], 201);
    }

    public function update(string $collection, string $id): never
    {
        $this->requireCollection($collection);
        $body = $this->requestJson();
        $existing = $this->content->findByIdentifier($collection, $id);

        if ($existing === null) {
            $this->json(['error' => ['code' => 'not_found', 'message' => 'Entry not found.']], 404);
        }

        $user = $this->requirePermission('content.update', ['type' => 'content', 'collection' => $collection, 'entry' => $existing, 'fields' => $this->payloadFields($body), 'locale' => $body['locale'] ?? null]);
        $this->requirePublishPermission($user, $collection, $existing, $body);
        $this->verifyCsrf();

        try {
            $entry = $this->content->save($collection, $body, $user, (string) $existing['id'], true);
        } catch (ValidationException $e) {
            $this->json(['error' => ['code' => 'validation_failed', 'message' => $e->getMessage(), 'fields' => $e->fields()]], 422);
        }

        $this->json(['data' => $entry]);
    }

    public function bulkUpdate(string $collection): never
    {
        $this->requireCollection($collection);
        $body = $this->requestJson();
        $ids  = $body['ids'] ?? [];
        $data = $body['data'] ?? [];

        if (!is_array($ids) || $ids === []) {
            $this->json(['error' => ['code' => 'validation_failed', 'message' => 'ids must be a non-empty array.']], 422);
        }

        if (!is_array($data) || $data === []) {
            $this->json(['error' => ['code' => 'validation_failed', 'message' => 'data must be a non-empty object.']], 422);
        }

        $user = $this->requirePermission('content.update', ['type' => 'content', 'collection' => $collection, 'fields' => $this->payloadFields($data)]);
        $this->verifyCsrf();

        $updated = 0;
        $failed  = 0;
        $errors  = [];

        foreach ($ids as $id) {
            $id = (string) $id;
            $existing = $this->content->find($collection, $id, true);

            if ($existing === null) {
                $failed++;
                $errors[$id] = ['_entry' => ['Entry not found.']];
                continue;
            }

            // The collection-level check above cannot see per-entry grants
            // (denies, ownership) or the separate publish permission.
            $context = ['type' => 'content', 'collection' => $collection, 'entry' => $existing, 'fields' => $this->payloadFields($data), 'principal' => $user];
            $publishing = ($data['status'] ?? null) === 'published' && ($existing['status'] ?? null) !== 'published';
            if (!$this->permissions->allows($user, 'content.update', $context)
                || ($publishing && !$this->permissions->allows($user, 'content.publish', $context))) {
                $failed++;
                $errors[$id] = ['_entry' => ['You do not have permission to change this entry.']];
                continue;
            }

            try {
                $this->content->bulkUpdateFields($collection, $existing, $data, $user);
                $updated++;
            } catch (ValidationException $e) {
                $failed++;
                $errors[$id] = $e->fields();
            }
        }

        $this->json(['data' => ['updated' => $updated, 'failed' => $failed, 'errors' => $errors]]);
    }

    public function duplicate(string $collection, string $id): never
    {
        $this->requireCollection($collection);
        $existing = $this->content->find($collection, $id, true);

        if ($existing === null) {
            $this->json(['error' => ['code' => 'not_found', 'message' => 'Entry not found.']], 404);
        }

        $user = $this->requirePermission('content.create', ['type' => 'content', 'collection' => $collection, 'entry' => $existing]);
        $this->verifyCsrf();

        try {
            $entry = $this->content->duplicate($collection, (string) $existing['id'], $user);
        } catch (ValidationException $e) {
            $this->json(['error' => ['code' => 'validation_failed', 'message' => $e->getMessage(), 'fields' => $e->fields()]], 422);
        }

        if ($entry === null) {
            $this->json(['error' => ['code' => 'not_found', 'message' => 'Entry not found.']], 404);
        }

        $this->json(['data' => $entry], 201);
    }

    public function destroy(string $collection, string $id): never
    {
        $this->requireCollection($collection);
        $entry = $this->content->findByIdentifier($collection, $id);

        if ($entry === null) {
            $this->json(['error' => ['code' => 'not_found', 'message' => 'Entry not found.']], 404);
        }

        $user = $this->requirePermission('content.delete', ['type' => 'content', 'collection' => $collection, 'entry' => $entry]);
        $this->verifyCsrf();
        $this->content->softDelete($collection, (string) $entry['id'], $user);
        $this->json(['data' => ['ok' => true]]);
    }

    public function bulkDelete(string $collection): never
    {
        $this->requireCollection($collection);
        $user = $this->requirePermission('content.delete', ['type' => 'content', 'collection' => $collection]);
        $this->verifyCsrf();

        $body = $this->requestJson();
        $ids  = $body['ids'] ?? [];

        if (!is_array($ids) || $ids === []) {
            $this->json(['error' => ['code' => 'validation_failed', 'message' => 'ids must be a non-empty array.']], 422);
        }

        $deleted = 0;
        $failed  = 0;
        $errors  = [];

        foreach ($ids as $id) {
            $id = (string) $id;
            $entry = $this->content->find($collection, $id);

            if ($entry === null) {
                $failed++;
                $errors[$id] = 'Entry not found.';
                continue;
            }

            if (!$this->permissions->allows($user, 'content.delete', ['type' => 'content', 'collection' => $collection, 'entry' => $entry, 'principal' => $user])) {
                $failed++;
                $errors[$id] = 'You do not have permission to delete this entry.';
                continue;
            }

            try {
                $this->content->softDelete($collection, (string) $entry['id'], $user);
                $deleted++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[$id] = 'The entry could not be deleted.';
                $this->logger->error('content.bulk_delete_failed', ['collection' => $collection, 'id' => $id, 'message' => $e->getMessage()]);
            }
        }

        $this->json(['data' => ['deleted' => $deleted, 'failed' => $failed, 'errors' => $errors]]);
    }

    public function revisions(string $collection, string $id): never
    {
        $this->requireCollection($collection);
        $entry = $this->content->findByIdentifier($collection, $id, true);

        if ($entry === null) {
            $this->json(['error' => ['code' => 'not_found', 'message' => 'Entry not found.']], 404);
        }

        $this->requirePermission('content.revisions.read', ['type' => 'content', 'collection' => $collection, 'entry' => $entry]);
        $this->json(['data' => $this->content->revisions($collection, (string) $entry['id'])]);
    }

    public function revisionRestore(string $collection, string $id, string $revisionId): never
    {
        $this->requireCollection($collection);
        $current = $this->content->findByIdentifier($collection, $id);

        if ($current === null) {
            $this->json(['error' => ['code' => 'not_found', 'message' => 'Entry not found.']], 404);
        }

        $user = $this->requirePermission('content.revisions.restore', ['type' => 'content', 'collection' => $collection, 'entry' => $current]);
        $this->verifyCsrf();
        $entry = $this->content->restoreRevision($collection, (string) $current['id'], $revisionId, $user);

        if ($entry === null) {
            $this->json(['error' => ['code' => 'not_found', 'message' => 'Revision not found.']], 404);
        }

        $this->json(['data' => $entry]);
    }

    public function destroyTranslation(string $collection, string $id, string $locale): never
    {
        $this->requireCollection($collection);
        $current = $this->content->findByIdentifier($collection, $id, true);

        if ($current === null) {
            $this->json(['error' => ['code' => 'not_found', 'message' => 'Entry or locale not found, or locale is the default.']], 404);
        }

        $user = $this->requirePermission('content.update', ['type' => 'content', 'collection' => $collection, 'entry' => $current, 'locale' => $locale]);
        $this->verifyCsrf();
        $entry = $this->content->deleteTranslation($collection, (string) $current['id'], $locale, $user);

        if ($entry === null) {
            $this->json(['error' => ['code' => 'not_found', 'message' => 'Entry or locale not found, or locale is the default.']], 404);
        }

        $this->json(['data' => $entry]);
    }

    private function requireCollection(string $collection): void
    {
        if (!$this->types->exists($collection)) {
            $this->json(['error' => ['code' => 'not_found', 'message' => 'Content collection not found.']], 404);
        }
    }

    private function payloadFields(array $payload): array
    {
        return array_values(array_diff(array_keys($payload), ['id', 'uid', 'collection', 'created_at', 'updated_at', 'deleted_at', 'deleted_by', 'updated_by', 'translations', 'locale']));
    }

    private function requirePublishPermission(array $user, string $collection, ?array $entry, array $payload): void
    {
        if (($payload['status'] ?? null) !== 'published') {
            return;
        }

        if ($this->permissions->allows($user, 'content.publish', ['type' => 'content', 'collection' => $collection, 'entry' => $entry ?? [], 'principal' => $user])) {
            return;
        }

        $this->json(['error' => ['code' => 'forbidden', 'message' => 'You do not have permission to publish this entry.']], 403);
    }
}
