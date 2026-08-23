import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api, getActiveWorkspace } from '../api/index.js'

/**
 * Caches the content-type list so the sidebar never re-fetches from scratch
 * on navigation. Pattern: show stale data immediately, refresh in background.
 */
export const useContentTypesStore = defineStore('contentTypes', () => {
  const list    = ref([])
  let fetched   = false
  let inFlight  = null
  let workspace = ''
  let requestGeneration = 0

  function syncWorkspace() {
    const activeWorkspace = getActiveWorkspace()

    if (workspace === activeWorkspace) return activeWorkspace

    workspace = activeWorkspace
    list.value = []
    fetched = false
    inFlight = null
    requestGeneration += 1

    return activeWorkspace
  }

  async function _doFetch() {
    const requestedWorkspace = syncWorkspace()
    if (inFlight) return inFlight

    const generation = ++requestGeneration
    const request = api.contentTypes.list()
      .then(res => {
        if (generation !== requestGeneration || requestedWorkspace !== getActiveWorkspace()) return
        list.value = res.data
        fetched = true
      })
      .catch(() => {})
      .finally(() => {
        if (inFlight === request) inFlight = null
      })
    inFlight = request
    return request
  }

  /**
   * Call from onMounted. If we already have data, kicks off a background
   * refresh and returns immediately (no await needed). On first call, waits
   * for the data so the sidebar has types before it renders.
   */
  async function fetch() {
    syncWorkspace()
    if (fetched) {
      _doFetch()   // background refresh – don't await
      return
    }
    await _doFetch()
  }

  /** Call after creating or deleting a content type. */
  function invalidate({ clear = false } = {}) {
    fetched = false
    requestGeneration += 1
    inFlight = null
    workspace = getActiveWorkspace()
    if (clear) list.value = []
  }

  function setList(types) {
    workspace = getActiveWorkspace()
    list.value = Array.isArray(types) ? types : []
    fetched = true
  }

  return { list, fetch, invalidate, setList }
})
