import { describe, expect, it } from 'vitest'
import { adoptLooseRules, cssRegions, looseRules, regionText, setRegionText } from './cssRegions.js'

const CSS = `body { color: red; }

/* #region .hero */
.hero { padding: 1rem; }
/* #endregion */

.card { gap: 1rem; }
`

describe('CSS regions', () => {
  it('reads a class section between its markers', () => {
    expect(regionText(CSS, 'hero')).toBe('.hero { padding: 1rem; }')
    expect(regionText(CSS, 'card')).toBeNull()
  })

  it('ignores markers inside strings and rule bodies', () => {
    const css = `.a::before { content: "/* #region .fake */"; }\n.b { /* #region .inner */ }`
    expect(cssRegions(css)).toEqual([])
  })

  it('ends an unclosed section at the next one', () => {
    const css = `/* #region .a */\n.a {}\n/* #region .b */\n.b {}\n`
    expect(regionText(css, 'a')).toBe('.a {}')
    expect(regionText(css, 'b')).toBe('.b {}')
  })

  it('round-trips edits without touching the rest of the file', () => {
    const next = setRegionText(CSS, 'hero', '.hero {\n  padding: 2rem;\n}')
    expect(regionText(next, 'hero')).toBe('.hero {\n  padding: 2rem;\n}')
    expect(next.startsWith('body { color: red; }\n\n/* #region .hero */\n')).toBe(true)
    expect(next.endsWith('/* #endregion */\n\n.card { gap: 1rem; }\n')).toBe(true)
  })

  it('appends a new section and removes an emptied one', () => {
    const added = setRegionText(CSS, 'card', '.card:hover { gap: 0; }')
    expect(added).toBe(`${CSS}\n/* #region .card */\n.card:hover { gap: 0; }\n/* #endregion */\n`)
    expect(setRegionText(added, 'card', '  ')).toBe(CSS)
    expect(setRegionText('', 'card', '.card {}')).toBe('/* #region .card */\n.card {}\n/* #endregion */\n')
  })

  it('finds loose rules for a class, including inside media queries', () => {
    const css = `${CSS}@media (min-width: 40rem) { .card { gap: 2rem; } }\n.hero-title { color: blue; }\n`
    expect(looseRules(css, 'card').map((rule) => rule.prelude)).toEqual(['.card', '@media (min-width: 40rem)'])
    expect(looseRules(css, 'hero')).toEqual([])
  })

  it('moves loose rules into the section', () => {
    const next = adoptLooseRules(CSS, 'card')
    expect(regionText(next, 'card')).toBe('.card { gap: 1rem; }')
    expect(looseRules(next, 'card')).toEqual([])
    expect(regionText(next, 'hero')).toBe('.hero { padding: 1rem; }')
  })
})
