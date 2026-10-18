// Node derives navigator.language from the machine's locale (LANG); pin the
// admin to English so test output does not depend on where the tests run.
Object.defineProperty(globalThis.navigator, 'language', { value: 'en-US', configurable: true })
