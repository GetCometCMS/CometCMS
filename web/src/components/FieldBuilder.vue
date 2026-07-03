<!-- Visual drag-and-drop field editor for content types -->
<template>
  <div class="space-y-3">
    <TransitionGroup tag="div" name="field-sort" class="space-y-3">
      <div
        v-for="(field, index) in fields"
        :key="field._id"
        class="group/field select-none"
        :class="[
          field._collapsed
            ? 'card cursor-grab p-3'
            : 'rounded-lg border border-theme-400 bg-white shadow-sm ring-1 ring-theme-300',
          { 'ring-2 ring-theme-400': dragIndex === index },
        ]"
        draggable="true"
        @dragstart="onDragStart(index, $event)"
        @dragover.prevent="onDragOver(index, $event)"
        @dragend="onDragEnd"
      >
        <div v-if="field._collapsed" class="flex items-center gap-3">
          <button
            type="button"
            @click.stop="toggleField(field)"
            class="shrink-0 text-slate-400 hover:text-theme-600 transition-colors p-1 rounded"
            title="Expand field"
            aria-label="Expand field"
            :aria-expanded="false"
          >
            <Icon icon="mdi:chevron-down" class="w-4 h-4 -rotate-90" />
          </button>

          <Icon
            icon="mdi:drag-vertical"
            class="w-4 h-4 text-slate-400 shrink-0"
          />
          <span
            class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-theme-100"
          >
            <Icon
              :icon="fieldTypeIcon(field.type)"
              class="h-4 w-4 text-theme-600"
            />
          </span>

          <button
            type="button"
            class="flex-1 min-w-0 flex items-center gap-3 text-left"
            @click.stop="expandCollapsedField(field)"
          >
            <div class="min-w-0">
              <div class="truncate text-sm font-medium text-slate-800">
                {{ field.label || field.key || "Untitled field" }}
              </div>
              <div
                class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500"
              >
                <code class="font-mono bg-slate-100 px-1 py-0.5 rounded">{{
                  field.key || "field_key"
                }}</code>
                <span
                  class="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5"
                >
                  <Icon :icon="fieldTypeIcon(field.type)" class="h-3 w-3" />
                  {{ field.type }}
                </span>
                <span
                  v-if="field.required"
                  class="inline-flex rounded bg-amber-50 px-1.5 py-0.5 text-amber-700"
                  >Required</span
                >
                <span
                  v-if="field.localized === false"
                  class="inline-flex items-center gap-1 rounded bg-sky-50 px-1.5 py-0.5 text-sky-700"
                >
                  <Icon icon="mdi:web" class="h-3 w-3" />
                  Universal
                </span>
                <span
                  v-if="fieldLayoutWidth(field) !== 'full'"
                  class="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-slate-600"
                  :title="fieldLayoutLabel(field)"
                >
                  <span
                    class="flex h-3 w-6 overflow-hidden rounded-sm bg-slate-300"
                  >
                    <span
                      class="h-full rounded-sm"
                      :style="fieldLayoutIndicatorStyle(field)"
                    ></span>
                  </span>
                  {{ fieldLayoutShortLabel(field) }}
                </span>
                <span
                  v-if="fieldErrors[index]"
                  class="inline-flex rounded bg-red-50 px-1.5 py-0.5 text-red-700"
                  >{{ fieldErrors[index] }}</span
                >
              </div>
            </div>
          </button>

          <div
            class="shrink-0 flex items-center gap-1 opacity-0 transition-opacity group-hover/field:opacity-100 focus-within:opacity-100"
          >
            <button
              type="button"
              @click="duplicateField(index)"
              class="text-slate-400 hover:text-theme-600 transition-colors p-1 rounded"
              title="Duplicate field"
              aria-label="Duplicate field"
            >
              <Icon icon="mdi:content-copy" class="w-4 h-4" />
            </button>
            <button
              type="button"
              @click="removeField(index)"
              class="text-slate-400 hover:text-red-500 transition-colors p-1 rounded"
              title="Remove field"
              aria-label="Remove field"
            >
              <Icon icon="mdi:close" class="w-4 h-4" />
            </button>
          </div>
        </div>

        <div v-else class="flex items-center gap-3 p-3">
          <button
            type="button"
            @click.stop="toggleField(field)"
            class="shrink-0 text-slate-400 hover:text-theme-600 transition-colors p-1 rounded"
            title="Collapse field"
            aria-label="Collapse field"
            :aria-expanded="true"
          >
            <Icon icon="mdi:chevron-down" class="w-4 h-4" />
          </button>

          <Icon
            icon="mdi:drag-vertical"
            class="w-4 h-4 text-slate-400 shrink-0"
          />
          <span
            class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-theme-100"
          >
            <Icon
              :icon="fieldTypeIcon(field.type)"
              class="h-4 w-4 text-theme-600"
            />
          </span>

          <div class="flex-1 min-w-0">
            <div class="truncate text-sm font-medium text-slate-800">
              {{ field.label || field.key || "Untitled field" }}
            </div>
            <div
              class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-500"
            >
              <code class="font-mono bg-slate-100 px-1 py-0.5 rounded">{{
                field.key || "field_key"
              }}</code>
              <span
                class="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5"
              >
                <Icon :icon="fieldTypeIcon(field.type)" class="h-3 w-3" />
                {{ field.type }}
              </span>
              <span
                v-if="field.required"
                class="inline-flex rounded bg-amber-50 px-1.5 py-0.5 text-amber-700"
                >Required</span
              >
              <span
                v-if="field.localized === false"
                class="inline-flex items-center gap-1 rounded bg-sky-50 px-1.5 py-0.5 text-sky-700"
              >
                <Icon icon="mdi:web" class="h-3 w-3" />
                Universal
              </span>
              <span
                v-if="fieldLayoutWidth(field) !== 'full'"
                class="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 text-slate-600"
                :title="fieldLayoutLabel(field)"
              >
                <span
                  class="flex h-3 w-6 overflow-hidden rounded-sm bg-slate-300"
                >
                  <span
                    class="h-full rounded-sm"
                    :style="fieldLayoutIndicatorStyle(field)"
                  ></span>
                </span>
                {{ fieldLayoutShortLabel(field) }}
              </span>
              <span
                v-if="fieldErrors[index]"
                class="inline-flex rounded bg-red-50 px-1.5 py-0.5 text-red-700"
                >{{ fieldErrors[index] }}</span
              >
            </div>
          </div>

          <div
            class="shrink-0 flex items-center gap-1 opacity-0 transition-opacity group-hover/field:opacity-100 focus-within:opacity-100"
          >
            <button
              type="button"
              @click="duplicateField(index)"
              class="text-slate-400 hover:text-theme-600 transition-colors p-1 rounded"
              title="Duplicate field"
              aria-label="Duplicate field"
            >
              <Icon icon="mdi:content-copy" class="w-4 h-4" />
            </button>
            <button
              type="button"
              @click="removeField(index)"
              class="text-slate-400 hover:text-red-500 transition-colors p-1 rounded"
              title="Remove field"
              aria-label="Remove field"
            >
              <Icon icon="mdi:close" class="w-4 h-4" />
            </button>
          </div>
        </div>

        <div v-if="!field._collapsed" class="border-t border-slate-200 px-5 pb-5 pt-4">
          <div
            class="grid gap-4 lg:grid-cols-[1fr_1fr_1fr_auto_18rem]"
          >
            <div>
              <label class="text-xs text-slate-500 block mb-1">Label</label>
              <input
                v-model="field.label"
                type="text"
                placeholder="Display name"
                class="form-input w-full rounded-lg border-slate-300 text-sm"
                @input="onLabelInput(field)"
              />
            </div>

            <div>
              <label class="text-xs text-slate-500 block mb-1">
                Field key
                <span class="text-slate-400 font-normal">(API / storage)</span>
              </label>
              <input
                v-model="field.key"
                type="text"
                placeholder="field_name"
                class="form-input w-full rounded-lg border-slate-300 text-sm"
                :class="{
                  'border-red-400':
                    !isValidKey(field.key) || fieldErrors[index],
                  'border-amber-400': isKeyRenamed(field),
                }"
                @blur="normalizeFieldKey(field, index)"
                @input="onKeyInput(field)"
              />
              <p v-if="fieldErrors[index]" class="mt-1 text-xs text-red-600">
                {{ fieldErrors[index] }}
              </p>
              <p
                v-else-if="isKeyRenamed(field)"
                class="mt-1 text-xs text-amber-600"
              >
                <Icon
                  icon="mdi:alert-outline"
                  class="w-3 h-3 inline-block align-middle mr-0.5"
                />
                Was
                <code class="font-mono bg-amber-50 px-0.5 rounded">{{
                  field._originalKey
                }}</code>
                - all existing entries will be migrated on save.
              </p>
            </div>

            <div>
              <label class="text-xs text-slate-500 block mb-1">Type</label>
              <SearchableSelect
                :model-value="field.type"
                :options="allowedTypeOptions(field)"
                :clearable="false"
                :searchable="true"
                @update:model-value="setFieldType(field, $event)"
              />
            </div>

            <div>
              <label class="text-xs text-slate-500 block mb-1">Required</label>
              <button
                type="button"
                class="relative mt-1 h-7 w-12 rounded-full transition-colors"
                :class="field.required ? 'bg-theme-500' : 'bg-slate-300'"
                :aria-pressed="field.required"
                @click="field.required = !field.required"
              >
                <span
                  class="absolute left-1 top-1 h-5 w-5 rounded-full bg-white shadow transition-transform"
                  :class="{ 'translate-x-5': field.required }"
                ></span>
              </button>
            </div>

            <div>
              <label class="text-xs text-slate-500 block mb-1">{{
                t("fieldBuilder.editorWidth")
              }}</label>
              <div
                class="grid grid-cols-4 gap-1 rounded-lg border border-slate-200 bg-slate-50 p-1"
              >
                <button
                  v-for="option in layoutWidthOptions"
                  :key="option.value"
                  type="button"
                  class="group/layout flex min-w-0 flex-col gap-1 rounded-md px-2 py-1.5 text-xs font-medium transition"
                  :class="
                    fieldLayoutWidth(field) === option.value
                      ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200'
                      : 'text-slate-500 hover:bg-white/70 hover:text-slate-800'
                  "
                  :title="fieldLayoutOptionLabel(option)"
                  @click="setFieldLayoutWidth(field, option.value)"
                >
                  <span class="flex h-5 overflow-hidden rounded bg-slate-200">
                    <span
                      class="h-full rounded bg-slate-400 transition-colors group-hover/layout:bg-slate-500"
                      :class="
                        fieldLayoutWidth(field) === option.value
                          ? 'bg-theme-500 group-hover/layout:bg-theme-500'
                          : ''
                      "
                      :style="{ width: option.percent }"
                    ></span>
                  </span>
                  <span>{{ fieldLayoutOptionShortLabel(option) }}</span>
                </button>
              </div>
            </div>
          </div>
          <div class="mt-4 rounded-lg border border-slate-200">
            <div class="grid gap-0 lg:grid-cols-[26rem_1fr]">
              <div class="space-y-5 border-b border-slate-200 p-4 lg:border-b-0 lg:border-r">
                <label class="flex items-start gap-3">
                  <input
                    type="checkbox"
                    :checked="field.localized === false"
                    class="form-checkbox mt-0.5 rounded border-slate-300 text-theme-600"
                    @change="setFieldUniversal(field, $event.target.checked)"
                  />
                  <span>
                    <span class="block text-sm font-medium text-slate-700">
                      Same value for all languages
                    </span>
                    <span class="mt-1 block text-sm text-slate-500">
                      Use a single value for this field in all languages.
                    </span>
                  </span>
                </label>

                <div
                  v-if="supportsFieldDefault(field)"
                  class="border-t border-slate-200 pt-5"
                >
                  <label class="flex items-start gap-3">
                    <input
                      v-if="field.type !== 'range'"
                      type="checkbox"
                      :checked="hasFieldDefault(field)"
                      class="form-checkbox mt-0.5 rounded border-slate-300 text-theme-600"
                      @change="toggleFieldDefault(field, $event.target.checked)"
                    />
                    <span>
                      <span class="block text-sm font-medium text-slate-700">
                        Default value
                      </span>
                      <span class="mt-1 block text-sm text-slate-500">
                        Pre-fill the editor with a default value.
                      </span>
                    </span>
                  </label>

                  <div v-if="hasFieldDefault(field)" class="mt-3">
                    <textarea
                      v-if="['textarea', 'markdown', 'html'].includes(field.type)"
                      :value="field.default ?? ''"
                      rows="3"
                      class="form-textarea w-full rounded-lg border-slate-300 text-sm"
                      @input="setFieldDefault(field, $event.target.value)"
                    />

                    <input
                      v-else-if="['text', 'date', 'datetime'].includes(field.type)"
                      :type="
                        field.type === 'date'
                          ? 'date'
                          : field.type === 'datetime'
                            ? 'datetime-local'
                            : 'text'
                      "
                      :value="field.default ?? ''"
                      class="form-input w-full rounded-lg border-slate-300 text-sm"
                      @input="setFieldDefault(field, $event.target.value)"
                    />

                    <input
                      v-else-if="field.type === 'number' || field.type === 'range'"
                      v-model.number="field.default"
                      type="number"
                      step="any"
                      class="form-input w-full rounded-lg border-slate-300 text-sm"
                      @input="normalizeFieldDefault(field)"
                    />

                    <label
                      v-else-if="field.type === 'boolean'"
                      class="inline-flex items-center gap-2 h-[38px]"
                    >
                      <input
                        type="checkbox"
                        :checked="field.default === true"
                        class="form-checkbox rounded border-slate-300 text-theme-600"
                        @change="setFieldDefault(field, $event.target.checked)"
                      />
                      <span class="text-sm text-slate-600">Enabled</span>
                    </label>

                    <SearchableSelect
                      v-else-if="field.type === 'select' && field.multiple"
                      :model-value="selectDefaultValues(field)"
                      :options="selectDefaultOptions(field)"
                      :multiple="true"
                      placeholder="Select defaults..."
                      @update:model-value="setFieldDefault(field, $event)"
                    />

                    <select
                      v-else-if="field.type === 'select'"
                      :value="selectDefaultSingleValue(field)"
                      class="form-select w-full rounded-lg border-slate-300 text-sm"
                      @change="setFieldDefault(field, $event.target.value)"
                    >
                      <option value="">- select -</option>
                      <option
                        v-for="opt in parseSelectOptions(field._optionsText)"
                        :key="opt.key"
                        :value="opt.key"
                      >
                        {{ opt.label }}
                      </option>
                    </select>

                    <textarea
                      v-else-if="field.type === 'json'"
                      :value="field._defaultJsonText"
                      rows="4"
                      class="form-textarea w-full rounded-lg border-slate-300 text-sm font-mono"
                      @input="setJsonFieldDefault(field, $event.target.value)"
                    />

                    <div
                      v-else-if="field.type === 'color'"
                      class="flex items-center gap-2"
                    >
                      <input
                        type="color"
                        :value="validColorDefault(field.default)"
                        class="h-9 w-12 rounded-lg border border-slate-300 bg-white p-1"
                        @input="setFieldDefault(field, $event.target.value)"
                      />
                      <input
                        :value="field.default ?? ''"
                        type="text"
                        maxlength="7"
                        class="form-input w-32 rounded-lg border-slate-300 text-sm font-mono"
                        @input="setFieldDefault(field, $event.target.value)"
                      />
                    </div>
                  </div>
                </div>
              </div>

              <div class="space-y-4 p-4">
                <div>
                  <label class="text-sm font-medium text-slate-700 block mb-2"
                    >Description
                    <span class="font-normal text-slate-400"
                      >(optional helper text shown in the editor)</span
                    ></label
                  >
                  <textarea
                    v-model="field.description"
                    rows="2"
                    placeholder="Explain what this field is for..."
                    class="form-textarea w-full rounded-lg border-slate-300 text-sm"
                  />
                </div>

                <FieldConfigSelect
                  v-if="field.type === 'select'"
                  :field="field"
                />
                <FieldConfigRelation
                  v-if="field.type === 'relation'"
                  :field="field"
                  :content-types="contentTypes"
                />
                <FieldConfigRange
                  v-if="field.type === 'range'"
                  :field="field"
                />
                <FieldConfigMedia
                  v-if="field.type === 'media'"
                  :field="field"
                />
                <FieldConfigRepeater
                  v-if="field.type === 'repeater'"
                  :field="field"
                  :content-types="contentTypes"
                />
                <FieldConfigColor
                  v-if="field.type === 'color'"
                  :field="field"
                />
              </div>
            </div>
          </div>

        </div>
      </div>
    </TransitionGroup>
    <button
      type="button"
      @click="addField"
      class="w-full py-2.5 rounded-xl border-2 border-dashed border-slate-300 text-sm text-slate-500 hover:border-theme-400 hover:text-theme-600 transition-colors"
    >
      + Add field
    </button>
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { Icon } from "@iconify/vue";
import SearchableSelect from "./SearchableSelect.vue";
import FieldConfigSelect from "./FieldConfigSelect.vue";
import FieldConfigRelation from "./FieldConfigRelation.vue";
import FieldConfigRange from "./FieldConfigRange.vue";
import FieldConfigMedia from "./FieldConfigMedia.vue";
import FieldConfigRepeater from "./FieldConfigRepeater.vue";
import FieldConfigColor from "./FieldConfigColor.vue";
import { useFieldTypeMeta } from "../composables/useFieldTypeMeta.js";
import {
  normalizeKey,
  optionsToText,
  parseSelectOptions,
  serializeSelectOptions,
  uniqueKey,
  rangeDefaults,
} from "../composables/fieldBuilderUtils.js";
import {
  emptyConfiguredDefault,
  hasConfiguredDefault,
  supportsConfiguredDefault,
} from "../composables/fieldDefaults.js";
import {
  formatJsonDefaultText,
  layoutRowColors,
  layoutWidthOptions,
  normalizeExternalDefault,
  normalizeExternalLayout,
  normalizeFieldDefault,
  normalizeLayoutWidth,
  selectDefaultOptions,
  selectDefaultSingleValue,
  selectDefaultValues,
  setFieldLayoutWidth,
  setJsonFieldDefault,
  validColorDefault,
} from "../composables/fieldSchemaUtils.js";
import { useI18n } from "../i18n/index.js";

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  fieldTypes: { type: Array, default: () => [] },
  contentTypes: { type: Array, default: () => [] },
  fieldErrors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue"]);
const { fieldTypeIcon } = useFieldTypeMeta();
const { t } = useI18n();

const fieldTypeOptions = computed(() => props.fieldTypes.map(fieldTypeOption));
let uid = 0;

// Work on an internal list that includes a stable _id for list rendering.
const fields = ref(toInternal(props.modelValue));
let lastExternalJson = JSON.stringify(toExternal(fields.value));

const layoutRowByFieldId = computed(() => {
  const rows = new Map();
  let row = 0;
  let used = 0;

  for (const field of fields.value) {
    const units = fieldLayoutUnits(field);

    if (units === 12) {
      rows.set(field._id, row);
      row += 1;
      used = 0;
      continue;
    }

    if (used > 0 && used + units > 12) {
      row += 1;
      used = 0;
    }

    rows.set(field._id, row);
    used += units;

    if (used >= 12) {
      row += 1;
      used = 0;
    }
  }

  return rows;
});

watch(
  () => props.modelValue,
  (val) => {
    if (JSON.stringify(toExternal(fields.value)) !== JSON.stringify(val)) {
      fields.value = toInternal(val);
    }
    lastExternalJson = JSON.stringify(toExternal(fields.value));
  },
);

watch(
  () => props.fieldErrors,
  (errors) => {
    for (const index of Object.keys(errors ?? {})) {
      if (fields.value[index]) fields.value[index]._collapsed = false;
    }
  },
  { deep: true },
);

watch(
  fields,
  () => {
    const external = toExternal(fields.value);
    const externalJson = JSON.stringify(external);

    if (externalJson === lastExternalJson) return;

    lastExternalJson = externalJson;
    emit("update:modelValue", external);
  },
  { deep: true },
);

function toInternal(arr) {
  return (arr ?? []).map((f) => {
    const originalKey = f._originalKey ?? f.key ?? "";

    return {
      ...f,
      label: f.label ?? "",
      target: f.target ?? f.collection ?? "",
      _optionsText: optionsToText(f.options),
      _defaultJsonText: formatJsonDefaultText(f),
      ...(f.type === "range" ? rangeDefaults(f) : {}),
      subfields: Array.isArray(f.subfields)
        ? f.subfields.map(toInternalNestedField)
        : [],
      _originalKey: originalKey,
      _originalType: f._originalType ?? f.type ?? "text",
      _collapsed: f._collapsed ?? originalKey !== "",
      _id: uid++,
    };
  });
}

function toExternal(arr) {
  return arr.map(
    ({
      _id,
      _collapsed,
      collection,
      multiple,
      _optionsText,
      _defaultJsonText,
      subfields,
      ...rest
    }) => {
      const field = { ...rest };
      if (!field.label?.trim()) delete field.label;
      if (!field.description?.trim()) delete field.description;
      if (field.localized !== false) delete field.localized;
      normalizeExternalLayout(field);

      if (field.type !== "relation") {
        delete field.target;
      }

      if (!["media", "relation", "select"].includes(field.type)) {
        delete field.multiple;
      }

      if (multiple && ["media", "relation", "select"].includes(field.type)) {
        field.multiple = true;
      }

      if (field.type === "select") {
        field.options = serializeSelectOptions(
          parseSelectOptions(_optionsText),
        );
      } else {
        delete field.options;
      }

      if (field.type === "range") {
        Object.assign(field, rangeDefaults(field));
      } else {
        delete field.min;
        delete field.step;
        delete field.max;
        delete field.display_decimals;
      }

      normalizeExternalDefault(field, _defaultJsonText);

      if (field.type === "repeater") {
        field.subfields = (subfields ?? []).map(toExternalNestedField);
      } else {
        delete field.subfields;
      }

      return field;
    },
  );
}

function fieldTypeOption(type) {
  return {
    value: type,
    label: type,
    icon: fieldTypeIcon(type),
  };
}

function toInternalNestedField(field) {
  return {
    ...field,
    target: field.target ?? field.collection ?? "",
    _optionsText: optionsToText(field.options),
    _defaultJsonText: formatJsonDefaultText(field),
    ...(field.type === "range" ? rangeDefaults(field) : {}),
    _id: uid++,
  };
}

function toExternalNestedField({
  _id: _sid,
  collection,
  multiple,
  _optionsText,
  _defaultJsonText,
  subfields,
  ...rest
}) {
  const field = { ...rest };
  if (!field.label?.trim()) delete field.label;
  if (!field.description?.trim()) delete field.description;
  if (field.localized !== false) delete field.localized;
  normalizeExternalLayout(field);

  if (field.type !== "relation") {
    delete field.target;
  }

  if (!["media", "relation", "select"].includes(field.type)) {
    delete field.multiple;
  }

  if (multiple && ["media", "relation", "select"].includes(field.type)) {
    field.multiple = true;
  }

  if (field.type === "select") {
    field.options = serializeSelectOptions(parseSelectOptions(_optionsText));
  } else {
    delete field.options;
  }

  if (field.type === "range") {
    Object.assign(field, rangeDefaults(field));
  } else {
    delete field.min;
    delete field.step;
    delete field.max;
    delete field.display_decimals;
  }

  normalizeExternalDefault(field, _defaultJsonText);
  delete field.subfields;

  return field;
}

function supportsFieldDefault(field) {
  return supportsConfiguredDefault(field);
}

function hasFieldDefault(field) {
  return hasConfiguredDefault(field);
}

function toggleFieldDefault(field, enabled) {
  if (!enabled) {
    delete field.default;
    delete field._defaultJsonText;
    return;
  }

  field.default = emptyConfiguredDefault(field.type, field);
  field._defaultJsonText = formatJsonDefaultText(field);
}

function setFieldDefault(field, value) {
  field.default = value;
  normalizeFieldDefault(field);
}

function setFieldUniversal(field, enabled) {
  if (enabled) {
    field.localized = false;
  } else {
    delete field.localized;
  }
}

function fieldLayoutWidth(field) {
  return normalizeLayoutWidth(field?.layout?.width);
}

function fieldLayoutOption(field) {
  const width = fieldLayoutWidth(field);
  return (
    layoutWidthOptions.find((option) => option.value === width) ??
    layoutWidthOptions[layoutWidthOptions.length - 1]
  );
}

function fieldLayoutPercent(field) {
  return fieldLayoutOption(field).percent;
}

function fieldLayoutUnits(field) {
  return fieldLayoutOption(field).units;
}

function fieldLayoutIndicatorStyle(field) {
  const row = layoutRowByFieldId.value.get(field?._id) ?? 0;

  return {
    width: fieldLayoutPercent(field),
    backgroundColor: layoutRowColors[row % layoutRowColors.length],
  };
}

function fieldLayoutLabel(field) {
  return t("fieldBuilder.editorWidthTooltip", {
    width: fieldLayoutOptionLabel(fieldLayoutOption(field)),
  });
}

function fieldLayoutShortLabel(field) {
  return fieldLayoutOptionShortLabel(fieldLayoutOption(field));
}

function fieldLayoutOptionLabel(option) {
  return t(option.labelKey);
}

function fieldLayoutOptionShortLabel(option) {
  return option.shortLabelKey ? t(option.shortLabelKey) : option.shortLabel;
}

function addField() {
  fields.value.push({
    _id: uid++,
    key: "",
    label: "",
    type: "text",
    required: false,
    subfields: [],
    _originalKey: "",
    _originalType: "text",
    _collapsed: false,
    _keyTouched: false,
  });
}

function toggleField(field) {
  field._collapsed = !field._collapsed;
}

function expandCollapsedField(field) {
  if (suppressNextClick.value || !field._collapsed) return;
  field._collapsed = false;
}

function expandAllFields() {
  for (const field of fields.value) {
    field._collapsed = false;
  }
}

function collapseAllFields() {
  for (const field of fields.value) {
    field._collapsed = true;
  }
}

defineExpose({
  expandAllFields,
  collapseAllFields,
});

function onLabelInput(field) {
  // For brand-new fields (never saved), auto-derive the key from the label,
  // but only while the user hasn't manually edited the key field yet.
  if (field._originalKey === "" && !field._keyTouched) {
    field.key = normalizeKey(field.label || "");
  }
}

function onKeyInput(field) {
  field._keyTouched = true;
}

function isKeyRenamed(field) {
  return field._originalKey !== "" && field.key !== field._originalKey;
}

// Type groups whose members are interchangeable without data loss.
const losslessTypeGroups = [
  new Set(["text", "textarea", "markdown", "html"]),
  new Set(["number", "range"]),
];

function allowedTypeOptions(field) {
  if (field._originalKey === "") return fieldTypeOptions.value; // new field — show all
  const group = losslessTypeGroups.find((g) => g.has(field._originalType));
  // Return all options, disabling those outside the compatible group (or all others if locked)
  return fieldTypeOptions.value.map((opt) => ({
    ...opt,
    disabled: group ? !group.has(opt.value) : opt.value !== field._originalType,
  }));
}

function onFieldTypeChange(field) {
  if (field.type === "range") {
    normalizeRange(field);
  }

  if (!supportsFieldDefault(field)) {
    delete field.default;
    delete field._defaultJsonText;
  } else if (hasFieldDefault(field)) {
    normalizeFieldDefault(field);
    field._defaultJsonText = formatJsonDefaultText(field);
  }
}

function setFieldType(field, type) {
  field.type = type;
  onFieldTypeChange(field);
}

function removeField(index) {
  fields.value.splice(index, 1);
}

function duplicateField(index) {
  const source = fields.value[index];
  if (!source) return;

  const clone = JSON.parse(JSON.stringify(source));
  clone._id = uid++;
  clone._originalKey = ""; // duplicate is a new field; no rename concern
  clone._originalType = clone.type; // type starts fresh from the cloned type
  clone._keyTouched = false; // key can still be auto-derived after duplication
  clone._collapsed = false;

  if (Array.isArray(clone.subfields)) {
    clone.subfields = clone.subfields.map((sub) => ({ ...sub, _id: uid++ }));
  }

  const baseKey = normalizeKey(clone.key || "field") || "field";
  const reserved = new Set(["title", "slug", "body"]);
  const used = new Set(
    fields.value.map((item) => String(item.key ?? "")).filter(Boolean),
  );
  clone.key = uniqueKey(baseKey, used, reserved);

  fields.value.splice(index + 1, 0, clone);
}

function isValidKey(key) {
  return key === "" || /^[a-z0-9_]+$/.test(key);
}

function normalizeFieldKey(field, index) {
  const base = normalizeKey(field.key);

  if (base === "") {
    field.key = "";
    return;
  }

  const reserved = new Set(["title", "slug", "body"]);
  const used = new Set(
    fields.value
      .filter((_, i) => i !== index)
      .map((item) => String(item.key ?? ""))
      .filter(Boolean),
  );

  field.key = uniqueKey(base, used, reserved);
}

function normalizeRange(field) {
  Object.assign(field, rangeDefaults(field));
}

// ── Drag-and-drop ─────────────────────────────────────────────────────────────
const dragIndex = ref(null);
const suppressNextClick = ref(false);

function onDragStart(index, e) {
  dragIndex.value = index;
  suppressNextClick.value = true;
  e.dataTransfer.effectAllowed = "move";
}

function onDragOver(index, event) {
  if (dragIndex.value === null || dragIndex.value === index) return;

  // Only reorder once the cursor crosses the midpoint of the target card.
  // This prevents the oscillation caused by rapid back-and-forth swaps.
  const rect = event.currentTarget.getBoundingClientRect();
  const midY = rect.top + rect.height / 2;
  if (dragIndex.value < index && event.clientY < midY) return;
  if (dragIndex.value > index && event.clientY > midY) return;

  const moved = fields.value.splice(dragIndex.value, 1)[0];
  fields.value.splice(index, 0, moved);
  dragIndex.value = index;
}

function onDragEnd() {
  dragIndex.value = null;
  setTimeout(() => {
    suppressNextClick.value = false;
  }, 0);
}
</script>

<style scoped>
.field-sort-move {
  transition: transform 0.2s ease;
}
</style>
