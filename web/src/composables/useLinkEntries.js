import { computed, onScopeDispose, ref, toValue, watch } from 'vue'
import { api, getActiveWorkspace } from '../api/index.js'
import { entryIdentifier, entryTitle } from './siteLinks.js'

/** Scoped requests: responses from an old selection, query or workspace are ignored. */
export function useLinkEntries(sources, { browse = true } = {}) {
  const entries = ref([])
  const selected = ref(null)
  const total = ref(0)
  const query = ref('')
  const loading = ref(false)
  const loadingSelected = ref(false)
  const error = ref(null)
  const selectedError = ref(null)
  const workspace = ref(getActiveWorkspace())
  let listVersion = 0
  let selectionVersion = 0
  let timer
  let disposed = false
  let resetting = false
  const value = (key) => toValue(sources[key]) ?? ''
  const scope = () => [value('collection'), value('locale'), value('context'), value('onlyIdentifier'), workspace.value]
  const sameWorkspace = () => workspace.value === getActiveWorkspace()
  const selectedTitle = computed(() => entryTitle(selected.value, value('locale')))

  async function loadMore(reset = false) {
    if (!browse || !value('collection') || (!reset && loading.value)) return
    const version = ++listVersion
    const collection = value('collection')
    const locale = value('locale')
    const offset = reset ? 0 : entries.value.length
    if (reset) entries.value = []
    loading.value = true
    error.value = null
    try {
      let res
      if (value('onlyIdentifier')) {
        const result = await api.content.get(collection, value('onlyIdentifier'))
        const needle = query.value.trim().toLowerCase()
        const data = result.data && (!needle || `${entryTitle(result.data, locale)} ${result.data.slug}`.toLowerCase().includes(needle)) ? [result.data] : []
        res = { data, meta: { total: data.length } }
      } else {
        res = await api.content.list(collection, { q: query.value.trim(), limit: 50, offset, sort: 'title', ...(locale ? { locale } : {}) })
      }
      if (disposed || version !== listVersion || !sameWorkspace()) return
      entries.value = reset ? (res.data ?? []) : [...entries.value, ...(res.data ?? [])]
      total.value = res.meta?.total ?? entries.value.length
    } catch (err) {
      if (!disposed && version === listVersion && sameWorkspace()) error.value = err
    } finally {
      if (!disposed && version === listVersion && sameWorkspace()) loading.value = false
    }
  }

  async function hydrate() {
    const version = ++selectionVersion
    selected.value = null
    selectedError.value = null
    loadingSelected.value = false
    const collection = value('collection')
    const identifier = value('identifier')
    if (!collection || !identifier) return
    loadingSelected.value = true
    try {
      const res = await api.content.get(collection, identifier)
      if (disposed || version !== selectionVersion || !sameWorkspace()) return
      selected.value = res.data ?? null
      if (!selected.value) selectedError.value = { status: 404 }
    } catch (err) {
      if (!disposed && version === selectionVersion && sameWorkspace()) selectedError.value = err
    } finally {
      if (!disposed && version === selectionVersion && sameWorkspace()) loadingSelected.value = false
    }
  }

  const stopScope = watch(scope, () => {
    clearTimeout(timer)
    ++listVersion
    entries.value = []
    total.value = 0
    resetting = true
    query.value = ''
    resetting = false
    loading.value = false
    error.value = null
    if (browse) loadMore(true)
  }, { immediate: true, flush: 'sync' })
  const stopSelection = watch(() => [...scope(), value('identifier')], hydrate, { immediate: true, flush: 'sync' })
  const stopQuery = watch(query, () => {
    if (resetting || !browse) return
    ++listVersion
    entries.value = []
    total.value = 0
    loading.value = !!value('collection')
    error.value = null
    clearTimeout(timer)
    timer = setTimeout(() => loadMore(true), 250)
  }, { flush: 'sync' })
  const changeWorkspace = () => { workspace.value = getActiveWorkspace() }
  if (typeof window !== 'undefined') window.addEventListener('cometcms:workspace-changed', changeWorkspace)
  onScopeDispose(() => {
    disposed = true
    ++listVersion
    ++selectionVersion
    clearTimeout(timer)
    stopScope()
    stopSelection()
    stopQuery()
    if (typeof window !== 'undefined') window.removeEventListener('cometcms:workspace-changed', changeWorkspace)
  })

  return {
    entries, selected, selectedTitle, total, query, loading, loadingSelected, error, selectedError,
    loadMore: () => loadMore(false),
    retry: () => loadMore(entries.value.length === 0),
    retrySelected: hydrate,
    identifierFor: entryIdentifier,
  }
}
