import { computed, onScopeDispose, ref, watch } from 'vue'

const STORAGE_KEY = 'cometcms-builder-panels-v2'
const DOCKS = ['left', 'right']
const MODES = [...DOCKS, 'floating']
const RAIL_WIDTH = 52
const GAP = 8
/** Distance from an edge at which a dragged panel offers to dock. */
const SNAP_ZONE = 64
/** Pointer travel before a press becomes a drag; docked panels need a more deliberate pull. */
const MOVE_THRESHOLD = 6
const TEAR_OFF_THRESHOLD = 10
const minimumWidth = (id) => ({ inspector: 288, design: 320, css: 360, fields: 320 })[id] ?? 224
const maximumWidth = (id) => id === 'css' ? 720 : 560
const clamp = (value, min, max) => Math.max(min, Math.min(max, value))

/**
 * One panel per dock, with independent floating windows inside the editor.
 * Docking onto an occupied side replaces the panel there; it stays one click away on the rail.
 */
export function useBuilderPanels({ area, ids, docked = { left: 'add', right: 'inspector' }, initial = null, storage } = {}) {
  if (storage === undefined) {
    try { storage = globalThis.localStorage } catch { storage = null }
  }
  const bounds = ref({ width: globalThis.innerWidth ?? 1280, height: Math.max(1, (globalThis.innerHeight ?? 856) - 48) })
  /** The layout a first visit starts with. */
  const defaults = () => Object.fromEntries(ids.map((id, index) => {
    const side = DOCKS.find((key) => docked[key] === id)
    return [id, {
      open: !!side, mode: side ?? 'left', dock: side ?? 'left', width: Math.max(minimumWidth(id), 320),
      height: 560, x: RAIL_WIDTH + 24 + index * 24, y: 24 + index * 24, z: index,
    }]
  }))
  const windows = ref(defaults())
  const dragging = ref(false)
  const resizing = ref(false)
  const snap = ref(null)
  /** The panel being dragged and where it was before the drag began. */
  const gesture = ref(null)
  const suppressClick = ref(false)
  let cleanupGesture = null
  let observer = null

  try {
    const saved = JSON.parse(storage?.getItem(STORAGE_KEY) ?? 'null')
    const occupied = new Set()
    if (ids.some((id) => MODES.includes(saved?.[id]?.mode))) {
      for (const panel of Object.values(windows.value)) panel.open = false
    }
    for (const id of ids) {
      const value = saved?.[id]
      if (!value || !MODES.includes(value.mode)) continue
      const panel = windows.value[id]
      panel.mode = value.mode
      panel.dock = DOCKS.includes(value.dock) ? value.dock : DOCKS.includes(value.mode) ? value.mode : panel.dock
      panel.open = value.open === true && (value.mode === 'floating' || !occupied.has(value.mode))
      if (panel.open) occupied.add(panel.mode)
      for (const key of ['width', 'height', 'x', 'y', 'z']) if (Number.isFinite(value[key])) panel[key] = value[key]
    }
  } catch { /* Local preferences are optional. */ }
  // Explicit settings links should open the requested panel.
  if (initial) open(initial, 'left')

  function limits(id) {
    const panel = windows.value[id]
    const other = Object.entries(windows.value).find(([key, item]) => key !== id && item.open && item.mode !== 'floating' && item.mode !== panel.mode)?.[1]
    const available = panel.mode === 'floating'
      ? bounds.value.width - RAIL_WIDTH - GAP * 2
      : bounds.value.width - RAIL_WIDTH - (other?.width ?? 0) - 320
    const min = Math.min(minimumWidth(id), Math.max(1, bounds.value.width - RAIL_WIDTH - GAP * 2))
    const max = Math.max(min, Math.min(maximumWidth(id), available))
    return { min, max }
  }

  function constrain(id) {
    const panel = windows.value[id]
    const { min, max } = limits(id)
    panel.width = clamp(panel.width, min, max)
    const maxHeight = Math.max(1, bounds.value.height - GAP * 2)
    panel.height = clamp(panel.height, Math.min(200, maxHeight), maxHeight)
    panel.x = clamp(panel.x, RAIL_WIDTH + GAP, Math.max(RAIL_WIDTH + GAP, bounds.value.width - panel.width - GAP))
    panel.y = clamp(panel.y, GAP, Math.max(GAP, bounds.value.height - panel.height - GAP))
  }

  function focus(id) {
    const order = ids.filter((key) => key !== id).sort((a, b) => windows.value[a].z - windows.value[b].z)
    order.push(id)
    order.forEach((key, index) => { windows.value[key].z = index })
  }

  /** Whether no other floating window covers this one. */
  function inFront(id) {
    const z = windows.value[id].z
    return ids.every((key) => key === id || !windows.value[key].open || windows.value[key].mode !== 'floating' || windows.value[key].z < z)
  }

  function occupantOf(side, except) {
    return ids.find((key) => key !== except && windows.value[key].open && windows.value[key].mode === side) ?? null
  }

  /** Places a panel leaving a dock right next to where it was docked. */
  function popOut(id, side) {
    const panel = windows.value[id]
    panel.x = side === 'right' ? bounds.value.width - panel.width - GAP * 3 : RAIL_WIDTH + GAP * 3
    panel.y = GAP * 3
  }

  /**
   * Shows a panel in the given place. `origin` is where the panel was before,
   * so popping out places it next to the dock it left.
   */
  function open(id, mode = windows.value[id]?.mode, origin = windows.value[id]) {
    const panel = windows.value[id]
    if (!panel || !MODES.includes(mode)) return
    const from = { open: origin.open, mode: origin.mode }
    if (mode !== 'floating') {
      const occupant = occupantOf(mode, id)
      if (occupant) windows.value[occupant].open = false
      panel.dock = mode
    } else if (from.open && from.mode !== 'floating') {
      popOut(id, from.mode)
    }
    Object.assign(panel, { open: true, mode })
    makeRoom(id)
    ids.forEach(constrain)
    focus(id)
  }

  function makeRoom(id) {
    const panel = windows.value[id]
    if (panel.mode === 'floating') return
    const other = ids.find((key) => key !== id && windows.value[key].open && windows.value[key].mode !== 'floating')
    if (other && bounds.value.width < RAIL_WIDTH + minimumWidth(id) + minimumWidth(other) + 320) {
      popOut(other, windows.value[other].mode)
      windows.value[other].mode = 'floating'
      constrain(other)
    }
  }

  /** Rail click: a floating window hidden behind another comes forward before it closes. */
  function toggle(id) {
    if (suppressClick.value) { suppressClick.value = false; return }
    const panel = windows.value[id]
    if (panel.open && panel.mode === 'floating' && !inFront(id)) focus(id)
    else if (panel.open) panel.open = false
    else open(id)
  }

  /** Floats a docked panel, or returns a floating one to the side it last docked on. */
  function toggleDock(id) {
    const panel = windows.value[id]
    open(id, panel.mode === 'floating' ? panel.dock : 'floating')
  }

  /** Back to the default layout, for when panels end up somewhere unhelpful. */
  function reset() {
    cleanupGesture?.()
    windows.value = defaults()
    if (windows.value[docked.left]) makeRoom(docked.left)
    ids.forEach(constrain)
  }

  function resize(id, width, height = windows.value[id].height) {
    Object.assign(windows.value[id], { width, height })
    constrain(id)
  }

  function move(id, x, y) {
    Object.assign(windows.value[id], { x, y })
    constrain(id)
  }

  function startGesture(event, id, resizeWindow = false, fromRail = false) {
    if (event.button !== 0 || (!fromRail && event.target?.closest?.('button, input, select, textarea, a'))) return
    cleanupGesture?.()
    event.preventDefault()
    const target = event.currentTarget
    // Pointer focus: no keyboard focus ring after a click.
    if (fromRail) target.focus?.({ focusVisible: false })
    target.setPointerCapture?.(event.pointerId)
    const origin = { ...windows.value[id] }
    const start = { ...origin }
    const pointer = { x: event.clientX, y: event.clientY }
    const tearsOff = !resizeWindow && (fromRail || !origin.open || origin.mode !== 'floating')
    const threshold = tearsOff ? TEAR_OFF_THRESHOLD : MOVE_THRESHOLD
    let moved = false
    // A rail press may still turn out to be a click, which decides on its own whether to focus.
    if (!fromRail) focus(id)

    function update(current) {
      if (current.pointerId !== event.pointerId) return
      const dx = current.clientX - pointer.x
      const dy = current.clientY - pointer.y
      if (!moved && Math.hypot(dx, dy) < threshold) return
      if (!moved) {
        moved = true
        dragging.value = true
        resizing.value = resizeWindow
        suppressClick.value = fromRail
        gesture.value = { id, origin }
        if (tearsOff) {
          open(id, 'floating')
          const rect = area.value?.getBoundingClientRect() ?? { left: 0, top: 0 }
          start.width = windows.value[id].width
          start.x = pointer.x - rect.left - Math.min(start.width / 2, 120)
          start.y = pointer.y - rect.top - 20
        }
        focus(id)
      }
      if (resizeWindow) resize(id, start.width + dx, start.height + dy)
      else {
        move(id, start.x + dx, start.y + dy)
        const rect = area.value?.getBoundingClientRect() ?? { left: 0 }
        const x = current.clientX - rect.left
        snap.value = x <= RAIL_WIDTH + SNAP_ZONE ? 'left' : x >= bounds.value.width - SNAP_ZONE ? 'right' : null
      }
    }

    function finish(current) {
      if (current.pointerId !== event.pointerId) return
      const destination = current.type === 'pointerup' && moved ? snap.value : null
      cleanupGesture?.()
      if (destination) open(id, destination, origin)
      // Pointer capture can generate a click immediately after the drag.
      if (fromRail) setTimeout(() => { suppressClick.value = false }, 0)
    }

    /** Escape puts the panel back where the drag started. */
    function abort(current) {
      if (current.key !== 'Escape') return
      current.preventDefault?.()
      current.stopPropagation?.()
      const restore = moved
      cleanupGesture?.()
      if (!restore) return
      Object.assign(windows.value[id], origin)
      ids.forEach(constrain)
      if (fromRail) setTimeout(() => { suppressClick.value = false }, 0)
    }

    cleanupGesture = () => {
      window.removeEventListener('pointermove', update)
      window.removeEventListener('pointerup', finish)
      window.removeEventListener('pointercancel', finish)
      window.removeEventListener('keydown', abort, true)
      window.removeEventListener('blur', cancel)
      if (target.hasPointerCapture?.(event.pointerId)) target.releasePointerCapture(event.pointerId)
      dragging.value = false
      resizing.value = false
      snap.value = null
      gesture.value = null
      cleanupGesture = null
    }
    function cancel() { cleanupGesture?.() }
    window.addEventListener('pointermove', update)
    window.addEventListener('pointerup', finish)
    window.addEventListener('pointercancel', finish)
    window.addEventListener('keydown', abort, true)
    window.addEventListener('blur', cancel)
  }

  /** Where a drop would dock the dragged panel and what happens to the panel already there. */
  const dropPreview = computed(() => {
    if (!snap.value || !gesture.value) return null
    const { id } = gesture.value
    const occupant = occupantOf(snap.value, id)
    return {
      side: snap.value,
      offset: snap.value === 'left' ? RAIL_WIDTH : 0,
      width: clamp(windows.value[id].width, minimumWidth(id), maximumWidth(id)),
      occupant,
    }
  })

  watch(area, (element) => {
    observer?.disconnect()
    if (!element) return
    observer = new ResizeObserver(([entry]) => {
      bounds.value = { width: entry.contentRect.width, height: entry.contentRect.height }
      const left = ids.find((id) => windows.value[id].open && windows.value[id].mode === 'left')
      if (left) makeRoom(left)
      ids.forEach(constrain)
    })
    observer.observe(element)
  }, { flush: 'post' })

  watch(windows, (value) => {
    try { storage?.setItem(STORAGE_KEY, JSON.stringify(value)) } catch { /* Local preferences are optional. */ }
  }, { deep: true })
  onScopeDispose(() => { cleanupGesture?.(); observer?.disconnect() })

  return {
    windows, bounds, dragging, resizing, snap, dropPreview, limits, open, toggle, toggleDock, reset, resize, move, focus,
    visible: computed(() => ids.filter((id) => windows.value[id].open)),
    close: (id) => { windows.value[id].open = false },
    startMove: (event, id, fromRail = false) => startGesture(event, id, false, fromRail),
    startResize: (event, id) => startGesture(event, id, true),
  }
}
