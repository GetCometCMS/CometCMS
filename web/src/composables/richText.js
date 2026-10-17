import { marked } from "marked";
import TurndownService from "turndown";

export const turndown = new TurndownService({
  headingStyle: "atx",
  codeBlockStyle: "fenced",
});

const ALLOWED_HTML_TAGS = {
  a: ["href", "title", "target", "rel"],
  blockquote: [],
  br: [],
  code: [],
  div: ["class"],
  em: [],
  h1: [],
  h2: [],
  h3: [],
  h4: [],
  h5: [],
  h6: [],
  hr: [],
  img: ["src", "alt", "title", "width", "height"],
  li: [],
  mark: [],
  ol: [],
  p: [],
  pre: [],
  s: [],
  span: ["class"],
  strong: [],
  sub: [],
  sup: [],
  table: [],
  tbody: [],
  td: [],
  th: [],
  thead: [],
  tr: [],
  u: [],
  ul: [],
};

const DROP_HTML_TAGS = new Set(["iframe", "object", "script", "style"]);

export function markdownToHtml(value) {
  return marked.parse(String(value ?? ""), { async: false });
}

export function sanitizeHtml(value, documentRef = globalThis.document) {
  const html = String(value ?? "");

  if (html.trim() === "" || typeof documentRef === "undefined") {
    return html.trim();
  }

  const template = documentRef.createElement("template");
  template.innerHTML = html;
  sanitizeHtmlChildren(template.content);

  return template.innerHTML.trim();
}

function sanitizeHtmlChildren(node) {
  Array.from(node.childNodes).forEach((child) => sanitizeHtmlNode(child));
}

function sanitizeHtmlNode(node) {
  if (node.nodeType !== Node.ELEMENT_NODE) return;

  const tag = node.tagName.toLowerCase();

  if (DROP_HTML_TAGS.has(tag)) {
    node.remove();
    return;
  }

  sanitizeHtmlChildren(node);

  if (!Object.prototype.hasOwnProperty.call(ALLOWED_HTML_TAGS, tag)) {
    node.replaceWith(...Array.from(node.childNodes));
    return;
  }

  const allowedAttributes = ALLOWED_HTML_TAGS[tag];

  Array.from(node.attributes).forEach((attribute) => {
    const name = attribute.name.toLowerCase();

    if (
      !allowedAttributes.includes(name) ||
      !isSafeHtmlAttributeValue(tag, name, attribute.value)
    ) {
      node.removeAttribute(attribute.name);
    }
  });

  if (tag === "a" && node.getAttribute("target")?.toLowerCase() === "_blank") {
    node.setAttribute("rel", "noopener noreferrer");
  }
}

function isSafeHtmlAttributeValue(tag, name, value) {
  const trimmed = String(value ?? "").trim();

  if (name === "href" || name === "src") {
    return isSafeHtmlUrl(trimmed);
  }

  if (name === "target") {
    return ["_blank", "_self", "_parent", "_top"].includes(trimmed);
  }

  if (tag === "img" && ["width", "height"].includes(name)) {
    return /^\d{1,4}$/.test(trimmed);
  }

  return true;
}

function isSafeHtmlUrl(url) {
  if (
    url === "" ||
    url.startsWith("#") ||
    url.startsWith("/") ||
    url.startsWith("./") ||
    url.startsWith("../")
  ) {
    return true;
  }

  try {
    const parsed = new URL(url, window.location.origin);
    return ["http:", "https:", "mailto:", "tel:"].includes(parsed.protocol);
  } catch {
    return false;
  }
}
