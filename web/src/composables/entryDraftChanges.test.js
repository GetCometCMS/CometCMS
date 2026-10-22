import { computed, ref } from 'vue'
import { describe, expect, it } from 'vitest'
import { hasEntryDraftChanges } from './entryDraftChanges.js'

const clone = (value) => JSON.parse(JSON.stringify(value))

function fixture() {
  const saved = {
    en: { locale: 'en', title: 'Hello', status: 'published', rows: [{ text: 'Original' }] },
    de: { locale: 'de', title: 'Hallo', status: 'draft', rows: [{ text: 'Original' }] },
  }
  const language = ref('en')
  const form = ref(clone(saved.en))
  const resets = ref([])
  const drafts = ref({})
  const dirty = computed(() => hasEntryDraftChanges(
    { ...drafts.value, [language.value]: { form: form.value, resets: resets.value } },
    (locale) => saved[locale] ?? saved.en,
    (locale) => !(locale in saved),
  ))
  function switchLocale(locale) {
    drafts.value[language.value] = { form: clone(form.value), resets: [...resets.value] }
    language.value = locale
    form.value = clone(drafts.value[locale]?.form ?? saved[locale] ?? saved.en)
    form.value.locale = locale
    resets.value = [...(drafts.value[locale]?.resets ?? [])]
  }
  return { saved, language, form, resets, drafts, dirty, switchLocale }
}

describe('content entry draft changes', () => {
  it('stays clean when displaying different saved languages and switching back', () => {
    const state = fixture()
    expect(state.dirty.value).toBe(false)
    state.switchLocale('de')
    expect(state.dirty.value).toBe(false)
    state.switchLocale('en')
    expect(state.dirty.value).toBe(false)
  })

  it('retains edits in another language and becomes clean when they are reverted', () => {
    const state = fixture()
    state.form.value.title = 'Edited'
    expect(state.dirty.value).toBe(true)
    state.switchLocale('de')
    expect(state.dirty.value).toBe(true)
    state.switchLocale('en')
    expect(state.form.value.title).toBe('Edited')
    state.form.value.title = 'Hello'
    expect(state.dirty.value).toBe(false)
  })

  it('compares nested fields and publication settings with the saved language', () => {
    const state = fixture()
    state.switchLocale('de')
    state.form.value.rows[0].text = 'Changed'
    expect(state.dirty.value).toBe(true)
    state.form.value.rows[0].text = 'Original'
    expect(state.dirty.value).toBe(false)
    state.form.value.status = 'published'
    expect(state.dirty.value).toBe(true)
  })

  it('tracks explicit resets even when the displayed value stays the same', () => {
    const state = fixture()
    state.switchLocale('de')
    state.resets.value.push('rows')
    expect(state.dirty.value).toBe(true)
    state.switchLocale('en')
    expect(state.dirty.value).toBe(true)
  })

  it('marks a new translation dirty even if all values are inherited', () => {
    const state = fixture()
    state.switchLocale('fr')
    expect(state.dirty.value).toBe(true)
    state.switchLocale('en')
    expect(state.dirty.value).toBe(true)
  })

  it('becomes clean when a save establishes the new baseline', () => {
    const state = fixture()
    state.form.value.title = 'Saved edit'
    state.switchLocale('de')
    state.form.value.title = 'Gespeichert'
    expect(state.dirty.value).toBe(true)
    state.saved.en = clone(state.drafts.value.en.form)
    state.saved.de = clone(state.form.value)
    state.drafts.value = {}
    expect(state.dirty.value).toBe(false)
  })

  it('ignores locale display metadata and object key order', () => {
    expect(hasEntryDraftChanges(
      { de: { form: { title: 'Hello', locale: 'de' }, resets: [] } },
      () => ({ locale: 'en', title: 'Hello' }),
    )).toBe(false)
  })
})
