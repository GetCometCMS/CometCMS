<template>
  <SitePreviewFrame
    ref="frameRef"
    :body="body"
    :device="device"
    :title="t('site.builder.canvas')"
    interactive
    @ready="attach"
  />
</template>

<script setup>
import { onBeforeUnmount, ref, watch } from "vue";
import SitePreviewFrame from "../SitePreviewFrame.vue";
import { findNode, isStructure } from "../../../composables/layoutTree.js";
import { useSiteBuilder } from "../../../composables/useSiteBuilder.js";
import { useI18n } from "../../../i18n/index.js";

defineProps({
  body: { type: Object, default: null },
  device: { type: String, default: "desktop" },
});

const emit = defineEmits(["keydown"]);
const builder = useSiteBuilder();
const { t } = useI18n();
const frameRef = ref(null);

let doc = null;
let indicator = null;
let badge = null;
let dropTargetState = null;
let selectedFromCanvas = false;


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
  outline:1px dashed var(--builder-color)!important;
}

[data-builder-selected]{
  outline:2px solid var(--builder-color)!important;
  outline-offset:-2px;
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
  font:500 13px/1.4 system-ui,sans-serif;
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
  position:absolute;
  z-index:2147483647;
  cursor:pointer;
  transform:translateY(-100%);
  background:color-mix(in srgb,var(--builder-color,#5875c9) 85%,#111827);
  color:#fff;
  font:600 11px/1 system-ui,sans-serif;
  padding:5px 8px;
  border-radius:5px 5px 0 0;
  white-space:nowrap;
  box-shadow:0 -1px 3px rgba(0,0,0,.06);
}
`;


function attach(document) {
  doc = document;
  const style = doc.createElement("style");
  style.textContent = BUILDER_CSS;
  doc.head.appendChild(style);

  indicator = doc.createElement("div");
  indicator.className = "cometcms-drop";
  indicator.hidden = true;
  badge = doc.createElement("div");
  badge.className = "cometcms-badge";
  badge.hidden = true;
  doc.body.append(indicator, badge);

  doc.querySelectorAll("[data-drop-root]").forEach((element) => {
    element.setAttribute("data-empty-label", t("site.builder.dropHere"));
  });
  doc.querySelectorAll("[data-node]").forEach((element) => element.setAttribute("draggable", "true"));
  doc.querySelectorAll("img, a").forEach((element) => element.setAttribute("draggable", "false"));

  doc.addEventListener("click", onClick, true);
  doc.addEventListener("contextmenu", onContextMenu);
  doc.addEventListener("pointerdown", (event) => {
    if (!event.target.closest?.(".cometcms-badge")) builder.closeContextMenu();
  });
  doc.defaultView?.addEventListener("scroll", () => builder.closeContextMenu(), { passive: true });
  doc.addEventListener("mouseover", onMouseOver);
  doc.documentElement.addEventListener("mouseleave", () => {
    builder.hoverId.value = null;
  });
  doc.addEventListener("dragstart", onDragStart);
  doc.addEventListener("dragover", onDragOver);
  doc.addEventListener("dragleave", onDragLeave);
  doc.addEventListener("drop", onDrop);
  doc.addEventListener("dragend", onDragEnd);
  doc.addEventListener("keydown", (event) => emit("keydown", event));
  doc.defaultView?.addEventListener("resize", () => applySelection(false));

  applySelection(false);
  applyHover();
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
  event.preventDefault();
  event.stopPropagation();

  // The badge on the selection opens its menu.
  if (event.target.closest?.(".cometcms-badge") && builder.selectedId.value) {
    const rect = badge.getBoundingClientRect();
    const point = toViewport(rect.left, rect.bottom + 4);
    builder.openContextMenu(builder.selectedId.value, point.x, point.y);
    return;
  }

  const el = event.target.closest?.("[data-node]");
  selectedFromCanvas = true;
  builder.select(el?.dataset.node ?? null);
}

function onMouseOver(event) {
  const el = event.target.closest?.("[data-node]");
  builder.hoverId.value = el?.dataset.node ?? null;
}

function onDragStart(event) {
  const el = event.target.closest?.("[data-node]");
  if (!el) return;
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
  const container = isStructure(kind)
    || (kind === "field" && builder.isTemplateField(findNode(builder.nodes.value, el.dataset.node)));
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
// Labels follow the arrangement (Column ↔ Row) before the preview re-renders.
watch(() => builder.nodeLabel(builder.selected.value), () => applySelection(false));

onBeforeUnmount(() => {
  doc = null;
});

defineExpose({ refresh: () => frameRef.value?.refresh(), positionFor });
</script>
