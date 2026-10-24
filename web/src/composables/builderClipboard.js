import { computed, onScopeDispose, shallowRef } from 'vue'
import { getActiveWorkspace } from '../api/index.js'

const STORAGE_KEY = 'cometcms-builder-clipboard-v1'

const isObject = (value) => !!value && typeof value === 'object' && !Array.isArray(value)
const isNode = (value) => isObject(value) && typeof value.kind === 'string' && typeof value.id === 'string' && Array.isArray(value.children ?? [])

/**
 * The builder's copied element and copied style, kept in the browser for each
 * workspace, so they survive a reload and reach every builder tab. Without
 * storage (blocked, full, or on the server) they only last as long as the page.
 */
export function useBuilderClipboard({ storage, workspace = getActiveWorkspace } = {}) {
  if (storage === undefined) {
    try { storage = globalThis.localStorage ?? null } catch { storage = null }
  }
  const key = () => `${STORAGE_KEY}:${workspace() || 'default'}`
  const copied = shallowRef({ node: null, style: null })

  function read() {
    let saved = null
    try { saved = JSON.parse(storage?.getItem(key()) ?? 'null') } catch { /* A broken entry counts as empty. */ }
    copied.value = { node: isNode(saved?.node) ? saved.node : null, style: isObject(saved?.style) ? saved.style : null }
  }

  function write(change) {
    // Preserve the other value when updating a saved copy (for example, detaching a component).
    refresh()
    copied.value = { ...copied.value, ...change }
    if (!storage) return
    try {
      if (copied.value.node || copied.value.style) storage.setItem(key(), JSON.stringify(copied.value))
      else storage.removeItem(key())
    } catch { /* The copy still works in this page. */ }
  }

  // Assigning either one saves it.
  const node = computed({ get: () => copied.value.node, set: (value) => write({ node: value }) })
  const style = computed({ get: () => copied.value.style, set: (value) => write({ style: value }) })

  /** Pick up what another tab or builder copied since. */
  function refresh() {
    if (storage) read()
  }

  read()

  // Other tabs hear about a copy right away, so their menus and shortcuts can offer it.
  const onStorage = (event) => { if (event.key === key()) read() }
  globalThis.addEventListener?.('storage', onStorage)
  onScopeDispose(() => globalThis.removeEventListener?.('storage', onStorage))

  /** Save the node and its style together, so both paste actions use the same copy. */
  function copy(value) {
    write({ node: value, style: value.style ?? {} })
  }

  return { node, style, copy, refresh }
}
