import { api } from '../api/index.js'

/** The named sizes every upload gets, largest side in pixels. */
export const IMAGE_SIZE_NAMES = ['thumbnail', 'medium', 'large']

/** Files rebuilt per request, so no request runs into the server's time limit. */
export const REGENERATE_BATCH_SIZE = 3

/**
 * Rebuild the sizes of the given files, or of every resizable file, a few per
 * request. `onProgress(done, total)` follows each batch; `shouldStop()` ends
 * the run between batches. Resolves to the summed summary.
 */
export async function regenerateInBatches(files = null, { onProgress = () => {}, shouldStop = () => false } = {}) {
  const names = files ?? (await api.media.sizeFiles()).data ?? []
  const summary = { generated: 0, failed: 0, skipped: 0, total: names.length, stopped: false }
  onProgress(0, names.length)
  for (let index = 0; index < names.length; index += REGENERATE_BATCH_SIZE) {
    if (shouldStop()) {
      summary.stopped = true
      break
    }
    const result = (await api.media.regenerateSizes(names.slice(index, index + REGENERATE_BATCH_SIZE))).data ?? {}
    summary.generated += Number(result.generated ?? 0)
    summary.failed += Number(result.failed ?? 0)
    summary.skipped += Number(result.skipped ?? 0)
    onProgress(Math.min(names.length, index + REGENERATE_BATCH_SIZE), names.length)
  }
  return summary
}
