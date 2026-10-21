import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { effectScope, nextTick, ref } from 'vue'
import { useBuilderPanels } from './useBuilderPanels.js'

let scope
let observe
let disconnect
let layout
let area
let storage
const ids = ['inspector', 'add', 'outline', 'fields', 'design', 'css', 'history']

async function fixture(options = {}) {
  area = ref(null)
  storage = { getItem: vi.fn(() => null), setItem: vi.fn() }
  scope = effectScope()
  layout = scope.run(() => useBuilderPanels({ area, ids, storage, ...options }))
  area.value = { getBoundingClientRect: () => ({ left: 100, top: 60 }) }
  await nextTick()
  observe([{ contentRect: { width: 1440, height: 900 } }])
  return layout
}

function pointer(type, x, y, pointerId = 1) {
  const event = new Event(type)
  Object.assign(event, { clientX: x, clientY: y, pointerId })
  window.dispatchEvent(event)
}
function start(x = 400, y = 160, fromRail = false, id = 'inspector') {
  const target = { setPointerCapture: vi.fn(), hasPointerCapture: () => true, releasePointerCapture: vi.fn(), focus: vi.fn() }
  const event = { button: 0, clientX: x, clientY: y, pointerId: 1, currentTarget: target, target: { closest: () => null }, preventDefault: vi.fn() }
  layout.startMove(event, id, fromRail)
  return { target, event }
}

beforeEach(() => {
  vi.stubGlobal('window', new EventTarget())
  vi.stubGlobal('innerWidth', 1440)
  vi.stubGlobal('innerHeight', 956)
  disconnect = vi.fn()
  vi.stubGlobal('ResizeObserver', class {
    constructor(callback) { observe = callback }
    observe() {}
    disconnect = disconnect
  })
})
afterEach(() => { scope?.stop(); vi.unstubAllGlobals(); vi.useRealTimers() })

describe('builder panel docking', () => {
  it('starts with tools on the left and the inspector on the right, plus multiple floating panels', async () => {
    await fixture()
    expect(layout.visible.value).toEqual(['inspector', 'add'])
    expect(layout.windows.value.inspector.mode).toBe('right')
    expect(layout.windows.value.add.mode).toBe('left')
    layout.open('outline', 'floating')
    layout.open('css', 'floating')
    expect(layout.visible.value).toEqual(['inspector', 'add', 'outline', 'css'])
    layout.open('history', 'right')
    expect(layout.windows.value.inspector.open).toBe(false)
    expect(layout.windows.value.add.open).toBe(true)
    expect(layout.windows.value.outline.open).toBe(true)
  })

  it('toggles panels without losing their placement, bounds or resize settings', async () => {
    await fixture()
    layout.open('css', 'floating')
    layout.resize('css', 650, 600)
    layout.move('css', 400, 100)
    layout.toggle('css')
    expect(layout.windows.value.css.open).toBe(false)
    layout.toggle('css')
    expect(layout.windows.value.css).toMatchObject({ open: true, mode: 'floating', width: 650, height: 600, x: 400, y: 100 })
  })

  it('resizes both docks to preserve space for the canvas when opening a wide panel', async () => {
    await fixture()
    observe([{ contentRect: { width: 1200, height: 900 } }])
    layout.resize('inspector', 560)
    layout.open('css', 'left')
    const { inspector, css } = layout.windows.value
    expect(inspector.open && css.open).toBe(true)
    expect(inspector.width + css.width + 52 + 320).toBeLessThanOrEqual(1200)
  })

  it('keeps floating windows in the editor and makes room when both docks no longer fit', async () => {
    await fixture()
    layout.open('css', 'floating')
    layout.move('css', 9999, 9999)
    expect(layout.windows.value.css.x + layout.windows.value.css.width).toBeLessThanOrEqual(1432)
    expect(layout.windows.value.css.y + layout.windows.value.css.height).toBeLessThanOrEqual(892)
    observe([{ contentRect: { width: 760, height: 450 } }])
    expect(layout.windows.value.inspector.mode).toBe('floating')
    expect(layout.windows.value.inspector.open).toBe(true)
    expect(layout.windows.value.css.x + layout.windows.value.css.width).toBeLessThanOrEqual(752)
    expect(layout.windows.value.css.y + layout.windows.value.css.height).toBeLessThanOrEqual(442)
  })

  it('restores valid preferences and lets an explicit CSS settings link override the left dock', async () => {
    const saved = { inspector: { open: true, mode: 'right', width: 380 }, add: { open: true, mode: 'left', width: 280 }, css: { open: false, mode: 'floating', x: 250, y: 40, width: 620 } }
    const preferences = { getItem: () => JSON.stringify(saved), setItem: vi.fn() }
    await fixture({ storage: preferences, initial: 'css' })
    expect(layout.windows.value.inspector).toMatchObject({ open: true, mode: 'right', width: 380 })
    expect(layout.windows.value.css).toMatchObject({ open: true, mode: 'left' })
    expect(layout.windows.value.add.open).toBe(false)
    layout.resize('css', 500)
    await nextTick()
    expect(preferences.setItem).toHaveBeenCalled()
  })

  it('restores a partial preference without opening two panels in the same dock', async () => {
    await fixture({ storage: { getItem: () => JSON.stringify({ add: { mode: 'left', open: true } }) } })
    expect(layout.visible.value).toEqual(['add'])
  })

  it('replaces the panel on an occupied side instead of moving it elsewhere', async () => {
    await fixture()
    layout.open('inspector', 'left')
    expect(layout.windows.value.add.open).toBe(false)
    layout.open('outline', 'floating')
    layout.open('outline', 'left')
    expect(layout.windows.value.inspector.open).toBe(false)
    expect(layout.windows.value.outline).toMatchObject({ open: true, mode: 'left' })
    layout.open('inspector')
    expect(layout.windows.value.outline.open).toBe(false)
    expect(layout.windows.value.inspector).toMatchObject({ open: true, mode: 'left' })
  })

  it('resets to the default layout', async () => {
    await fixture()
    layout.open('outline', 'left')
    layout.open('css', 'floating')
    layout.resize('inspector', 500)
    layout.reset()
    expect(layout.visible.value).toEqual(['inspector', 'add'])
    expect(layout.windows.value.add.mode).toBe('left')
    expect(layout.windows.value.inspector).toMatchObject({ mode: 'right', width: 320 })
  })

  it('pops a docked panel out next to its dock and returns it to that dock', async () => {
    await fixture()
    layout.toggleDock('inspector')
    const { inspector } = layout.windows.value
    expect(inspector.mode).toBe('floating')
    expect(inspector.x + inspector.width).toBeGreaterThan(1300)
    layout.toggleDock('inspector')
    expect(inspector.mode).toBe('right')
  })

  it('brings a covered floating window forward before a rail click closes it', async () => {
    await fixture()
    layout.open('outline', 'floating')
    layout.open('css', 'floating')
    layout.toggle('outline')
    expect(layout.windows.value.outline.open).toBe(true)
    expect(layout.windows.value.outline.z).toBeGreaterThan(layout.windows.value.css.z)
    layout.toggle('outline')
    expect(layout.windows.value.outline.open).toBe(false)
  })

  it('ignores broken preferences and works when browser storage is unavailable', async () => {
    await fixture({ storage: { getItem() { throw new Error('unavailable') }, setItem() { throw new Error('unavailable') } } })
    layout.open('outline', 'floating')
    await nextTick()
    expect(layout.windows.value.outline.open).toBe(true)
  })
})

describe('builder panel gestures', () => {
  it('resizes a floating panel in both dimensions without starting an edge snap', async () => {
    await fixture()
    layout.open('inspector', 'floating')
    layout.startResize({ button: 0, pointerId: 1, clientX: 400, clientY: 600, currentTarget: {}, target: {}, preventDefault() {} }, 'inspector')
    pointer('pointermove', 500, 650)
    expect(layout.windows.value.inspector).toMatchObject({ width: 420, height: 610 })
    expect(layout.resizing.value).toBe(true)
    expect(layout.snap.value).toBe(null)
    pointer('pointerup', 500, 650)
    expect(layout.resizing.value).toBe(false)
  })

  it('distinguishes a click from a drag, then floats a panel and snaps it on the right', async () => {
    await fixture()
    const { target } = start()
    pointer('pointermove', 403, 162)
    expect(layout.dragging.value).toBe(false)
    pointer('pointermove', 650, 250)
    expect(layout.windows.value.inspector.mode).toBe('floating')
    expect(layout.dragging.value).toBe(true)
    pointer('pointermove', 1520, 260)
    expect(layout.snap.value).toBe('right')
    pointer('pointerup', 1520, 260)
    expect(layout.windows.value.inspector.mode).toBe('right')
    expect(layout.dragging.value).toBe(false)
    expect(layout.snap.value).toBe(null)
    expect(target.releasePointerCapture).toHaveBeenCalledWith(1)
  })

  it('drags a closed rail panel into a floating window and suppresses the resulting click', async () => {
    await fixture()
    vi.useFakeTimers()
    start(130, 220, true, 'outline')
    pointer('pointermove', 700, 300)
    pointer('pointerup', 700, 300)
    expect(layout.windows.value.outline).toMatchObject({ open: true, mode: 'floating' })
    layout.toggle('outline')
    expect(layout.windows.value.outline.open).toBe(true)
    vi.runAllTimers()
    layout.toggle('outline')
    expect(layout.windows.value.outline.open).toBe(false)
  })

  it('previews a replacement and closes the displaced panel', async () => {
    await fixture()
    start()
    pointer('pointermove', 650, 250)
    pointer('pointermove', 140, 260)
    expect(layout.dropPreview.value).toMatchObject({ side: 'left', occupant: 'add' })
    pointer('pointerup', 140, 260)
    expect(layout.windows.value.inspector.mode).toBe('left')
    expect(layout.windows.value.add.open).toBe(false)
    expect(layout.dropPreview.value).toBe(null)
  })

  it('needs a deliberate pull to undock and restores the panel when Escape cancels the drag', async () => {
    await fixture()
    start()
    pointer('pointermove', 408, 160)
    expect(layout.dragging.value).toBe(false)
    pointer('pointermove', 650, 250)
    expect(layout.windows.value.inspector.mode).toBe('floating')
    const escape = new Event('keydown')
    escape.key = 'Escape'
    window.dispatchEvent(escape)
    expect(layout.windows.value.inspector).toMatchObject({ open: true, mode: 'right' })
    expect(layout.dragging.value).toBe(false)
  })

  it('cancels a snap on pointer cancellation and removes gesture listeners when disposed', async () => {
    await fixture()
    start()
    pointer('pointermove', 1520, 260)
    pointer('pointercancel', 1520, 260)
    expect(layout.windows.value.inspector.mode).toBe('floating')
    expect(layout.snap.value).toBe(null)
    start()
    pointer('pointermove', 650, 250)
    scope.stop()
    const position = layout.windows.value.inspector.x
    pointer('pointermove', 800, 250)
    expect(layout.windows.value.inspector.x).toBe(position)
    expect(layout.dragging.value).toBe(false)
    expect(disconnect).toHaveBeenCalled()
  })
})
