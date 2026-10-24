import { readFileSync } from 'node:fs'
import * as Vue from 'vue'
import { createRenderer, h, nextTick, reactive, ref, ssrContextKey } from 'vue'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import { afterEach, describe, expect, it, vi } from 'vitest'
import FieldConfigSelect from './FieldConfigSelect.vue'
import { optionsToText, parseSelectOptions, serializeSelectOptions } from '../composables/fieldBuilderUtils.js'
import { setLocalized } from '../composables/localizedValues.js'

vi.mock('../composables/useLanguages.js', () => ({ useLanguages: () => ({ defaultLocale: ref('en') }) }))
vi.mock('@iconify/vue', () => ({ Icon: { render: () => null } }))
vi.mock('./LocalizedInput.vue', () => ({ default: {
  props: ['modelValue', 'id', 'label'],
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    return () => h('input', { id: props.id, value: props.modelValue, onInput: (event) => emit('update:modelValue', event.target.value) })
  },
} }))

const node = (tag = '') => ({ tag, props: {}, children: [], parent: null, addEventListener() {} })
const renderer = createRenderer({
  createElement: node, createText: () => node(), createComment: () => node(),
  setText() {}, setElementText() {},
  patchProp: (el, key, _prev, value) => { el.props[key] = value },
  insert(child, parent, anchor = null) {
    if (child.parent) child.parent.children.splice(child.parent.children.indexOf(child), 1)
    child.parent = parent
    const index = anchor ? parent.children.indexOf(anchor) : -1
    parent.children.splice(index < 0 ? parent.children.length : index, 0, child)
  },
  remove(child) { child.parent?.children.splice(child.parent.children.indexOf(child), 1); child.parent = null },
  parentNode: (child) => child.parent,
  nextSibling: (child) => child.parent?.children[child.parent.children.indexOf(child) + 1] ?? null,
})
// Use the client template to exercise the row controls without a browser.
const { descriptor } = parse(readFileSync(new URL('./FieldConfigSelect.vue', import.meta.url), 'utf8'))
const { bindings } = compileScript(descriptor, { id: 'select-config-test' })
const { code } = compileTemplate({ source: descriptor.template.content, filename: 'FieldConfigSelect.vue', id: 'select-config-test', compilerOptions: { bindingMetadata: bindings, mode: 'function' } })
const render = new Function('Vue', code)(Vue)
let app
let root
const all = (el, predicate) => [...(predicate(el) ? [el] : []), ...el.children.flatMap((child) => all(child, predicate))]
const labels = () => all(root, (el) => el.tag === 'input' && el.props.id?.endsWith('-label'))
const keys = () => all(root, (el) => el.tag === 'input' && el.props.id?.endsWith('-key'))
const buttons = (label) => all(root, (el) => el.tag === 'button' && el.props['aria-label'] === label)
async function mount(options) {
  const field = reactive({ options, _optionsText: optionsToText(options) })
  root = node()
  app = renderer.createApp({ ...FieldConfigSelect, render }, { field })
  app.provide(ssrContextKey, {})
  app.mount(root)
  return field
}
async function input(index, value) {
  labels()[index].props.onInput({ target: { value: typeof value === 'string' ? setLocalized(labels()[index].props.value, 'en', value) : value } })
  await nextTick()
}
function saved(field) {
  return serializeSelectOptions(parseSelectOptions(field._optionsText), field.options)
}
afterEach(() => { app?.unmount(); vi.unstubAllGlobals() })

describe('select option rows', () => {
  it('replaces the textarea with rows and preserves stored keys and translations when editing', async () => {
    const label = setLocalized(setLocalized('Draft', 'de', 'Entwurf'), 'en', 'Draft')
    const field = await mount({ draft: label, published: 'Published' })
    expect(all(root, (el) => el.tag === 'textarea')).toHaveLength(0)
    expect(keys().map((el) => el.props.value)).toEqual(['draft', 'published'])
    const updated = setLocalized(label, 'en', 'Draft article')
    await input(0, updated)
    expect(saved(field)).toEqual({ draft: updated, published: 'Published' })
  })

  it('adds a focused row after the clicked option and removes rows without losing other labels', async () => {
    const focus = vi.fn()
    vi.stubGlobal('document', { getElementById: () => ({ focus }) })
    const field = await mount(['Vue.js', 'Electron'])
    await buttons('Add option')[0].props.onClick()
    expect(labels()).toHaveLength(3)
    expect(focus).toHaveBeenCalledOnce()
    await input(1, 'Tailwind CSS')
    expect(Object.keys(saved(field))).toEqual(['Vue.js', 'Tailwind CSS', 'Electron'])
    buttons('Remove option')[0].props.onClick()
    await nextTick()
    expect(saved(field)).toEqual({ 'Tailwind CSS': expect.any(Object), Electron: 'Electron' })
    expect(labels()).toHaveLength(2)
  })

  it('keeps unfinished rows out of the schema and creates distinct values for duplicate labels', async () => {
    vi.stubGlobal('document', { getElementById: () => null })
    const field = await mount([])
    expect(labels()).toHaveLength(1)
    expect(saved(field)).toEqual([])
    await input(0, 'First')
    await buttons('Add option')[0].props.onClick()
    expect(Object.keys(saved(field))).toEqual(['First'])
    await input(1, 'First')
    expect(Object.keys(saved(field))).toEqual(['First', 'First_2'])
    keys()[1].props.onChange({ target: { value: 'custom' } })
    await nextTick()
    await input(1, 'Second')
    expect(Object.keys(saved(field))).toEqual(['First', 'custom'])
  })

  it('can remove all options and refresh rows when the field is replaced externally', async () => {
    const field = await mount(['One'])
    buttons('Remove option')[0].props.onClick()
    await nextTick()
    expect(saved(field)).toEqual([])
    expect(labels()).toHaveLength(0)
    field.options = { new: 'New' }
    await nextTick()
    expect(keys().map((el) => el.props.value)).toEqual(['new'])
  })
})
