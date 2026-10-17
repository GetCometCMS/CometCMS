<template>
  <div class="space-y-5 p-3">
    <p class="px-1 text-xs text-slate-500">{{ t("site.builder.paletteHint") }}</p>

    <section>
      <h3 class="mb-2 px-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ t("site.builder.layout") }}</h3>
      <div class="grid grid-cols-3 gap-1.5">
        <button
          v-for="preset in PRESETS"
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
        <PaletteItem v-for="kind in ['row', 'column']" :key="kind" :icon="KIND_ICONS[kind]" :label="builder.kindLabel(kind)" @add="builder.insert(create(kind))" @drag="start($event, () => create(kind))" @dragend="end" />
      </div>
    </section>

    <section>
      <h3 class="mb-2 px-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ t("site.builder.elements") }}</h3>
      <div class="grid grid-cols-2 gap-1.5">
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

    <section v-if="showFields">
      <h3 class="mb-2 px-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
        {{ templateOwner ? t("site.builder.itemFields", { field: templateOwnerLabel }) : t("site.builder.fields") }}
      </h3>
      <div class="space-y-1">
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
  </div>
</template>

<script setup>
import { computed } from "vue";
import PaletteItem from "./PaletteItem.vue";
import { KIND_ICONS } from "./kindIcons.js";
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

const elementKinds = computed(() =>
  (builder.spec.value?.elements ?? []).filter((kind) => kind !== "field"),
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
const fields = computed(() =>
  templateOwner.value ? builder.subfieldsOf(templateOwner.value) : props.withFields ? builder.typeFields() : [],
);

function create(kind) {
  return builder.make(kind);
}

function fieldNode(field) {
  const displays = builder.spec.value?.field_displays?.[field.type] ?? ["text"];
  const props = { field: field.key, display: displays[0] };
  if (field.key === "title") props.tag = "h2";
  if (field.type === "media") props.ratio = "16:9";
  if (field.type === "relation") props.columns = 3;
  const node = builder.make("field", props);

  if (field.type === "repeater") {
    node.children = (field.config?.subfields ?? [])
      .filter((subfield) => subfield?.key && subfield.type !== "json")
      .map((subfield, index) => builder.make("field", {
        field: subfield.key,
        display: builder.spec.value?.field_displays?.[subfield.type]?.[0] ?? "text",
        // The first text subfield reads as the item's heading.
        ...(index === 0 && (subfield.type ?? "text") === "text" ? { tag: "h4" } : {}),
      }, { margin_bottom: "xs" }));
  }

  return node;
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
