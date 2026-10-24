import { readFileSync } from 'node:fs'
import * as Vue from 'vue'
import { createRenderer, h, nextTick, reactive, ssrContextKey } from 'vue'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import { afterEach, describe, expect, it } from 'vitest'
import ShapePicker from './ShapePicker.vue'
import { createSiteBuilder } from '../../../composables/useSiteBuilder.js'
import { t } from '../../../i18n/index.js'

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

const { descriptor } = parse(readFileSync(new URL('./ShapePicker.vue', import.meta.url), 'utf8'))
const { bindings } = compileScript(descriptor, { id: 'shape-picker-test' })
const { code } = compileTemplate({ source: descriptor.template.content, filename: 'ShapePicker.vue', id: 'shape-picker-test', compilerOptions: { bindingMetadata: bindings, mode: 'function' } })
const Picker = { ...ShapePicker, render: new Function('Vue', code)(Vue) }
const SHAPES = {
  divider: { width: 1200, height: 120, paths: { wave: 'M0,0V60Z', curve: 'M0,0Q600,240 1200,0Z' } },
}

let app
let root
const all = (el, predicate) => [...(predicate(el) ? [el] : []), ...el.children.flatMap((child) => all(child, predicate))]
const radios = () => all(root, (el) => el.props.role === 'radio')

function mount(props) {
  const state = reactive({ value: props.modelValue })
  app = renderer.createApp({ setup() {
    const builder = createSiteBuilder({ t })
    builder.spec.value = { shapes: SHAPES }
    return () => h(Picker, { ...props, modelValue: state.value, 'onUpdate:modelValue': (value) => { state.value = value } })
  } })
  app.provide(ssrContextKey, {})
  root = node()
  app.mount(root)
  return state
}
afterEach(() => app?.unmount())

describe('shape picker', () => {
  it('offers each divider as a radio drawn the way the section shows it', async () => {
    const state = mount({ options: ['none', 'wave', 'curve'], modelValue: 'none', label: 'Shape', edge: 'bottom', invert: true })
    expect(radios().map((el) => el.props['aria-checked'])).toEqual([true, false, false])
    radios()[2].props.onClick()
    await nextTick()
    expect(state.value).toBe('curve')
    expect(radios()[2].props['aria-checked']).toBe(true)
    const paths = all(root, (el) => el.tag === 'path' && el.props['fill-rule'] === 'evenodd')
    // "None" draws no band; inverted shapes fill the rest of the box, and a bottom inverted one is not mirrored.
    expect(paths).toHaveLength(2)
    expect(paths[1].props.d).toBe('M0,0H1200V120H0ZM0,0Q600,240 1200,0Z')
    expect(paths[1].props.transform).toContain('scale(1 1)')
  })
})
