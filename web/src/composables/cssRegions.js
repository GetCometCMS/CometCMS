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

function mentionsClass(css, item, name) {
  if (item.type !== "rule") return false;
  if (!item.prelude.startsWith("@")) return classesFromSelector(item.prelude).includes(name);
  return !!item.body && cssStatements(css, item.body[0], item.body[1]).some((inner) => mentionsClass(css, inner, name));
}

/** Top-level rules outside any section whose selectors use the class. */
export function looseRules(css, name) {
  const source = css ?? "";
  const regions = cssRegions(source);
  return cssStatements(source).filter((item) =>
    mentionsClass(source, item, name) && !regions.some((region) => item.start >= region.start && item.end <= region.end),
  );
}

/** Move the class's loose rules into its section, keeping their order. */
export function adoptLooseRules(css, name) {
  const source = css ?? "";
  const rules = looseRules(source, name);
  if (!rules.length) return source;
  const moved = rules.map((rule) => source.slice(rule.start, rule.end)).join("\n\n");
  let rest = source;
  for (const rule of [...rules].reverse()) {
    const before = rest.slice(0, rule.start).replace(/[ \t]+$/, "");
    let after = rest.slice(rule.end).replace(/^[ \t]*\n/, "");
    if (/(^|\n\n)$/.test(before)) after = after.replace(/^\n+/, "");
    rest = before + after;
  }
  const current = regionText(rest, name);
  return setRegionText(rest, name, current?.trim() ? `${current}\n\n${moved}` : moved);
}
