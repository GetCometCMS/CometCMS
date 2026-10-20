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

    <BuilderGroup v-if="presets.length || layoutKinds.length" v-model:open="expanded.layout" :title="t('site.builder.layout')">
      <div class="grid grid-cols-2 gap-1.5">
        <button
          v-for="preset in presets"
          :key="preset.join('-')"
          type="button"
          draggable="true"
          class="group/preset flex flex-col items-stretch gap-2 rounded-lg border border-slate-200 bg-white p-2.5 text-xs font-medium text-slate-700 shadow-xs transition hover:border-theme-300 hover:bg-theme-50/40 hover:text-theme-700"
          style="cursor: grab"
          :title="t('site.builder.presetHint')"
          @click="builder.insert(sectionPreset(builder.spec.value, preset))"
          @dragstart="start($event, () => sectionPreset(builder.spec.value, preset))"
          @dragend="end"
        >
          <span class="flex h-6 w-full gap-1 rounded-md bg-slate-50 p-1 ring-1 ring-inset ring-slate-200 group-hover/preset:bg-white">
            <span v-for="(span, index) in preset" :key="index" class="rounded-[3px] bg-theme-200 group-hover/preset:bg-theme-300" :style="{ flex: span }"></span>
          </span>
          <span class="text-left">{{ t(`site.builder.preset.${preset.join("-")}`) }}</span>
        </button>
      </div>
      <div class="mt-1.5 grid grid-cols-2 gap-1.5">
        <PaletteItem v-for="kind in layoutKinds" :key="kind" stacked :icon="KIND_ICONS[kind]" :label="builder.kindLabel(kind)" :hint="kindHint(kind)" @add="builder.insert(create(kind))" @drag="start($event, () => create(kind))" @dragend="end" />
      </div>
    </BuilderGroup>

    <BuilderGroup v-if="elementKinds.length" v-model:open="expanded.elements" :title="t('site.builder.elements')" content-class="mt-2 grid grid-cols-2 gap-1.5">
      <PaletteItem
        v-for="kind in elementKinds"
        :key="kind"
        stacked
        :icon="KIND_ICONS[kind]"
        :label="builder.kindLabel(kind)"
        :hint="kindHint(kind)"
        @add="builder.insert(create(kind))"
        @drag="start($event, () => create(kind))"
        @dragend="end"
      />
    </BuilderGroup>

    <BuilderGroup
      v-if="showFields && (fields.length || !searchTerms.length)"
      v-model:open="expanded.fields"
      :title="templateOwner ? t('site.builder.itemFields', { field: templateOwnerLabel }) : context.collection ? t('site.builder.cardFields', { type: context.type?.label ?? context.type?.name ?? '' }) : t('site.builder.fields')"
      content-class="mt-2 space-y-1"
    >
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
    </BuilderGroup>
    <p v-if="searchTerms.length && !presets.length && !layoutKinds.length && !elementKinds.length && !fields.length" role="status" class="px-1 text-sm text-slate-500">
      {{ t("site.builder.noSearchResults") }}
    </p>
  </div>
</template>

<script setup>
import { computed, ref, useId, watch } from "vue";
import BuilderGroup from "./BuilderGroup.vue";
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
  t("site.builder.columns", { count: preset.length }), t(`site.builder.preset.${preset.join("-")}`),
)));

function kindHint(kind) {
  const key = `site.kindHint.${kind}`;
  const text = t(key);
  return text === key ? "" : text;
}
const layoutKinds = computed(() => ["row", "column"].filter((kind) => matches(
  t("site.builder.layout"), builder.kindLabel(kind), kind,
)));

const elementKinds = computed(() =>
  (builder.spec.value?.elements ?? []).filter((kind) => !["field", "component"].includes(kind) && matches(builder.kindLabel(kind), kind, kindHint(kind))),
);

/** The repeater whose item template the selection is in (or is). */
const templateOwner = computed(() => {
  const node = builder.selected.value;
  if (!node) return null;
  if (builder.isTemplateField(node)) return node;
  return [...ancestors(builder.nodes.value, node.id)].reverse().find((item) => item.kind === "field") ?? null;
});
const templateOwnerLabel = computed(() => (templateOwner.value ? builder.nodeSummary(templateOwner.value) : ""));
/** What "current" means at the selection: inside a collection's card, the card's entry. */
const context = computed(() => builder.entryContext(builder.selectedId.value));
const showFields = computed(() => props.withFields || templateOwner.value !== null || context.value.collection !== null);
const availableFields = computed(() =>
  templateOwner.value
    ? builder.subfieldsOf(templateOwner.value)
    : props.withFields || context.value.collection
      ? builder.typeFields(builder.selectedId.value)
      : [],
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
