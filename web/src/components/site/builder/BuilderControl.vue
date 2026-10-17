<template>
  <div>
    <div class="mb-1 flex items-center justify-between gap-2">
      <label class="text-xs font-medium text-slate-700" :for="inputId">{{ label }}</label>
      <span v-if="hint" class="truncate text-[10px] text-slate-400">{{ hint }}</span>
    </div>

    <!-- enum -->
    <template v-if="def.type === 'enum' || def.type === 'display'">
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

    <input
      v-else-if="def.type === 'string' || def.type === 'class' || (def.type === 'key' && !keyOptions)"
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

    <LinkEditor v-else-if="def.type === 'link'" :model-value="value" :types="builder.types.value" @update:model-value="emitValue" />

    <div v-else-if="def.type === 'spans'" class="space-y-1.5">
      <div v-for="device in ['desktop', 'tablet', 'mobile']" :key="device" class="flex items-center gap-1.5">
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

    <OptionBar
      v-else-if="def.type === 'set'"
      multiple
      danger
      :model-value="value ?? []"
      :options="setOptions"
      @update:model-value="emitValue"
    />

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
import { Icon } from "@iconify/vue";
import FieldInput from "../../FieldInput.vue";
import ToggleSwitch from "../../ToggleSwitch.vue";
import LinkEditor from "../LinkEditor.vue";
import MediaSelect from "../MediaSelect.vue";
import SegmentedControl from "../SegmentedControl.vue";
import OptionBar from "./OptionBar.vue";
import { DEVICE_ICONS, barOptions, numberBar } from "./controlOptions.js";
import { effectiveSpan } from "../../../composables/layoutTree.js";
import { useSiteStore } from "../../../stores/site.js";
import { useSiteBuilder } from "../../../composables/useSiteBuilder.js";
import { useI18n } from "../../../i18n/index.js";

const SORTABLE_TYPES = ["text", "number", "range", "date", "datetime", "select", "boolean", "slug"];

const props = defineProps({
  node: { type: Object, required: true },
  name: { type: String, required: true },
  def: { type: Object, required: true },
  value: { default: null },
  group: { type: String, default: "props" },
});

const emit = defineEmits(["update"]);
const builder = useSiteBuilder();
const site = useSiteStore();
const { t } = useI18n();
const inputId = computed(() => `builder-${props.node.id}-${props.group}-${props.name}`);
const label = computed(() => {
  // Fields have a yes/no "link to the entry"; other elements pick a link target.
  if (props.name === "link" && props.def.type === "bool") return t("site.prop.linkToEntry");
  if (props.name === "span" && builder.isStacked(props.node)) return t("site.prop.spanStacked");
  return t(`site.${props.group === "style" ? "style" : "prop"}.${props.name}`);
});

const hint = computed(() => {
  if (props.name === "class") return t("site.builder.classHint");
  return "";
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
    const displays = builder.spec.value?.field_displays?.[fieldConfig.value?.type] ?? [];
    return displays.map((display) => ({ value: display, label: t(`site.display.${display}`) }));
  }
  return (props.def.options ?? []).map((option) => ({ value: option, label: optionLabel(option) }));
});

/** Theme colors for swatches, matching what the site shows. */
const themeColors = computed(() => {
  const theme = site.data?.theme;
  if (!theme) return null;
  return theme.mode === "dark" ? theme.dark_colors : theme.colors;
});

const enumBar = computed(() =>
  props.def.type === "enum"
    ? barOptions(props.group, props.name, props.def.options ?? [], {
        title: optionLabel,
        colors: themeColors.value,
        theme: site.data?.theme ?? null,
        kind: props.node.kind,
      })
    : null,
);
const isPositionGrid = computed(() => (props.def.options ?? []).length === 9 && props.def.options.includes("top-left"));
const numberOptions = computed(() => numberBar(props.def.min ?? 0, props.def.max ?? 0));
const setOptions = computed(() =>
  (props.def.options ?? []).map((option) => ({
    value: option,
    icon: DEVICE_ICONS[option],
    title: t("site.builder.hideOnDevice", { device: t(`site.device.${option}`) }),
  })),
);

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
    case "field": {
      const fields = builder.bindableFields(props.node).map((field) => ({ value: field.key, label: field.label }));
      if (props.value && !fields.some((field) => field.value === props.value)) {
        fields.unshift({ value: props.value, label: `${props.value} (${t("site.builder.missing")})` });
      }
      return [{ value: "", label: t("site.builder.chooseField") }, ...fields];
    }
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
