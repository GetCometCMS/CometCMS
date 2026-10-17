import { describe, expect, it } from 'vitest'
import {
  ancestors,
  applyColumnPreset,
  columnLayoutOf,
  stackColumns,
  canContain,
  cloneWithNewIds,
  countNodes,
  createNode,
  duplicateNode,
  effectiveSpan,
  findNode,
  insertNode,
  moveNode,
  removeNode,
  resolveDrop,
  sectionPreset,
  shiftNode,
  wrapFor,
} from './layoutTree.js'

const ELEMENTS = ['field', 'heading', 'text', 'image', 'button', 'spacer', 'divider', 'collection', 'menu']
const spec = {
  root: ['section'],
  item_elements: ['field', 'heading', 'text', 'image', 'button', 'spacer', 'divider'],
  kinds: {
    section: { props: { width: { default: 'contained' } }, children: ['row'] },
    row: { props: { gap: { default: 'md' } }, children: ['column'] },
    column: { props: { span: { default: { mobile: 12, tablet: 0, desktop: 0 } } }, children: ELEMENTS },
    heading: { props: { text: { default: 'Heading' }, level: { default: 'h2' } }, children: [] },
    field: { props: { field: { default: '' } }, children: ['field', 'heading', 'text'] },
    text: { props: {}, children: [] },
    collection: { props: {}, children: [] },
  },
}

function layout() {
  return [
    { id: 's1', kind: 'section', props: {}, style: {}, children: [
      { id: 'r1', kind: 'row', props: {}, style: {}, children: [
        { id: 'c1', kind: 'column', props: {}, style: {}, children: [
          { id: 'h1', kind: 'heading', props: {}, style: {}, children: [] },
          { id: 'f1', kind: 'field', props: { field: 'steps' }, style: {}, children: [
            { id: 'sub1', kind: 'field', props: { field: 'heading' }, style: {}, children: [] },
          ] },
        ] },
        { id: 'c2', kind: 'column', props: {}, style: {}, children: [] },
      ] },
    ] },
  ]
}

const repeaters = { isTemplateField: (node) => node.props.field === 'steps' }

describe('layout tree', () => {
  it('creates nodes with schema defaults', () => {
    const node = createNode(spec, 'heading', { level: 'h1' })
    expect(node.props).toEqual({ text: 'Heading', level: 'h1' })
    expect(node.id).toMatch(/^n[0-9a-f]{10}$/)
  })

  it('finds, counts and lists ancestors', () => {
    const nodes = layout()
    expect(findNode(nodes, 'sub1').props.field).toBe('heading')
    expect(countNodes(nodes)).toBe(7)
    expect(ancestors(nodes, 'sub1').map((n) => n.id)).toEqual(['s1', 'r1', 'c1', 'f1'])
  })

  it('only lets repeater fields hold an item template', () => {
    const nodes = layout()
    expect(canContain(spec, findNode(nodes, 'f1'), 'heading', repeaters)).toBe(true)
    expect(canContain(spec, findNode(nodes, 'h1'), 'heading', repeaters)).toBe(false)
    expect(canContain(spec, findNode(nodes, 'f1'), 'collection', repeaters)).toBe(false)
    expect(canContain(spec, null, 'heading')).toBe(false)
  })

  it('wraps elements in the structure their target needs', () => {
    const wrapped = wrapFor(spec, 'root', createNode(spec, 'heading'))
    expect(wrapped.kind).toBe('section')
    expect(wrapped.children[0].kind).toBe('row')
    expect(wrapped.children[0].children[0].kind).toBe('column')
    expect(wrapped.children[0].children[0].children[0].kind).toBe('heading')
    expect(wrapFor(spec, 'column', createNode(spec, 'section'))).toBeNull()
  })

  it('resolves drops by climbing to a parent that accepts the node', () => {
    const nodes = layout()
    const section = createNode(spec, 'section')
    expect(resolveDrop(spec, nodes, section, 'h1', 'after', repeaters)).toMatchObject({ parentId: null, index: 1 })

    const heading = createNode(spec, 'heading')
    expect(resolveDrop(spec, nodes, heading, 'c2', 'inside', repeaters)).toMatchObject({ parentId: 'c2', index: 0 })
    expect(resolveDrop(spec, nodes, heading, 'h1', 'before', repeaters)).toMatchObject({ parentId: 'c1', index: 0 })
    expect(resolveDrop(spec, nodes, heading, 'f1', 'inside', repeaters)).toMatchObject({ parentId: 'f1', index: 1 })

    const collection = createNode(spec, 'collection')
    const intoTemplate = resolveDrop(spec, nodes, collection, 'sub1', 'after', repeaters)
    expect(intoTemplate).toMatchObject({ parentId: 'c1', index: 2 })

    const atEnd = resolveDrop(spec, [], heading, null, 'inside', repeaters)
    expect(atEnd.parentId).toBeNull()
    expect(atEnd.node.kind).toBe('section')
  })

  it('inserts, moves, shifts, duplicates and removes nodes', () => {
    const nodes = layout()
    expect(insertNode(nodes, 'c2', 0, createNode(spec, 'text'))).toBe(true)
    expect(findNode(nodes, 'c2').children).toHaveLength(1)

    expect(moveNode(nodes, 'h1', 'c2', 1)).toBe(true)
    expect(findNode(nodes, 'c2').children.map((n) => n.id)[1]).toBe('h1')
    expect(moveNode(nodes, 's1', 'c1', 0)).toBe(false)

    expect(shiftNode(nodes, 'h1', -1)).toBe(true)
    expect(findNode(nodes, 'c2').children[0].id).toBe('h1')

    const copy = duplicateNode(nodes, 'f1')
    expect(copy.id).not.toBe('f1')
    expect(copy.children[0].id).not.toBe('sub1')
    expect(findNode(nodes, 'c1').children.map((n) => n.kind)).toEqual(['field', 'field'])

    expect(removeNode(nodes, 'c2').id).toBe('c2')
    expect(findNode(nodes, 'h1')).toBeNull()
  })

  it('moves within the same list accounting for the removed slot', () => {
    const nodes = [createNode(spec, 'section'), createNode(spec, 'section'), createNode(spec, 'section')]
    const [a, b, c] = nodes.map((n) => n.id)
    moveNode(nodes, a, null, 3)
    expect(nodes.map((n) => n.id)).toEqual([b, c, a])
  })

  it('builds column presets and resolves responsive spans', () => {
    const preset = sectionPreset(spec, [4, 8])
    const spans = preset.children[0].children.map((column) => column.props.span)
    expect(spans).toEqual([{ mobile: 12, tablet: 4, desktop: 0 }, { mobile: 12, tablet: 8, desktop: 0 }])
    expect(sectionPreset(spec, [4, 4, 4]).children[0].children[0].props.span).toEqual({ mobile: 12, tablet: 6, desktop: 4 })
    expect(effectiveSpan({ mobile: 12, tablet: 6, desktop: 0 }, 'desktop')).toBe(6)
    expect(effectiveSpan({ mobile: 12, tablet: 0, desktop: 4 }, 'tablet')).toBe(12)
  })

  it('rearranges columns without losing content', () => {
    const nodes = layout()
    const row = findNode(nodes, 'r1')

    applyColumnPreset(spec, row, [12])
    expect(row.children.map((column) => column.id)).toEqual(['c1'])
    expect(row.children[0].children.map((node) => node.id)).toEqual(['h1', 'f1'])
    expect(columnLayoutOf(row)).toEqual({ spans: [12], stacked: false })

    applyColumnPreset(spec, row, [4, 4, 4])
    expect(row.children).toHaveLength(3)
    expect(columnLayoutOf(row)).toEqual({ spans: [4, 4, 4], stacked: false })

    stackColumns(row)
    expect(columnLayoutOf(row)).toEqual({ spans: [12, 12, 12], stacked: true })
  })

  it('clones nodes with fresh ids', () => {
    const original = layout()[0]
    const copy = cloneWithNewIds(original)
    expect(copy.id).not.toBe(original.id)
    expect(countNodes([copy])).toBe(countNodes([original]))
  })
})
