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

/**
 * The drop target for the gap below `rows[index]` (-1: above the first row)
 * when the pointer asks for nesting level `depth`. A gap between a page and
 * its first visible child only leads into that page; elsewhere the level can
 * range from the page below to the page above, so a page dropped below the
 * last child of a branch can be placed after any of its ancestors.
 * @returns {{ target: object, zone: "before" | "after", depth: number } | null}
 */
export function gapTarget(rows, index, depth) {
  const above = rows[index];
  const below = rows[index + 1];
  if (!above) return below ? { target: below.entry, zone: "before", depth: below.depth } : null;
  if (below && below.depth > above.depth) return { target: below.entry, zone: "before", depth: below.depth };
  const level = Math.max(below?.depth ?? 0, Math.min(above.depth, Math.round(depth)));
  for (let i = index; i >= 0; i--) {
    if (rows[i].depth === level) return { target: rows[i].entry, zone: "after", depth: level };
  }
  return null;
}

/** Whether `placement` would leave `dragged` where it already is. */
export function isCurrentPlace(entries, dragged, placement) {
  const known = new Set(entries.map(entryKey));
  const parent = known.has(entryParent(dragged)) ? entryParent(dragged) : "";
  if (placement.parent !== parent) return false;
  const siblings = childrenByParent(entries).get(parent) ?? [];
  return siblings.findIndex((entry) => entryKey(entry) === entryKey(dragged)) === placement.index;
}

/** Entries with `key` moved to `placement`, sibling positions renumbered as the server does. */
export function applyMove(entries, key, placement) {
  const moved = entries.find((entry) => entryKey(entry) === key);
  if (!moved) return entries;
  const siblings = (childrenByParent(entries).get(placement.parent) ?? []).filter((entry) => entry !== moved);
  siblings.splice(Math.max(0, Math.min(placement.index, siblings.length)), 0, moved);
  const positions = new Map(siblings.map((entry, position) => [entry, position]));
  return entries.map((entry) => entry === moved
    ? { ...entry, parent: placement.parent, position: positions.get(entry) }
    : positions.has(entry) ? { ...entry, position: positions.get(entry) } : entry);
}
