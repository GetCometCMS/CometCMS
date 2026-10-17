import { computed, inject, provide, ref, shallowRef } from 'vue'
import {
  ancestors,
  clone,
  contains,
  createNode,
  duplicateNode,
  findNode,
  insertNode,
  isInsideTemplate,
  locate,
  removeNode,
  resolveDrop,
  shiftNode,
} from './layoutTree.js'

const KEY = Symbol('site-builder')
const HISTORY_LIMIT = 100

const SYSTEM_FIELD_TYPES = {
  title: 'text',
  published_at: 'datetime',
  created_at: 'datetime',
  updated_at: 'datetime',
}

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
  const selectedId = ref(null)
  const hoverId = ref(null)
  const drag = shallowRef(null)
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

  /** Fields a field node can bind to: its repeater's subfields inside a template, otherwise the type's fields. */
  function bindableFields(node) {
    const owner = node ? templateOwner(node.id) : null
    return owner ? subfieldsOf(owner) : typeFields()
  }

  /** Config of the field a field node is bound to (resolving item templates). */
  function fieldConfig(node) {
    if (!node || node.kind !== 'field' || !node.props?.field) return null
    const owner = templateOwner(node.id)

    if (owner) {
      const ownerConfig = fieldConfig(owner)
      const subfield = (ownerConfig?.subfields ?? []).find((item) => item?.key === node.props.field)
      return subfield ?? null
    }

    if (SYSTEM_FIELD_TYPES[node.props.field]) return { type: SYSTEM_FIELD_TYPES[node.props.field] }
    return type.value?.fields?.[node.props.field] ?? null
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

  /** Insert a new node near the selection (or at the end). */
  function insert(node) {
    const current = selected.value
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

  function make(kind, props = {}, style = {}, children = []) {
    return createNode(spec.value, kind, props, style, children)
  }

  // ─── Labels ────────────────────────────────────────────────────────────

  function kindLabel(kind) {
    return t(`site.kind.${kind}`)
  }

  function nodeSummary(node) {
    const props = node.props ?? {}
    switch (node.kind) {
      case 'field': {
        if (!props.field) return t('site.builder.noField')
        const field = bindableFields(node).find((item) => item.key === props.field)
        return field?.label ?? `${props.field} ⚠`
      }
      case 'heading':
        return props.text
      case 'button':
        return props.label
      case 'collection':
        return types.value.find((item) => item.name === props.type)?.label ?? t('site.builder.noType')
      case 'menu':
        return menus.value.find((item) => item.name === props.menu)?.label ?? props.menu
      case 'image':
        return props.media || t('site.builder.noImage')
      case 'text':
        return String(props.html ?? '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 40)
      case 'column': {
        const span = props.span ?? {}
        return [span.mobile, span.tablet, span.desktop].map((value) => value || '·').join(' / ')
      }
      case 'section':
        return t(`site.option.${props.width ?? 'contained'}`)
      default:
        return ''
    }
  }

  const api = {
    nodes, spec, type, types, menus, selectedId, selected, hoverId, drag,
    isDirty, canUndo, canRedo, savedSnapshot,
    load, markSaved, mutate, undo, redo, select, selectParent,
    bindableFields, subfieldsOf, templateOwner, fieldConfig, isTemplateField, typeFields,
    updateValue, insert, dropAt, remove, duplicate, shift, make,
    kindLabel, nodeSummary,
  }

  provide(KEY, api)
  return api
}

export function useSiteBuilder() {
  return inject(KEY)
}
