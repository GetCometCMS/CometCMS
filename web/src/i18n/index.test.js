import { describe, expect, it } from 'vitest'
import { ADMIN_LANGUAGES, messages, normalizeLocale, setLocale, t } from './index.js'

describe('admin i18n', () => {
  it('discovers language files as selectable admin languages', () => {
    expect(ADMIN_LANGUAGES.map((language) => language.value)).toEqual(['en', 'de'])
  })

  it('normalizes supported locale variants', () => {
    expect(normalizeLocale('de-DE')).toBe('de')
    expect(normalizeLocale('en_US')).toBe('en')
    expect(normalizeLocale('xx')).toBe('en')
  })

  it('translates messages with fallback replacements', () => {
    setLocale('de', { persist: false })
    expect(t('profile.language')).toBe('Admin-Sprache')
    expect(t('app.version', { version: '1.2.3' })).toBe('CometCMS v1.2.3')

    setLocale('en', { persist: false })
    expect(t('profile.language')).toBe('Admin language')
  })

  it('defines every message key in every admin language', () => {
    const reference = Object.keys(messages.en).sort()

    for (const language of ADMIN_LANGUAGES) {
      expect(Object.keys(messages[language.value]).sort(), language.value).toEqual(reference)
    }
  })
})
