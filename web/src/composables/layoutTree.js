// Pure helpers for website layout trees.
//
// A layout is a list of nodes shaped { id, kind, props, style, children }:
// section > row > column > element. The schema ("spec") comes from the server
// (LayoutSchema::spec()) and decides which kinds may contain which. All
// mutating helpers change the tree in place; the builder snapshots the tree
// for undo/redo.

export const ROOT = 'root'

/** Structure kind that directly wraps a node of the given kind. */
const WRAPPER = { section: null, row: 'section', column: 'row' }

export function newId() {
  const bytes = new Uint8Array(5)
  globalThis.crypto.getRandomValues(bytes)
  return 'n' + Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('')
}

export function clone(value) {
  return JSON.parse(JSON.stringify(value))
}

export function defaultProps(spec, kind) {
  const props = {}
  for (const [key, def] of Object.entries(spec?.kinds?.[kind]?.props ?? {})) {
    props[key] = clone(def.default)
  }
  return props
}

export function createNode(spec, kind, props = {}, style = {}, children = []) {
  return {
    id: newId(),
    kind,
    props: { ...defaultProps(spec, kind), ...clone(props) },
    style: clone(style),
    children,
  }
}

export function isStructure(kind) {
  return kind === 'section' || kind === 'row' || kind === 'column'
}

/** Kinds that may be placed inside a parent of the given kind. */
export function allowedChildren(spec, parentKind) {
  if (parentKind === ROOT) return spec?.root ?? ['section']
  return spec?.kinds?.[parentKind]?.children ?? []
}

/**
 * Whether `parent` (a node, or null for the root) may hold a node of `kind`.
 * Field nodes only hold an item template when `isTemplateField(parent)` says
 * so (repeaters), and item templates do not nest.
 */
export function canContain(spec, parent, kind, { isTemplateField = () => false, insideTemplate = false } = {}) {
  if (parent === null) return allowedChildren(spec, ROOT).includes(kind)
  if (parent.kind === 'field') {
    return !insideTemplate && isTemplateField(parent) && (spec?.item_elements ?? []).includes(kind)
  }
  return allowedChildren(spec, parent.kind).includes(kind)
}

export function walk(nodes, visit, parent = null, depth = 0) {
  for (const [index, node] of nodes.entries()) {
    if (visit(node, parent, index, depth) === false) return false
    if (Array.isArray(node.children) && node.children.length > 0) {
      if (walk(node.children, visit, node, depth + 1) === false) return false
    }
  }
  return true
}

export function findNode(nodes, id) {
  let found = null
  walk(nodes, (node) => {
    if (node.id === id) {
      found = node
      return false
    }
    return true
  })
  return found
}

/** { parent, list, index } of the node with `id`, or null. parent is null at the root. */
export function locate(nodes, id, parent = null) {
  for (const [index, node] of nodes.entries()) {
    if (node.id === id) return { parent, list: nodes, index }
    if (Array.isArray(node.children)) {
      const found = locate(node.children, id, node)
      if (found) return found
    }
  }
  return null
}

/** Ancestors of `id` from the root down (excluding the node itself). */
export function ancestors(nodes, id) {
  const path = []
  const search = (list) => {
    for (const node of list) {
      if (node.id === id) return true
      path.push(node)
      if (Array.isArray(node.children) && search(node.children)) return true
      path.pop()
    }
    return false
  }
  return search(nodes) ? path : []
}

export function isInsideTemplate(nodes, id) {
  return ancestors(nodes, id).some((node) => node.kind === 'field')
}

export function contains(node, id) {
  return findNode(node.children ?? [], id) !== null
}

export function countNodes(nodes) {
  let count = 0
  walk(nodes, () => {
    count += 1
  })
  return count
}

/** Deep copy of a node with fresh ids throughout. */
export function cloneWithNewIds(node) {
  const copy = clone(node)
  walk([copy], (item) => {
    item.id = newId()
  })
  return copy
}

/**
 * Wrap `node` in structure nodes until a parent of `parentKind` accepts it,
 * e.g. a heading dropped on the root becomes section > row > column > heading.
 * Returns null when no wrapping helps (a section cannot go into a column).
 */
export function wrapFor(spec, parentKind, node) {
  let current = node
  for (let i = 0; i < 4; i += 1) {
    if (allowedChildren(spec, parentKind).includes(current.kind)) return current
    const wrapper = isStructure(current.kind) ? WRAPPER[current.kind] : 'column'
    if (!wrapper) return null
    if (parentKind !== ROOT && !isStructure(parentKind)) return null
    // Never wrap upwards past the parent's own level.
    if (structureLevel(wrapper) <= structureLevel(parentKind)) return null
    current = createNode(spec, wrapper, {}, {}, [current])
  }
  return allowedChildren(spec, parentKind).includes(current.kind) ? current : null
}

function structureLevel(kind) {
  return { root: 0, section: 1, row: 2, column: 3 }[kind] ?? 4
}

/**
 * Turn a drop on `targetId` at `position` (before | after | inside) into a
 * concrete { parentId, index, node } for inserting `node`, climbing up to
 * ancestors when the direct target cannot hold it. `targetId` null means the
 * end of the layout.
 */
export function resolveDrop(spec, nodes, node, targetId, position, options = {}) {
  const tryPlace = (parent, index) => {
    const insideTemplate = parent ? parent.kind === 'field' || isInsideTemplate(nodes, parent.id) : false
    if (parent && parent.kind === 'field') {
      return canContain(spec, parent, node.kind, { ...options, insideTemplate: isInsideTemplate(nodes, parent.id) })
        ? { parentId: parent.id, index, node }
        : null
    }
    if (insideTemplate && !(spec?.item_elements ?? []).includes(node.kind)) return null
    const placed = wrapFor(spec, parent ? parent.kind : ROOT, node)
    return placed ? { parentId: parent ? parent.id : null, index, node: placed } : null
  }

  if (targetId === null) {
    return tryPlace(null, nodes.length)
  }

  let currentId = targetId
  let currentPosition = position

  for (let guard = 0; guard < 8 && currentId !== null; guard += 1) {
    const where = locate(nodes, currentId)
    if (!where) return null
    const target = where.list[where.index]

    if (currentPosition === 'inside') {
      const result = tryPlace(target, target.children?.length ?? 0)
      if (result) return result
      currentPosition = 'after'
      continue
    }

    const index = currentPosition === 'before' ? where.index : where.index + 1
    const result = tryPlace(where.parent, index)
    if (result) return result

    // Climb: drop before/after the parent instead.
    currentId = where.parent ? where.parent.id : null
  }

  return currentId === null ? tryPlace(null, currentPosition === 'before' ? 0 : nodes.length) : null
}

export function insertNode(nodes, parentId, index, node) {
  const list = parentId === null ? nodes : findNode(nodes, parentId)?.children
  if (!list) return false
  list.splice(Math.max(0, Math.min(index, list.length)), 0, node)
  return true
}

export function removeNode(nodes, id) {
  const where = locate(nodes, id)
  if (!where) return null
  return where.list.splice(where.index, 1)[0]
}

/** Move `id` to `parentId` at `index` (index in the list before removal). */
export function moveNode(nodes, id, parentId, index) {
  const where = locate(nodes, id)
  if (!where) return false
  const node = where.list[where.index]
  if (parentId !== null && (parentId === id || contains(node, parentId))) return false

  const targetList = parentId === null ? nodes : findNode(nodes, parentId)?.children
  if (!targetList) return false

  let targetIndex = index
  if (targetList === where.list && where.index < index) targetIndex -= 1
  where.list.splice(where.index, 1)
  targetList.splice(Math.max(0, Math.min(targetIndex, targetList.length)), 0, node)
  return true
}

/** Move a node by one position among its siblings. */
export function shiftNode(nodes, id, delta) {
  const where = locate(nodes, id)
  if (!where) return false
  const next = where.index + delta
  if (next < 0 || next >= where.list.length) return false
  const [node] = where.list.splice(where.index, 1)
  where.list.splice(next, 0, node)
  return true
}

export function duplicateNode(nodes, id) {
  const where = locate(nodes, id)
  if (!where) return null
  const copy = cloneWithNewIds(where.list[where.index])
  where.list.splice(where.index + 1, 0, copy)
  return copy
}

/**
 * Responsive spans for column `span` (of 12) in a layout of `count` columns:
 * phones always stack, two columns sit side by side from tablets on, three
 * or more show two per line on tablets and the full layout on desktops.
 */
export function presetSpan(span, count) {
  if (count <= 1 || span >= 12) return { mobile: 12, tablet: 0, desktop: 0 }
  if (count === 2) return { mobile: 12, tablet: span, desktop: 0 }
  return { mobile: 12, tablet: 6, desktop: span }
}

/** Section > row > columns preset with the given column spans (desktop). */
export function sectionPreset(spec, spans) {
  const columns = spans.map((span) => createNode(spec, 'column', { span: presetSpan(span, spans.length) }))
  return createNode(spec, 'section', {}, { padding_y: 'xl' }, [createNode(spec, 'row', {}, {}, columns)])
}

/**
 * Rearrange a row into columns with the given desktop spans. Content is kept:
 * surplus columns hand their elements to the last remaining column.
 */
export function applyColumnPreset(spec, row, spans) {
  const columns = (row.children ?? []).filter((child) => child.kind === 'column')
  while (columns.length < spans.length) columns.push(createNode(spec, 'column'))

  if (columns.length > spans.length) {
    const surplus = columns.splice(spans.length)
    columns[columns.length - 1].children.push(...surplus.flatMap((column) => column.children ?? []))
  }

  columns.forEach((column, index) => {
    column.props = { ...column.props, span: presetSpan(spans[index], spans.length) }
  })
  row.children = columns
  return row
}

/** Put a row's columns underneath each other on every device. */
export function stackColumns(row) {
  for (const column of row.children ?? []) {
    column.props = { ...column.props, span: { mobile: 12, tablet: 0, desktop: 0 } }
  }
  return row
}

/** Desktop spans of a row's columns and whether they are stacked. */
export function columnLayoutOf(row) {
  const spans = (row?.children ?? []).map((column) => effectiveSpan(column.props?.span, 'desktop'))
  return { spans, stacked: spans.length > 1 && spans.every((span) => span === 12) }
}

/** Column spans on a device, applying the mobile > tablet > desktop fallback. */
export function effectiveSpan(span, device) {
  const mobile = span?.mobile || 12
  const tablet = span?.tablet || mobile
  const desktop = span?.desktop || tablet
  return device === 'desktop' ? desktop : device === 'tablet' ? tablet : mobile
}
