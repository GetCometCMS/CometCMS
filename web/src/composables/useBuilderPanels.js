import { computed, onScopeDispose, ref, watch } from 'vue'

const STORAGE_KEY = 'cometcms-builder-panels'
const MODES = ['left', 'right', 'floating']
const RAIL_WIDTH = 76
const GAP = 8
const minimumWidth = (id) => ({ inspector: 288, design: 320, css: 360, fields: 320 })[id] ?? 224
const maximumWidth = (id) => id === 'css' ? 720 : 560
const clamp = (value, min, max) => Math.max(min, Math.min(max, value))

/** One panel per dock, with independent floating windows inside the editor. */
export function useBuilderPanels({ area, ids, initial = 'inspector', storage } = {}) {
  if (storage === undefined) {
    try { storage = globalThis.localStorage } catch { storage = null }
  }
  const bounds = ref({ width: globalThis.innerWidth ?? 1280, height: Math.max(1, (globalThis.innerHeight ?? 856) - 56) })
  const windows = ref(Object.fromEntries(ids.map((id, index) => [id, {
    open: id === initial, mode: 'left', width: Math.max(minimumWidth(id), 320),
    height: 560, x: RAIL_WIDTH + 24 + index * 24, y: 24 + index * 24, z: index,
  }])))
  const dragging = ref(false)
  const resizing = ref(false)
  const snap = ref(null)
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
      panel.open = value.open === true && (value.mode === 'floating' || !occupied.has(value.mode))
      if (panel.open) occupied.add(panel.mode)
      for (const key of ['width', 'height', 'x', 'y', 'z']) if (Number.isFinite(value[key])) panel[key] = value[key]
    }
    // Explicit settings links should open the requested panel.
    if (initial !== 'inspector') open(initial, 'left')
  } catch { /* Local preferences are optional. */ }

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

  function open(id, mode = windows.value[id]?.mode) {
    if (!windows.value[id] || !MODES.includes(mode)) return
    if (mode !== 'floating') {
      for (const [key, item] of Object.entries(windows.value)) if (key !== id && item.mode === mode) item.open = false
    }
    Object.assign(windows.value[id], { open: true, mode })
    makeRoom(id)
    ids.forEach(constrain)
    focus(id)
  }

  function makeRoom(id) {
    const panel = windows.value[id]
    if (panel.mode === 'floating') return
    const other = ids.find((key) => key !== id && windows.value[key].open && windows.value[key].mode !== 'floating')
    if (other && bounds.value.width < RAIL_WIDTH + minimumWidth(id) + minimumWidth(other) + 320) {
      windows.value[other].mode = 'floating'
      constrain(other)
    }
  }

  function toggle(id) {
    if (suppressClick.value) { suppressClick.value = false; return }
    if (windows.value[id].open) windows.value[id].open = false
    else open(id)
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
    if (fromRail) target.focus?.()
    target.setPointerCapture?.(event.pointerId)
    const start = { ...windows.value[id] }
    const pointer = { x: event.clientX, y: event.clientY }
    let moved = false
    focus(id)

    function update(current) {
      if (current.pointerId !== event.pointerId) return
      const dx = current.clientX - pointer.x
      const dy = current.clientY - pointer.y
      if (!moved && Math.hypot(dx, dy) < 6) return
      if (!moved) {
        moved = true
        dragging.value = true
        resizing.value = resizeWindow
        suppressClick.value = fromRail
        if (!resizeWindow && (fromRail || windows.value[id].mode !== 'floating')) {
          open(id, 'floating')
          const rect = area.value?.getBoundingClientRect() ?? { left: 0, top: 0 }
          start.x = pointer.x - rect.left - Math.min(start.width / 2, 120)
          start.y = pointer.y - rect.top - 20
        }
        if (!resizeWindow) open(id, 'floating')
      }
      if (resizeWindow) resize(id, start.width + dx, start.height + dy)
      else {
        move(id, start.x + dx, start.y + dy)
        const rect = area.value?.getBoundingClientRect() ?? { left: 0 }
        const x = current.clientX - rect.left
        snap.value = x <= RAIL_WIDTH + 40 ? 'left' : x >= bounds.value.width - 40 ? 'right' : null
      }
    }

    function finish(current) {
      if (current.pointerId !== event.pointerId) return
      const destination = current.type === 'pointerup' && moved ? snap.value : null
      cleanupGesture?.()
      if (destination) open(id, destination)
      // Pointer capture can generate a click immediately after the drag.
      if (fromRail) setTimeout(() => { suppressClick.value = false }, 0)
    }

    cleanupGesture = () => {
      window.removeEventListener('pointermove', update)
      window.removeEventListener('pointerup', finish)
      window.removeEventListener('pointercancel', finish)
      window.removeEventListener('blur', cancel)
      if (target.hasPointerCapture?.(event.pointerId)) target.releasePointerCapture(event.pointerId)
      dragging.value = false
      resizing.value = false
      snap.value = null
      cleanupGesture = null
    }
    function cancel() { cleanupGesture?.() }
    window.addEventListener('pointermove', update)
    window.addEventListener('pointerup', finish)
    window.addEventListener('pointercancel', finish)
    window.addEventListener('blur', cancel)
  }

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
    windows, bounds, dragging, resizing, snap, limits, open, toggle, resize, move, focus,
    visible: computed(() => ids.filter((id) => windows.value[id].open)),
    close: (id) => { windows.value[id].open = false },
    startMove: (event, id, fromRail = false) => startGesture(event, id, false, fromRail),
    startResize: (event, id) => startGesture(event, id, true),
  }
}
