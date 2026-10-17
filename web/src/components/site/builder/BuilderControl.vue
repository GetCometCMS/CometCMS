<template>
  <div>
    <div class="mb-1 flex items-center justify-between gap-2">
      <label class="text-xs font-medium text-slate-700" :for="inputId">{{ label }}</label>
      <span v-if="hint" class="truncate text-[10px] text-slate-400">{{ hint }}</span>
    </div>

    <!-- enum -->
    <template v-if="def.type === 'enum' || def.type === 'display'">
      <SegmentedControl
        v-if="enumOptions.length <= 4 && enumOptions.every((option) => option.label.length <= 9)"
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

    <div v-else-if="def.type === 'spans'" class="grid grid-cols-3 gap-1.5">
      <label v-for="device in ['mobile', 'tablet', 'desktop']" :key="device" class="block">
        <span class="mb-0.5 flex items-center gap-1 text-[10px] text-slate-500">
          <Icon :icon="DEVICE_ICONS[device]" class="h-3 w-3" />{{ t(`site.device.${device}`) }}
        </span>
        <select
          :value="value?.[device] ?? 0"
          class="form-select w-full rounded-lg border-slate-300 px-2 py-1 text-xs"
          @change="emitValue({ ...value, [device]: Number($event.target.value) })"
        >
          <option v-if="device !== 'mobile'" :value="0">{{ t("site.option.inherit") }}</option>
          <option v-for="span in 12" :key="span" :value="span">{{ spanLabel(span) }}</option>
        </select>
      </label>
    </div>

    <div v-else-if="def.type === 'set'" class="flex flex-wrap gap-3">
      <label v-for="option in def.options" :key="option" class="flex items-center gap-1.5 text-xs text-slate-600">
        <input
          type="checkbox"
          class="rounded border-slate-300 text-theme-600"
          :checked="(value ?? []).includes(option)"
          @change="toggleSet(option, $event.target.checked)"
        />
        {{ t(`site.device.${option}`) }}
      </label>
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
import { Icon } from "@iconify/vue";
import FieldInput from "../../FieldInput.vue";
import ToggleSwitch from "../../ToggleSwitch.vue";
import LinkEditor from "../LinkEditor.vue";
import MediaSelect from "../MediaSelect.vue";
import SegmentedControl from "../SegmentedControl.vue";
import { useSiteBuilder } from "../../../composables/useSiteBuilder.js";
import { useI18n } from "../../../i18n/index.js";

const DEVICE_ICONS = { mobile: "mdi:cellphone", tablet: "mdi:tablet", desktop: "mdi:monitor" };
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
const { t } = useI18n();
const inputId = computed(() => `builder-${props.node.id}-${props.group}-${props.name}`);
const label = computed(() => {
  // Fields have a yes/no "link to the entry"; other elements pick a link target.
  if (props.name === "link" && props.def.type === "bool") return t("site.prop.linkToEntry");
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

function spanLabel(span) {
  return span === 12 ? t("site.option.full") : `${span}/12`;
}

function emitValue(value) {
  emit("update", value);
}

function emitNumber(raw) {
  if (raw === "") return;
  const number = Math.round(Number(raw));
  if (Number.isFinite(number)) emit("update", Math.max(props.def.min ?? number, Math.min(props.def.max ?? number, number)));
}

function toggleSet(option, checked) {
  const current = new Set(props.value ?? []);
  if (checked) current.add(option);
  else current.delete(option);
  emit("update", (props.def.options ?? []).filter((item) => current.has(item)));
}
</script>

<style scoped>
.site-builder-richtext :deep(.tiptap-content) {
  max-height: 18rem;
  overflow-y: auto;
}
</style>
