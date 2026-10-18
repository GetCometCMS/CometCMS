import { hasInvalidValues, isSmartValue, reference, referenceConfig, smartSummary } from './smartValues.js'
import { normalizeKey, uniqueKey } from './fieldBuilderUtils.js'
import { computed, inject, provide, ref, shallowRef } from 'vue'
import {
  COMPONENT,
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
  instanceNode,
  isInsideTemplate,
  isStructure,
  locate,
  removeNode,
  resolveDrop,
  sectionPreset,
  shiftNode,
  stackColumns,
  templateCollection,
  usesCurrentEntry,
} from './layoutTree.js'

const KEY = Symbol('site-builder')
const HISTORY_LIMIT = 100

/** The content of each element that can become a field of its own: the prop and the type of field it becomes. */
const CONTENT_PROPS = {
  heading: { key: 'text', field: 'text' },
  text: { key: 'html', field: 'html' },
  button: { key: 'label', field: 'text' },
  image: { key: 'media', field: 'media' },
}
/** Field keys every content type has or keeps free. */
const RESERVED_FIELDS = new Set(['title', 'slug', 'body', 'url', 'published_at', 'created_at', 'updated_at'])
/** Longest literal a reference can fall back to (see SmartValue.php). */
const MAX_FALLBACK = 2000

/**
 * Editing state of the website builder: the layout tree, selection,
 * undo/redo history, drag and drop and the field context nodes bind to.
 * The view creates it with createSiteBuilder(); child components read it
 * with useSiteBuilder().
 *
 * `componentApi` connects reusable components: create({ name, nodes, type })
 * stores a new component, load(id) returns one with its nodes, rename(id, name)
 * returns its saved summary, and remove(id) deletes it, detaches saved instances
 * and returns its last definition.
 *
 * `templateApi` connects collection cards: load(type) returns the standard
 * card of a content type ({ nodes }), which a collection can take over.
 *
 * `fieldApi` connects content types: can(type) tells whether the user may
 * change a content type, create({ type, key, config }) adds a field to it
 * and returns the type's saved schema.
 *
 * `linked` ({ get(), set(value) }) is state outside the tree that shares its
 * undo history, such as the site design; report its edits with linkedChanged().
 */
export function createSiteBuilder({ t, componentApi = {}, templateApi = {}, fieldApi = {}, linked = null }) {
  const nodes = ref([])
  const spec = ref(null)
  const type = ref(null)
  const types = ref([])
  const menus = ref([])
  /** Website forms ({ id, name, type, fields }) a form element can show. */
  const forms = ref([])
  const settings = ref(null)
  /** Types with editable website pages; all types remain available for data bindings. */
  const pageTypes = computed(() => types.value.filter((item) => item.visibility !== 'private'
    && (settings.value?.routes?.[item.name]?.enabled || settings.value?.homepage?.type === item.name)))
  const currentAvailable = ref(true)
  /** Reusable components ({ id, name, root, type }) listed in the components panel. */
  const components = ref([])
  /** Editing a component: its id and root kind; its tree holds just that one root node. */
  const componentId = ref(null)
  const rootKind = ref(null)
  /** Node waiting for a name before it becomes a component: { id }. */
  const componentDialog = ref(null)
  /** Element waiting for a name before its content becomes a field: { id }. */
  const fieldDialog = ref(null)
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
  /** JSON of the linked state as the history last saw it. */
  let linkedSnapshot = readLinked()

  const selected = computed(() => (selectedId.value ? findNode(nodes.value, selectedId.value) : null))
  const isDirty = computed(() => JSON.stringify(nodes.value) !== savedSnapshot.value)
  const canUndo = computed(() => past.value.length > 0)
  const canRedo = computed(() => future.value.length > 0)

  function load(tree) {
    nodes.value = clone(tree ?? [])
    savedSnapshot.value = JSON.stringify(nodes.value)
    past.value = []
    future.value = []
    linkedSnapshot = readLinked()
    if (selectedId.value && !findNode(nodes.value, selectedId.value)) selectedId.value = null
  }

  function markSaved(tree) {
    nodes.value = clone(tree)
    savedSnapshot.value = JSON.stringify(nodes.value)
    if (selectedId.value && !findNode(nodes.value, selectedId.value)) selectedId.value = null
  }

  /** Apply a change with an undo step; edits with the same `coalesce` key in quick succession share one. */
  function mutate(change, { coalesce = '' } = {}) {
    record(coalesce, linkedSnapshot)
    return change(nodes.value)
  }

  /**
   * Turn an edit of the linked state into an undo step; edits in quick
   * succession share one. With `record: false` the new state just becomes
   * the baseline, e.g. after it was saved or reloaded.
   */
  function linkedChanged({ record: recordStep = true } = {}) {
    const current = readLinked()
    if (current === linkedSnapshot) return
    const previous = linkedSnapshot
    linkedSnapshot = current
    // The first state that arrives is a load, not an edit.
    if (recordStep && previous !== 'null') record('linked', previous)
  }

  /**
   * Make the current linked state the one every undo and redo step keeps,
   * e.g. after a restored version replaced it: undoing a layout edit must not
   * bring back the state it replaced. Steps that only changed it drop out.
   */
  function rebaseLinked() {
    linkedSnapshot = readLinked()
    const keepChanges = (steps, from) => {
      const kept = []
      let next = from
      for (let index = steps.length - 1; index >= 0; index--) {
        if (steps[index].nodes === next) continue
        next = steps[index].nodes
        kept.unshift({ nodes: next, linked: linkedSnapshot })
      }
      return kept
    }
    const current = JSON.stringify(nodes.value)
    past.value = keepChanges(past.value, current)
    future.value = keepChanges(future.value, current)
    lastCoalesce = ''
  }

  function record(coalesce, linkedState) {
    const now = Date.now()
    if (!coalesce || coalesce !== lastCoalesce || now - lastChange > 1000) {
      past.value.push({ nodes: JSON.stringify(nodes.value), linked: linkedState })
      if (past.value.length > HISTORY_LIMIT) past.value.shift()
      future.value = []
    }
    lastCoalesce = coalesce
    lastChange = now
  }

  function readLinked() {
    return linked ? JSON.stringify(linked.get() ?? null) : 'null'
  }

  function currentStep() {
    return { nodes: JSON.stringify(nodes.value), linked: linkedSnapshot }
  }

  function restore(step) {
    nodes.value = JSON.parse(step.nodes)
    if (linked && step.linked !== 'null' && step.linked !== linkedSnapshot) {
      // Set the snapshot first so the change this causes is not recorded again.
      linkedSnapshot = step.linked
      linked.set(JSON.parse(step.linked))
    }
    lastCoalesce = ''
    keepSelection()
  }

  function undo() {
    if (!past.value.length) return
    future.value.push(currentStep())
    restore(past.value.pop())
  }

  function redo() {
    if (!future.value.length) return
    past.value.push(currentStep())
    restore(future.value.pop())
  }

  function keepSelection() {
    if (selectedId.value && !findNode(nodes.value, selectedId.value)) selectedId.value = null
  }

  function select(id) {
    selectedId.value = id
  }

  // ─── Field context ─────────────────────────────────────────────────────

  /**
   * The entry that "current" means at `id`: inside a collection's card it is
   * the card's entry (of the collection's type), elsewhere the entry the
   * edited layout shows.
   */
  function entryContext(id) {
    const collection = id ? templateCollection(nodes.value, id) : null
    if (collection) return { type: types.value.find((item) => item.name === collection.props?.type) ?? null, currentAvailable: true, collection }
    return { type: type.value, currentAvailable: currentAvailable.value, collection: null }
  }

  function typeFields(id = null) {
    const schema = entryContext(id).type
    const fields = schema?.fields ?? {}
    const slugField = schema?.slug_field ?? 'slug'
    const list = [
      { key: 'title', label: t('site.systemField.title'), type: 'text', config: { type: 'text' }, system: true },
    ]

    if (!schema?.singleton) {
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
      type: entryContext(node.id).type,
      types: types.value,
      itemFields: owner ? subfieldsOf(owner) : [],
    })
  }

  function isTemplateField(node) {
    return node?.kind === 'field' && !isInsideTemplate(nodes.value, node.id) && fieldConfig(node)?.type === 'repeater'
  }

  const dropOptions = { isTemplateField }

  /** Whether drops can go inside a node: structure, item templates and lists without a card yet. */
  function isDropContainer(node) {
    return !!node && (isStructure(node.kind) || isTemplateField(node) || (node.kind === 'collection' && !node.children?.length))
  }

  /** The single root of a component's tree stays where it is. */
  function isLockedRoot(id) {
    return !!rootKind.value && nodes.value[0]?.id === id
  }

  /** Whether a resolved drop may happen: nothing joins a component's root. */
  function placeable(result) {
    return !!result && !(rootKind.value && result.parentId === null)
  }

  // ─── Editing ───────────────────────────────────────────────────────────

  function isDefaultStyle(key, value) {
    const fallback = spec.value?.style?.[key]?.default ?? ''
    return Array.isArray(value) ? value.length === 0 : value === fallback
  }

  function updateValue(id, group, key, value) {
    mutate((tree) => {
      const node = findNode(tree, id)
      if (!node) return
      node[group] = { ...(node[group] ?? {}), [key]: value }
      // Like the server, style keeps only values that differ from their default.
      if (group === 'style' && isDefaultStyle(key, value)) {
        delete node.style[key]
      }
    }, { coalesce: `${id}:${group}:${key}` })
  }

  /** Editor-only name; clearing it restores the automatic label. */
  function renameNode(id, value) {
    const node = findNode(nodes.value, id)
    if (!node || typeof value !== 'string') return
    const name = Array.from(value.replace(/[\r\n]/g, ' ')).slice(0, spec.value?.max_node_name_length ?? 120).join('').trim()
    if ((node.name ?? '') === name) return
    mutate(() => {
      if (name) node.name = name
      else delete node.name
    })
  }

  /** Insert a new node near `targetId` (default: the selection), or at the end. */
  function insert(node, targetId = selectedId.value) {
    const current = targetId ? findNode(nodes.value, targetId) : null
    const inside = isDropContainer(current)
    const result = resolveDrop(spec.value, nodes.value, node, current ? current.id : null, inside ? 'inside' : 'after', dropOptions)
    if (!placeable(result)) return false
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
      if (!placeable(result)) return false
      mutate((tree) => insertNode(tree, result.parentId, result.index, result.node))
      selectedId.value = node.id
      return true
    }

    const node = findNode(nodes.value, current.id)
    if (!node || isLockedRoot(node.id) || targetId === current.id || (targetId && contains(node, targetId))) return false

    const before = JSON.stringify(nodes.value)
    let moved = false
    mutate((tree) => {
      removeNode(tree, current.id)
      const result = resolveDrop(spec.value, tree, node, targetId, position, dropOptions)
      if (placeable(result)) {
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
    if (!id || isLockedRoot(id)) return
    const where = locate(nodes.value, id)
    mutate((tree) => removeNode(tree, id))
    if (selectedId.value === id || !findNode(nodes.value, selectedId.value)) {
      const sibling = where?.list[Math.min(where.index, where.list.length - 1)]
      selectedId.value = sibling?.id ?? where?.parent?.id ?? null
    }
  }

  function duplicate(id = selectedId.value) {
    if (!id || isLockedRoot(id) || isCard(findNode(nodes.value, id))) return
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
    if (node.kind === 'column' || (node.kind === COMPONENT && node.props?.root === 'column')) {
      const parent = locate(nodes.value, node.id)?.parent
      return parent?.kind === 'row' ? parent : null
    }
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
    if (rootKind.value) return
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

  // ─── Components ────────────────────────────────────────────────────────

  function componentOf(node) {
    return node?.kind === COMPONENT ? components.value.find((item) => item.id === node.props?.component) ?? null : null
  }

  function canInsertComponent(component) {
    const context = entryContext(selectedId.value)
    return component.id !== componentId.value
      && (!component.type || (context.currentAvailable && component.type === context.type?.name))
  }

  async function renameComponent(id, name) {
    const updated = await componentApi.rename(id, name)
    components.value = components.value.map((item) => (item.id === id ? { ...item, name: updated.name } : item))
    return updated
  }

  /** Detach deleted instances in the draft, clipboard and every undo/redo state. */
  async function deleteComponent(id) {
    const component = await componentApi.remove(id)
    // Reuse ids across snapshots so deleting alone does not make a saved layout dirty.
    const copies = new Map()
    function detachInstances(tree) {
      return tree.map((node) => {
        if (node.kind === COMPONENT && node.props?.component === id) {
          if (!copies.has(node.id)) copies.set(node.id, cloneWithNewIds(component.nodes[0]))
          const copy = clone(copies.get(node.id))
          copy.id = node.id
          if (node.name) copy.name = node.name
          if (copy.kind === 'column') copy.props.span = clone(node.props.span ?? copy.props.span)
          return copy
        }
        return { ...node, children: detachInstances(node.children ?? []) }
      })
    }
    const detachSnapshot = (snapshot) => JSON.stringify(detachInstances(JSON.parse(snapshot)))
    nodes.value = detachInstances(nodes.value)
    savedSnapshot.value = detachSnapshot(savedSnapshot.value)
    past.value = past.value.map((step) => ({ ...step, nodes: detachSnapshot(step.nodes) }))
    future.value = future.value.map((step) => ({ ...step, nodes: detachSnapshot(step.nodes) }))
    if (clipboard.value) clipboard.value = detachInstances([clipboard.value])[0]
    components.value = components.value.filter((item) => item.id !== id)
    drag.value = null
    keepSelection()
    return component
  }

  /** Whether a structure node can become a component (not a component's own root). */
  function canConvert(node) {
    return !!node && isStructure(node.kind) && !isLockedRoot(node.id) && !!componentApi.create
  }

  /** Swap the node `id` for an instance of `component`, as one undo step. */
  function replaceWithInstance(id, component) {
    let instance = null
    mutate((tree) => {
      const where = locate(tree, id)
      if (!where) return
      const original = where.list[where.index]
      const props = component.root === 'column' ? { span: clone(original.props?.span ?? {}) } : {}
      instance = instanceNode(spec.value, component, props)
      if (original.name) instance.name = original.name
      where.list.splice(where.index, 1, instance)
    })
    if (instance) selectedId.value = instance.id
    return instance
  }

  /**
   * Store the node `id` (with everything in it) as a new component and put an
   * instance in its place. Components showing the page entry's fields belong
   * to the type being edited.
   */
  async function convertToComponent(id, name) {
    const node = findNode(nodes.value, id)
    if (!canConvert(node)) return null
    const source = clone(node)
    const component = await componentApi.create({ name, nodes: [source], type: usesCurrentEntry([source]) ? entryContext(id).type?.name ?? '' : '' })
    const { nodes: _tree, ...summary } = component
    components.value = [...components.value.filter((item) => item.id !== component.id), summary]
      .sort((a, b) => a.name.localeCompare(b.name))
    replaceWithInstance(id, component)
    return component
  }

  // ─── Content ───────────────────────────────────────────────────────────

  /** The literal content of an element, which can become a field. */
  function literalContent(node) {
    const content = CONTENT_PROPS[node?.kind]
    if (!content || isSmartValue(node.props?.[content.key]) || typeof (node.props?.[content.key] ?? '') !== 'string') return null
    return { ...content, value: node.props?.[content.key] ?? '' }
  }

  /** Whether the element's content can move into a new field of the entry it shows. */
  function canMakeField(node) {
    return !!literalContent(node) && !!type.value && currentAvailable.value && !!fieldApi.create
      && (fieldApi.can?.(type.value.name) ?? true) && !isInsideTemplate(nodes.value, node.id) && !entryContext(node.id).collection
  }

  /**
   * Add a field named `label` to the content type and bind the element to it.
   * Entries without a value keep showing the current content, and new
   * entries start with it.
   */
  async function convertToField(id, label) {
    const node = findNode(nodes.value, id)
    if (!canMakeField(node)) return null
    const { key: prop, field, value } = literalContent(node)
    const fields = type.value.fields ?? {}
    const key = uniqueKey(normalizeKey(label) || node.kind, new Set(Object.keys(fields)), RESERVED_FIELDS)
    const config = { type: field, label, required: false }
    if (field !== 'media' && value !== '') config.default = value
    // Text follows the type's languages; an image is usually the same in all of them.
    if (field === 'media') config.localized = false
    const schema = await fieldApi.create({ type: type.value.name, key, config })
    type.value = { ...type.value, fields: schema?.fields ?? { ...fields, [key]: config } }
    types.value = types.value.map((item) => (item.name === type.value.name ? { ...item, fields: type.value.fields } : item))

    const binding = reference(key)
    if (value !== '' && value.length <= MAX_FALLBACK) binding.filters.push({ name: 'fallback', value })
    updateValue(id, 'props', prop, binding)
    return key
  }

  // ─── Collection cards ──────────────────────────────────────────────────

  /** Give the collection `id` a card of its own, starting from its type's standard card. */
  async function customizeCard(id) {
    const node = findNode(nodes.value, id)
    if (node?.kind !== 'collection' || !node.props?.type || node.children?.length || !templateApi.load) return null
    const card = (await templateApi.load(node.props.type))?.nodes?.[0]
    if (!card) return null
    const copy = cloneWithNewIds(card)
    let placed = false
    mutate((tree) => {
      const target = findNode(tree, id)
      if (target?.kind !== 'collection' || target.children?.length) return
      target.children = [copy]
      placed = true
    })
    if (!placed) return null
    selectedId.value = copy.id
    return copy
  }

  /** Go back to the type's standard card. */
  function resetCard(id) {
    mutate((tree) => {
      const node = findNode(tree, id)
      if (node?.kind === 'collection') node.children = []
    })
    selectedId.value = id
    keepSelection()
  }

  function isCard(node) {
    return !!node && locate(nodes.value, node.id)?.parent?.kind === 'collection'
  }

  /** Open a component in its own builder (the view decides how). */
  function editComponent(id) {
    componentApi.edit?.(id)
  }

  /** Replace an instance with an editable copy of its component. */
  async function detach(id) {
    const node = findNode(nodes.value, id)
    if (node?.kind !== COMPONENT || !componentApi.load) return false
    const component = await componentApi.load(node.props.component)
    const root = component?.nodes?.[0]
    if (!root) return false
    const copy = cloneWithNewIds(root)
    if (node.name) copy.name = node.name
    if (copy.kind === 'column') copy.props = { ...copy.props, span: clone(node.props.span ?? copy.props.span) }
    let replaced = false
    mutate((tree) => {
      const where = locate(tree, id)
      if (!where) return
      where.list.splice(where.index, 1, copy)
      replaced = true
    })
    if (replaced) selectedId.value = copy.id
    return replaced
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
    if (node.name) return node.name
    if (node.kind === COMPONENT) return componentOf(node)?.name ?? t('site.component.missing')
    if (isCard(node)) return t('site.kind.card')
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
      case 'accordion':
        return smartSummary(props.title)
      case 'icon':
        return props.icon
      case 'collection':
        return types.value.find((item) => item.name === props.type)?.label ?? t('site.builder.noType')
      case 'menu':
        return menus.value.find((item) => item.name === props.menu)?.label ?? props.menu
      case 'form':
        return forms.value.find((item) => item.id === props.form)?.name ?? t('site.forms.none')
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
      case COMPONENT:
        return t('site.component.instanceOf', { kind: kindLabel(props.root) })
      default:
        return ''
    }
  }

  const api = {
    nodes, spec, type, types, pageTypes, menus, forms, settings, selectedId, selected, hoverId, drag,
    isDirty, canUndo, canRedo, savedSnapshot,
    load, markSaved, mutate, linkedChanged, rebaseLinked, undo, redo, select, selectParent,
    currentAvailable, locale, hasSyntaxErrors,
    subfieldsOf, templateOwner, fieldConfig, isTemplateField, isDropContainer, typeFields, entryContext,
    customizeCard, resetCard, isCard,
    updateValue, renameNode, insert, dropAt, remove, duplicate, shift, make, columnRowFor, setColumns,
    kindLabel, nodeLabel, isStacked, nodeSummary,
    clipboard, styleClipboard, contextMenu, copy, paste, copyStyle, pasteStyle,
    addColumn, addSectionAfter, openContextMenu, closeContextMenu,
    components, componentId, rootKind, componentDialog, isLockedRoot, componentOf, canConvert, canInsertComponent, deleteComponent, renameComponent,
    replaceWithInstance, convertToComponent, detach, editComponent,
    fieldDialog, canMakeField, convertToField,
  }

  provide(KEY, api)
  return api
}

export function useSiteBuilder() {
  return inject(KEY)
}
