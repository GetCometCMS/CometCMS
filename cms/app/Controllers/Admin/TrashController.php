<?php

declare(strict_types=1);

namespace CometCMS\Controllers\Admin;

use CometCMS\Content\ContentRepository;
use CometCMS\Core\Http;
use CometCMS\Trash\TrashStore;

final class TrashController extends BaseController
{
    private ContentRepository $content;

    public function __construct(Http $http)
    {
        parent::__construct($http);
        $this->content = ContentRepository::make();
    }

    public function index(string $collection): never
    {
        $this->requirePermission('content.read', ['type' => 'content', 'collection' => $collection]);
        $items = (new TrashStore())->allContent($collection);
        $this->json(['data' => array_values(array_map($this->content->normalizeStoredEntry(...), $items))]);
    }

    public function restore(string $collection, string $id): never
    {
        $user = $this->requirePermission('content.restore', ['type' => 'content', 'collection' => $collection, 'id' => $id]);
        $this->verifyCsrf();
        if ($this->content->restore($collection, $id, $user) === null) {
            $this->json(['error' => ['code' => 'conflict', 'message' => 'This item cannot be restored because an active entry already exists.']], 409);
        }
        $this->json(['data' => ['ok' => true]]);
    }

    public function purge(string $collection, string $id): never
    {
        $user = $this->requirePermission('content.delete', ['type' => 'content', 'collection' => $collection, 'id' => $id]);
        $this->verifyCsrf();
        $this->content->purge($collection, $id, $user);
        $this->json(['data' => ['ok' => true]]);
    }

    public function empty(string $collection): never
    {
        $this->requirePermission('content.delete', ['type' => 'content', 'collection' => $collection]);
        $this->verifyCsrf();
        $trash = new TrashStore();
        foreach ($trash->allContent($collection) as $entry) {
            $id = (string) ($entry['id'] ?? '');
            if ($id !== '') {
                $this->content->purge($collection, $id, null);
            }
        }
        $this->json(['data' => ['ok' => true]]);
    }
}
