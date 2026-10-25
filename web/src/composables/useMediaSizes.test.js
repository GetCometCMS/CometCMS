import { beforeEach, describe, expect, it, vi } from 'vitest'

const mocks = vi.hoisted(() => ({ sizeFiles: vi.fn(), regenerateSizes: vi.fn() }))
vi.mock('../api/index.js', () => ({ api: { media: { sizeFiles: mocks.sizeFiles, regenerateSizes: mocks.regenerateSizes } }, getActiveWorkspace: () => 'default' }))
import { REGENERATE_BATCH_SIZE, regenerateInBatches } from './useMediaSizes.js'

const names = Array.from({ length: 7 }, (_, index) => `image-${index}.jpg`)

describe('rebuilding image sizes', () => {
  beforeEach(() => {
    mocks.sizeFiles.mockReset().mockResolvedValue({ data: names })
    mocks.regenerateSizes.mockReset().mockImplementation(async (files) => ({ data: { generated: files.length, failed: 0, skipped: 0 } }))
  })

  it('sends every image in small batches and reports progress after each', async () => {
    const progress = []
    const summary = await regenerateInBatches(null, { onProgress: (done, total) => progress.push([done, total]) })

    expect(mocks.regenerateSizes).toHaveBeenCalledTimes(Math.ceil(names.length / REGENERATE_BATCH_SIZE))
    expect(mocks.regenerateSizes.mock.calls.flatMap(([files]) => files)).toEqual(names)
    expect(progress).toEqual([[0, 7], [3, 7], [6, 7], [7, 7]])
    expect(summary).toMatchObject({ generated: 7, failed: 0, total: 7, stopped: false })
  })

  it('stops between batches and rebuilds only the given files', async () => {
    let calls = 0
    const summary = await regenerateInBatches(names, { shouldStop: () => calls++ >= 1 })

    expect(mocks.sizeFiles).not.toHaveBeenCalled()
    expect(mocks.regenerateSizes).toHaveBeenCalledTimes(1)
    expect(summary).toMatchObject({ generated: REGENERATE_BATCH_SIZE, stopped: true })
  })
})
