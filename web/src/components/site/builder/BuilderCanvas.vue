<template>
  <SitePreviewFrame
    ref="frameRef"
    :body="body"
    :device="device"
    :head-css="BUILDER_CSS"
    :title="t('site.builder.canvas')"
    interactive
    @ready="attach"
    @updated="prepare"
  />
</template>

<script setup>
import { onBeforeUnmount, ref, watch } from "vue";
import SitePreviewFrame from "../SitePreviewFrame.vue";
import { PREVIEW_KEEP } from "../../../composables/previewDocument.js";
import { templatePartBounds } from "../../../composables/builderTemplateParts.js";
import { findNode, isStructure } from "../../../composables/layoutTree.js";
import { useSiteBuilder } from "../../../composables/useSiteBuilder.js";
import { useI18n } from "../../../i18n/index.js";

const props = defineProps({
  body: { type: Object, default: null },
  device: { type: String, default: "desktop" },
  selectedTemplatePart: { type: String, default: null },
});

const emit = defineEmits(["keydown", "select-template-part"]);
const builder = useSiteBuilder();
const { t } = useI18n();
const frameRef = ref(null);

let doc = null;
let indicator = null;
let badge = null;
let templateHighlight = null;
let templateLabel = null;
let hoveredTemplatePart = null;
let dropTargetState = null;
let selectedFromCanvas = false;
let resizeHandle = null;
let resizeLabel = null;
/** Column edge the grip sits on: { el, side }. */
let resizeEdge = null;
/** Running resize: { el, id, side, startX, start, span, unit, device }. */
let resizing = null;


const BUILDER_CSS = `
[data-node]{
  --builder-color:#5875c9;
  outline:1px solid transparent;
  outline-offset:-1px;
  transition:outline-color .12s;
}

/* Node colors */
[data-kind="section"]{
  --builder-color:#5875c9;
}

[data-kind="row"]{
  --builder-color:#238d85;
}


[data-kind="component"]{
  --builder-color:#9464b5;
  outline:1px dashed color-mix(in srgb,var(--builder-color) 45%,transparent);
}

[data-kind="column"]{
  --builder-color:#e07868;
}

/* Hover and selection */
[data-builder-hover]{
  outline:calc(1px * var(--cometcms-ui-scale,1)) dashed var(--builder-color)!important;
}

[data-builder-selected]{
  outline:calc(2px * var(--cometcms-ui-scale,1)) solid var(--builder-color)!important;
  outline-offset:calc(-2px * var(--cometcms-ui-scale,1));
}

/* A separate overlay stays visible above shared template part content. */
.cometcms-template-highlight{
  --builder-color:#e2a0ce;
  position:fixed;
  z-index:2147483647;
  pointer-events:none;
  outline:calc(2px * var(--cometcms-ui-scale,1)) dashed var(--builder-color);
  outline-offset:calc(-2px * var(--cometcms-ui-scale,1));
}

.cometcms-template-label{
  --s:var(--cometcms-ui-scale,1);
  position:absolute;
  top:0;
  left:0;
  background:var(--builder-color);
  color:#54223f;
  font:600 calc(11px * var(--s))/1 system-ui,sans-serif;
  padding:calc(5px * var(--s)) calc(8px * var(--s));
  border-radius:0 0 calc(5px * var(--s)) 0;
  white-space:nowrap;
}

/* Empty drop area */
[data-drop-root]:empty{
  min-height:180px;
  display:flex;
  align-items:center;
  justify-content:center;
  margin:24px;
  border:2px dashed #cbd5e1;
  border-radius:12px;
  background:rgba(148,163,184,.035);
}

[data-drop-root]:empty::before{
  content:attr(data-empty-label);
  font:500 calc(13px * var(--cometcms-ui-scale,1))/1.4 system-ui,sans-serif;
  color:#64748b;
}

/* Drag and drop indicators */
.cometcms-drop{
  position:absolute;
  z-index:2147483646;
  pointer-events:none;
  background:var(--builder-color,#5875c9);
  border-radius:3px;
  box-shadow:0 0 0 2px rgba(255,255,255,.85);
}

.cometcms-drop.is-box{
  background:color-mix(in srgb,var(--builder-color,#5875c9) 7%,transparent);
  border:2px dashed var(--builder-color,#5875c9);
  box-shadow:none;
  border-radius:6px;
}

/* Node badges */
.cometcms-badge{
  --s:var(--cometcms-ui-scale,1);
  position:absolute;
  z-index:2147483647;
  cursor:pointer;
  transform:translateY(-100%);
  background:color-mix(in srgb,var(--builder-color,#5875c9) 88%,#111827);
  color:#fff;
  font:600 calc(11px * var(--s))/1 system-ui,sans-serif;
  letter-spacing:.01em;
  padding:calc(5px * var(--s)) calc(8px * var(--s));
  border-radius:calc(5px * var(--s)) calc(5px * var(--s)) 0 0;
  white-space:nowrap;
  box-shadow:0 -1px 3px rgba(0,0,0,.08);
}

/* Column resizing: a grip on the edge under the pointer */
[data-builder-resize]{
  outline:2px solid var(--builder-color)!important;
  outline-offset:-2px;
}

.cometcms-resize{
  position:absolute;
  z-index:2147483646;
  width:14px;
  margin-left:-7px;
  cursor:col-resize;
  touch-action:none;
}

.cometcms-resize::before{
  content:"";
  position:absolute;
  top:0;
  bottom:0;
  left:50%;
  width:4px;
  transform:translateX(-50%);
  background:var(--builder-color,#5875c9);
  border-radius:2px;
}

.cometcms-resize::after{
  content:"";
  position:absolute;
  top:50%;
  left:50%;
  width:6px;
  height:28px;
  transform:translate(-50%,-50%);
  background:#fff;
  border:2px solid var(--builder-color,#5875c9);
  border-radius:4px;
  box-shadow:0 1px 3px rgba(0,0,0,.18);
}

.cometcms-resize-label{
  position:absolute;
  top:50%;
  left:50%;
  transform:translate(-50%,calc(-100% - 22px));
  background:color-mix(in srgb,var(--builder-color,#5875c9) 85%,#111827);
  color:#fff;
  font:600 11px/1 system-ui,sans-serif;
  padding:5px 7px;
  border-radius:5px;
  white-space:nowrap;
  pointer-events:none;
}

html.cometcms-resizing,html.cometcms-resizing *{
  cursor:col-resize!important;
  user-select:none!important;
}
`;

/** Distance in px from a column edge within which its resize grip appears. */
const RESIZE_EDGE = 8;


function attach(document) {
  doc = document;

  indicator = doc.createElement("div");
  indicator.className = "cometcms-drop";
  indicator.hidden = true;
  badge = doc.createElement("div");
  badge.className = "cometcms-badge";
  badge.hidden = true;
  templateHighlight = doc.createElement("div");
  templateHighlight.className = "cometcms-template-highlight";
  templateHighlight.hidden = true;
  templateHighlight.setAttribute("aria-hidden", "true");
  templateLabel = doc.createElement("span");
  templateLabel.className = "cometcms-template-label";
  templateHighlight.append(templateLabel);
  hoveredTemplatePart = null;
  resizeHandle = doc.createElement("div");
  resizeHandle.className = "cometcms-resize";
  resizeHandle.hidden = true;
  resizeLabel = doc.createElement("span");
  resizeLabel.className = "cometcms-resize-label";
  resizeLabel.hidden = true;
  resizeHandle.append(resizeLabel);
  for (const overlay of [indicator, badge, resizeHandle, templateHighlight]) overlay.setAttribute(PREVIEW_KEEP, "");
  resizeEdge = null;
  resizing = null;

  resizeHandle.addEventListener("pointerdown", onResizeStart);
  resizeHandle.addEventListener("pointermove", onResizeMove);
  resizeHandle.addEventListener("pointerup", onResizeEnd);
  resizeHandle.addEventListener("pointercancel", onResizeEnd);

  doc.addEventListener("click", onClick, true);
  doc.addEventListener("contextmenu", onContextMenu);
  doc.addEventListener("pointerdown", (event) => {
    if (!event.target.closest?.(".cometcms-badge")) builder.closeContextMenu();
  });
  doc.defaultView?.addEventListener("scroll", () => {
    builder.closeContextMenu();
    if (!resizing) showResizeEdge(null);
    applyTemplatePartHover();
  }, { passive: true, capture: true });
  doc.addEventListener("mouseover", onMouseOver);
  doc.addEventListener("pointermove", onPointerMove);
  doc.documentElement.addEventListener("mouseleave", () => {
    builder.hoverId.value = null;
    hoveredTemplatePart = null;
    applyTemplatePartHover();
    if (!resizing) showResizeEdge(null);
  });
  doc.addEventListener("dragstart", onDragStart);
  doc.addEventListener("dragover", onDragOver);
  doc.addEventListener("dragleave", onDragLeave);
  doc.addEventListener("drop", onDrop);
  doc.addEventListener("dragend", onDragEnd);
  doc.addEventListener("keydown", (event) => {
    if (!event.target.closest?.('.c-lightbox')) emit("keydown", event);
  });
  doc.defaultView?.addEventListener("resize", () => {
    applySelection(false);
    applyTemplatePartHover();
  });

  prepare(document);
}

/** Editor markup for the rendered page, on load and after each in-place update. */
function prepare(document) {
  if (document !== doc) return;
  doc.body.append(indicator, badge, resizeHandle, templateHighlight);

  doc.querySelectorAll("[data-drop-root]").forEach((element) => {
    element.setAttribute("data-empty-label", t("site.builder.dropHere"));
  });
  doc.querySelectorAll("[data-node]").forEach((element) => element.setAttribute("draggable", "true"));
  doc.querySelectorAll("img, a").forEach((element) => element.setAttribute("draggable", "false"));

  // Restore the editor state the update cleared before layout is read, so outlines don't blink.
  applySelection(false);
  applyHover();
  applyTemplatePartHover();
  if (!resizing) {
    const edge = resizeEdge;
    resizeEdge = null;
    showResizeEdge(edge?.el.isConnected ? edge : null);
  }
}

function element(id) {
  return id && doc ? doc.querySelector(`[data-node="${CSS.escape(id)}"]`) : null;
}

function applySelection(scroll) {
  if (!doc) return;
  doc.querySelectorAll("[data-builder-selected]").forEach((el) => el.removeAttribute("data-builder-selected"));
  const el = element(builder.selectedId.value);

  if (!el) {
    if (badge) badge.hidden = true;
    return;
  }

  el.setAttribute("data-builder-selected", "");
  const rect = el.getBoundingClientRect();
  const win = doc.defaultView;
  badge.textContent = `${builder.nodeLabel(findNode(builder.nodes.value, el.dataset.node))}  ⋯`;
  badge.title = t("site.menu.more");
  badge.style.setProperty("--builder-color", win.getComputedStyle(el).getPropertyValue("--builder-color"));
  badge.style.left = `${rect.left + win.scrollX}px`;
  badge.style.top = `${Math.max(16, rect.top + win.scrollY)}px`;
  badge.hidden = false;

  if (scroll) el.scrollIntoView({ block: "nearest", behavior: "smooth" });
}

function applyHover() {
  if (!doc) return;
  doc.querySelectorAll("[data-builder-hover]").forEach((el) => el.removeAttribute("data-builder-hover"));
  const el = element(builder.hoverId.value);
  if (el && builder.hoverId.value !== builder.selectedId.value) el.setAttribute("data-builder-hover", "");
}

function applyTemplatePartHover() {
  if (!doc || !templateHighlight) return;
  const name = hoveredTemplatePart || props.selectedTemplatePart;
  const part = name && doc.querySelector(`.c-${name}:not([data-drop-root])`);
  const rect = part && templatePartBounds(part);
  templateHighlight.hidden = !rect;
  if (!rect) return;
  templateLabel.textContent = `${t(`site.region.${name}`)} · ${t("site.builder.templatePart")}`;
  Object.assign(templateHighlight.style, {
    left: `${rect.left}px`, top: `${rect.top}px`, width: `${rect.width}px`, height: `${rect.height}px`,
    outlineStyle: name === props.selectedTemplatePart ? "solid" : "dashed",
  });
}

/** Viewport coordinates in the admin for a point inside the (scaled) preview. */
function toViewport(x, y) {
  const iframe = doc?.defaultView?.frameElement;
  if (!iframe) return { x, y };
  const rect = iframe.getBoundingClientRect();
  const scale = iframe.offsetWidth > 0 ? rect.width / iframe.offsetWidth : 1;
  return { x: rect.left + x * scale, y: rect.top + y * scale };
}

/** Where to open the menu for a node, e.g. from the keyboard. */
function positionFor(id) {
  const el = element(id);
  if (!el) return null;
  const rect = el.getBoundingClientRect();
  return toViewport(rect.left + 12, Math.max(rect.top, 0) + 12);
}

function onContextMenu(event) {
  const el = event.target.closest?.("[data-node]");
  if (!el) return;
  event.preventDefault();
  selectedFromCanvas = true;
  const point = toViewport(event.clientX, event.clientY);
  builder.openContextMenu(el.dataset.node, point.x, point.y);
}

function onClick(event) {
  if (event.target.closest?.('.c-lightbox')) return;
  // A second click on the selected gallery previews its viewer.
  if (event.target.closest?.('[data-gallery-image]') && event.target.closest?.('[data-node]')?.dataset.node === builder.selectedId.value) return;
  // Native disclosure controls remain usable while selecting the element.
  if (!event.target.closest?.('.c-accordion-title')) event.preventDefault();
  event.stopPropagation();

  // Releasing a resize grip keeps the column selected.
  if (event.target.closest?.(".cometcms-resize")) return;

  // The badge on the selection opens its menu.
  if (event.target.closest?.(".cometcms-badge") && builder.selectedId.value) {
    const rect = badge.getBoundingClientRect();
    const point = toViewport(rect.left, rect.bottom + 4);
    builder.openContextMenu(builder.selectedId.value, point.x, point.y);
    return;
  }

  const el = event.target.closest?.("[data-node]");
  const part = templatePartAt(event.target);
  selectedFromCanvas = true;
  builder.select(part ? null : el?.dataset.node ?? null);
  emit("select-template-part", part);
}

function templatePartAt(target) {
  const part = target.closest?.(".c-region:not([data-drop-root])");
  return part?.classList.contains("c-header") ? "header" : part?.classList.contains("c-footer") ? "footer" : null;
}

function onMouseOver(event) {
  hoveredTemplatePart = templatePartAt(event.target);
  applyTemplatePartHover();
  const el = event.target.closest?.("[data-node]");
  builder.hoverId.value = el?.dataset.node ?? null;
}

function onDragStart(event) {
  const el = event.target.closest?.("[data-node]");
  if (!el) return;
  showResizeEdge(null);
  builder.drag.value = { source: "move", id: el.dataset.node };
  event.dataTransfer.effectAllowed = "move";
  event.dataTransfer.setData("text/plain", el.dataset.node);
}

/** Columns and column components sit side by side in their row. */
function isColumnElement(el) {
  return el.dataset?.kind === "column" || el.dataset?.componentRoot === "column";
}

function isHorizontalColumn(el) {
  const siblings = Array.from(el.parentElement?.children ?? []).filter((child) => child !== el && isColumnElement(child));
  const top = el.getBoundingClientRect().top;
  return siblings.some((sibling) => Math.abs(sibling.getBoundingClientRect().top - top) < 4);
}

/** The column whose left or right edge is next to the pointer, innermost first. */
function resizableEdgeAt(target, x) {
  for (let el = target?.closest?.("[data-node]"); el; el = el.parentElement?.closest("[data-node]")) {
    if (!isColumnElement(el) || !el.parentElement?.classList.contains("c-row")) continue;
    const node = findNode(builder.nodes.value, el.dataset.node);
    if (!node || builder.isCard(node)) continue;
    const rect = el.getBoundingClientRect();
    if (x - rect.left <= RESIZE_EDGE) return { el, side: "left" };
    if (rect.right - x <= RESIZE_EDGE) return { el, side: "right" };
  }
  return null;
}

function placeResizeHandle() {
  const { el, side } = resizeEdge;
  const win = doc.defaultView;
  const rect = el.getBoundingClientRect();
  const x = (side === "left" ? rect.left : rect.right) + win.scrollX;
  resizeHandle.style.setProperty("--builder-color", win.getComputedStyle(el).getPropertyValue("--builder-color"));
  Object.assign(resizeHandle.style, { left: `${x}px`, top: `${rect.top + win.scrollY}px`, height: `${rect.height}px` });
}

function showResizeEdge(edge) {
  if (!resizeHandle) return;
  if (edge?.el === resizeEdge?.el && edge?.side === resizeEdge?.side) return;
  resizeEdge?.el.removeAttribute("data-builder-resize");
  resizeEdge = edge;
  resizeHandle.hidden = !edge;
  if (!edge) return;
  edge.el.setAttribute("data-builder-resize", "");
  placeResizeHandle();
}

function onPointerMove(event) {
  if (resizing || event.target === resizeHandle) return;
  showResizeEdge(builder.drag.value ? null : resizableEdgeAt(event.target, event.clientX));
}

function contentWidth(el) {
  const style = doc.defaultView.getComputedStyle(el);
  return el.clientWidth - (parseFloat(style.paddingLeft) || 0) - (parseFloat(style.paddingRight) || 0);
}

/**
 * The device whose span a column shows: by the screen width, or for a nested
 * row by the row's own width (see the grid in site.css).
 */
function spanDeviceOf(row) {
  const width = row.classList.contains("c-row-nested") ? contentWidth(row) : doc.defaultView.innerWidth;
  return width >= 1024 ? "desktop" : width >= 768 ? "tablet" : "mobile";
}

function onResizeStart(event) {
  if (!resizeEdge || event.button !== 0) return;
  event.preventDefault();
  event.stopPropagation();
  builder.closeContextMenu();

  const { el, side } = resizeEdge;
  const row = el.parentElement;
  const gap = parseFloat(doc.defaultView.getComputedStyle(row).columnGap) || 0;
  // One step: a twelfth of the row, with the gap it brings along.
  const unit = (contentWidth(row) + gap) / 12;
  if (unit <= 0) return;
  const start = Math.min(12, Math.max(1, Math.round((el.getBoundingClientRect().width + gap) / unit)));

  resizing = { el, id: el.dataset.node, side, startX: event.clientX, start, span: start, unit, device: spanDeviceOf(row) };
  resizeHandle.setPointerCapture(event.pointerId);
  doc.documentElement.classList.add("cometcms-resizing");
  resizeLabel.textContent = t("site.builder.spanOf", { span: start });
  resizeLabel.hidden = false;

  selectedFromCanvas = true;
  builder.select(resizing.id);
}

function onResizeMove(event) {
  if (!resizing) return;
  const { el, side, startX, start, unit } = resizing;
  const distance = (event.clientX - startX) * (side === "left" ? -1 : 1);
  const span = Math.min(12, Math.max(1, start + Math.round(distance / unit)));
  if (span === resizing.span) return;

  // Preview the width right away; the saved span re-renders the page.
  resizing.span = span;
  el.style.gridColumn = `span ${span}`;
  resizeLabel.textContent = t("site.builder.spanOf", { span });
  placeResizeHandle();
  applySelection(false);
}

function onResizeEnd(event) {
  if (!resizing) return;
  const { el, id, start, span, device } = resizing;
  resizing = null;
  if (resizeHandle.hasPointerCapture(event.pointerId)) resizeHandle.releasePointerCapture(event.pointerId);
  doc.documentElement.classList.remove("cometcms-resizing");
  resizeLabel.hidden = true;

  if (span === start) {
    el.style.gridColumn = "";
    return;
  }
  const node = findNode(builder.nodes.value, id);
  if (node) builder.updateValue(id, "props", "span", { ...(node.props?.span ?? {}), [device]: span });
}

/** The node under the pointer and where a drop would go relative to it. */
function computeTarget(event) {
  const drag = builder.drag.value;
  let el = event.target.closest?.("[data-node]");

  // Never drop a node into itself.
  if (el && drag?.source === "move") {
    const dragged = element(drag.id);
    if (dragged && (dragged === el || dragged.contains(el))) return null;
  }

  if (!el) {
    const root = event.target.closest?.("[data-drop-root]");
    return root ? { id: null, position: "inside", rect: root.getBoundingClientRect(), horizontal: false } : null;
  }

  const kind = el.dataset.kind;
  // Component instances are one unit: drops go before or after them.
  const container = isStructure(kind) || builder.isDropContainer(findNode(builder.nodes.value, el.dataset.node));
  const rect = el.getBoundingClientRect();
  // Columns side by side, or elements in a column that lays them out in a row.
  const horizontal = isColumnElement(el) ? isHorizontalColumn(el) : !!el.parentElement?.classList.contains("c-flow-row");
  const ratio = horizontal ? (event.clientX - rect.left) / rect.width : (event.clientY - rect.top) / rect.height;
  const position = container
    ? ratio < 0.2 ? "before" : ratio > 0.8 ? "after" : "inside"
    : ratio < 0.5 ? "before" : "after";

  return { id: el.dataset.node, position, rect, horizontal };
}

function showIndicator(target) {
  if (!target) {
    indicator.hidden = true;
    return;
  }

  const win = doc.defaultView;
  const { rect, position, horizontal } = target;
  const el = element(target.id);
  indicator.style.setProperty("--builder-color", el ? win.getComputedStyle(el).getPropertyValue("--builder-color") : "#2563eb");
  const x = rect.left + win.scrollX;
  const y = rect.top + win.scrollY;
  indicator.classList.toggle("is-box", position === "inside");

  if (position === "inside") {
    Object.assign(indicator.style, { left: `${x}px`, top: `${y}px`, width: `${rect.width}px`, height: `${rect.height}px` });
  } else if (horizontal) {
    const left = position === "before" ? x - 2 : x + rect.width - 2;
    Object.assign(indicator.style, { left: `${left}px`, top: `${y}px`, width: "4px", height: `${rect.height}px` });
  } else {
    const top = position === "before" ? y - 2 : y + rect.height - 2;
    Object.assign(indicator.style, { left: `${x}px`, top: `${top}px`, width: `${rect.width}px`, height: "4px" });
  }

  indicator.hidden = false;
}

function onDragOver(event) {
  if (!builder.drag.value) return;
  dropTargetState = computeTarget(event);
  if (!dropTargetState) {
    showIndicator(null);
    return;
  }
  event.preventDefault();
  event.dataTransfer.dropEffect = builder.drag.value.source === "move" ? "move" : "copy";
  showIndicator(dropTargetState);
}

function onDragLeave(event) {
  if (!event.relatedTarget) showIndicator(null);
}

function onDrop(event) {
  event.preventDefault();
  showIndicator(null);
  const target = dropTargetState;
  dropTargetState = null;

  if (target) {
    builder.dropAt(target.id, target.position);
  } else {
    builder.drag.value = null;
  }
}

function onDragEnd() {
  showIndicator(null);
  dropTargetState = null;
  setTimeout(() => {
    builder.drag.value = null;
  }, 0);
}

watch(
  () => builder.selectedId.value,
  () => {
    applySelection(!selectedFromCanvas);
    applyHover();
    selectedFromCanvas = false;
  },
);
watch(() => builder.hoverId.value, applyHover);
watch(() => props.selectedTemplatePart, applyTemplatePartHover);
// Labels follow the arrangement (Column ↔ Row) before the preview re-renders.
watch(() => builder.nodeLabel(builder.selected.value), () => applySelection(false));

onBeforeUnmount(() => {
  doc = null;
});

defineExpose({ refresh: () => frameRef.value?.refresh(), positionFor });
</script>
