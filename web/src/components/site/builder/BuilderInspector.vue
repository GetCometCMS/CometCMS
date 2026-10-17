<template>
  <div v-if="node" class="flex h-full flex-col">
    <div class="flex items-center gap-2 border-b border-slate-200 px-4 py-3">
      <Icon :icon="nodeIcon(node, builder.isStacked(node))" class="h-5 w-5 shrink-0 text-theme-600" />
      <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-slate-900">{{ builder.nodeLabel(node) }}</p>
        <p class="min-h-[1rem] truncate text-xs text-slate-500">{{ builder.nodeSummary(node) }}</p>
      </div>
      <button type="button" class="inspector-action" :title="t('site.builder.selectParent')" :disabled="!hasParent" @click="builder.selectParent()">
        <Icon icon="mdi:arrow-up-left" class="h-4 w-4" />
      </button>
      <button type="button" class="inspector-action" :title="t('site.builder.duplicate')" @click="builder.duplicate(node.id)">
        <Icon icon="mdi:content-copy" class="h-4 w-4" />
      </button>
      <button type="button" class="inspector-action hover:!text-red-600" :title="t('common.delete')" @click="builder.remove(node.id)">
        <Icon icon="mdi:trash-can-outline" class="h-4 w-4" />
      </button>
      <button type="button" class="inspector-action" :title="t('site.menu.more')" @click="openMenu">
        <Icon icon="mdi:dots-horizontal" class="h-4 w-4" />
      </button>
    </div>

    <div class="border-b border-slate-200 px-4 py-2">
      <SegmentedControl
        v-model="tab"
        :options="[
          { value: 'content', label: t('site.builder.content'), icon: 'mdi:tune-variant', showLabel: true },
          { value: 'style', label: t('site.builder.style'), icon: 'mdi:palette-swatch-outline', showLabel: true },
        ]"
      />
    </div>

    <div class="flex-1 space-y-4 overflow-y-auto px-4 py-4">
      <template v-if="tab === 'content'">
        <ColumnLayoutPicker v-if="['section', 'row', 'column'].includes(node.kind)" :node="node" />
        <div v-if="fieldWarning" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-amber-200">
          {{ fieldWarning }}
        </div>
        <BuilderControl
          v-for="key in visibleProps"
          :key="`${node.id}-p-${key}`"
          :node="node"
          :name="key"
          :def="propSpec[key]"
          :value="node.props[key]"
          @update="(value) => updateProp(key, value)"
        />
        <p v-if="visibleProps.length === 0" class="text-xs text-slate-500">{{ t("site.builder.noOptions") }}</p>
        <p v-if="node.kind === 'field' && builder.isTemplateField(node)" class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
          {{ t("site.builder.templateHint") }}
        </p>
        <p v-if="['heading', 'text', 'button'].includes(node.kind)" class="text-[11px] text-slate-400">
          {{ t("site.builder.placeholderHint") }}
        </p>
      </template>

      <template v-else>
        <section v-for="group in styleGroups" :key="group.key" class="space-y-3">
          <h4 class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ t(`site.styleGroup.${group.key}`) }}</h4>
          <BuilderControl
            v-for="key in group.keys"
            :key="`${node.id}-s-${key}`"
            :node="node"
            :name="key"
            group="style"
            :def="styleSpec[key]"
            :value="node.style?.[key] ?? styleSpec[key].default"
            @update="(value) => builder.updateValue(node.id, 'style', key, value)"
          />
        </section>
      </template>
    </div>
  </div>

  <div v-else class="flex h-full flex-col items-center justify-center px-6 text-center">
    <Icon icon="mdi:cursor-default-click-outline" class="h-10 w-10 text-slate-300" />
    <p class="mt-3 text-sm font-medium text-slate-700">{{ t("site.builder.nothingSelected") }}</p>
    <p class="mt-1 text-xs text-slate-500">{{ t("site.builder.nothingSelectedHint") }}</p>
    <ul class="mt-4 space-y-1 text-left text-[11px] text-slate-500">
      <li><kbd class="kbd">Ctrl</kbd> + <kbd class="kbd">Z</kbd> {{ t("site.builder.undo") }}</li>
      <li><kbd class="kbd">Ctrl</kbd> + <kbd class="kbd">D</kbd> {{ t("site.builder.duplicate") }}</li>
      <li><kbd class="kbd">Del</kbd> {{ t("common.delete") }}</li>
      <li><kbd class="kbd">Ctrl</kbd> + <kbd class="kbd">S</kbd> {{ t("site.builder.save") }}</li>
    </ul>
  </div>
</template>

<script setup>
import { computed, ref } from "vue";
import { Icon } from "@iconify/vue";
import BuilderControl from "./BuilderControl.vue";
import ColumnLayoutPicker from "./ColumnLayoutPicker.vue";
import SegmentedControl from "../SegmentedControl.vue";
import { nodeIcon } from "./kindIcons.js";
import { findNode, locate } from "../../../composables/layoutTree.js";
import { useSiteBuilder } from "../../../composables/useSiteBuilder.js";
import { useI18n } from "../../../i18n/index.js";

const STYLE_GROUPS = [
  { key: "spacing", keys: ["padding_y", "padding_x", "margin_bottom"] },
  { key: "colors", keys: ["background", "text_color"] },
  { key: "text", keys: ["align", "font_size", "font_weight", "font_family"] },
  { key: "shape", keys: ["radius", "shadow", "border", "max_width"] },
  { key: "advanced", keys: ["hide_on", "anchor", "class"] },
];

const builder = useSiteBuilder();
const { t } = useI18n();
const tab = ref("content");

const node = computed(() => builder.selected.value);
const propSpec = computed(() => builder.spec.value?.kinds?.[node.value?.kind]?.props ?? {});
const styleSpec = computed(() => builder.spec.value?.style ?? {});
const hasParent = computed(() => !!(node.value && locate(builder.nodes.value, node.value.id)?.parent));
const fieldConfig = computed(() => (node.value?.kind === "field" ? builder.fieldConfig(node.value) : null));

const fieldWarning = computed(() => {
  if (node.value?.kind !== "field") return "";
  if (!node.value.props.field) return t("site.builder.chooseFieldHint");
  return fieldConfig.value ? "" : t("site.builder.missingFieldHint", { field: node.value.props.field });
});

const styleGroups = computed(() => {
  const allowed = builder.spec.value?.kinds?.[node.value?.kind]?.style ?? [];
  return STYLE_GROUPS
    .map((group) => ({ ...group, keys: group.keys.filter((key) => allowed.includes(key)) }))
    .filter((group) => group.keys.length > 0);
});

/** Which props make sense for the node in its current configuration. */
const visibleProps = computed(() => {
  const current = node.value;
  if (!current) return [];
  const keys = Object.keys(propSpec.value);
  const props = current.props ?? {};

  if (current.kind === "field") {
    const type = fieldConfig.value?.type;
    const displays = builder.spec.value?.field_displays?.[type] ?? [];
    const display = props.display && displays.includes(props.display) ? props.display : displays[0];
    const rules = {
      field: true,
      display: displays.length > 1,
      label: !!type,
      tag: ["text", "slug", "select", "number", "range", "textarea"].includes(type) && display !== "excerpt",
      link: ["text", "slug"].includes(type) || (type === "media" && display === "image") || (type === "textarea" && display === "excerpt"),
      truncate: ["text", "textarea"].includes(type) || (["markdown", "html"].includes(type) && display === "excerpt"),
      date_format: ["date", "datetime"].includes(type),
      ratio: type === "media" && display !== "files",
      position: type === "media" && display !== "files" && !!props.ratio,
      fit: type === "media" && display !== "files" && !!props.ratio,
      columns: (type === "media" && display === "gallery") || (type === "relation" && display === "cards") || type === "repeater",
      prefix: ["number", "range", "select"].includes(type),
      suffix: ["number", "range", "select"].includes(type),
      true_text: type === "boolean",
      false_text: type === "boolean",
    };
    return keys.filter((key) => rules[key]);
  }

  if (current.kind === "section") {
    const imageOptions = ["background_size", "background_position", "background_repeat", "background_blur", "overlay"];
    return keys.filter((key) =>
      (!imageOptions.includes(key) || props.background_image)
      && (key !== "background_repeat" || props.background_size !== "cover"),
    );
  }

  if (current.kind === "collection") {
    return keys.filter((key) => (key !== "filter_value" || props.filter_field) && (key !== "columns" || props.display === "cards"));
  }

  if (current.kind === "image") {
    return keys.filter((key) => !["fit", "position"].includes(key) || props.ratio);
  }

  return keys;
});

function openMenu(event) {
  const rect = event.currentTarget.getBoundingClientRect();
  builder.openContextMenu(node.value.id, rect.right - 288, rect.bottom + 6);
}

function updateProp(key, value) {
  if (key !== "field") {
    builder.updateValue(node.value.id, "props", key, value);
    return;
  }

  // A new field binding starts with that field's default display; one undo step.
  const id = node.value.id;
  builder.mutate((tree) => {
    const target = findNode(tree, id);
    if (!target) return;
    target.props = { ...target.props, field: value };
    const displays = builder.spec.value?.field_displays?.[builder.fieldConfig(target)?.type] ?? [];
    if (!displays.includes(target.props.display)) target.props.display = displays[0] ?? "";
  });
}
</script>

<style scoped>
.inspector-action {
  display: inline-flex;
  height: 1.75rem;
  width: 1.75rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.375rem;
  color: rgb(100 116 139);
}
.inspector-action:hover:not(:disabled) {
  background: rgb(241 245 249);
  color: rgb(15 23 42);
}
.inspector-action:disabled {
  opacity: 0.35;
}
.kbd {
  display: inline-block;
  min-width: 1.25rem;
  padding: 0 0.3rem;
  border: 1px solid rgb(203 213 225);
  border-bottom-width: 2px;
  border-radius: 0.25rem;
  font: 600 10px/1.4 ui-monospace, monospace;
  text-align: center;
  background: white;
}
</style>
