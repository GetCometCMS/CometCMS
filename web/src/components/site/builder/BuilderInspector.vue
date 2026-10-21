<template>
  <div v-if="templatePart" class="flex h-full flex-col overflow-y-auto">
    <div class="flex items-center gap-3 border-b border-slate-200 p-4">
      <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#f8eaf3] text-[#8c3b73] ring-1 ring-inset ring-[#e2a0ce]/50">
        <Icon :icon="templatePart === 'header' ? 'mdi:page-layout-header' : 'mdi:page-layout-footer'" class="h-5 w-5" aria-hidden="true" />
      </span>
      <div class="min-w-0">
        <p class="text-xs text-slate-500">{{ t("site.builder.templatePart") }}</p>
        <h2 class="text-sm font-semibold text-slate-900">{{ t(`site.region.${templatePart}`) }}</h2>
      </div>
    </div>
    <div class="space-y-4 p-4">
      <p class="text-sm leading-relaxed text-slate-600">{{ t("site.builder.templatePartHint") }}</p>
      <button type="button" class="btn-primary w-full justify-between" @click="$emit('edit-template-part', templatePart)">
        <span>{{ t("site.builder.editTemplatePart", { name: t(`site.region.${templatePart}`) }) }}</span>
        <Icon icon="mdi:arrow-right" class="h-4 w-4 shrink-0" aria-hidden="true" />
      </button>
    </div>
  </div>

  <div v-else-if="node" class="flex h-full flex-col">
    <nav :aria-label="t('site.builder.hierarchy')" class="shrink-0 border-b border-slate-100 px-2 py-1">
      <ol class="flex flex-wrap items-center gap-x-0.5 text-[11px]">
        <li v-for="(ancestor, index) in nodePath" :key="ancestor.id" class="flex min-w-0 max-w-full items-center gap-0.5">
          <Icon v-if="index" icon="mdi:chevron-right" class="h-3 w-3 shrink-0 text-slate-400" aria-hidden="true" />
          <span
            v-if="ancestor.id === node.id"
            aria-current="location"
            :title="builder.nodeLabel(ancestor)"
            class="max-w-[9rem] truncate rounded px-1.5 py-0.5 font-semibold text-theme-700"
          >{{ builder.nodeLabel(ancestor) }}</span>
          <button
            v-else
            type="button"
            :title="t('site.menu.selectParent', { name: builder.nodeLabel(ancestor) })"
            class="min-w-0 max-w-[9rem] truncate rounded px-1.5 py-0.5 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-theme-500"
            @click="builder.select(ancestor.id)"
          >{{ builder.nodeLabel(ancestor) }}</button>
        </li>
      </ol>
    </nav>
    <div class="relative z-10 flex shrink-0 items-center gap-2.5 px-3 pb-2 pt-2.5" :class="{ 'border-b border-slate-200': isInstance }">
      <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-theme-50 text-theme-600 ring-1 ring-inset ring-theme-100">
        <Icon :icon="nodeIcon(node, builder.isStacked(node))" class="h-[18px] w-[18px]" />
      </span>
      <div class="min-w-0 flex-1">
        <div class="flex min-w-0 items-center gap-1">
          <p class="truncate text-sm font-semibold text-slate-900" :title="builder.nodeLabel(node)">{{ builder.nodeLabel(node) }}</p>
          <BuilderHelp
            :key="node.id"
            :label="`${builder.nodeLabel(node)} — ${t('site.builder.elementHelp')}`"
            :hints="helpHints"
          />
        </div>
        <p class="min-h-[1rem] truncate text-xs text-slate-500">{{ builder.nodeSummary(node) }}</p>
      </div>
      <button type="button" class="inspector-action" :title="t('site.builder.duplicate')" :disabled="locked || builder.isCard(node)" @click="builder.duplicate(node.id)">
        <Icon icon="mdi:content-copy" class="h-4 w-4" />
      </button>
      <button type="button" class="inspector-action hover:!text-red-600" :title="t('common.delete')" :disabled="locked" @click="builder.remove(node.id)">
        <Icon icon="mdi:trash-can-outline" class="h-4 w-4" />
      </button>
    </div>

    <div v-if="!isInstance" class="flex shrink-0 gap-4 border-b border-slate-200 px-3" role="tablist">
      <button
        v-for="option in inspectorTabs"
        :key="option.value"
        type="button"
        role="tab"
        :aria-selected="tab === option.value"
        class="inspector-tab"
        :class="{ 'is-active': tab === option.value }"
        @click="tab = option.value"
      >
        <Icon :icon="option.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
        {{ option.label }}
      </button>
    </div>

    <div class="flex-1 space-y-4 overflow-y-auto overflow-x-hidden px-3 py-3 pb-32">
      <template v-if="isInstance">
        <div class="rounded-lg bg-slate-50 px-3 py-3 text-xs text-slate-600 ring-1 ring-slate-200">
          <p v-if="!component" class="text-amber-800">{{ t("site.component.missingHint") }}</p>
          <div class="mt-3 flex flex-wrap gap-2">
            <button v-if="component" type="button" class="btn-primary btn-sm" @click="builder.editComponent(component.id)">
              <Icon icon="mdi:puzzle-edit-outline" class="mr-1 h-4 w-4" />{{ t("site.component.edit") }}
            </button>
            <button v-if="component" type="button" class="btn-secondary btn-sm" :title="t('site.component.detachHint')" @click="builder.detach(node.id)">
              <Icon icon="mdi:link-variant-off" class="mr-1 h-4 w-4" />{{ t("site.component.detach") }}
            </button>
          </div>
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
      </template>

      <template v-else-if="tab === 'content'">
        <ColumnLayoutPicker v-if="['row', 'column'].includes(node.kind) && !builder.isCard(node)" :node="node" />
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
        <BuilderTabs v-if="node.kind === 'tabs'" :group="node" />
        <p v-if="node.kind === 'tab'" class="text-xs text-slate-500">{{ t('site.tabs.contentHint') }}</p>
        <div v-if="node.kind === 'collection' && node.props.display === 'cards'" class="rounded-lg bg-slate-50 px-3 py-3 text-xs text-slate-600 ring-1 ring-slate-200">
          <p class="font-medium text-slate-800">{{ hasCard ? t("site.card.custom") : t("site.card.standard") }}</p>
          <p class="mt-1">{{ hasCard ? t("site.card.customHint") : t("site.card.standardHint") }}</p>
          <div class="mt-3 flex flex-wrap gap-2">
            <button v-if="!hasCard" type="button" class="btn-primary btn-sm" :disabled="!node.props.type || cardBusy" @click="customizeCard">
              <Icon icon="mdi:card-text-outline" class="mr-1 h-4 w-4" />{{ t("site.card.customize") }}
            </button>
            <template v-else>
              <button type="button" class="btn-primary btn-sm" @click="builder.select(node.children[0].id)">
                <Icon icon="mdi:pencil-outline" class="mr-1 h-4 w-4" />{{ t("site.card.edit") }}
              </button>
              <button type="button" class="btn-secondary btn-sm" @click="builder.resetCard(node.id)">
                <Icon icon="mdi:restore" class="mr-1 h-4 w-4" />{{ t("site.card.reset") }}
              </button>
            </template>
          </div>
        </div>
        <p v-if="visibleProps.length === 0" class="text-xs text-slate-500">{{ t("site.builder.noOptions") }}</p>
      </template>

      <template v-else>
        <BuilderGroup
          v-for="group in styleGroups"
          :key="group.key"
          v-model:open="expandedStyleGroups[group.key]"
          heading-tag="h4"
          :title="t(`site.styleGroup.${group.key}`)"
          content-class="mt-3 space-y-3"
        >
          <SpacingControl
            v-if="group.key === 'spacing'"
            :node="node"
            :spec="styleSpec"
            :keys="group.keys"
            @update="(key, value) => builder.updateValue(node.id, 'style', key, value)"
          />
          <template v-else>
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
          </template>
        </BuilderGroup>
      </template>
    </div>
  </div>

  <div v-else class="h-full">
    <slot name="empty">
      <div class="flex h-full flex-col items-center justify-center px-6 text-center">
        <Icon icon="mdi:cursor-default-click-outline" class="h-10 w-10 text-slate-300" />
        <p class="mt-3 text-sm font-medium text-slate-700">{{ t("site.builder.nothingSelected") }}</p>
        <p class="mt-1 text-xs text-slate-500">{{ t("site.builder.nothingSelectedHint") }}</p>
      </div>
    </slot>
  </div>
</template>

<script setup>
import { computed, ref } from "vue";
import { Icon } from "@iconify/vue";
import BuilderControl from "./BuilderControl.vue";
import SpacingControl from "./SpacingControl.vue";
import BuilderHelp from "./BuilderHelp.vue";
import BuilderGroup from "./BuilderGroup.vue";
import BuilderTabs from "./BuilderTabs.vue";
import ColumnLayoutPicker from "./ColumnLayoutPicker.vue";
import { nodeIcon } from "./kindIcons.js";
import { COMPONENT, findNode } from "../../../composables/layoutTree.js";
import { useBuilderBreadcrumbs } from "../../../composables/useBuilderBreadcrumbs.js";
import { useSiteBuilder } from "../../../composables/useSiteBuilder.js";
import { useI18n } from "../../../i18n/index.js";

defineProps({
  templatePart: { type: String, default: null },
});
defineEmits(["edit-template-part"]);

const STYLE_GROUPS = [
  { key: "spacing", keys: ["padding_y", "padding_x", "padding_top", "padding_right", "padding_bottom", "padding_left", "margin_top", "margin_right", "margin_bottom", "margin_left"] },
  { key: "background", keys: ["background", "background_gradient", "background_image", "background_size", "background_position", "background_repeat", "background_blur", "overlay"] },
  { key: "text", keys: ["text_color", "text_gradient", "align", "font_size", "font_weight", "font_family", "text_shadow"] },
  { key: "shape", keys: ["radius", "shadow", "border", "border_width", "border_style", "border_color", "max_width"] },
  { key: "animation", keys: ["animation", "animation_trigger", "animation_duration", "animation_delay"] },
  { key: "advanced", keys: ["hide_on", "anchor", "class"] },
];

const builder = useSiteBuilder();
const { t } = useI18n();
const tab = ref("content");
const inspectorTabs = computed(() => [
  { value: "content", label: t("site.builder.content"), icon: "mdi:tune-variant" },
  { value: "style", label: t("site.builder.style"), icon: "mdi:palette-swatch-outline" },
]);
/** Only the most used groups start open. */
const OPEN_STYLE_GROUPS = ["spacing", "text"];
const expandedStyleGroups = ref(Object.fromEntries(STYLE_GROUPS.map(({ key }) => [key, OPEN_STYLE_GROUPS.includes(key)])));

const node = computed(() => builder.selected.value);
/** One sentence on what the selected kind of element is for. */
const kindHint = computed(() => {
  if (!node.value) return "";
  const kind = builder.isCard(node.value) ? "card" : node.value.kind;
  const key = `site.kindHint.${kind}`;
  const text = t(key);
  return text === key ? "" : text;
});
const helpHints = computed(() => {
  const current = node.value;
  if (!current) return [];
  const hints = [current.kind === COMPONENT && builder.componentOf(current) ? t("site.component.instanceHint") : kindHint.value];
  if (current.kind === "field" && builder.isTemplateField(current)) hints.push(t("site.builder.templateHint"));
  if (["heading", "text", "button"].includes(current.kind)) hints.push(t("site.builder.placeholderHint"));
  return hints.filter(Boolean);
});
const propSpec = computed(() => builder.spec.value?.kinds?.[node.value?.kind]?.props ?? {});
const styleSpec = computed(() => builder.spec.value?.style ?? {});
const isInstance = computed(() => node.value?.kind === COMPONENT);
const component = computed(() => builder.componentOf(node.value));
const locked = computed(() => builder.translating?.value || (!!node.value && builder.isLockedRoot(node.value.id)));
const nodePath = useBuilderBreadcrumbs(builder);
const fieldConfig = computed(() => (node.value?.kind === "field" ? builder.fieldConfig(node.value) : null));
const hasCard = computed(() => node.value?.kind === "collection" && (node.value.children?.length ?? 0) > 0);
const cardBusy = ref(false);

async function customizeCard() {
  cardBusy.value = true;
  try {
    await builder.customizeCard(node.value.id);
  } finally {
    cardBusy.value = false;
  }
}

const fieldWarning = computed(() => {
  if (node.value?.kind !== "field") return "";
  if (!node.value.props.source) return t("site.builder.chooseFieldHint");
  return fieldConfig.value ? "" : t("site.builder.missingFieldHint", { field: builder.nodeSummary(node.value) });
});

/** Image options only apply once there is an image; covering images never repeat. */
const BACKGROUND_IMAGE_OPTIONS = ["background_size", "background_position", "background_repeat", "background_blur", "overlay"];

/** How a border looks only matters once a side is picked; timing only once there is an animation. */
const BORDER_OPTIONS = ["border_width", "border_style", "border_color"];
const ANIMATION_OPTIONS = ["animation_trigger", "animation_duration", "animation_delay"];

function styleVisible(key) {
  const style = node.value?.style ?? {};
  if (style.background_gradient?.type && ["background_image", ...BACKGROUND_IMAGE_OPTIONS].includes(key)) return false;
  if (BACKGROUND_IMAGE_OPTIONS.includes(key) && !style.background_image) return false;
  if (BORDER_OPTIONS.includes(key) && !style.border?.length) return false;
  if (ANIMATION_OPTIONS.includes(key) && (style.animation ?? "none") === "none") return false;
  return key !== "background_repeat" || (style.background_size ?? styleSpec.value.background_size?.default) !== "cover";
}

const styleGroups = computed(() => {
  const allowed = builder.spec.value?.kinds?.[node.value?.kind]?.style ?? [];
  return STYLE_GROUPS
    .map((group) => ({ ...group, keys: group.keys.filter((key) => allowed.includes(key) && styleVisible(key)) }))
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
    const displays = type === 'media' && fieldConfig.value?.multiple ? ['gallery', 'files'] : builder.spec.value?.field_displays?.[type] ?? [];
    const display = props.display && displays.includes(props.display) ? props.display : displays[0];
    const rules = {
      source: true,
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

  // Only a column component's instances have something of their own: their width.
  if (current.kind === COMPONENT) {
    return props.root === "column" ? ["span"] : [];
  }

  if (current.kind === "collection") {
    return keys.filter((key) => (key !== "filter_value" || props.filter_field) && (key !== "columns" || props.display === "cards"));
  }

  if (["image", "gallery"].includes(current.kind)) {
    return keys.filter((key) => {
      if (props.masonry && ["ratio", "fit", "position"].includes(key)) return false;
      if (["fit", "position"].includes(key) && !props.ratio) return false;
      if (key === "label") return props.media?.type === "reference";
      if (key === "lightbox_navigation") return !!props.lightbox;
      if (key === "link") return !props.lightbox;
      return true;
    });
  }

  if (current.kind === "column") {
    // A card fills its place in the collection's grid; it has no width of its own.
    return keys.filter((key) => (props.direction === "row" || !["content_justify", "content_align"].includes(key)) && (key !== "span" || !builder.isCard(current)));
  }

  if (current.kind === "language_switcher") {
    // Flags on their own leave no text to format.
    return keys.filter((key) => key !== "format" || props.flag !== "only");
  }

  if (current.kind === "section") {
    // Scroll effects show on a section that stays in view while scrolling.
    return keys.filter((key) => key !== "scroll_effect" || (props.sticky ?? "none") !== "none" || props.overlap);
  }

  if (current.kind === "menu") {
    // The panel style only matters once there is a menu button.
    const hasButton = props.always_button || props.collapse_mobile;
    const rules = {
      panel: hasButton,
      panel_side: hasButton && props.panel === "drawer",
      panel_background: hasButton && ["fullscreen", "drawer"].includes(props.panel),
      panel_text_color: hasButton && ["fullscreen", "drawer"].includes(props.panel),
    };
    return keys.filter((key) => rules[key] !== false && (!props.always_button || !["orientation", "collapse_mobile"].includes(key)));
  }

  return keys;
});

function updateProp(key, value) {
  if (key !== "source") {
    builder.updateValue(node.value.id, "props", key, value);
    return;
  }

  // A new field binding starts with that field's default display; one undo step.
  const id = node.value.id;
  builder.mutate((tree) => {
    const target = findNode(tree, id);
    if (!target) return;
    target.props = { ...target.props, source: value };
    const config = builder.fieldConfig(target);
    const displays = config?.type === 'media' && config?.multiple ? ['gallery', 'files'] : builder.spec.value?.field_displays?.[config?.type] ?? [];
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
  color: rgb(var(--color-slate-500));
}
.inspector-action:hover:not(:disabled) {
  background: rgb(var(--color-slate-100));
  color: rgb(var(--color-slate-900));
}
.inspector-action:disabled {
  opacity: 0.35;
}
.inspector-tab {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5rem 0.125rem;
  font-size: 0.75rem;
  font-weight: 500;
  color: rgb(var(--color-slate-500));
  transition: color 150ms;
}
.inspector-tab:hover {
  color: rgb(var(--color-slate-900));
}
.inspector-tab.is-active {
  color: rgb(var(--color-theme-700));
}
.inspector-tab.is-active::after {
  content: "";
  position: absolute;
  inset: auto 0 -1px;
  height: 2px;
  border-radius: 2px 2px 0 0;
  background: rgb(var(--color-theme-600));
}
.inspector-tab:focus-visible {
  outline: none;
  box-shadow: inset 0 -2px 0 rgb(var(--color-theme-500));
}
.kbd {
  display: inline-block;
  min-width: 1.25rem;
  padding: 0 0.3rem;
  border: 1px solid rgb(var(--color-slate-300));
  border-bottom-width: 2px;
  border-radius: 0.25rem;
  font: 600 10px/1.4 ui-monospace, monospace;
  text-align: center;
  background: rgb(var(--color-surface));
}
</style>
