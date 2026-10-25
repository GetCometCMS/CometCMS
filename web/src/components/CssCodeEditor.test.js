import { readFileSync } from 'node:fs'
import * as Vue from 'vue'
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc'
import { afterEach, expect, it, vi } from 'vitest'
import CssCodeEditor from './CssCodeEditor.vue'

vi.mock('codemirror', () => { throw new Error('504 (Outdated Optimize Dep)') })
vi.mock('./LoadingSpinner.vue', () => ({ default: { render: () => null } }))

const node = (tag = '') => ({ tag, props: {}, children: [], parent: null })
const renderer = Vue.createRenderer({
  createElement: node, createText: () => node(), createComment: () => node(),
  setText() {}, setElementText() {},
  patchProp: (el, key, _prev, value) => { el.props[key] = value },
  insert(child, parent) { child.parent = parent; parent.children.push(child) },
  remove(child) { child.parent?.children.splice(child.parent.children.indexOf(child), 1) },
  parentNode: (child) => child.parent,
  nextSibling: () => null,
})
const { descriptor } = parse(readFileSync(new URL('./CssCodeEditor.vue', import.meta.url), 'utf8'))
const { bindings } = compileScript(descriptor, { id: 'css-editor-test' })
const { code } = compileTemplate({ source: descriptor.template.content, filename: 'CssCodeEditor.vue', id: 'css-editor-test', compilerOptions: { bindingMetadata: bindings, mode: 'function' } })
const render = new Function('Vue', code)(Vue)
const find = (el, tag) => el.tag === tag ? el : el.children.map((child) => find(child, tag)).find(Boolean)
let app
afterEach(() => { app?.unmount(); vi.restoreAllMocks() })

it('keeps CSS editable when the lazy dependency import fails', async () => {
  const warning = vi.spyOn(console, 'warn').mockImplementation(() => {})
  const update = vi.fn()
  const root = node()
  const props = Vue.reactive({ modelValue: '.example {}', disabled: false, label: 'Custom CSS', 'onUpdate:modelValue': update })
  const editorRef = Vue.ref(null)
  const component = { ...CssCodeEditor, render }
  app = renderer.createApp({ render: () => Vue.h(component, { ...props, ref: editorRef }) })
  app.provide(Vue.ssrContextKey, {})
  app.mount(root)
  const editor = editorRef.value
  await vi.waitFor(() => expect(find(root, 'textarea')).toBeTruthy())
  const textarea = find(root, 'textarea')
  expect(textarea.props.value).toBe('.example {}')
  expect(textarea.props['aria-label']).toBe('Custom CSS')
  textarea.props.onInput({ target: { value: '.changed {}' } })
  expect(update).toHaveBeenLastCalledWith('.changed {}')
  editor.addRule('.another')
  expect(update).toHaveBeenLastCalledWith('.example {}\n\n.another {\n  \n}\n')
  props.modelValue = '.external {}'
  props.disabled = true
  await Vue.nextTick()
  expect(textarea.props.value).toBe('.external {}')
  expect(textarea.props.disabled).toBe(true)
  update.mockClear()
  editor.addRule('.blocked')
  expect(update).not.toHaveBeenCalled()
  expect(warning).toHaveBeenCalledWith(expect.stringContaining('plain text'), expect.any(Error))
})
