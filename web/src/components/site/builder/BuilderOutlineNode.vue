<template>
  <li role="treeitem" :aria-expanded="hasChildren ? expanded : undefined" :aria-selected="isSelected">
    <div
      ref="rowRef"
      class="group relative flex h-8 cursor-pointer select-none items-center gap-1.5 rounded-md pr-1 text-xs transition-colors"
      :class="[
        isSelected ? 'bg-theme-600 text-white' : isHovered ? 'bg-theme-50 text-slate-900' : 'text-slate-700 hover:bg-slate-100',
        dropPosition === 'inside' ? 'ring-2 ring-inset ring-theme-500' : '',
      ]"
      :style="{ paddingLeft: `${depth * 14 + 4}px` }"
      draggable="true"
      @click="builder.select(node.id)"
      @mouseenter="builder.hoverId.value = node.id"
      @mouseleave="builder.hoverId.value = null"
      @dragstart.stop="onDragStart"
      @dragend="onDragEnd"
      @dragover.prevent.stop="onDragOver"
      @dragleave="dropPosition = ''"
      @drop.prevent.stop="onDrop"
    >
      <span v-if="dropPosition === 'before'" class="pointer-events-none absolute inset-x-0 -top-px h-0.5 rounded bg-theme-500"></span>
      <span v-if="dropPosition === 'after'" class="pointer-events-none absolute inset-x-0 -bottom-px h-0.5 rounded bg-theme-500"></span>

      <button
        v-if="hasChildren"
        type="button"
        class="flex h-5 w-5 shrink-0 items-center justify-center rounded opacity-70 hover:opacity-100"
        :aria-label="expanded ? t('site.builder.collapse') : t('site.builder.expand')"
        @click.stop="expanded = !expanded"
      >
        <Icon icon="mdi:chevron-right" class="h-4 w-4 transition-transform" :class="{ 'rotate-90': expanded }" />
      </button>
      <span v-else class="w-5 shrink-0"></span>

      <Icon :icon="icon" class="h-4 w-4 shrink-0" :class="isSelected ? 'text-white/80' : 'text-slate-400'" />
      <span class="shrink-0 font-medium">{{ builder.kindLabel(node.kind) }}</span>
      <span class="min-w-0 flex-1 truncate" :class="isSelected ? 'text-white/70' : 'text-slate-400'">{{ builder.nodeSummary(node) }}</span>

      <span class="hidden shrink-0 items-center group-hover:flex" :class="{ '!flex': isSelected }">
        <button type="button" class="outline-action" :disabled="first" :title="t('site.builder.moveUp')" @click.stop="builder.shift(node.id, -1)">
          <Icon icon="mdi:arrow-up" class="h-3.5 w-3.5" />
        </button>
        <button type="button" class="outline-action" :disabled="last" :title="t('site.builder.moveDown')" @click.stop="builder.shift(node.id, 1)">
          <Icon icon="mdi:arrow-down" class="h-3.5 w-3.5" />
        </button>
        <button type="button" class="outline-action" :title="t('site.builder.duplicate')" @click.stop="builder.duplicate(node.id)">
          <Icon icon="mdi:content-copy" class="h-3.5 w-3.5" />
        </button>
        <button type="button" class="outline-action" :title="t('common.delete')" @click.stop="builder.remove(node.id)">
          <Icon icon="mdi:trash-can-outline" class="h-3.5 w-3.5" />
        </button>
      </span>
    </div>

    <ul v-if="hasChildren && expanded" role="group">
      <BuilderOutlineNode
        v-for="(child, index) in node.children"
        :key="child.id"
        :node="child"
        :depth="depth + 1"
        :first="index === 0"
        :last="index === node.children.length - 1"
      />
    </ul>
  </li>
</template>

<script setup>
import { computed, nextTick, ref, watch } from "vue";
import { Icon } from "@iconify/vue";
import { KIND_ICONS } from "./kindIcons.js";
import { contains, findNode } from "../../../composables/layoutTree.js";
import { useFieldTypeMeta } from "../../../composables/useFieldTypeMeta.js";
import { useSiteBuilder } from "../../../composables/useSiteBuilder.js";
import { useI18n } from "../../../i18n/index.js";

const props = defineProps({
  node: { type: Object, required: true },
  depth: { type: Number, default: 0 },
  first: { type: Boolean, default: false },
  last: { type: Boolean, default: false },
});

const builder = useSiteBuilder();
const { t } = useI18n();
const { fieldTypeIcon } = useFieldTypeMeta();
const expanded = ref(true);
const dropPosition = ref("");
const rowRef = ref(null);

const hasChildren = computed(() => (props.node.children?.length ?? 0) > 0);
const isSelected = computed(() => builder.selectedId.value === props.node.id);
const isHovered = computed(() => builder.hoverId.value === props.node.id);
const isContainer = computed(() =>
  ["section", "row", "column"].includes(props.node.kind) || builder.isTemplateField(props.node),
);
const icon = computed(() => {
  if (props.node.kind === "field") {
    const config = builder.fieldConfig(props.node);
    return config ? fieldTypeIcon(config.type) : "mdi:alert-outline";
  }
  return KIND_ICONS[props.node.kind] ?? "mdi:shape-outline";
});

// Reveal the selection when it is chosen on the canvas.
watch(
  () => builder.selectedId.value,
  async (id) => {
    if (!id) return;
    if (id !== props.node.id && contains(props.node, id)) expanded.value = true;
    if (id === props.node.id) {
      await nextTick();
      rowRef.value?.scrollIntoView({ block: "nearest" });
    }
  },
  { immediate: true },
);

function onDragStart(event) {
  builder.drag.value = { source: "move", id: props.node.id };
  event.dataTransfer.effectAllowed = "move";
  event.dataTransfer.setData("text/plain", props.node.id);
}

function onDragEnd() {
  dropPosition.value = "";
  setTimeout(() => {
    builder.drag.value = null;
  }, 0);
}

function onDragOver(event) {
  const drag = builder.drag.value;
  if (!drag || (drag.source === "move" && (drag.id === props.node.id || contains(findNode(builder.nodes.value, drag.id) ?? { children: [] }, props.node.id)))) {
    dropPosition.value = "";
    return;
  }

  const rect = event.currentTarget.getBoundingClientRect();
  const ratio = (event.clientY - rect.top) / rect.height;
  dropPosition.value = isContainer.value
    ? ratio < 0.3 ? "before" : ratio > 0.7 ? "after" : "inside"
    : ratio < 0.5 ? "before" : "after";
}

function onDrop() {
  const position = dropPosition.value;
  dropPosition.value = "";
  if (position) builder.dropAt(props.node.id, position);
}
</script>

<style scoped>
.outline-action {
  display: inline-flex;
  height: 1.375rem;
  width: 1.375rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.25rem;
  opacity: 0.75;
}
.outline-action:hover:not(:disabled) {
  opacity: 1;
  background: rgb(0 0 0 / 0.08);
}
.outline-action:disabled {
  opacity: 0.25;
}
</style>
