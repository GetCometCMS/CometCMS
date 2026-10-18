<template>
  <div>
    <div v-if="!def.binding" class="mb-1 flex items-center justify-between gap-2">
      <label class="text-xs font-medium text-slate-700" :for="inputId">{{ label }}</label>
      <span v-if="hint" class="truncate text-[10px] text-slate-400">{{ hint }}</span>
    </div>

    <SmartValueEditor v-if="def.binding" :model-value="value" :node="node" :target="def.binding" :label="label" @update:model-value="emitValue">
      <template v-if="def.binding === 'media' || def.binding === 'link'" #literal>
        <MediaSelect v-if="def.binding === 'media'" :model-value="value" :name="inputId" :compact="group === 'style'" @update:model-value="emitValue" />
        <LinkEditor v-else :model-value="value" :types="builder.types.value" :settings="linkSettings" :context="node.id" compact @update:model-value="emitValue" />
      </template>
    </SmartValueEditor>

    <IconPickerGrid
      v-else-if="def.type === 'icon'"
      :id="inputId"
      :model-value="value ?? def.default"
      :icons="def.options ?? SITE_ICONS"
      :allow-custom="false"
      @update:model-value="emitValue"
    />

    <!-- Language switcher flags, each choice drawn as it will look -->
    <div v-else-if="isFlagChoice" class="grid grid-cols-3 gap-1.5" role="radiogroup" :aria-label="label">
      <button
        v-for="option in def.options"
        :key="option"
        type="button"
        role="radio"
        :aria-checked="value === option"
        class="flex min-w-0 flex-col items-center gap-1 rounded-lg border px-1 py-2 transition-colors"
        :class="value === option ? 'border-theme-300 bg-theme-50 text-theme-800 ring-1 ring-theme-300' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50'"
        @click="emitValue(option)"
      >
        <span class="flex h-5 items-center gap-1 text-xs font-semibold">
          <span v-if="option !== 'none' && flagSample.country" :class="['fi', `fi-${flagSample.country}`]" class="rounded-[2px] shadow-[0_0_0_1px_rgb(0_0_0_/_0.12)]" aria-hidden="true"></span>
          <span v-if="option !== 'only' || !flagSample.country" class="max-w-[4.5rem] truncate">{{ flagSample.text }}</span>
        </span>
        <span class="w-full truncate text-center text-[10px] font-medium">{{ t(`site.flag.${option}`) }}</span>
      </button>
    </div>

    <!-- enum -->
    <ThemeColorPicker
      v-else-if="def.type === 'color'"
      :id="inputId"
      :label="label"
      :model-value="value ?? ''"
      :options="colorOptions"
      @update:model-value="emitValue"
    />

    <template v-else-if="def.type === 'enum' || def.type === 'display'">
      <div v-if="isPositionGrid" class="grid w-24 grid-cols-3 gap-1 rounded-lg border border-slate-200 bg-slate-50 p-1" role="radiogroup" :aria-label="label">
        <button
          v-for="option in def.options"
          :key="option"
          type="button"
          role="radio"
          :aria-checked="value === option"
          :title="optionLabel(option)"
          class="flex h-6 items-center justify-center rounded transition-colors"
          :class="value === option ? 'bg-white shadow-sm ring-1 ring-slate-200' : 'hover:bg-white/70'"
          @click="emitValue(option)"
        >
          <span class="h-2 w-2 rounded-full" :class="value === option ? 'bg-theme-600' : 'bg-slate-300'"></span>
        </button>
      </div>
      <OptionBar v-else-if="enumBar" :model-value="enumValue" :options="enumBar" @update:model-value="emitValue" />
      <SegmentedControl
        v-else-if="enumOptions.length <= 4 && enumOptions.every((option) => option.label.length <= 9)"
        :model-value="enumValue"
        :options="enumOptions"
        @update:model-value="emitValue"
      />
      <select
        v-else
        :id="inputId"
        :value="enumValue"
        class="form-select w-full rounded-lg border-slate-300 py-1.5 text-xs"
        @change="emitValue($event.target.value)"
      >
        <option v-for="option in enumOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
      </select>
    </template>

    <ToggleSwitch v-else-if="def.type === 'bool'" :id="inputId" :model-value="!!value" @update:model-value="emitValue" />

    <OptionBar
      v-else-if="def.type === 'int' && numberOptions"
      :model-value="value"
      :options="numberOptions"
      @update:model-value="emitValue"
    />

    <!-- A size in units: slider with an exact value beside it; 0 (empty) is automatic. -->
    <div v-else-if="def.type === 'int' && def.unit" class="flex items-center gap-2">
      <input
        type="range"
        class="min-w-0 flex-1 accent-theme-600"
        :min="def.min"
        :max="def.slider_max ?? def.max"
        :step="node.kind === 'icon' ? 1 : SIZE_STEP"
        :value="value ?? 0"
        :aria-label="label"
        @input="emitNumber($event.target.value)"
      />
      <span class="relative w-20 shrink-0">
        <input
          :id="inputId"
          type="number"
          :min="def.min"
          :max="def.max"
          :value="value || ''"
          :placeholder="t('site.builder.spanAuto')"
          class="form-input w-full rounded-lg border-slate-300 py-1.5 pr-7 text-xs"
          @change="emitNumber($event.target.value || 0)"
        />
        <span class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-[10px] text-slate-400">{{ def.unit }}</span>
      </span>
    </div>

    <input
      v-else-if="def.type === 'int'"
      :id="inputId"
      type="number"
      :min="def.min"
      :max="def.max"
      :value="value"
      class="form-input w-full rounded-lg border-slate-300 py-1.5 text-xs"
      @input="emitNumber($event.target.value)"
    />

    <CssClassInput
      v-else-if="def.type === 'class'"
      :id="inputId"
      :model-value="value ?? ''"
      :options="customClasses"
      @update:model-value="emitValue"
    />

    <input
      v-else-if="def.type === 'string' || (def.type === 'key' && !keyOptions)"
      :id="inputId"
      type="text"
      :value="value"
      :maxlength="def.max"
      class="form-input w-full rounded-lg border-slate-300 py-1.5 text-xs"
      :class="{ 'font-mono': def.type !== 'string' }"
      @input="emitValue($event.target.value)"
    />

    <div v-else-if="def.type === 'html'" class="site-builder-richtext">
      <FieldInput :name="inputId" :config="{ type: 'html' }" :model-value="value" @update:model-value="emitValue" />
    </div>

    <MediaSelect v-else-if="def.type === 'media'" :model-value="value" :name="inputId" @update:model-value="emitValue" />

    <LinkEditor v-else-if="def.type === 'link'" :model-value="value" :types="builder.types.value" :settings="linkSettings" :context="node.id" compact @update:model-value="emitValue" />

    <div v-else-if="def.type === 'spans'" class="space-y-1.5">
      <div v-for="device in devices" :key="device" class="flex items-center gap-1.5">
        <Icon :icon="DEVICE_ICONS[device]" class="h-4 w-4 shrink-0 text-slate-400" :title="t(`site.device.${device}`)" />
        <div class="flex h-6 flex-1 gap-px overflow-hidden rounded-md bg-slate-200 p-px" role="radiogroup" :aria-label="t(`site.device.${device}`)">
          <button
            v-for="span in 12"
            :key="span"
            type="button"
            role="radio"
            :aria-checked="value?.[device] === span"
            :title="t('site.builder.spanOf', { span })"
            class="flex-1 rounded-[3px] transition-colors"
            :class="spanCellClass(device, span)"
            @click="emitValue({ ...value, [device]: span })"
          ></button>
        </div>
        <button
          v-if="device !== 'mobile'"
          type="button"
          class="w-10 shrink-0 rounded-md px-1 py-0.5 text-[10px] font-semibold"
          :class="!value?.[device] ? 'bg-theme-600 text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'"
          :title="t('site.builder.spanAutoHint')"
          @click="emitValue({ ...value, [device]: 0 })"
        >
          {{ t("site.builder.spanAuto") }}
        </button>
        <span v-else class="w-10 shrink-0 text-center text-[10px] font-semibold text-slate-500">{{ value?.mobile ?? 12 }}/12</span>
      </div>
    </div>

    <div v-else-if="def.type === 'set'" class="flex gap-1.5">
      <OptionBar
        multiple
        :danger="name === 'hide_on'"
        :model-value="value ?? []"
        :options="setOptions"
        @update:model-value="emitValue"
      />
      <button
        v-if="name === 'border'"
        type="button"
        class="flex w-9 shrink-0 items-center justify-center rounded-lg border transition-colors"
        :class="allSet ? 'border-theme-200 bg-white text-theme-700 shadow-sm' : 'border-slate-200 bg-slate-50 text-slate-500 hover:text-slate-900'"
        :aria-pressed="allSet"
        :title="t('site.option.all')"
        :aria-label="t('site.option.all')"
        @click="emitValue(allSet ? [] : [...def.options])"
      >
        <Icon icon="mdi:border-all-variant" class="h-4 w-4" />
      </button>
    </div>

    <select
      v-else
      :id="inputId"
      :value="value"
      class="form-select w-full rounded-lg border-slate-300 py-1.5 text-xs"
      @change="emitValue($event.target.value)"
    >
      <option v-for="option in selectOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
    </select>
  </div>
</template>

<script setup>
import { computed } from "vue";
import SmartValueEditor from "./SmartValueEditor.vue";
import { Icon } from "@iconify/vue";
import FieldInput from "../../FieldInput.vue";
import IconPickerGrid from "../../IconPickerGrid.vue";
import { SITE_ICONS } from "../../../composables/siteIcons.js";
import ToggleSwitch from "../../ToggleSwitch.vue";
import LinkEditor from "../LinkEditor.vue";
import { getActiveWorkspace } from "../../../api/index.js";
import MediaSelect from "../MediaSelect.vue";
import SegmentedControl from "../SegmentedControl.vue";
import OptionBar from "./OptionBar.vue";
import CssClassInput from "./CssClassInput.vue";
import ThemeColorPicker from "./ThemeColorPicker.vue";
import { themeCssClasses } from "../../../composables/cssClasses.js";
import { themeColorOptions } from "../../../composables/themeColors.js";
import { DEVICE_ICONS, barOptions, numberBar } from "./controlOptions.js";
import { effectiveSpan } from "../../../composables/layoutTree.js";
import { localeFlagCountry } from "../../../composables/localeOptions.js";
import { useSiteStore } from "../../../stores/site.js";
import { useSiteBuilder } from "../../../composables/useSiteBuilder.js";
import { useI18n } from "../../../i18n/index.js";

/** Slider step of sizes; the number field beside it takes any value. */
const SIZE_STEP = 10;
const SORTABLE_TYPES = ["text", "number", "range", "date", "datetime", "select", "boolean", "slug"];

const props = defineProps({
  node: { type: Object, required: true },
  name: { type: String, required: true },
  def: { type: Object, required: true },
  value: { default: null },
  group: { type: String, default: "props" },
  /** Devices a column width control shows, e.g. only the one previewed. */
  devices: { type: Array, default: () => ["desktop", "tablet", "mobile"] },
});

const emit = defineEmits(["update"]);
const builder = useSiteBuilder();
const site = useSiteStore();
const linkSettings = computed(() => site.data?.workspace === getActiveWorkspace() && site.draft ? site.draft.settings : builder.settings.value);
const { t } = useI18n();
const inputId = computed(() => `builder-${props.node.id}-${props.group}-${props.name}`);
/** The design being edited, so new custom colors and classes are offered right away. */
const siteTheme = computed(() => site.draft?.theme ?? site.data?.theme ?? null);
const customClasses = computed(() => themeCssClasses(siteTheme.value?.custom_css ?? ""));
const label = computed(() => {
  // Fields have a yes/no "link to the entry"; other elements pick a link target.
  if (props.name === "link" && props.def.type === "bool") return t("site.prop.linkToEntry");
  if (props.name === "span" && builder.isStacked(props.node)) return t("site.prop.spanStacked");
  if (props.name === "text_color" && props.node.kind === "divider") return t("site.style.divider_color");
  if (props.name === "label" && props.node.kind === "icon") return t("site.prop.iconLabel");
  return t(`site.${props.group === "style" ? "style" : "prop"}.${props.name}`);
});

const hint = computed(() => {
  if (props.name === "class") return t("site.builder.classHint");
  if (props.name === "background_blur") return t("site.builder.backgroundBlurHint");
  if (props.node.kind === "section" && props.name === "sticky" && props.value !== "none") return t("site.builder.stickyHint");
  if (props.node.kind === "section" && props.name === "overlap" && props.value) return t("site.builder.overlapHint");
  return "";
});

const isFlagChoice = computed(() => props.node.kind === "language_switcher" && props.name === "flag");

/** The site's main language as the switcher would show it, for the flag previews. */
const flagSample = computed(() => {
  const settings = builder.settings.value ?? {};
  const locale = settings.default_locale || settings.locales?.[0] || "en";
  let text = locale.toUpperCase();
  if (props.node.props?.format === "names") {
    try {
      const name = new Intl.DisplayNames([locale], { type: "language" }).of(locale);
      if (name) text = name.charAt(0).toUpperCase() + name.slice(1);
    } catch {
      // Unknown to the browser: keep the code.
    }
  }
  return { country: localeFlagCountry(locale), text };
});

function optionLabel(option) {
  if (option === "") return t(props.group === "style" ? "site.option.default" : "site.option.auto");
  if (/^h[1-6]$/.test(option)) return option.toUpperCase();
  if (/^\d+:\d+$/.test(option)) return option;
  const key = `site.option.${option}`;
  const translated = t(key);
  return translated === key ? option : translated;
}

const fieldConfig = computed(() => builder.fieldConfig(props.node));

const enumOptions = computed(() => {
  if (props.def.type === "display") {
    const displays = fieldConfig.value?.type === 'media' && fieldConfig.value?.multiple
      ? ['gallery', 'files']
      : builder.spec.value?.field_displays?.[fieldConfig.value?.type] ?? [];
    return displays.map((display) => ({ value: display, label: t(`site.display.${display}`) }));
  }
  return (props.def.options ?? []).map((option) => ({ value: option, label: optionLabel(option) }));
});

const colorOptions = computed(() => themeColorOptions(siteTheme.value, props.def.options ?? [], optionLabel));

const enumBar = computed(() =>
  props.def.type === "enum"
    ? barOptions(props.group, props.name, props.def.options ?? [], {
        title: optionLabel,
        theme: siteTheme.value,
        kind: props.node.kind,
      })
    : null,
);
const isPositionGrid = computed(() => (props.def.options ?? []).length === 9 && props.def.options.includes("top-left"));
const numberOptions = computed(() => numberBar(props.def.min ?? 0, props.def.max ?? 0));
const setOptions = computed(() => {
  const options = props.def.options ?? [];
  if (props.name !== "hide_on") {
    return barOptions(props.group, props.name, options, { title: optionLabel }) ?? options.map((option) => ({ value: option, title: optionLabel(option), label: optionLabel(option) }));
  }
  return options.map((option) => ({
    value: option,
    icon: DEVICE_ICONS[option],
    title: t("site.builder.hideOnDevice", { device: t(`site.device.${option}`) }),
  }));
});
const allSet = computed(() => (props.def.options ?? []).every((option) => (props.value ?? []).includes(option)));

function spanCellClass(device, span) {
  const own = props.value?.[device] ?? 0;
  if (own > 0) return span <= own ? "bg-theme-500" : "bg-white hover:bg-theme-100";
  // Inherited from the smaller device: shown lighter.
  return span <= effectiveSpan(props.value, device) ? "bg-theme-200" : "bg-white hover:bg-theme-100";
}

// A field without a chosen display uses its type's first display.
const enumValue = computed(() =>
  props.def.type === "display" && !props.value ? (enumOptions.value[0]?.value ?? "") : props.value,
);

const collectionType = computed(() => builder.types.value.find((type) => type.name === props.node.props?.type) ?? null);

function fieldsOfCollection(filter = () => true) {
  const fields = Object.entries(collectionType.value?.fields ?? {})
    .filter(([key, config]) => key !== "slug" && config && filter(config))
    .map(([key, config]) => ({ value: key, label: config.label || key }));
  return [
    { value: "title", label: t("site.systemField.title") },
    ...fields,
  ];
}

const keyOptions = computed(() => {
  if (props.name === "filter_field") {
    return [{ value: "", label: t("site.option.none") }, ...fieldsOfCollection((config) => config.type !== "json" && config.type !== "repeater")];
  }
  return null;
});

const selectOptions = computed(() => {
  switch (props.def.type) {
    case "content_type":
      return [
        { value: "", label: t("site.builder.chooseType") },
        ...builder.types.value
          .filter((type) => !type.singleton && type.visibility !== "private")
          .map((type) => ({ value: type.name, label: type.label })),
      ];
    case "menu":
      return builder.menus.value.map((menu) => ({ value: menu.name, label: menu.label }));
    case "sort_field":
      return [
        { value: "published_at", label: t("site.systemField.published_at") },
        { value: "updated_at", label: t("site.systemField.updated_at") },
        { value: "created_at", label: t("site.systemField.created_at") },
        ...fieldsOfCollection((config) => SORTABLE_TYPES.includes(config.type)),
      ];
    case "key":
      return keyOptions.value ?? [];
    default:
      return [];
  }
});

function emitValue(value) {
  emit("update", value);
}

function emitNumber(raw) {
  if (raw === "") return;
  const number = Math.round(Number(raw));
  if (Number.isFinite(number)) emit("update", Math.max(props.def.min ?? number, Math.min(props.def.max ?? number, number)));
}
</script>

<style scoped>
.site-builder-richtext :deep(.tiptap-content) {
  max-height: 18rem;
  overflow-y: auto;
}
</style>
