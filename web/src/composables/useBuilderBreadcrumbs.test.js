import { computed, ref } from 'vue'
import { describe, expect, it } from 'vitest'
import { findNode } from './layoutTree.js'
import { useBuilderBreadcrumbs } from './useBuilderBreadcrumbs.js'

function setup() {
  const nodes = ref([
    { id: 'section', children: [
      { id: 'column', children: [{ id: 'heading' }, { id: 'text' }] },
      { id: 'other-column', children: [{ id: 'image' }] },
    ] },
    { id: 'other-section' },
  ])
  const selectedId = ref('heading')
  const builder = {
    nodes,
    selected: computed(() => findNode(nodes.value, selectedId.value)),
    isCard: (node) => node.id === 'card',
  }
  const breadcrumbs = useBuilderBreadcrumbs(builder)
  return { nodes, selectedId, breadcrumbs, ids: () => breadcrumbs.value.map((node) => node.id) }
}

describe('builder breadcrumbs', () => {
  it('keeps descendants available when navigating up and back down', () => {
    const { selectedId, ids } = setup()
    expect(ids()).toEqual(['section', 'column', 'heading'])
    for (const id of ['section', 'column', 'heading']) {
      selectedId.value = id
      expect(ids()).toEqual(['section', 'column', 'heading'])
    }
  })

  it('replaces the remembered descendants with a different selection', () => {
    const { selectedId, ids } = setup()
    ids()
    selectedId.value = 'column'
    ids()
    selectedId.value = 'text'
    expect(ids()).toEqual(['section', 'column', 'text'])
    selectedId.value = 'image'
    expect(ids()).toEqual(['section', 'other-column', 'image'])
    selectedId.value = 'other-section'
    expect(ids()).toEqual(['other-section'])
  })

  it('uses live nodes after tree replacement and trims deleted descendants', () => {
    const { nodes, selectedId, breadcrumbs, ids } = setup()
    ids()
    selectedId.value = 'section'
    ids()
    nodes.value = JSON.parse(JSON.stringify(nodes.value))
    findNode(nodes.value, 'heading').label = 'Updated heading'
    expect(breadcrumbs.value.at(-1).label).toBe('Updated heading')
    findNode(nodes.value, 'column').children = []
    expect(ids()).toEqual(['section', 'column'])
  })

  it('trims descendants moved out of the remembered branch', () => {
    const { nodes, selectedId, ids } = setup()
    ids()
    selectedId.value = 'section'
    ids()
    const heading = findNode(nodes.value, 'column').children.shift()
    findNode(nodes.value, 'other-column').children.push(heading)
    expect(ids()).toEqual(['section', 'column'])
  })

  it('clears the trail when selection is cleared', () => {
    const { selectedId, ids } = setup()
    ids()
    selectedId.value = null
    expect(ids()).toEqual([])
    selectedId.value = 'section'
    expect(ids()).toEqual(['section'])
  })

  it('keeps a card as the local root without hiding selections outside it', () => {
    const { nodes, selectedId, ids } = setup()
    findNode(nodes.value, 'column').id = 'card'
    expect(ids()).toEqual(['card', 'heading'])
    selectedId.value = 'card'
    expect(ids()).toEqual(['card', 'heading'])
    selectedId.value = 'section'
    expect(ids()).toEqual(['section', 'card', 'heading'])
  })
})
