import { hasInvalidValues, referenceConfig, smartSummary } from './smartValues.js'
import { computed, inject, provide, ref, shallowRef } from 'vue'
import {
  ancestors,
  applyColumnPreset,
  clone,
  cloneWithNewIds,
  columnLayoutOf,
  contains,
  createNode,
  duplicateNode,
  effectiveSpan,
  findNode,
  insertNode,
  isInsideTemplate,
  locate,
  removeNode,
  resolveDrop,
  sectionPreset,
  shiftNode,
  stackColumns,
} from './layoutTree.js'

const KEY = Symbol('site-builder')
const HISTORY_LIMIT = 100

/**
 * Editing state of the website builder: the layout tree, selection,
 * undo/redo history, drag and drop and the field context nodes bind to.
 * The view creates it with createSiteBuilder(); child components read it
 * with useSiteBuilder().
 */
export function createSiteBuilder({ t }) {
  const nodes = ref([])
  const spec = ref(null)
  const type = ref(null)
  const types = ref([])
  const menus = ref([])
  const settings = ref(null)
  const currentAvailable = ref(true)
  const locale = ref('')
  const hasSyntaxErrors = computed(() => hasInvalidValues(nodes.value))
  const selectedId = ref(null)
  const hoverId = ref(null)
  const drag = shallowRef(null)
  /** Copied node and copied style, for paste. */
  const clipboard = shallowRef(null)
  const styleClipboard = shallowRef(null)
  /** Open context menu: { id, x, y } in viewport coordinates. */
  const contextMenu = ref(null)
  const savedSnapshot = ref('[]')
  const past = ref([])
  const future = ref([])
  let lastCoalesce = ''
  let lastChange = 0

  const selected = computed(() => (selectedId.value ? findNode(nodes.value, selectedId.value) : null))
  const isDirty = computed(() => JSON.stringify(nodes.value) !== savedSnapshot.value)
  const canUndo = computed(() => past.value.length > 0)
  const canRedo = computed(() => future.value.length > 0)

  function load(tree) {
    nodes.value = clone(tree ?? [])
    savedSnapshot.value = JSON.stringify(nodes.value)
    past.value = []
    future.value = []
    if (selectedId.value && !findNode(nodes.value, selectedId.value)) selectedId.value = null
  }

  function markSaved(tree) {
    nodes.value = clone(tree)
    savedSnapshot.value = JSON.stringify(nodes.value)
    if (selectedId.value && !findNode(nodes.value, selectedId.value)) selectedId.value = null
  }

  /** Apply a change with an undo step; edits with the same `coalesce` key in quick succession share one. */
  function mutate(change, { coalesce = '' } = {}) {
    const now = Date.now()
    if (!coalesce || coalesce !== lastCoalesce || now - lastChange > 1000) {
      past.value.push(JSON.stringify(nodes.value))
      if (past.value.length > HISTORY_LIMIT) past.value.shift()
      future.value = []
    }
    lastCoalesce = coalesce
    lastChange = now
    return change(nodes.value)
  }

  function undo() {
    if (!past.value.length) return
    future.value.push(JSON.stringify(nodes.value))
    nodes.value = JSON.parse(past.value.pop())
    lastCoalesce = ''
    keepSelection()
  }

  function redo() {
    if (!future.value.length) return
    past.value.push(JSON.stringify(nodes.value))
    nodes.value = JSON.parse(future.value.pop())
    lastCoalesce = ''
    keepSelection()
  }

  function keepSelection() {
    if (selectedId.value && !findNode(nodes.value, selectedId.value)) selectedId.value = null
  }

  function select(id) {
    selectedId.value = id
  }

  // ─── Field context ─────────────────────────────────────────────────────

  function typeFields() {
    const fields = type.value?.fields ?? {}
    const slugField = type.value?.slug_field ?? 'slug'
    const list = [
      { key: 'title', label: t('site.systemField.title'), type: 'text', config: { type: 'text' }, system: true },
    ]

    if (!type.value?.singleton) {
      list.push({ key: 'published_at', label: t('site.systemField.published_at'), type: 'datetime', config: { type: 'datetime' }, system: true })
    }

    list.push({ key: 'updated_at', label: t('site.systemField.updated_at'), type: 'datetime', config: { type: 'datetime' }, system: true })

    for (const [key, config] of Object.entries(fields)) {
      if (key === 'title' || key === 'slug' || key === slugField || !config) continue
      list.push({ key, label: config.label || key, type: config.type ?? 'text', config })
    }

    return list
  }

  /** The repeater field node whose item template contains `id`, if any. */
  function templateOwner(id) {
    const chain = ancestors(nodes.value, id)
    for (let index = chain.length - 1; index >= 0; index -= 1) {
      if (chain[index].kind === 'field') return chain[index]
    }
    return null
  }

  /** Subfields of a repeater field node, offered inside its item template. */
  function subfieldsOf(repeaterNode) {
    return (fieldConfig(repeaterNode)?.subfields ?? [])
      .filter((subfield) => subfield?.key)
      .map((subfield) => ({ key: subfield.key, label: subfield.label || subfield.key, type: subfield.type ?? 'text', config: subfield }))
  }

  /** Config of the field a field node is bound to (resolving item templates). */
  function fieldConfig(node) {
    if (!node || node.kind !== 'field') return null
    const owner = templateOwner(node.id)
    return referenceConfig(node.props?.source, {
      type: type.value,
      types: types.value,
      itemFields: owner ? subfieldsOf(owner) : [],
    })
  }

  function isTemplateField(node) {
    return node?.kind === 'field' && !isInsideTemplate(nodes.value, node.id) && fieldConfig(node)?.type === 'repeater'
  }

  const dropOptions = { isTemplateField }

  // ─── Editing ───────────────────────────────────────────────────────────

  function updateValue(id, group, key, value) {
    mutate((tree) => {
      const node = findNode(tree, id)
      if (!node) return
      node[group] = { ...(node[group] ?? {}), [key]: value }
      if (group === 'style' && (value === '' || (Array.isArray(value) && value.length === 0))) {
        delete node.style[key]
      }
    }, { coalesce: `${id}:${group}:${key}` })
  }

  /** Insert a new node near `targetId` (default: the selection), or at the end. */
  function insert(node, targetId = selectedId.value) {
    const current = targetId ? findNode(nodes.value, targetId) : null
    const inside = current && (['section', 'row', 'column'].includes(current.kind) || isTemplateField(current))
    const result = resolveDrop(spec.value, nodes.value, node, current ? current.id : null, inside ? 'inside' : 'after', dropOptions)
    if (!result) return false
    mutate((tree) => insertNode(tree, result.parentId, result.index, result.node))
    selectedId.value = node.id
    return true
  }

  /** Complete a drag: insert a palette node or move an existing one. */
  function dropAt(targetId, position) {
    const current = drag.value
    drag.value = null
    if (!current) return false

    if (current.source === 'palette') {
      const node = current.create()
      const result = resolveDrop(spec.value, nodes.value, node, targetId, position, dropOptions)
      if (!result) return false
      mutate((tree) => insertNode(tree, result.parentId, result.index, result.node))
      selectedId.value = node.id
      return true
    }

    const node = findNode(nodes.value, current.id)
    if (!node || targetId === current.id || (targetId && contains(node, targetId))) return false

    const before = JSON.stringify(nodes.value)
    let moved = false
    mutate((tree) => {
      removeNode(tree, current.id)
      const result = resolveDrop(spec.value, tree, node, targetId, position, dropOptions)
      if (result) {
        insertNode(tree, result.parentId, result.index, result.node)
        moved = true
      }
    })

    if (!moved) {
      nodes.value = JSON.parse(before)
      past.value.pop()
    }

    selectedId.value = current.id
    return moved
  }

  function remove(id = selectedId.value) {
    if (!id) return
    const where = locate(nodes.value, id)
    mutate((tree) => removeNode(tree, id))
    if (selectedId.value === id || !findNode(nodes.value, selectedId.value)) {
      const sibling = where?.list[Math.min(where.index, where.list.length - 1)]
      selectedId.value = sibling?.id ?? where?.parent?.id ?? null
    }
  }

  function duplicate(id = selectedId.value) {
    if (!id) return
    let copy = null
    mutate((tree) => {
      copy = duplicateNode(tree, id)
    })
    if (copy) selectedId.value = copy.id
  }

  function shift(id, delta) {
    mutate((tree) => shiftNode(tree, id, delta))
  }

  function selectParent() {
    const where = selectedId.value ? locate(nodes.value, selectedId.value) : null
    if (where?.parent) selectedId.value = where.parent.id
  }

  /** The column row a section, row or column belongs to (a section's first row). */
  function columnRowFor(node) {
    if (!node) return null
    if (node.kind === 'row') return node
    if (node.kind === 'section') return node.children.find((child) => child.kind === 'row') ?? null
    if (node.kind === 'column') return locate(nodes.value, node.id)?.parent ?? null
    return null
  }

  /** Rearrange the columns around `id` into `spans`, or stack them with 'stacked'. */
  function setColumns(id, layout) {
    mutate((tree) => {
      const node = findNode(tree, id)
      if (!node) return
      let row = columnRowFor(node)
      if (!row && node.kind === 'section') {
        row = createNode(spec.value, 'row')
        node.children.push(row)
      }
      if (!row) return
      if (layout === 'stacked') stackColumns(row)
      else applyColumnPreset(spec.value, row, layout)
    })
    keepSelection()
  }

  /** Add one more column to the set around `id`, keeping the columns equal. */
  function addColumn(id) {
    const row = columnRowFor(findNode(nodes.value, id))
    const count = (row?.children.length ?? 0) + 1
    setColumns(id, Array.from({ length: count }, () => Math.max(1, Math.floor(12 / count))))
  }

  /** Insert an empty one-column section after the section that contains `id`. */
  function addSectionAfter(id) {
    const section = id ? [...ancestors(nodes.value, id), findNode(nodes.value, id)].find((node) => node?.kind === 'section') : null
    const node = sectionPreset(spec.value, [12])
    const index = section ? locate(nodes.value, section.id).index + 1 : nodes.value.length
    mutate((tree) => insertNode(tree, null, index, node))
    selectedId.value = node.id
  }

  function copy(id = selectedId.value) {
    const node = id ? findNode(nodes.value, id) : null
    if (node) clipboard.value = clone(node)
  }

  /** Paste the copied node after `id` (or inside it when it is a container). */
  function paste(id = selectedId.value) {
    if (!clipboard.value) return false
    return insert(cloneWithNewIds(clipboard.value), id)
  }

  function copyStyle(id = selectedId.value) {
    const node = id ? findNode(nodes.value, id) : null
    if (node) styleClipboard.value = clone(node.style ?? {})
  }

  /** Apply the copied style, keeping only what the target kind supports. */
  function pasteStyle(id = selectedId.value) {
    if (!styleClipboard.value || !id) return
    mutate((tree) => {
      const node = findNode(tree, id)
      if (!node) return
      const allowed = spec.value?.kinds?.[node.kind]?.style ?? []
      node.style = Object.fromEntries(Object.entries(styleClipboard.value).filter(([key]) => allowed.includes(key)))
    })
  }

  function openContextMenu(id, x, y) {
    if (id) selectedId.value = id
    contextMenu.value = { id: id ?? selectedId.value, x, y }
  }

  function closeContextMenu() {
    contextMenu.value = null
  }

  function make(kind, props = {}, style = {}, children = []) {
    return createNode(spec.value, kind, props, style, children)
  }

  // ─── Labels ────────────────────────────────────────────────────────────

  function kindLabel(kind) {
    return t(`site.kind.${kind}`)
  }

  /**
   * Whether a column set (or a column inside one) is arranged underneath
   * each other on desktops — then it reads as rows rather than columns.
   */
  function isStacked(node) {
    if (!node) return false
    if (node.kind === 'row') return columnLayoutOf(node).stacked
    if (node.kind === 'column') {
      const parent = locate(nodes.value, node.id)?.parent
      return parent?.kind === 'row' && columnLayoutOf(parent).stacked
    }
    return false
  }

  /** Name of a node as the user sees it: column sets follow their arrangement. */
  function nodeLabel(node) {
    if (!node) return ''
    if (isStacked(node)) return t(`site.kind.${node.kind}Stacked`)
    return kindLabel(node.kind)
  }

  function nodeSummary(node) {
    const props = node.props ?? {}
    switch (node.kind) {
      case 'field': {
        return smartSummary(props.source) || t('site.builder.noField')
      }
      case 'heading':
        return smartSummary(props.text)
      case 'button':
        return smartSummary(props.label)
      case 'collection':
        return types.value.find((item) => item.name === props.type)?.label ?? t('site.builder.noType')
      case 'menu':
        return menus.value.find((item) => item.name === props.menu)?.label ?? props.menu
      case 'image':
        return smartSummary(props.media) || t('site.builder.noImage')
      case 'text':
        return smartSummary(props.html)
      case 'column': {
        const span = effectiveSpan(props.span, 'desktop')
        return span === 12 ? t('site.prop.full_width') : `${span}/12`
      }
      case 'row': {
        const layout = columnLayoutOf(node)
        return layout.stacked ? t('site.builder.stacked') : layout.spans.join(' + ')
      }
      case 'section':
        return t(`site.option.${props.width ?? 'contained'}`)
      default:
        return ''
    }
  }

  const api = {
    nodes, spec, type, types, menus, settings, selectedId, selected, hoverId, drag,
    isDirty, canUndo, canRedo, savedSnapshot,
    load, markSaved, mutate, undo, redo, select, selectParent,
    currentAvailable, locale, hasSyntaxErrors,
    subfieldsOf, templateOwner, fieldConfig, isTemplateField, typeFields,
    updateValue, insert, dropAt, remove, duplicate, shift, make, columnRowFor, setColumns,
    kindLabel, nodeLabel, isStacked, nodeSummary,
    clipboard, styleClipboard, contextMenu, copy, paste, copyStyle, pasteStyle,
    addColumn, addSectionAfter, openContextMenu, closeContextMenu,
  }

  provide(KEY, api)
  return api
}

export function useSiteBuilder() {
  return inject(KEY)
}
