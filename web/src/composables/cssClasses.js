// Keep these limits in sync with LayoutSchema::cssClasses().
export const MAX_CSS_CLASSES = 8;
const CLASS_NAME = /^[A-Za-z_][A-Za-z0-9_-]{0,60}$/;

export function normalizeCssClasses(value) {
  const values = Array.isArray(value) ? value : String(value ?? "").split(/\s+/);
  return [...new Set(values.filter((name) => CLASS_NAME.test(name)))].slice(0, MAX_CSS_CLASSES);
}

/** Read class selectors, excluding strings, comments and attribute selectors. */
export function classesFromSelector(selector) {
  const classes = [];
  const tokens = /\/\*[\s\S]*?\*\/|"(?:\\[\s\S]|[^"\\])*"|'(?:\\[\s\S]|[^'\\])*'|\[(?:\\[\s\S]|"(?:\\[\s\S]|[^"\\])*"|'(?:\\[\s\S]|[^'\\])*'|[^\]"'\\])*\]|\.((?:\\(?:[0-9a-fA-F]{1,6}\s?|[^\r\n\f])|[\w\u0080-\uFFFF-])+)/g;
  for (const match of selector.matchAll(tokens)) {
    if (!match[1]) continue;
    const name = match[1].replace(/\\([0-9a-fA-F]{1,6})\s?|\\([^\r\n\f])/g, (_, hex, char) => {
      const code = hex ? parseInt(hex, 16) : 0;
      return hex ? String.fromCodePoint(code > 0 && code <= 0x10ffff ? code : 0xfffd) : char;
    });
    if (CLASS_NAME.test(name)) classes.push(name);
  }
  return [...new Set(classes)];
}

/** Parse in a detached stylesheet so custom CSS never affects the admin UI. */
export function themeCssClasses(css) {
  if (!css || typeof CSSStyleSheet === "undefined") return [];
  const sheet = new CSSStyleSheet();
  sheet.replaceSync(css);
  const classes = new Set();
  function collect(rules) {
    for (const rule of rules) {
      if (rule.selectorText) {
        for (const name of classesFromSelector(rule.selectorText)) classes.add(name);
      }
      if (rule.cssRules) collect(rule.cssRules);
    }
  }
  collect(sheet.cssRules);
  return [...classes].sort();
}
