import { createSSRApp, h } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { describe, expect, it } from 'vitest'
import ShadowEditor from './ShadowEditor.vue'
import { shadowPreview, shadowSettings } from '../../../composables/shadows.js'

const options = [{ value: 'primary', label: 'Primary', swatch: '#123456' }, { value: 'custom-brand', label: 'Brand', swatch: '#ff0000', custom: true }]
const presets = { hard: { label: '', color: 'primary', x: 6, y: 6, blur: 0, spread: 0, opacity: 100, inset: false }, 'shadow-card': { label: 'Card', color: 'black', x: 0, y: 12, blur: 30, spread: -8, opacity: 30, inset: false } }
const render = (modelValue, text = false) => renderToString(createSSRApp({ render: () => h(ShadowEditor, { id: 'shadow', modelValue, options, text, presets }) }))

describe('shadow editor', () => {
  it('keeps disabled shadows compact and offers fixed sizes and the theme presets', async () => {
    const html = await render('')
    expect(html).toContain('value="xs"')
    expect(html).toContain('value="xl"')
    expect(html).toContain('>Block</option>')
    expect(html).toContain('>Card</option>')
    expect(html).not.toContain('value="glow"')
    expect(html).toContain('Custom')
    expect(html).not.toContain('type="number"')
    expect(html).not.toContain('<details')
  })

  it('previews theme colors and opens custom settings with labeled bounded inputs', async () => {
    const html = await render({ color: 'custom-brand', x: -8, y: 10, blur: 0, spread: 2, opacity: 80, inset: true })
    expect(html).toContain('box-shadow:inset -8px 10px 0px 2px color-mix(in srgb, #ff0000 80%, transparent)')
    expect(html).toContain('<details open')
    expect(html).toContain('id="shadow-x" type="number" min="-100" max="100"')
    expect(html).toContain('for="shadow-opacity"')
    expect(html).toContain('Brand')
    expect(html).toContain('type="checkbox" checked')
  })

  it('previews theme presets and flags deleted ones', async () => {
    expect(await render('shadow-card')).toContain('box-shadow:0px 12px 30px -8px color-mix(in srgb, #000000 30%, transparent)')
    expect(await render('shadow-gone')).toContain('shadow-gone (missing)')
  })

  it('shows only supported text shadow settings', async () => {
    const html = await render({ color: 'primary', x: 0, y: 0, blur: 16, opacity: 100 }, true)
    expect(html).toContain('text-shadow:0px 0px 16px color-mix(in srgb, #123456 100%, transparent)')
    expect(html).not.toContain('shadow-spread')
    expect(html).not.toContain('type="checkbox"')
    expect(html).not.toContain('value="inset"')
    expect(html).not.toContain('Card')
    expect(html).toContain('value="glow"')
  })

  it('starts editable effects with the published preset geometry', () => {
    expect(shadowSettings('hard', false, presets)).toEqual({ color: 'primary', x: 6, y: 6, blur: 0, spread: 0, opacity: 100, inset: false })
    expect(shadowSettings('shadow-card', true, presets)).toEqual({ color: 'primary', x: 3, y: 3, blur: 0, opacity: 100 })
    expect(shadowSettings('glow', true)).toEqual({ color: 'primary', x: 0, y: 0, blur: 16, opacity: 100 })
    expect(shadowPreview('md', false, options).boxShadow).toBe('0px 6px 18px -6px color-mix(in srgb, #000000 15%, transparent)')
  })
})
