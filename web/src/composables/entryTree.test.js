import { describe, expect, it } from 'vitest'
import { dropPlacement, subtreeKeys, treeRows } from './entryTree.js'

const page = (uid, parent = '', position = 0) => ({ uid, id: uid, slug: uid, parent, position })
const entries = [
  page('contact', '', 1),
  page('team', 'about', 0),
  page('about', '', 0),
  page('berlin', 'team', 0),
  page('history', 'about', 1),
]

describe('entry tree', () => {
  it('lists pages depth-first in saved order with their paths', () => {
    expect(treeRows(entries).map(({ entry, depth, path }) => [entry.uid, depth, path])).toEqual([
      ['about', 0, 'about'],
      ['team', 1, 'about/team'],
      ['berlin', 2, 'about/team/berlin'],
      ['history', 1, 'about/history'],
      ['contact', 0, 'contact'],
    ])
    expect(treeRows(entries)[0].hasChildren).toBe(true)
  })

  it('hides children of collapsed pages and lifts orphans to the top level', () => {
    expect(treeRows(entries, new Set(['about'])).map((row) => row.entry.uid)).toEqual(['about', 'contact'])
    expect(treeRows([page('lost', 'gone')])[0]).toMatchObject({ depth: 0, parent: '' })
  })

  it('collects a page and its descendants', () => {
    expect([...subtreeKeys(entries, 'about')].sort()).toEqual(['about', 'berlin', 'history', 'team'])
  })

  it('places drops among siblings without counting the dragged page', () => {
    const byUid = Object.fromEntries(entries.map((entry) => [entry.uid, entry]))
    expect(dropPlacement(entries, byUid.contact, byUid.team, 'before')).toEqual({ parent: 'about', index: 0 })
    expect(dropPlacement(entries, byUid.contact, byUid.team, 'after')).toEqual({ parent: 'about', index: 1 })
    expect(dropPlacement(entries, byUid.contact, byUid.berlin, 'inside')).toEqual({ parent: 'berlin', index: 0 })
    expect(dropPlacement(entries, byUid.about, byUid.contact, 'after')).toEqual({ parent: '', index: 1 })
    expect(dropPlacement(entries, byUid.about, byUid.berlin, 'inside')).toBeNull()
    expect(dropPlacement(entries, byUid.about, byUid.about, 'before')).toBeNull()
  })
})
