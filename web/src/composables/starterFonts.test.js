import { beforeEach, describe, expect, it, vi } from 'vitest'
import { installStarterFonts } from './starterFonts.js'
import { api } from '../api/index.js'

vi.mock('../api/index.js', () => ({ api: { site: { get: vi.fn(), saveTheme: vi.fn() }, fonts: { install: vi.fn(), step: vi.fn() } } }))

beforeEach(() => {
  vi.resetAllMocks()
  api.site.get.mockResolvedValue({ data: { font_families: [], theme: { base_size: 18, fonts: { body: 'system', heading: 'system' } } } })
  api.fonts.install.mockImplementation(async ({ id }) => ({ data: { id, cursor: 0, status: 'pending' } }))
  api.fonts.step.mockImplementation(async (id, cursor) => ({ data: { id, cursor: cursor + 1, status: cursor === 0 ? 'pending' : 'complete' } }))
})

describe('website starter fonts', () => {
  it('downloads exactly two ordinary families and applies them only after both finish in the new workspace', async () => {
    await installStarterFonts('new-website')
    expect(api.fonts.install.mock.calls).toEqual([
      [{ id: 'inter', subsets: ['latin', 'latin-ext'] }, 'new-website'],
      [{ id: 'lora', subsets: ['latin', 'latin-ext'] }, 'new-website'],
    ])
    expect(api.fonts.step.mock.calls).toEqual([
      ['inter', 0, 'new-website'], ['inter', 1, 'new-website'],
      ['lora', 0, 'new-website'], ['lora', 1, 'new-website'],
    ])
    expect(api.site.saveTheme).toHaveBeenCalledWith({ base_size: 18, fonts: { body: 'fontsource-inter', heading: 'fontsource-lora' } }, 'new-website')
    expect(api.site.saveTheme.mock.invocationCallOrder[0]).toBeGreaterThan(api.fonts.step.mock.invocationCallOrder.at(-1))
  })

  it('keeps the working system-font theme if a download fails, with the pending job available for resuming', async () => {
    api.fonts.step.mockRejectedValueOnce(new Error('Provider offline'))
    await expect(installStarterFonts('new-website')).rejects.toThrow('Provider offline')
    expect(api.site.saveTheme).not.toHaveBeenCalled()
    expect(api.fonts.install).toHaveBeenCalledTimes(1)
  })

  it('reuses completed installations when retrying after an interrupted setup', async () => {
    api.site.get.mockResolvedValue({ data: { font_families: [{ id: 'fontsource-inter' }], theme: {} } })
    await installStarterFonts('new-website')
    expect(api.fonts.install).toHaveBeenCalledTimes(1)
    expect(api.fonts.install).toHaveBeenCalledWith({ id: 'lora', subsets: ['latin', 'latin-ext'] }, 'new-website')
  })
})
