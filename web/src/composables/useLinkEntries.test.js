import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { effectScope, ref } from 'vue'
const mocks = vi.hoisted(() => ({ list: vi.fn(), get: vi.fn(), workspace: 'one' }))
vi.mock('../api/index.js', () => ({ api: { content: { list: mocks.list, get: mocks.get } }, getActiveWorkspace: () => mocks.workspace }))
import { useLinkEntries } from './useLinkEntries.js'
const flush = async () => { await Promise.resolve(); await Promise.resolve() }
const deferred = () => { let resolve, reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no }); return { promise, resolve, reject } }
let scopes = []
function setup(options = {}, browse = true) {
  const sources = { collection: ref('blog'), identifier: ref(''), locale: ref('de'), context: ref('first'), ...options }
  const scope = effectScope()
  scopes.push(scope)
  return { sources, state: scope.run(() => useLinkEntries(sources, { browse })) }
}
beforeEach(() => {
  vi.useFakeTimers()
  mocks.workspace = 'one'
  mocks.list.mockReset().mockResolvedValue({ data: [], meta: { total: 0 } })
  mocks.get.mockReset().mockResolvedValue({ data: { uid: 'saved', title: 'Saved' } })
})
afterEach(() => {
  scopes.forEach((scope) => scope.stop())
  scopes = []
  vi.useRealTimers()
  vi.unstubAllGlobals()
})

describe('content destination search', () => {
  it('paginates past 200 entries and sends the website locale', async () => {
    mocks.list.mockImplementation((_collection, { offset, limit }) => Promise.resolve({
      data: Array.from({ length: Math.min(limit, 231 - offset) }, (_, i) => ({ uid: `entry-${offset + i}` })), meta: { total: 231 },
    }))
    const { state } = setup()
    await flush()
    for (let i = 0; i < 4; i++) await state.loadMore()
    expect(state.entries.value).toHaveLength(231)
    expect(mocks.list.mock.lastCall[1]).toMatchObject({ offset: 200, locale: 'de', limit: 50 })
  })

  it('hydrates an existing selection outside the result page independently', async () => {
    mocks.get.mockResolvedValue({ data: { uid: 'saved', title: 'Saved', translations: { de: { title: 'Gespeichert' } } } })
    const { state } = setup({ identifier: ref('saved') })
    await flush()
    expect(state.entries.value).toEqual([])
    expect(state.selectedTitle.value).toBe('Gespeichert')
    expect(mocks.get).toHaveBeenCalledWith('blog', 'saved')
  })

  it('debounces queries and ignores responses from previous searches', async () => {
    const old = deferred()
    mocks.list.mockReturnValueOnce(old.promise)
    const { state } = setup()
    state.query.value = 'n'
    state.query.value = 'new'
    await vi.advanceTimersByTimeAsync(249)
    expect(mocks.list).toHaveBeenCalledTimes(1)
    mocks.list.mockResolvedValue({ data: [{ uid: 'new' }], meta: { total: 1 } })
    await vi.advanceTimersByTimeAsync(1)
    old.resolve({ data: [{ uid: 'old' }], meta: { total: 1 } })
    await flush()
    expect(state.entries.value).toEqual([{ uid: 'new' }])
    expect(state.loading.value).toBe(false)
  })

  it('ignores stale selection success and errors after changing the item or collection', async () => {
    const old = deferred()
    mocks.get.mockReturnValueOnce(old.promise)
    const { sources, state } = setup({ identifier: ref('saved') })
    sources.collection.value = 'pages'
    sources.context.value = 'second'
    await flush()
    old.reject({ status: 403 })
    await flush()
    expect(state.selected.value.uid).toBe('saved')
    expect(state.selectedError.value).toBeNull()
    expect(state.loadingSelected.value).toBe(false)
  })

  it('clears state on workspace change and ignores the previous response', async () => {
    vi.stubGlobal('window', new EventTarget())
    const old = deferred()
    mocks.list.mockReturnValueOnce(old.promise)
    const { state } = setup()
    mocks.workspace = 'two'
    window.dispatchEvent(new Event('cometcms:workspace-changed'))
    await flush()
    old.resolve({ data: [{ uid: 'wrong-workspace' }], meta: { total: 1 } })
    await flush()
    expect(state.entries.value).toEqual([])
    expect(state.loading.value).toBe(false)
  })

  it('offers retry for a failed query and preserves the unavailable selected identifier', async () => {
    mocks.list.mockRejectedValueOnce({ status: 403 })
    mocks.get.mockRejectedValueOnce({ status: 404 })
    const { sources, state } = setup({ identifier: ref('missing') })
    await flush()
    expect(state.error.value.status).toBe(403)
    expect(state.selectedError.value.status).toBe(404)
    expect(sources.identifier.value).toBe('missing')
    await state.retry()
    expect(state.error.value).toBeNull()
  })

  it('limits the homepage-only collection to its one routable entry', async () => {
    mocks.get.mockResolvedValue({ data: { uid: 'home-id', slug: 'home', title: 'Home' } })
    const { state } = setup({ onlyIdentifier: ref('home') })
    await flush()
    expect(state.entries.value).toEqual([{ uid: 'home-id', slug: 'home', title: 'Home' }])
    expect(state.total.value).toBe(1)
    expect(mocks.list).not.toHaveBeenCalled()
  })

  it('does not publish request results after disposal', async () => {
    const old = deferred()
    mocks.list.mockReturnValueOnce(old.promise)
    const { state } = setup()
    scopes[0].stop()
    old.resolve({ data: [{ uid: 'late' }], meta: { total: 1 } })
    await flush()
    expect(state.entries.value).toEqual([])
  })
})
