import { createSSRApp, h } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { describe, expect, it } from 'vitest'
import GradientEditor from './GradientEditor.vue'

const options = [
  { value: 'primary', label: 'Primary', swatch: '#123456' },
  { value: 'accent', label: 'Accent', swatch: '#abcdef' },
  { value: 'custom-brand', label: 'Brand', swatch: '#ff0000', custom: true },
]
const render = (modelValue) => renderToString(createSSRApp({ render: () => h(GradientEditor, { id: 'gradient', modelValue, options }) }))

describe('gradient controls', () => {
  it('starts disabled without showing irrelevant settings', async () => {
    const html = await render([])
    expect(html).toContain('<select id="gradient"')
    expect(html).toContain('None')
    expect(html).not.toContain('Start color')
    expect(html).not.toContain('type="number"')
  })

  it('previews palette colors and offers labeled colors and a bounded linear angle', async () => {
    const html = await render({ type: 'linear', from: 'primary', to: 'custom-brand', angle: 45 })
    expect(html).toContain('background-image:linear-gradient(45deg, #123456, #ff0000)')
    expect(html).toContain('for="gradient-from"')
    expect(html).toContain('for="gradient-to"')
    expect(html).toContain('Brand')
    expect(html).toContain('id="gradient-angle" type="number" min="0" max="360" value="45"')
    expect(html).toContain('role="slider" tabindex="0"')
    expect(html).toContain('aria-valuenow="45"')
    expect(html).toContain('rotate(45 32 32)')
    expect(html).not.toContain('type="range"')
  })

  it('hides the angle for radial gradients and previews deleted colors using built-in fallbacks', async () => {
    const html = await render({ type: 'radial', from: 'custom-deleted', to: 'accent' })
    expect(html).toContain('background-image:radial-gradient(ellipse at center, #123456, #abcdef)')
    expect(html).not.toContain('type="number"')
    expect(html).toContain('custom-deleted (missing)')
  })
})
