import { createSSRApp, h } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import WorkspaceLanguageFields from './WorkspaceLanguageFields.vue'

const selects = vi.hoisted(() => [])
vi.mock('./SearchableSelect.vue', () => ({ default: {
  props: ['modelValue', 'options', 'multiple', 'disabled', 'clearable'],
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    selects.push({ props, update: (value) => emit('update:modelValue', value) })
    return () => null
  },
} }))

beforeEach(() => { selects.length = 0 })

async function render(modelValue) {
  const changes = []
  await renderToString(createSSRApp({ render: () => h(WorkspaceLanguageFields, {
    modelValue, 'onUpdate:modelValue': (value) => changes.push(value),
  }) }))
  return changes
}

describe('workspace language selection', () => {
  it('limits the default to selected languages and switches it when its language is removed', async () => {
    const changes = await render({ locales: ['de', 'fr'], default_locale: 'de' })
    expect(selects[1].props.options.map((option) => option.value)).toEqual(['fr', 'de'])
    expect(selects[1].props.clearable).toBe(false)
    selects[0].update(['fr'])
    expect(changes).toEqual([{ locales: ['fr'], default_locale: 'fr' }])
  })

  it('keeps the chosen default when adding languages and permits changing it', async () => {
    const changes = await render({ locales: ['de', 'fr'], default_locale: 'de' })
    selects[0].update(['de', 'fr', 'en'])
    selects[1].update('fr')
    expect(changes).toEqual([
      { locales: ['de', 'fr', 'en'], default_locale: 'de' },
      { locales: ['de', 'fr'], default_locale: 'fr' },
    ])
  })

  it('clears the default when all languages are removed and disables its selector', async () => {
    const changes = await render({ locales: ['de'], default_locale: 'de' })
    selects[0].update([])
    expect(changes).toEqual([{ locales: [], default_locale: '' }])
    selects.length = 0
    await render(changes[0])
    expect(selects[1].props.disabled).toBe(true)
    expect(selects[1].props.options).toEqual([])
  })
})
