import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useToastStore } from './toast.js'

describe('toast store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('does not stack identical messages', () => {
    const toast = useToastStore()
    toast.error('Could not reach the server.')
    toast.error('Could not reach the server.')
    toast.success('Could not reach the server.')

    expect(toast.toasts.map((item) => item.type)).toEqual(['error', 'success'])
  })

  it('keeps errors visible longer than confirmations', () => {
    const toast = useToastStore()
    toast.success('Saved.')
    toast.error('Failed.')

    vi.advanceTimersByTime(5000)
    expect(toast.toasts.map((item) => item.message)).toEqual(['Failed.'])

    vi.advanceTimersByTime(6000)
    expect(toast.toasts).toEqual([])
  })
})
