import { entryForLocale, isLocalized, localizedState, resolveLocalized } from './localizedValues.js'
import { computed, ref, watch } from 'vue'
import { fieldDefaultValue } from './fieldDefaults.js'

const clone = (value) => JSON.parse(JSON.stringify(value))

function formValue(value, config) {
  if (config.type !== 'datetime' || !value) return clone(value)
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  const pad = (number) => String(number).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function savedValue(value, config) {
  if (config.type !== 'datetime' || !value) return value
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : date.toISOString().replace(/\.\d{3}Z$/, 'Z')
}

/** Entry content drafts live outside the panel so closing it or changing the preview keeps edits. */
export function useBuilderEntry({ type, entryId, locale, contentApi, canUpdate }) {
  const records = ref({})
  const saving = ref(false)
  const fields = computed(() => Object.fromEntries(Object.entries(type.value?.fields ?? {})
    // Routing stays in the entry editor and page content on the canvas; this panel owns title and content fields.
    .filter(([name, config]) => name !== 'slug' && name !== (type.value?.slug_field ?? 'slug') && config?.type !== 'page')))
  const defaultLocale = computed(() => type.value?.default_locale || type.value?.locales?.[0] || '')
  const activeLocale = computed(() => type.value?.locales?.length ? locale.value || defaultLocale.value : '')
  const key = computed(() => type.value?.name && entryId.value ? `${type.value.name}:${entryId.value}` : '')
  const record = computed(() => records.value[key.value] ?? null)
  const draft = computed(() => record.value?.drafts[activeLocale.value] ?? null)
  const readonly = computed(() => !record.value?.entry || !canUpdate(type.value.name, record.value.entry))

  function isUniversal(name) {
    return !!defaultLocale.value && fields.value[name]?.localized === false
  }

  function fieldReadonly(name) {
    return readonly.value || (isUniversal(name) && activeLocale.value !== defaultLocale.value)
  }

  function valuesFor(entry, language) {
    const source = entryForLocale(entry, language, defaultLocale.value)
    const shared = entryForLocale(entry, defaultLocale.value, defaultLocale.value)
    return Object.fromEntries(Object.entries(fields.value).map(([name, config]) => {
      const value = (isUniversal(name) ? shared : source)[name]
      return [name, formValue(value === undefined ? fieldDefaultValue(config) : value, config)]
    }))
  }

  function ensureDraft(item, language) {
    if (!item.entry) return
    if (!item.drafts[language]) {
      const values = valuesFor(item.entry, language)
      item.drafts[language] = { values, baseline: clone(values), resets: [], error: '', errors: {} }
    }
    // Converting a canvas element to a field updates the schema while the panel is open.
    const defaults = valuesFor(item.entry, language)
    for (const name of Object.keys(fields.value)) {
      if (!(name in item.drafts[language].values)) {
        item.drafts[language].values[name] = defaults[name]
        item.drafts[language].baseline[name] = clone(defaults[name])
      }
    }
  }

  function editableValues(values, language) {
    return Object.fromEntries(Object.keys(fields.value)
      .filter((name) => !isUniversal(name) || language === defaultLocale.value)
      .map((name) => [name, values[name]]))
  }

  function dirty(item, language) {
    const form = item.drafts[language]
    return form.resets.length > 0 || JSON.stringify(editableValues(form.values, language)) !== JSON.stringify(editableValues(form.baseline, language))
  }

  const isDirty = computed(() => Object.values(records.value).some((item) => Object.keys(item.drafts).some((language) => dirty(item, language))))

  function valueFor(name) {
    if (isUniversal(name) && activeLocale.value !== defaultLocale.value) {
      const sharedDraft = record.value?.drafts[defaultLocale.value]
      return sharedDraft ? sharedDraft.values[name]
        : (record.value?.entry ? valuesFor(record.value.entry, defaultLocale.value)[name] : null)
    }
    if (draft.value && activeLocale.value !== defaultLocale.value) {
      const value = record.value?.entry.values?.[name]
      const inherited = !isLocalized(value) || !Object.prototype.hasOwnProperty.call(value.values, activeLocale.value)
      const unchanged = JSON.stringify(draft.value.values[name]) === JSON.stringify(draft.value.baseline[name])
      if ((inherited && unchanged) || draft.value.resets.includes(name)) {
        const sharedDraft = record.value?.drafts[defaultLocale.value]
        return sharedDraft ? sharedDraft.values[name] : valuesFor(record.value.entry, defaultLocale.value)[name]
      }
    }
    return draft.value?.values[name] ?? null
  }

  function update(name, value) {
    if (!draft.value || fieldReadonly(name) || saving.value) return
    draft.value.resets = draft.value.resets.filter((key) => key !== name)
    draft.value.values[name] = value
    delete draft.value.errors[name]
  }

  async function load() {
    if (!key.value) return
    const recordKey = key.value
    const collection = type.value.name
    const identifier = entryId.value
    records.value[recordKey] ??= { collection, identifier, entry: null, drafts: {}, loading: false, error: '' }
    const item = records.value[recordKey]
    if (item.entry || item.loading) return
    item.loading = true
    item.error = ''
    try {
      item.entry = (await contentApi.get(collection, identifier)).data
      if (key.value === recordKey) ensureDraft(item, activeLocale.value)
    } catch (error) {
      item.error = error.message
    } finally {
      item.loading = false
    }
  }

  /** Drop unsaved edits and loaded entries, e.g. when the builder moves to another page. */
  function discard() {
    records.value = {}
    load()
  }

  watch([key, activeLocale, fields], () => {
    if (record.value?.entry) ensureDraft(record.value, activeLocale.value)
    else load()
  }, { immediate: true })

  /** All unsaved entry values, with shared fields applied before translations. */
  function previewEntries() {
    const entries = []
    for (const item of Object.values(records.value)) {
      const languages = Object.keys(item.drafts).sort((a, b) => Number(b === defaultLocale.value) - Number(a === defaultLocale.value))
      for (const language of languages) {
        if (!dirty(item, language)) continue
        const form = item.drafts[language]
        const values = Object.fromEntries(Object.entries(editableValues(form.values, language))
          .filter(([name, value]) => !form.resets.includes(name) && JSON.stringify(value) !== JSON.stringify(form.baseline[name]))
          .map(([name, value]) => [name, savedValue(value, fields.value[name])]))
        if (form.resets.length) values.reset_fields = [...form.resets]
        if (language) values.locale = language
        entries.push({ type: item.collection, id: item.entry.uid || item.identifier, values })
      }
    }
    return entries
  }

  /** Save each edited entry/language, preserving failed drafts and unrelated entry metadata. */
  async function save() {
    if (saving.value) return
    saving.value = true
    const saved = []
    try {
      for (const item of Object.values(records.value)) {
        for (const [language, form] of Object.entries(item.drafts)) {
          if (!dirty(item, language)) continue
          const sent = clone(form.values)
          const payload = Object.fromEntries(Object.entries(editableValues(sent, language))
            .map(([name, value]) => [name, savedValue(value, fields.value[name])]))
          for (const name of Object.keys(payload)) {
            if (JSON.stringify(form.values[name]) === JSON.stringify(form.baseline[name])) delete payload[name]
          }
          payload.revision = item.entry.revision
          payload.reset_fields = [...form.resets]
          if (language) payload.locale = language
          form.error = ''
          form.errors = {}
          try {
            const response = await contentApi.update(item.collection, item.entry.uid || item.identifier, payload)
            item.entry = response.data
            const normalized = valuesFor(item.entry, language)
            // Keep any edits made while the request was in flight.
            for (const name of Object.keys(fields.value)) {
              if (JSON.stringify(form.values[name]) === JSON.stringify(sent[name])) form.values[name] = normalized[name]
            }
            form.baseline = normalized
            form.resets = []
            saved.push(item.entry)
          } catch (error) {
            form.error = error.message
            form.errors = error.fields ?? {}
            // Show the entry/language that needs attention in the panel.
            error.entryId = item.identifier
            error.locale = language
            throw error
          }
        }
      }
      return saved
    } finally {
      saving.value = false
    }
  }

  /** The shown entry was saved elsewhere (its page content): the next save builds on that version. */
  function setRevision(revision) {
    if (record.value?.entry) record.value.entry.revision = revision
  }

  function reset(name) {
    if (!draft.value || fieldReadonly(name) || activeLocale.value === defaultLocale.value) return
    draft.value.resets.push(name)
    draft.value.values[name] = formValue(resolveLocalized(record.value.entry.values?.[name], defaultLocale.value, defaultLocale.value), fields.value[name])
  }

  return { defaultLocale, activeLocale, reset, translationState: (name) => localizedState(record.value?.entry.values?.[name], activeLocale.value, defaultLocale.value), fields, record, draft, readonly, isDirty, saving, fieldReadonly, valueFor, update, load, save, setRevision, discard, previewEntries }
}
