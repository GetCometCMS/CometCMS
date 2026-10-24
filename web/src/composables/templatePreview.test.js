import { describe, expect, it, vi } from 'vitest'
import { previewCache, templatePreview } from './templatePreview.js'

const node = (kind, props = {}, children = [], extra = {}) => ({ kind, props, children, ...extra })

describe('template wireframes', () => {
  it('keeps element order and desktop column proportions without inventing images', async () => {
    const tree = [node('section', {}, [node('row', {}, [
      node('column', { span: { desktop: 8 } }, [node('heading'), node('text')]),
      node('column', { span: { desktop: 4 } }, [node('button')]),
    ])])]
    const preview = await templatePreview(tree)
    const columns = preview[0].children[0].children
    expect(columns.map((item) => item.span)).toEqual([8, 4])
    expect(columns[0].children.map((item) => item.kind)).toEqual(['heading', 'text'])
    expect(JSON.stringify(preview)).not.toContain('"kind":"image"')
    tree[0].children[0].children[1].children.push(node('image'))
    expect((await templatePreview(tree))[0].children[0].children[1].children.at(-1).kind).toBe('image')
  })

  it('uses field displays, schema types, and heading tags for dynamic content', async () => {
    const preview = await templatePreview([
      node('field', { source: { source: 'current', path: ['photo'] } }),
      node('field', { display: 'gallery' }),
      node('field', { tag: 'h2' }),
      node('field', { display: 'excerpt' }),
    ], { type: 'pages', types: [{ name: 'pages', fields: [{ key: 'photo', type: 'media' }] }] })
    expect(preview.map((item) => item.kind)).toEqual(['image', 'gallery', 'heading', 'text'])
  })

  it('expands component instances, applies editable overrides, and stops cycles', async () => {
    const brand = node('site_title', { show_name: true }, [], { id: 'brand', editable: ['show_name'] })
    const component = { nodes: [node('column', {}, [brand, node('component', { component: 'shared' })])] }
    const loadComponent = vi.fn(async () => component)
    const preview = await templatePreview([node('component', { component: 'shared', span: { desktop: 6 } }, [], { overrides: { brand: { show_name: false } } })], { loadComponent })
    expect(loadComponent).toHaveBeenCalledTimes(1)
    expect(preview[0].span).toBe(6)
    expect(preview[0].children).toHaveLength(1)
    expect(preview[0].children[0].name).toBe(false)
    expect(brand.props.show_name).toBe(true)
  })

  it('shows page content defaults and bounded collections in their entry context', async () => {
    const preview = await templatePreview([
      node('page_content', {}, [node('image')]),
      node('collection', { type: 'posts', columns: 2, limit: 50 }, [node('column', {}, [node('field', { source: { source: 'item', path: ['photo'] } })])]),
    ], { types: [{ name: 'posts', fields: [{ key: 'photo', type: 'media' }] }] })
    expect(preview[0].children[0].kind).toBe('image')
    expect(preview[1].columns).toBe(2)
    expect(preview[1].children).toHaveLength(3)
    expect(JSON.stringify(preview[1])).toContain('"kind":"image"')
  })

  it('omits desktop-hidden nodes and preserves empty layouts', async () => {
    expect(await templatePreview([])).toEqual([])
    expect(await templatePreview([node('image', {}, [], { style: { hide_on: ['desktop'] } }), node('text')])).toMatchObject([{ kind: 'text' }])
    expect(await templatePreview(Array.from({ length: 1000 }, () => node('text')))).toHaveLength(180)
  })

  it('deduplicates concurrent reads and allows retries after failure', async () => {
    const cached = previewCache()
    const read = vi.fn(async () => ({ nodes: [] }))
    await Promise.all([cached('layout', read), cached('layout', read)])
    expect(read).toHaveBeenCalledTimes(1)
    const failing = vi.fn().mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce([])
    await expect(cached('header', failing)).rejects.toThrow('offline')
    await expect(cached('header', failing)).resolves.toEqual([])
    expect(failing).toHaveBeenCalledTimes(2)
  })
})
