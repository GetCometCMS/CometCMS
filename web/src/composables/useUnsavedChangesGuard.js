import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate } from 'vue-router'

/**
 * Ask before leaving a view with unsaved changes.
 *
 * In-app navigation waits for the returned `leavePromptOpen` confirm dialog;
 * closing or reloading the tab triggers the browser's own prompt. Call
 * `bypass()` right before navigating away programmatically after a successful
 * save or delete, when the changes are no longer at risk.
 *
 * `isDirty` is a ref, or a function of the destination route (undefined when
 * the tab closes) for changes that survive some in-app navigation.
 * `guardUpdates` also guards path changes within the same route record, for
 * editors whose keyed view remounts when a different document is selected.
 */
export function useUnsavedChangesGuard(isDirty, { guardUpdates = false } = {}) {
  const dirtyFor = typeof isDirty === 'function' ? isDirty : () => isDirty.value
  const leavePromptOpen = ref(false)
  let resolveLeave = null
  let bypassed = false

  function settle(allowed) {
    const resolve = resolveLeave
    resolveLeave = null
    resolve?.(allowed)
  }

  function guard(to) {
    if (bypassed || !dirtyFor(to)) return true
    leavePromptOpen.value = true
    return new Promise((resolve) => {
      resolveLeave = resolve
    })
  }

  onBeforeRouteLeave(guard)
  if (guardUpdates) {
    onBeforeRouteUpdate((to, from) => to.path === from.path || guard(to))
  }

  function confirmLeave() {
    settle(true)
    leavePromptOpen.value = false
  }

  // Closing the dialog any other way (Cancel, Escape, backdrop) keeps the user here.
  watch(leavePromptOpen, (open) => {
    if (!open) settle(false)
  })

  function bypass() {
    bypassed = true
  }

  function onBeforeUnload(event) {
    if (bypassed || !dirtyFor(undefined)) return
    event.preventDefault()
    event.returnValue = ''
  }

  onMounted(() => window.addEventListener('beforeunload', onBeforeUnload))
  onBeforeUnmount(() => window.removeEventListener('beforeunload', onBeforeUnload))

  return { leavePromptOpen, confirmLeave, bypass }
}
