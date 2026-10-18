import { afterEach, describe, expect, it, vi } from 'vitest'
import { effectScope, nextTick, ref } from 'vue'
import { useBuilderEntry } from './useBuilderEntry.js'

const clone = (value) => JSON.parse(JSON.stringify(value))
const scopes = []
afterEach(() => { for (const scope of scopes.splice(0)) scope.stop() })

async function fixture({ entries, schema = {}, canUpdate = () => true, get } = {}) {
  const stored = entries ?? {
    first: { id: 'first', uid: 'stable-first', title: 'First entry', slug: 'first', intro: 'First intro', status: 'published', author_id: 'writer' },
    second: { id: 'second', uid: 'stable-second', title: 'Second entry', slug: 'second', intro: 'Second intro', status: 'draft' },
  }
  const type = ref({ name: 'posts', fields: { title: { type: 'text' }, slug: { type: 'slug' }, intro: { type: 'text' } }, ...schema })
  const entryId = ref(Object.keys(stored)[0])
  const locale = ref('')
  const contentApi = {
    get: get ?? vi.fn(async (_type, id) => ({ data: clone(stored[id]) })),
    update: vi.fn(async (_type, id, payload) => {
      const entry = Object.values(stored).find((item) => item.id === id || item.uid === id)
      if (payload.locale) {
        entry.translations ??= {}
        entry.translations[payload.locale] = { ...entry.translations[payload.locale], ...payload }
        if (payload.locale === type.value.default_locale) Object.assign(entry, payload)
      } else Object.assign(entry, payload)
      return { data: clone(entry) }
    }),
  }
  const scope = effectScope()
  scopes.push(scope)
  const state = scope.run(() => useBuilderEntry({ type, entryId, locale, contentApi, canUpdate }))
  await vi.waitFor(() => expect(state.draft.value).not.toBeNull())
  return { state, type, entryId, locale, contentApi, stored }
}

describe('builder entry content', () => {
  it('edits assigned content without mutating the loaded entry or submitting publishing metadata', async () => {
    const { state, contentApi, stored } = await fixture()
    expect(Object.keys(state.fields.value)).toEqual(['title', 'intro'])
    expect(state.valueFor('intro')).toBe('First intro')
    state.update('intro', 'Edited in the builder')
    expect(stored.first.intro).toBe('First intro')
    expect(state.isDirty.value).toBe(true)
    await state.save()
    expect(contentApi.update).toHaveBeenCalledWith('posts', 'stable-first', { title: 'First entry', intro: 'Edited in the builder' })
    expect(stored.first.status).toBe('published')
    expect(stored.first.author_id).toBe('writer')
    expect(state.isDirty.value).toBe(false)
  })

  it('retains separate drafts when switching preview entries and saves both', async () => {
    const { state, entryId, contentApi } = await fixture()
    state.update('intro', 'First draft')
    entryId.value = 'second'
    await vi.waitFor(() => expect(state.valueFor('intro')).toBe('Second intro'))
    state.update('intro', 'Second draft')
    entryId.value = 'first'
    await nextTick()
    expect(state.valueFor('intro')).toBe('First draft')
    expect(contentApi.get).toHaveBeenCalledTimes(2)
    await state.save()
    expect(contentApi.update).toHaveBeenCalledTimes(2)
    expect(state.isDirty.value).toBe(false)
  })

  it('keeps translated drafts separate, including shared fields edited in the default language', async () => {
    const { state, locale, contentApi } = await fixture({
      schema: { singleton: true, locales: ['en', 'de'], default_locale: 'en', fields: {
        title: { type: 'text' }, intro: { type: 'html' }, shared: { type: 'text', localized: false },
      } },
      entries: { home: { id: 'home', title: 'Old root copy', translations: {
        en: { title: 'Home', intro: '<p>English</p>', shared: 'Shared' },
        de: { title: 'Startseite', intro: '<p>Deutsch</p>', shared: 'Shared' },
      } } },
    })
    expect(state.valueFor('title')).toBe('Home')
    state.update('shared', 'Updated shared value')
    locale.value = 'de'
    await nextTick()
    expect(state.valueFor('title')).toBe('Startseite')
    expect(state.valueFor('shared')).toBe('Updated shared value')
    expect(state.fieldReadonly('shared')).toBe(true)
    state.update('shared', 'Disallowed')
    state.update('intro', '<p>Geändert</p>')
    locale.value = 'en'
    await nextTick()
    expect(state.valueFor('intro')).toBe('<p>English</p>')
    await state.save()
    expect(contentApi.update).toHaveBeenNthCalledWith(1, 'posts', 'home', {
      title: 'Home', intro: '<p>English</p>', shared: 'Updated shared value', locale: 'en',
    })
    expect(contentApi.update).toHaveBeenNthCalledWith(2, 'posts', 'home', {
      title: 'Startseite', intro: '<p>Geändert</p>', locale: 'de',
    })
    expect(state.isDirty.value).toBe(false)
  })

  it('preserves failed drafts and associates validation errors with their entry and language', async () => {
    const { state, entryId, contentApi } = await fixture()
    state.update('intro', 'Valid first draft')
    entryId.value = 'second'
    await vi.waitFor(() => expect(state.draft.value).not.toBeNull())
    state.update('title', '')
    const saveNormally = contentApi.update.getMockImplementation()
    contentApi.update.mockImplementationOnce(saveNormally)
      .mockRejectedValueOnce(Object.assign(new Error('Title is required.'), { fields: { title: 'Required' } }))
    await expect(state.save()).rejects.toMatchObject({ entryId: 'second', locale: '' })
    expect(state.isDirty.value).toBe(true)
    expect(state.valueFor('title')).toBe('')
    expect(state.draft.value.errors).toEqual({ title: 'Required' })
    state.update('title', 'Fixed title')
    expect(state.draft.value.errors).toEqual({})
    entryId.value = 'first'
    await nextTick()
    expect(state.valueFor('intro')).toBe('Valid first draft')
    expect(state.draft.value.error).toBe('')
    await state.save()
    expect(state.isDirty.value).toBe(false)
    expect(contentApi.update).toHaveBeenCalledTimes(3)
    entryId.value = 'second'
    await nextTick()
    expect(state.draft.value.error).toBe('')
  })

  it('allows retrying a failed entry load', async () => {
    const contentApi = { get: vi.fn()
      .mockRejectedValueOnce(new Error('Network unavailable'))
      .mockResolvedValueOnce({ data: { id: 'home', title: 'Home' } }) }
    const scope = effectScope()
    scopes.push(scope)
    const state = scope.run(() => useBuilderEntry({
      type: ref({ name: 'home', singleton: true, fields: { title: { type: 'text' } } }),
      entryId: ref('home'), locale: ref(''), contentApi, canUpdate: () => true,
    }))
    await vi.waitFor(() => expect(state.record.value.error).toBe('Network unavailable'))
    expect(state.draft.value).toBeNull()
    await state.load()
    expect(state.valueFor('title')).toBe('Home')
    expect(state.record.value.error).toBe('')
    expect(state.isDirty.value).toBe(false)
  })

  it('shows content without permitting edits when update permission is missing', async () => {
    const { state, contentApi } = await fixture({ canUpdate: () => false })
    expect(state.readonly.value).toBe(true)
    state.update('intro', 'Forbidden')
    expect(state.valueFor('intro')).toBe('First intro')
    await state.save()
    expect(contentApi.update).not.toHaveBeenCalled()
  })

  it('converts date-time controls to local time and saves UTC, preserving media and repeater values', async () => {
    const stamp = '2026-10-18T10:30:00Z'
    const { state, contentApi } = await fixture({
      schema: { fields: { title: { type: 'text' }, date: { type: 'datetime' }, images: { type: 'media' }, rows: { type: 'repeater' } } },
      entries: { page: { id: 'page', title: 'Page', date: stamp, images: ['photo.jpg'], rows: [{ text: 'Original' }] } },
    })
    expect(new Date(state.valueFor('date')).getTime()).toBe(new Date(stamp).getTime())
    state.update('rows', [{ text: 'Changed' }])
    await state.save()
    expect(contentApi.update).toHaveBeenCalledWith('posts', 'page', {
      title: 'Page', date: stamp, images: ['photo.jpg'], rows: [{ text: 'Changed' }],
    })
  })

  it('offers fields added from the canvas without losing existing drafts', async () => {
    const { state, type } = await fixture()
    state.update('intro', 'Pending intro')
    type.value = { ...type.value, fields: { ...type.value.fields, summary: { type: 'text', default: 'New field' } } }
    await nextTick()
    expect(state.valueFor('summary')).toBe('New field')
    expect(state.valueFor('intro')).toBe('Pending intro')
    state.update('intro', 'First intro')
    expect(state.isDirty.value).toBe(false)
  })

  it('keeps late responses attached to the original entry after a preview switch', async () => {
    let resolveSecond
    const second = new Promise((resolve) => { resolveSecond = resolve })
    const get = vi.fn(async (_type, id) => id === 'second' ? second : { data: { id: 'first', title: 'First' } })
    const { state, entryId } = await fixture({ get })
    entryId.value = 'second'
    await nextTick()
    entryId.value = 'first'
    await nextTick()
    resolveSecond({ data: { id: 'second', title: 'Second' } })
    await nextTick()
    expect(state.valueFor('title')).toBe('First')
    entryId.value = 'second'
    await nextTick()
    expect(state.valueFor('title')).toBe('Second')
  })
})
