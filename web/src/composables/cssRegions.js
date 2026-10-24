import { classesFromSelector } from "./cssClasses.js";

/**
 * The site stylesheet stays one file. Marker comments split it into sections
 * for single classes, so the builder can show only an element's own CSS:
 *
 *   /* #region .hero-title *\/
 *   .hero-title { letter-spacing: 0.05em; }
 *   /* #endregion *\/
 *
 * The markers are the ones code editors fold, so the file still reads well.
 * A section holds one rule for its class; the builder edits what is nested
 * inside, so its CSS only reaches the element and what the element contains.
 */
const OPEN = /^\/\*\s*#region\s+\.([A-Za-z_][A-Za-z0-9_-]{0,60})\s*\*\/$/;
const CLOSE = /^\/\*\s*#endregion\b[^*]*\*\/$/;

/** Skip a comment or string starting at i; returns the index after it, or i. */
function skipToken(css, i) {
  if (css[i] === "/" && css[i + 1] === "*") {
    const end = css.indexOf("*/", i + 2);
    return end === -1 ? css.length : end + 2;
  }
  if (css[i] === '"' || css[i] === "'") {
    let j = i + 1;
    while (j < css.length && css[j] !== css[i] && css[j] !== "\n") j += css[j] === "\\" ? 2 : 1;
    return Math.min(j + 1, css.length);
  }
  return i;
}

/**
 * Top-level comments and statements between from and to. Rules carry their
 * prelude and body range so nested at-rules can be read the same way.
 */
export function cssStatements(css, from = 0, to = css.length) {
  const items = [];
  let i = from;
  while (i < to) {
    if (/\s/.test(css[i])) { i++; continue; }
    if (css[i] === "/" && css[i + 1] === "*") {
      const end = Math.min(skipToken(css, i), to);
      items.push({ type: "comment", start: i, end, text: css.slice(i, end) });
      i = end;
      continue;
    }
    const start = i;
    let depth = 0;
    let bodyStart = -1;
    while (i < to) {
      const next = skipToken(css, i);
      if (next !== i) { i = next; continue; }
      const char = css[i++];
      if (char === "{") {
        if (depth++ === 0) bodyStart = i;
      } else if (char === "}") {
        if (--depth <= 0) break;
      } else if (char === ";" && depth === 0) {
        break;
      }
    }
    const end = Math.min(i, to);
    items.push({
      type: "rule",
      start,
      end,
      prelude: css.slice(start, bodyStart === -1 ? end : bodyStart - 1).trim(),
      preludeEnd: bodyStart === -1 ? end : bodyStart - 1,
      body: bodyStart === -1 ? null : [bodyStart, Math.max(bodyStart, end - 1)],
    });
  }
  return items;
}

/** The marked sections, each with the range of its own text between the markers. */
export function cssRegions(css) {
  const regions = [];
  let open = null;
  const close = (end, closeEnd) => {
    regions.push({ ...open, bodyEnd: end, end: closeEnd });
    open = null;
  };
  for (const item of cssStatements(css)) {
    if (item.type !== "comment") continue;
    const match = OPEN.exec(item.text);
    if (match) {
      if (open) close(item.start, item.start);
      open = { name: match[1], start: item.start, bodyStart: item.end };
    } else if (open && CLOSE.test(item.text)) {
      close(item.start, item.end);
    }
  }
  if (open) close(css.length, css.length);
  return regions;
}

/** A class's section text, or null when it has none. */
export function regionText(css, name) {
  const region = cssRegions(css ?? "").find((item) => item.name === name);
  if (!region) return null;
  return css.slice(region.bodyStart, region.bodyEnd).replace(/^\r?\n/, "").replace(/\r?\n$/, "");
}

/** Set a class's section text; a new section goes at the end, an emptied one is removed. */
export function setRegionText(css, name, text) {
  const source = css ?? "";
  const region = cssRegions(source).find((item) => item.name === name);
  const empty = !text.trim();
  if (!region) {
    if (empty) return source;
    const gap = !source.trim() ? "" : source.endsWith("\n\n") ? "" : source.endsWith("\n") ? "\n" : "\n\n";
    return `${source.trimStart() ? source : ""}${gap}/* #region .${name} */\n${text}\n/* #endregion */\n`;
  }
  if (empty) {
    const before = source.slice(0, region.start).replace(/\n+$/, "\n");
    const after = source.slice(region.end).replace(/^[ \t]*\n+/, "");
    return before.trim() ? `${before}${after ? "\n" : ""}${after}` : after;
  }
  return `${source.slice(0, region.bodyStart)}\n${text}\n${source.slice(region.bodyEnd)}`;
}

/** The rule wrapping a whole section, or null for CSS written before sections were scoped. */
function wrapper(text, name) {
  const items = cssStatements(text);
  const [rule] = items;
  const only = items.length === 1 && rule.type === "rule" && rule.prelude === `.${name}` && rule.body && rule.end === text.length;
  return only && text.startsWith(`.${name} {\n`) && text.endsWith("\n}") ? rule : null;
}

/** What is nested in a class's section, without the class rule around it. */
export function scopedText(css, name) {
  const text = regionText(css, name);
  if (text === null) return "";
  if (!wrapper(text, name)) return text;
  return text.slice(name.length + 4, -2).split("\n").map((line) => line.replace(/^ {2}/, "")).join("\n");
}

/** Set what is nested in a class's section; the class rule around it is added here. */
export function setScopedText(css, name, text) {
  if (!text.trim()) return setRegionText(css, name, "");
  const indented = text.split("\n").map((line) => (line ? `  ${line}` : line)).join("\n");
  return setRegionText(css, name, `.${name} {\n${indented}\n}`);
}

/** Split a selector list at its top-level commas. */
function selectorList(prelude) {
  const parts = [];
  let depth = 0;
  let start = 0;
  for (let i = 0; i < prelude.length; i++) {
    const next = skipToken(prelude, i);
    if (next !== i) { i = next - 1; continue; }
    if ("([".includes(prelude[i])) depth++;
    else if (")]".includes(prelude[i])) depth--;
    else if (prelude[i] === "," && depth === 0) { parts.push(prelude.slice(start, i)); start = i + 1; }
  }
  return [...parts, prelude.slice(start)].map((part) => part.trim());
}

/** Rules that can move into the class's section: every selector names the class. */
function scopable(css, item, name) {
  if (item.type !== "rule" || !item.body) return false;
  if (!item.prelude.startsWith("@")) return selectorList(item.prelude).every((selector) => classesFromSelector(selector).includes(name));
  if (!/^@(media|supports|container|layer)\b/i.test(item.prelude)) return false;
  const rules = cssStatements(css, item.body[0], item.body[1]).filter((inner) => inner.type === "rule");
  return rules.length > 0 && rules.every((inner) => scopable(css, inner, name));
}

/** A scopable rule's text with the class replaced by "&", so it nests inside the class. */
function nestedRule(css, item, name) {
  if (!item.prelude.startsWith("@")) {
    const self = new RegExp(`\\.${name.replace(/-/g, "\\-")}(?![\\w-])`, "g");
    return css.slice(item.start, item.preludeEnd).replace(self, "&") + css.slice(item.preludeEnd, item.end);
  }
  let text = css.slice(item.start, item.end);
  const inner = cssStatements(css, item.body[0], item.body[1]).filter((rule) => rule.type === "rule");
  for (const rule of inner.reverse()) {
    text = text.slice(0, rule.start - item.start) + nestedRule(css, rule, name) + text.slice(rule.end - item.start);
  }
  return text;
}

/** Top-level rules outside any section that could move into the class's section. */
export function looseRules(css, name) {
  const source = css ?? "";
  const regions = cssRegions(source);
  return cssStatements(source).filter((item) =>
    scopable(source, item, name) && !regions.some((region) => item.start >= region.start && item.end <= region.end),
  );
}

/** Move the class's loose rules into its section as nested rules, keeping their order. */
export function adoptLooseRules(css, name) {
  const source = css ?? "";
  const rules = looseRules(source, name);
  if (!rules.length) return source;
  const moved = rules.map((rule) => nestedRule(source, rule, name)).join("\n\n");
  let rest = source;
  for (const rule of [...rules].reverse()) {
    const before = rest.slice(0, rule.start).replace(/[ \t]+$/, "");
    let after = rest.slice(rule.end).replace(/^[ \t]*\n/, "");
    if (/(^|\n\n)$/.test(before)) after = after.replace(/^\n+/, "");
    rest = before + after;
  }
  const current = scopedText(rest, name);
  return setScopedText(rest, name, current.trim() ? `${current}\n\n${moved}` : moved);
}
