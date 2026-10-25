import { describe, expect, it, vi } from 'vitest'
import { createBuilderRecovery } from './builderRecovery.js'

function storageFixture() {
  const items = new Map()
  return {
    getItem: (key) => items.get(key) ?? null,
    setItem: (key, value) => items.set(key, value),
    removeItem: (key) => items.delete(key),
    items,
  }
}

const context = { target: 'layout', name: 'posts', variant: 'detail', template: 'default' }

describe('builder save recovery', () => {
  it('recovers failed saves after reload and keeps the stale revision for conflict detection', () => {
    const storage = storageFixture()
    const options = { workspace: 'studio', user: 'editor', storage }
    const draft = {
      layout: { nodes: [{ id: 'edited' }], baseline: [{ id: 'original' }], revision: 'original-revision' },
      theme: { custom_css: '.edited {}' },
      templates: { draft: { posts: {} }, revision: 'original-templates' },
      entries: { 'posts:one': { drafts: { de: { values: { title: 'Entwurf' } } } } },
      layoutConflict: true,
    }
    expect(createBuilderRecovery(options).write(context, draft)).toBe(true)
    const afterReload = createBuilderRecovery(options)
    expect(afterReload.read(context)).toEqual(draft)
    // An edit after the failed save replaces the recovery copy.
    draft.layout.nodes[0].id = 'newer-edit'
    afterReload.write(context, draft)
    expect(createBuilderRecovery(options).read(context).layout.nodes[0].id).toBe('newer-edit')
    afterReload.remove(context)
    expect(createBuilderRecovery(options).read(context)).toBeNull()
  })

  it('isolates users, workspaces, templates, regions, components and entry content areas', () => {
    const storage = storageFixture()
    const options = { workspace: 'studio', user: 'editor', storage }
    const recovery = createBuilderRecovery(options)
    recovery.write(context, { layout: { nodes: ['draft'] } })
    for (const change of [
      { template: 'landing' }, { name: 'pages' }, { variant: 'index' },
      { target: 'region' }, { target: 'component' },
      { target: 'page', pageEntry: 'first', field: 'body' },
      { target: 'page', pageEntry: 'second', field: 'sidebar' },
    ]) expect(recovery.read({ ...context, ...change })).toBeNull()
    expect(createBuilderRecovery({ ...options, workspace: 'other' }).read(context)).toBeNull()
    expect(createBuilderRecovery({ ...options, user: 'other' }).read(context)).toBeNull()
    const page = { target: 'page', name: 'posts', pageEntry: 'first', field: 'body' }
    recovery.write(page, { layout: { nodes: ['page draft'] } })
    expect(recovery.read({ ...page, field: 'sidebar' })).toBeNull()
    expect(recovery.read({ ...page, pageEntry: 'second' })).toBeNull()
  })

  it('reports unavailable or full storage without claiming that the draft is safe', () => {
    const blocked = vi.fn(() => { throw new Error('Storage unavailable') })
    for (const storage of [null, { getItem: blocked, setItem: blocked, removeItem: blocked }]) {
      const recovery = createBuilderRecovery({ storage })
      expect(recovery.write(context, { layout: {} })).toBe(false)
      expect(recovery.read(context)).toBeNull()
      expect(() => recovery.remove(context)).not.toThrow()
    }
  })

  it('ignores malformed and incompatible recovery copies', () => {
    const storage = storageFixture()
    const recovery = createBuilderRecovery({ storage })
    recovery.write(context, { layout: {} })
    const key = [...storage.items.keys()][0]
    for (const value of ['invalid JSON', 'null', '{}', '{"version":2,"draft":{}}']) {
      storage.setItem(key, value)
      expect(recovery.read(context)).toBeNull()
    }
  })
})
