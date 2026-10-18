<template>
  <div class="flex h-full flex-col overflow-y-auto">
    <section class="border-b border-slate-200 p-4">
      <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-400">{{ t("site.builder.overview.editing") }}</p>
      <div class="mt-2 flex items-start gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-theme-50 text-theme-600 ring-1 ring-inset ring-theme-600/10">
          <Icon :icon="icon" class="h-5 w-5" />
        </span>
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-semibold text-slate-900">{{ title }}</p>
          <p class="mt-0.5 text-xs leading-relaxed text-slate-500">{{ description }}</p>
        </div>
      </div>
      <div class="mt-4 grid grid-cols-2 gap-2">
        <div class="rounded-lg bg-slate-50 px-3 py-2 ring-1 ring-inset ring-slate-200">
          <p class="text-lg font-semibold leading-6 tabular-nums text-slate-900">{{ counts.sections }}</p>
          <p class="text-xs text-slate-500">{{ t("site.builder.overview.sections") }}</p>
        </div>
        <div class="rounded-lg bg-slate-50 px-3 py-2 ring-1 ring-inset ring-slate-200">
          <p class="text-lg font-semibold leading-6 tabular-nums text-slate-900">{{ counts.elements }}</p>
          <p class="text-xs text-slate-500">{{ t("site.builder.overview.elements") }}</p>
        </div>
      </div>
    </section>

    <section class="p-4">
      <p class="text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-400">{{ t("site.builder.overview.next") }}</p>
      <ul class="mt-2 space-y-1">
        <li v-for="action in actions" :key="action.panel">
          <button
            type="button"
            class="group flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left transition-colors hover:bg-slate-50"
            @click="$emit('open-panel', action.panel)"
          >
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-200 transition-colors group-hover:bg-theme-50 group-hover:text-theme-600 group-hover:ring-theme-200">
              <Icon :icon="action.icon" class="h-4 w-4" />
            </span>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-medium text-slate-800">{{ t(`site.builder.overview.${action.key}Title`) }}</span>
              <span class="block truncate text-xs text-slate-500">{{ t(`site.builder.overview.${action.key}Body`) }}</span>
            </span>
            <Icon icon="mdi:chevron-right" class="h-4 w-4 shrink-0 text-slate-300 group-hover:text-slate-500" />
          </button>
        </li>
      </ul>
    </section>

    <div class="mt-auto border-t border-slate-200 bg-slate-50/60 p-4">
      <p class="flex items-start gap-2 text-xs text-slate-500">
        <Icon icon="mdi:cursor-default-click-outline" class="mt-px h-4 w-4 shrink-0 text-slate-400" />
        {{ t("site.builder.overview.tip") }}
      </p>
      <button type="button" class="btn-secondary btn-sm mt-3 w-full" @click="$emit('open-guide')">
        <Icon icon="mdi:help-circle-outline" class="h-4 w-4" />
        {{ t("site.builder.help") }}
        <kbd class="kbd ml-1">?</kbd>
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { Icon } from "@iconify/vue";
import { useSiteBuilder } from "../../../composables/useSiteBuilder.js";
import { useI18n } from "../../../i18n/index.js";

const props = defineProps({
  title: { type: String, default: "" },
  description: { type: String, default: "" },
  icon: { type: String, default: "mdi:file-document-outline" },
  /** The editor offers the entry fields panel for this page. */
  withFields: { type: Boolean, default: false },
});
defineEmits(["open-panel", "open-guide"]);

const builder = useSiteBuilder();
const { t } = useI18n();

const STRUCTURE_KINDS = new Set(["section", "row", "column"]);

const counts = computed(() => {
  let sections = 0;
  let elements = 0;
  const walk = (nodes) => {
    for (const node of nodes ?? []) {
      if (node.kind === "section") sections++;
      else if (!STRUCTURE_KINDS.has(node.kind)) elements++;
      walk(node.children);
    }
  };
  walk(builder.nodes.value);
  return { sections, elements };
});

const actions = computed(() => [
  { key: "add", panel: "add", icon: "mdi:plus-box-outline" },
  ...(props.withFields ? [{ key: "fields", panel: "fields", icon: "mdi:file-document-edit-outline" }] : []),
  { key: "outline", panel: "outline", icon: "mdi:file-tree-outline" },
  { key: "design", panel: "design", icon: "mdi:palette-outline" },
]);
</script>
