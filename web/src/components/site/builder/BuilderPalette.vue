<template>
  <div class="space-y-5 p-3">
    <div class="sticky top-0 z-10 -mx-3 bg-white px-3 pb-2">
      <label class="sr-only" :for="searchId">{{ t("site.builder.searchPalette") }}</label>
      <input
        :id="searchId"
        v-model="search"
        type="search"
        :placeholder="t('site.builder.searchPalette')"
        class="form-input w-full rounded-lg border-slate-300 text-sm"
        @keydown.esc.stop.prevent="search = ''"
      />
    </div>
    <p class="px-1 text-xs text-slate-500">{{ t("site.builder.paletteHint") }}</p>

    <section v-if="presets.length || layoutKinds.length" class="group">
      <h3 class="mb-2">
        <button type="button" class="category-toggle" :aria-expanded="expanded.layout" :aria-controls="`${searchId}-layout`" @click="expanded.layout = !expanded.layout">
          {{ t("site.builder.layout") }}
          <Icon icon="mdi:chevron-right" class="h-4 w-4 shrink-0 opacity-0 transition-[opacity,transform] group-hover:opacity-100 group-focus-within:opacity-100" :class="{ 'rotate-90': expanded.layout }" aria-hidden="true" />
        </button>
      </h3>
      <div v-show="expanded.layout" :id="`${searchId}-layout`">
        <div class="grid grid-cols-3 gap-1.5">
          <button
            v-for="preset in presets"
            :key="preset.join('-')"
            type="button"
            draggable="true"
            class="flex flex-col items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs font-medium text-slate-700 transition hover:border-theme-300 hover:bg-theme-50/40 hover:text-theme-700"
            style="cursor: grab"
            :title="t('site.builder.columns', { count: preset.length })"
            @click="builder.insert(sectionPreset(builder.spec.value, preset))"
            @dragstart="start($event, () => sectionPreset(builder.spec.value, preset))"
            @dragend="end"
          >
            <span class="flex h-4 w-full gap-0.5">
              <span v-for="(span, index) in preset" :key="index" class="rounded-sm bg-theme-200" :style="{ flex: span }"></span>
            </span>
            <span class="text-[10px]">{{ preset.join("·") }}</span>
          </button>
        </div>
        <div class="mt-1.5 grid grid-cols-2 gap-1.5">
          <PaletteItem v-for="kind in layoutKinds" :key="kind" :icon="KIND_ICONS[kind]" :label="builder.kindLabel(kind)" @add="builder.insert(create(kind))" @drag="start($event, () => create(kind))" @dragend="end" />
        </div>
      </div>
    </section>

    <section v-if="elementKinds.length" class="group">
      <h3 class="mb-2">
        <button type="button" class="category-toggle" :aria-expanded="expanded.elements" :aria-controls="`${searchId}-elements`" @click="expanded.elements = !expanded.elements">
          {{ t("site.builder.elements") }}
          <Icon icon="mdi:chevron-right" class="h-4 w-4 shrink-0 opacity-0 transition-[opacity,transform] group-hover:opacity-100 group-focus-within:opacity-100" :class="{ 'rotate-90': expanded.elements }" aria-hidden="true" />
        </button>
      </h3>
      <div v-show="expanded.elements" :id="`${searchId}-elements`" class="grid grid-cols-2 gap-1.5">
        <PaletteItem
          v-for="kind in elementKinds"
          :key="kind"
          :icon="KIND_ICONS[kind]"
          :label="builder.kindLabel(kind)"
          @add="builder.insert(create(kind))"
          @drag="start($event, () => create(kind))"
          @dragend="end"
        />
      </div>
    </section>

    <section v-if="showFields && (fields.length || !searchTerms.length)" class="group">
      <h3 class="mb-2">
        <button type="button" class="category-toggle" :aria-expanded="expanded.fields" :aria-controls="`${searchId}-fields`" @click="expanded.fields = !expanded.fields">
          {{ templateOwner ? t("site.builder.itemFields", { field: templateOwnerLabel }) : t("site.builder.fields") }}
          <Icon icon="mdi:chevron-right" class="h-4 w-4 shrink-0 opacity-0 transition-[opacity,transform] group-hover:opacity-100 group-focus-within:opacity-100" :class="{ 'rotate-90': expanded.fields }" aria-hidden="true" />
        </button>
      </h3>
      <div v-show="expanded.fields" :id="`${searchId}-fields`" class="space-y-1">
        <PaletteItem
          v-for="field in fields"
          :key="field.key"
          wide
          :icon="fieldTypeIcon(field.type)"
          :label="field.label"
          :meta="field.system ? t('site.builder.system') : field.type"
          @add="builder.insert(fieldNode(field))"
          @drag="start($event, () => fieldNode(field))"
          @dragend="end"
        />
        <p v-if="fields.length === 0" class="px-1 text-xs text-slate-400">{{ t("site.builder.noFields") }}</p>
      </div>
    </section>
    <p v-if="searchTerms.length && !presets.length && !layoutKinds.length && !elementKinds.length && !fields.length" role="status" class="px-1 text-sm text-slate-500">
      {{ t("site.builder.noSearchResults") }}
    </p>
  </div>
</template>

<script setup>
import { computed, ref, useId, watch } from "vue";
import { Icon } from "@iconify/vue";
import PaletteItem from "./PaletteItem.vue";
import { KIND_ICONS } from "./kindIcons.js";
import { fieldShortcut } from "../../../composables/smartValues.js";
import { ancestors, sectionPreset } from "../../../composables/layoutTree.js";
import { useFieldTypeMeta } from "../../../composables/useFieldTypeMeta.js";
import { useSiteBuilder } from "../../../composables/useSiteBuilder.js";
import { useI18n } from "../../../i18n/index.js";

const PRESETS = [[12], [6, 6], [4, 4, 4], [3, 3, 3, 3], [4, 8], [8, 4]];

const props = defineProps({
  /** Layouts bind fields; header and footer do not. */
  withFields: { type: Boolean, default: true },
});

const builder = useSiteBuilder();
const { t } = useI18n();
const { fieldTypeIcon } = useFieldTypeMeta();
const search = ref("");
const searchId = `builder-palette-search-${useId()}`;
const searchTerms = computed(() => search.value.trim().toLowerCase().split(/\s+/).filter(Boolean));
const expanded = ref({ layout: true, elements: true, fields: true });

watch(searchTerms, (terms) => {
  if (terms.length) expanded.value = { layout: true, elements: true, fields: true };
});

function matches(...values) {
  const text = values.filter(Boolean).join(" ").toLowerCase();
  return searchTerms.value.every((term) => text.includes(term));
}

const presets = computed(() => PRESETS.filter((preset) => matches(
  t("site.builder.layout"), builder.kindLabel("section"), "section",
  t("site.builder.columns", { count: preset.length }), preset.join("·"),
)));
const layoutKinds = computed(() => ["row", "column"].filter((kind) => matches(
  t("site.builder.layout"), builder.kindLabel(kind), kind,
)));

const elementKinds = computed(() =>
  (builder.spec.value?.elements ?? []).filter((kind) => kind !== "field" && matches(builder.kindLabel(kind), kind)),
);

/** The repeater whose item template the selection is in (or is). */
const templateOwner = computed(() => {
  const node = builder.selected.value;
  if (!node) return null;
  if (builder.isTemplateField(node)) return node;
  return [...ancestors(builder.nodes.value, node.id)].reverse().find((item) => item.kind === "field") ?? null;
});
const templateOwnerLabel = computed(() => (templateOwner.value ? builder.nodeSummary(templateOwner.value) : ""));
const showFields = computed(() => props.withFields || templateOwner.value !== null);
const availableFields = computed(() =>
  templateOwner.value ? builder.subfieldsOf(templateOwner.value) : props.withFields ? builder.typeFields() : [],
);
const fields = computed(() => availableFields.value.filter((field) => matches(
  field.label, field.key, field.type, field.system ? t("site.builder.system") : "",
)));

function create(kind) {
  return builder.make(kind);
}

function fieldNode(field) {
  return fieldShortcut(field, builder.make, { source: templateOwner.value ? "item" : "current" });
}

function start(event, factory) {
  builder.drag.value = { source: "palette", create: factory };
  event.dataTransfer.effectAllowed = "copy";
  event.dataTransfer.setData("text/plain", "cometcms-node");
}

function end() {
  // A drop inside the canvas clears this itself; this covers cancelled drags.
  setTimeout(() => {
    builder.drag.value = null;
  }, 0);
}
</script>

<style scoped>
.category-toggle {
  @apply flex w-full items-center justify-between gap-2 rounded px-1 py-1 text-left text-[11px] font-semibold uppercase tracking-wider text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-500;
}
</style>
