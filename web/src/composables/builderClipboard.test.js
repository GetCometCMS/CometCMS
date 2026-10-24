import { effectScope } from 'vue'
import { describe, expect, it } from 'vitest'
import { useBuilderClipboard } from './builderClipboard.js'

function memoryStorage() {
  const items = new Map()
  return {
    items,
    getItem: (key) => items.get(key) ?? null,
    setItem: (key, value) => items.set(key, String(value)),
    removeItem: (key) => items.delete(key),
  }
}

function clipboardFor(storage, workspace = 'one') {
  return effectScope().run(() => useBuilderClipboard({ storage, workspace: () => workspace }))
}

const heading = { id: 'a', kind: 'heading', props: { text: 'Hi' }, style: {}, children: [] }

describe('builder clipboard', () => {
  it('keeps the copy per workspace across reloads and other builders', () => {
    const storage = memoryStorage()
    const first = clipboardFor(storage)
    const other = clipboardFor(storage)
    const copiedHeading = { ...heading, style: { background: 'accent' } }
    first.copy(copiedHeading)

    expect(clipboardFor(storage).node.value).toEqual(copiedHeading)
    expect(clipboardFor(storage).style.value).toEqual({ background: 'accent' })
    expect(clipboardFor(storage, 'two').node.value).toBeNull()

    expect(other.node.value).toBeNull()
    other.refresh()
    expect(other.node.value).toEqual(copiedHeading)

    first.node.value = null
    first.style.value = null
    expect(storage.items.size).toBe(0)
  })

  it('ignores broken entries and still copies when storage fails', () => {
    const storage = memoryStorage()
    storage.setItem('cometcms-builder-clipboard-v1:one', '{broken')
    expect(clipboardFor(storage).node.value).toBeNull()
    storage.setItem('cometcms-builder-clipboard-v1:one', JSON.stringify({ node: { kind: 'heading' }, style: ['bad'] }))
    expect(clipboardFor(storage).node.value).toBeNull()
    expect(clipboardFor(storage).style.value).toBeNull()

    const full = { ...memoryStorage(), setItem: () => { throw new Error('QuotaExceededError') } }
    const clipboard = clipboardFor(full)
    clipboard.copy(heading)
    expect(clipboard.node.value).toEqual(heading)
    expect(clipboard.style.value).toEqual({})
    expect(clipboardFor(null).node.value).toBeNull()
  })
})
