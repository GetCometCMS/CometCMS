/** Compare each language with its saved form, rather than with the displayed language. */
export function hasEntryDraftChanges(drafts, savedFormForLocale, isNewTranslation = () => false) {
  return Object.entries(drafts).some(([language, draft]) => {
    if (draft.resets?.length || isNewTranslation(language)) return true
    const saved = savedFormForLocale(language) ?? {}
    const keys = new Set([...Object.keys(saved), ...Object.keys(draft.form)])
    return [...keys].some((key) => key !== 'locale'
      && JSON.stringify(draft.form[key]) !== JSON.stringify(saved[key]))
  })
}
