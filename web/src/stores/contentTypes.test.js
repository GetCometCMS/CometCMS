import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

const mockApi = vi.hoisted(() => ({
  workspace: 'alpha',
  list: vi.fn(),
}))

vi.mock('../api/index.js', () => ({
  getActiveWorkspace: () => mockApi.workspace,
  api: { contentTypes: { list: mockApi.list } },
}))

import { useContentTypesStore } from './contentTypes.js'

function deferred() {
  let resolve
  const promise = new Promise((done) => { resolve = done })
  return { promise, resolve }
}

describe('content type workspace cache', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    mockApi.workspace = 'alpha'
    mockApi.list.mockReset()
  })

  it('clears old types and ignores a stale response after switching workspaces', async () => {
    const alpha = deferred()
    const beta = deferred()
    mockApi.list.mockReturnValueOnce(alpha.promise).mockReturnValueOnce(beta.promise)

    const store = useContentTypesStore()
    const alphaFetch = store.fetch()

    mockApi.workspace = 'beta'
    store.invalidate({ clear: true })
    expect(store.list).toEqual([])

    const betaFetch = store.fetch()
    beta.resolve({ data: [{ name: 'beta-pages' }] })
    await betaFetch
    expect(store.list).toEqual([{ name: 'beta-pages' }])

    alpha.resolve({ data: [{ name: 'alpha-posts' }] })
    await alphaFetch
    expect(store.list).toEqual([{ name: 'beta-pages' }])
  })
})
