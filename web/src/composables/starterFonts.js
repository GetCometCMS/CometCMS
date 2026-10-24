import { api } from '../api/index.js'

/** The website starter uses ordinary, removable catalogue installations. */
export async function installStarterFonts(workspace) {
  const { data } = await api.site.get(workspace)
  const families = new Set((data.font_families ?? []).map((font) => font.id))
  for (const id of ['inter', 'lora']) {
    if (families.has(`fontsource-${id}`)) continue
    let { data: job } = await api.fonts.install({ id, subsets: ['latin', 'latin-ext'] }, workspace)
    while (job.status === 'pending') {
      const response = await api.fonts.step(job.id, job.cursor, workspace)
      job = response.data
    }
    if (job.status !== 'complete') throw new Error('Demo font installation did not complete.')
  }
  // Keep the system-font design until both families are available.
  await api.site.saveTheme({ ...data.theme, fonts: { body: 'fontsource-inter', heading: 'fontsource-lora' } }, workspace)
}
