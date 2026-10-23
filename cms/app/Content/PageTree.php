<?php

declare(strict_types=1);

namespace CometCMS\Content;

/**
 * Parent/child structure of hierarchical content types. Entries store their
 * parent's uid ('' for top-level pages); URLs join the slugs from the root.
 * A parent that no longer exists makes its children top-level pages.
 */
final class PageTree
{
    /** Levels a tree may have, including top-level pages. */
    public const MAX_DEPTH = 8;

    public static function parent(array $entry): string
    {
        return trim((string) ($entry['parent'] ?? ''));
    }

    /** @return array<string, array> entries by uid */
    public static function byUid(array $entries): array
    {
        $byUid = [];
        foreach ($entries as $entry) {
            $uid = trim((string) ($entry['uid'] ?? ''));
            if ($uid !== '') $byUid[$uid] = $entry;
        }
        return $byUid;
    }

    /** @return list<string> ancestor uids, nearest first; cycles and missing parents end the chain */
    public static function ancestors(array $byUid, array $entry): array
    {
        $chain = [];
        $seen = [(string) ($entry['uid'] ?? '') => true];
        $parent = self::parent($entry);
        while ($parent !== '' && isset($byUid[$parent]) && !isset($seen[$parent]) && count($chain) < self::MAX_DEPTH) {
            $chain[] = $parent;
            $seen[$parent] = true;
            $parent = self::parent($byUid[$parent]);
        }
        return $chain;
    }

    /** Levels below $uid: 0 for a page without children. */
    public static function height(array $byUid, string $uid): int
    {
        $children = [];
        foreach ($byUid as $childUid => $entry) $children[self::parent($entry)][] = (string) $childUid;
        $height = static function (string $uid, int $depth) use (&$height, $children): int {
            if ($depth > self::MAX_DEPTH) return $depth;
            $max = 0;
            foreach ($children[$uid] ?? [] as $child) $max = max($max, 1 + $height($child, $depth + 1));
            return $max;
        };
        return $height($uid, 0);
    }

    /** Slash-joined slugs from the top-level page down to $entry; $slug reads one entry's slug. */
    public static function path(array $byUid, array $entry, callable $slug): string
    {
        $segments = [(string) $slug($entry)];
        foreach (self::ancestors($byUid, $entry) as $ancestor) array_unshift($segments, (string) $slug($byUid[$ancestor]));
        return implode('/', $segments);
    }

    /** A path for a URL pattern, each segment encoded. */
    public static function encode(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
