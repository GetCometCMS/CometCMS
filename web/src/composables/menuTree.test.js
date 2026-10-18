import { describe, expect, it } from 'vitest'
import { DEFAULT_MENU_LIMITS, itemCount, menuDrop, menuMoveTarget, moveMenuItem } from './menuTree.js'
const node = (id, children = []) => ({ id, label: id, labels: { de: id }, link: { type: 'home' }, children })
const chain = (length) => node(`n${length}`, length > 1 ? [chain(length - 1)] : [])

describe('menu tree movement', () => {
  it('reorders both directions without losing the subtree or translations', () => {
    const branch = node('a', [node('child')])
    const items = [branch, node('b'), node('c')]
    expect(moveMenuItem(items, 'a', 'c', 'after')).toBe(true)
    expect(items.map((item) => item.id)).toEqual(['b', 'c', 'a'])
    expect(items[2]).toBe(branch)
    expect(moveMenuItem(items, 'a', 'b', 'before')).toBe(true)
    expect(items[0].children[0].labels).toEqual({ de: 'child' })
    expect(itemCount(items)).toBe(4)
  })

  it('nests, outdents after the parent, and moves to the root end', () => {
    const items = [node('a'), node('b', [node('child')]), node('c')]
    expect(menuMoveTarget(items, 'b', 'indent')).toEqual(['a', 'inside'])
    expect(moveMenuItem(items, 'b', ...menuMoveTarget(items, 'b', 'indent'))).toBe(true)
    expect(items[0].children[0].id).toBe('b')
    expect(menuMoveTarget(items, 'b', 'outdent')).toEqual(['a', 'after'])
    moveMenuItem(items, 'b', ...menuMoveTarget(items, 'b', 'outdent'))
    expect(items.map((item) => item.id)).toEqual(['a', 'b', 'c'])
    moveMenuItem(items, 'child', null, 'inside')
    expect(items.at(-1).id).toBe('child')
  })

  it('rejects self and descendant drops without changing anything', () => {
    const items = [node('parent', [node('child', [node('grandchild')])])]
    const snapshot = JSON.stringify(items)
    for (const position of ['before', 'after', 'inside']) {
      expect(moveMenuItem(items, 'parent', 'child', position)).toBe(false)
      expect(moveMenuItem(items, 'parent', 'parent', position)).toBe(false)
    }
    expect(JSON.stringify(items)).toBe(snapshot)
  })

  it('allows exactly five levels and rejects a subtree that would exceed the limit', () => {
    const items = [chain(4), node('leaf'), node('branch', [node('child')])]
    expect(menuDrop(items, 'leaf', 'n1', 'inside')).not.toBeNull()
    expect(menuDrop(items, 'branch', 'n1', 'inside')).toBeNull()
    moveMenuItem(items, 'leaf', 'n1', 'inside')
    expect(menuDrop(items, 'branch', 'leaf', 'inside')).toBeNull()
  })

  it('checks sibling capacity but permits reordering a full list', () => {
    const limits = { ...DEFAULT_MENU_LIMITS, max_siblings: 2 }
    const items = [node('a', [node('x'), node('y')]), node('b')]
    expect(menuDrop(items, 'b', 'a', 'inside', limits)).toBeNull()
    expect(moveMenuItem(items, 'x', 'y', 'after', limits)).toBe(true)
    expect(moveMenuItem(items, 'x', null, 'inside', limits)).toBe(false)
    expect(menuMoveTarget(items, 'a', 'up')).toBeNull()
    expect(menuMoveTarget(items, 'a', 'outdent')).toBeNull()
  })
})
