import { createSSRApp, h } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { describe, expect, it, vi } from 'vitest'
import MediaSelect from './MediaSelect.vue'

const field = vi.hoisted(() => ({ props: null, emit: null }))
vi.mock('../FieldInput.vue', () => ({
  default: {
    props: ['config', 'modelValue'],
    emits: ['update:modelValue'],
    setup(props, { emit }) {
      field.props = props
      field.emit = (value) => emit('update:modelValue', value)
      return () => h('div')
    },
  },
}))

async function renderSelection(props) {
  const changes = []
  await renderToString(createSSRApp({ render: () => h(MediaSelect, { ...props, 'onUpdate:modelValue': (value) => changes.push(value) }) }))
  return changes
}

describe('website media selection', () => {
  it('preserves gallery selection order and emits arrays when reordered or cleared', async () => {
    const changes = await renderSelection({ multiple: true, modelValue: ['second.svg', 'first.svg'] })
    expect(field.props.config.multiple).toBe(true)
    expect(field.props.modelValue).toEqual(['second.svg', 'first.svg'])
    field.emit(['first.svg', 'second.svg'])
    field.emit([])
    expect(changes).toEqual([['first.svg', 'second.svg'], []])
  })

  it('keeps existing single-image settings scalar', async () => {
    const changes = await renderSelection({ modelValue: 'first.svg' })
    expect(field.props.config.multiple).toBe(false)
    expect(field.props.modelValue).toEqual(['first.svg'])
    field.emit(['second.svg'])
    field.emit([])
    expect(changes).toEqual(['second.svg', ''])
  })
})
