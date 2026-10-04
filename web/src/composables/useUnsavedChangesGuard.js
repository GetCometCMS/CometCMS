import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'

/**
 * Ask before leaving a view with unsaved changes.
 *
 * In-app navigation waits for the returned `leavePromptOpen` confirm dialog;
 * closing or reloading the tab triggers the browser's own prompt. Call
 * `bypass()` right before navigating away programmatically after a successful
 * save or delete, when the changes are no longer at risk.
 */
export function useUnsavedChangesGuard(isDirty) {
  const leavePromptOpen = ref(false)
  let resolveLeave = null
  let bypassed = false

  function settle(allowed) {
    const resolve = resolveLeave
    resolveLeave = null
    resolve?.(allowed)
  }

  onBeforeRouteLeave(() => {
    if (bypassed || !isDirty.value) return true
    leavePromptOpen.value = true
    return new Promise((resolve) => {
      resolveLeave = resolve
    })
  })

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
    if (bypassed || !isDirty.value) return
    event.preventDefault()
    event.returnValue = ''
  }

  onMounted(() => window.addEventListener('beforeunload', onBeforeUnload))
  onBeforeUnmount(() => window.removeEventListener('beforeunload', onBeforeUnload))

  return { leavePromptOpen, confirmLeave, bypass }
}
