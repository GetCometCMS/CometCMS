/**
 * Parent/child structure of entries of hierarchical content types. Entries
 * store their parent's uid in `parent` ('' for top-level pages) and their
 * order among siblings in `position`.
 */

export const entryKey = (entry) => entry?.uid || entry?.id || "";

/** Parent uid of an entry, '' for top-level pages. */
export function entryParent(entry) {
  return entry?.parent || "";
}

function bySavedOrder(a, b) {
  return (a.position ?? 0) - (b.position ?? 0)
    || String(a.created_at ?? "").localeCompare(String(b.created_at ?? ""));
}

/** Children lists by parent key; pages whose parent is not listed count as top-level. */
function childrenByParent(entries) {
  const known = new Set(entries.map(entryKey));
  const children = new Map();
  for (const entry of entries) {
    const parent = known.has(entryParent(entry)) ? entryParent(entry) : "";
    if (!children.has(parent)) children.set(parent, []);
    children.get(parent).push(entry);
  }
  for (const list of children.values()) list.sort(bySavedOrder);
  return children;
}

/**
 * Entries in tree order: every page followed by its children. Children of
 * keys in `collapsed` are left out.
 * @returns {{ entry: object, depth: number, hasChildren: boolean, parent: string, path: string }[]}
 */
export function treeRows(entries, collapsed = new Set()) {
  const children = childrenByParent(entries);
  const rows = [];
  const seen = new Set();
  const visit = (parent, depth, parentPath) => {
    for (const entry of children.get(parent) ?? []) {
      const key = entryKey(entry);
      if (seen.has(key)) continue;
      seen.add(key);
      const path = parentPath ? `${parentPath}/${entry.slug ?? ""}` : entry.slug ?? "";
      rows.push({ entry, depth, hasChildren: (children.get(key) ?? []).length > 0, parent, path });
      if (!collapsed.has(key)) visit(key, depth + 1, path);
    }
  };
  visit("", 0, "");
  return rows;
}

/** Keys of an entry and everything below it. */
export function subtreeKeys(entries, key) {
  const children = childrenByParent(entries);
  const keys = new Set();
  const visit = (current) => {
    if (keys.has(current)) return;
    keys.add(current);
    for (const child of children.get(current) ?? []) visit(entryKey(child));
  };
  visit(key);
  return keys;
}

/**
 * Where dropping `dragged` on `target` puts it: `zone` is "before", "after"
 * or "inside". Returns { parent, index } for the move request, or null when
 * the drop would place a page below itself.
 */
export function dropPlacement(entries, dragged, target, zone) {
  const draggedKey = entryKey(dragged);
  if (subtreeKeys(entries, draggedKey).has(entryKey(target))) return null;
  const children = childrenByParent(entries);
  const known = new Set(entries.map(entryKey));
  const parent = zone === "inside"
    ? entryKey(target)
    : known.has(entryParent(target)) ? entryParent(target) : "";
  const siblings = (children.get(parent) ?? []).filter((entry) => entryKey(entry) !== draggedKey);
  if (zone === "inside") return { parent, index: siblings.length };
  const index = siblings.findIndex((entry) => entryKey(entry) === entryKey(target));
  return { parent, index: zone === "after" ? index + 1 : index };
}
