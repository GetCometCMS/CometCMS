import { readFileSync } from 'node:fs'
import * as Vue from 'vue'
import { createRenderer, h, nextTick, reactive, ssrContextKey } from 'vue'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import { afterEach, describe, expect, it, vi } from 'vitest'
import AccordionItemsControl from './AccordionItemsControl.vue'
import { createSiteBuilder } from '../../../composables/useSiteBuilder.js'
import { t } from '../../../i18n/index.js'

vi.mock('@iconify/vue', () => ({ Icon: { render: () => null } }))
vi.mock('./SmartValueEditor.vue', () => ({ default: {
  props: ['modelValue', 'target'], emits: ['update:modelValue'],
  setup: (props, { emit }) => () => h('input', { 'data-target': props.target, value: props.modelValue, onInput: (event) => emit('update:modelValue', event.target.value) }),
} }))
vi.mock('../../ToggleSwitch.vue', () => ({ default: {
  props: ['modelValue', 'disabled'], emits: ['update:modelValue'],
  setup: (props, { emit }) => () => h('button', { role: 'switch', disabled: props.disabled, onClick: () => emit('update:modelValue', !props.modelValue) }),
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

const { descriptor } = parse(readFileSync(new URL('./AccordionItemsControl.vue', import.meta.url), 'utf8'))
const { bindings } = compileScript(descriptor, { id: 'accordion-items-test' })
const { code } = compileTemplate({ source: descriptor.template.content, filename: 'AccordionItemsControl.vue', id: 'accordion-items-test', compilerOptions: { bindingMetadata: bindings, mode: 'function' } })
const render = new Function('Vue', code)(Vue)
const Control = { ...AccordionItemsControl, render }
let app
let root
const all = (el, predicate) => [...(predicate(el) ? [el] : []), ...el.children.flatMap((child) => all(child, predicate))]
const buttons = (label) => all(root, (el) => el.tag === 'button' && el.props['aria-label']?.startsWith(label))
const inputs = () => all(root, (el) => el.tag === 'input')
function mount(items, single = false) {
  const state = reactive({ items })
  let builder
  app = renderer.createApp({ setup() {
    builder = createSiteBuilder({ t })
    return () => h(Control, { id: 'entries', node: { id: 'accordion', kind: 'accordion', props: { single_open: single } }, modelValue: state.items, 'onUpdate:modelValue': (value) => { state.items = value } })
  } })
  app.provide(ssrContextKey, {})
  root = node()
  app.mount(root)
  return { state, builder }
}
afterEach(() => app?.unmount())

describe('accordion entry controls', () => {
  it('adds, edits, duplicates, reorders, and removes entries', async () => {
    const { state } = mount([{ title: 'First', html: '<p>Answer</p>', open: true }])
    expect(inputs().map((el) => el.props.value)).toEqual(['First', '<p>Answer</p>'])
    inputs()[0].props.onInput({ target: { value: 'Question' } })
    await nextTick()
    buttons('Duplicate')[0].props.onClick()
    await nextTick()
    expect(state.items.map((item) => item.title)).toEqual(['Question', 'Question'])
    expect(state.items[1].open).toBe(false)
    inputs()[0].props.onInput({ target: { value: 'Second' } })
    await nextTick()
    buttons('Move up')[1].props.onClick()
    await nextTick()
    expect(state.items.map((item) => item.title)).toEqual(['Second', 'Question'])
    // The editor follows the moved entry.
    expect(inputs()[0].props.value).toBe('Second')
    buttons('Delete')[0].props.onClick()
    await nextTick()
    const add = all(root, (el) => el.tag === 'button' && el.props.class?.includes('w-full'))[0]
    add.props.onClick()
    await nextTick()
    expect(state.items).toHaveLength(2)
    expect(inputs()[0].props.value).toBe('Entry 2')
    buttons('Delete')[1].props.onClick()
    await nextTick()
    buttons('Delete')[0].props.onClick()
    await nextTick()
    expect(state.items).toEqual([])
  })

  it('closes other initial entries in exclusive mode and locks structure during translation', async () => {
    const { state, builder } = mount([{ title: 'First', html: '', open: true }, { title: 'Second', html: '', open: false }], true)
    all(root, (el) => el.tag === 'button' && el.props['aria-expanded'] !== undefined)[1].props.onClick()
    await nextTick()
    all(root, (el) => el.props.role === 'switch')[0].props.onClick()
    await nextTick()
    expect(state.items.map((item) => item.open)).toEqual([false, true])
    builder.locale.value = 'de'
    await nextTick()
    expect(buttons('Duplicate').every((el) => el.props.disabled)).toBe(true)
    expect(all(root, (el) => el.props.role === 'switch')[0].props.disabled).toBe(true)
  })
})
