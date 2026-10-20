// Node 20 has no navigator; newer Node versions derive its language from LANG.
// Pin the admin to English regardless of the runtime or machine locale.
if (typeof globalThis.navigator === 'undefined') {
  Object.defineProperty(globalThis, 'navigator', { value: {}, configurable: true })
}
Object.defineProperty(globalThis.navigator, 'language', { value: 'en-US', configurable: true })
