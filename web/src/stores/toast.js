import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useToastStore = defineStore('toast', () => {
  const toasts = ref([])
  let nextId = 0

  // Errors stay long enough to be read (and can be dismissed earlier).
  const DURATION = { success: 4000, error: 10000 }

  function show(message, type = 'success') {
    // A burst of failing requests should not stack identical messages.
    if (toasts.value.some((toast) => toast.message === message && toast.type === type)) return

    const id = nextId++
    toasts.value.push({ id, message, type })
    setTimeout(() => remove(id), DURATION[type] ?? DURATION.success)
  }

  function remove(id) {
    toasts.value = toasts.value.filter(t => t.id !== id)
  }

  const success = (msg) => show(msg, 'success')
  const error   = (msg) => show(msg, 'error')

  return { toasts, success, error, remove }
})
