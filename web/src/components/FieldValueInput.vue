<template>
  <select
    v-if="field.kind === 'status'"
    :value="modelValue ?? ''"
    :aria-label="ariaLabel || undefined"
    class="form-select rounded-lg border-slate-300 text-sm"
    :class="inputClass"
    @change="emitValue($event.target.value)"
  >
    <option value="">-- select status --</option>
    <option value="draft">Draft</option>
    <option value="published">Published</option>
    <option value="protected">Protected</option>
    <option value="archived">Archived</option>
  </select>

  <select
    v-else-if="field.kind === 'author'"
    :value="modelValue ?? ''"
    :aria-label="ariaLabel || undefined"
    class="form-select rounded-lg border-slate-300 text-sm"
    :class="inputClass"
    @change="emitValue($event.target.value)"
  >
    <option value="">-- select author --</option>
    <option v-for="user in users" :key="user.id" :value="user.id">
      {{ user.username }}
    </option>
  </select>

  <input
    v-else-if="field.kind === 'date'"
    :value="modelValue ?? ''"
    :aria-label="ariaLabel || undefined"
    type="date"
    class="form-input rounded-lg border-slate-300 text-sm"
    :class="inputClass"
    @input="emitValue($event.target.value)"
  />

  <input
    v-else-if="field.kind === 'datetime'"
    :value="datetimeValue"
    :aria-label="ariaLabel || undefined"
    type="datetime-local"
    class="form-input rounded-lg border-slate-300 text-sm"
    :class="inputClass"
    @input="emitDateTime($event.target.value)"
  />

  <select
    v-else-if="field.kind === 'boolean'"
    :value="booleanValue"
    :aria-label="ariaLabel || undefined"
    class="form-select rounded-lg border-slate-300 text-sm"
    :class="inputClass"
    @change="emitBoolean($event.target.value)"
  >
    <option value="">-- choose --</option>
    <option value="true">True</option>
    <option value="false">False</option>
  </select>

  <input
    v-else-if="field.kind === 'number' || field.kind === 'range'"
    :value="modelValue ?? ''"
    :aria-label="ariaLabel || undefined"
    type="number"
    :min="field.config?.min"
    :max="field.config?.max"
    :step="field.kind === 'range' ? rangeStep(field.config) : 'any'"
    class="form-input rounded-lg border-slate-300 text-sm"
    :class="inputClass"
    @input="emitNumber($event.target.value)"
  />

  <SearchableSelect
    v-else-if="field.kind === 'select' && usesMultiple"
    :options="selectOptions"
    :model-value="
      Array.isArray(modelValue) ? modelValue : modelValue ? [modelValue] : []
    "
    :multiple="true"
    :aria-label="ariaLabel"
    placeholder="Select options..."
    :class="inputClass"
    @update:model-value="emitValue($event)"
  />

  <select
    v-else-if="field.kind === 'select'"
    :value="modelValue ?? ''"
    :aria-label="ariaLabel || undefined"
    class="form-select rounded-lg border-slate-300 text-sm"
    :class="inputClass"
    @change="emitValue($event.target.value)"
  >
    <option value="">-- select --</option>
    <option
      v-for="opt in normalizedSelectOptions"
      :key="opt.value"
      :value="opt.value"
    >
      {{ opt.label }}
    </option>
  </select>

  <input
    v-else
    :value="modelValue ?? ''"
    :aria-label="ariaLabel || undefined"
    type="text"
    class="form-input rounded-lg border-slate-300 text-sm"
    :class="inputClass"
    :placeholder="placeholder"
    @input="emitValue($event.target.value)"
  />
</template>

<script setup>
import { computed } from "vue";
import SearchableSelect from "./SearchableSelect.vue";

const props = defineProps({
  field: { type: Object, required: true },
  modelValue: { type: [String, Number, Boolean, Array], default: null },
  users: { type: Array, default: () => [] },
  inputClass: { type: String, default: "" },
  multiple: { type: Boolean, default: false },
  placeholder: { type: String, default: "" },
  ariaLabel: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue"]);

const usesMultiple = computed(
  () => props.multiple || !!props.field.config?.multiple,
);
const normalizedSelectOptions = computed(() => {
  const opts = props.field.config?.options;
  if (opts && typeof opts === "object" && !Array.isArray(opts)) {
    return Object.entries(opts).map(([key, label]) => ({ value: key, label }));
  }
  return (opts ?? []).map((option) => ({ value: option, label: option }));
});
const selectOptions = computed(() => normalizedSelectOptions.value);
const booleanValue = computed(() => {
  if (
    props.modelValue === null ||
    props.modelValue === undefined ||
    props.modelValue === ""
  )
    return "";
  return props.modelValue === true || props.modelValue === "true"
    ? "true"
    : "false";
});
const datetimeValue = computed(() => toDatetimeLocal(props.modelValue));

function emitValue(value) {
  emit("update:modelValue", value);
}

function emitBoolean(value) {
  if (value === "") {
    emitValue(null);
    return;
  }

  emitValue(value === "true");
}

function emitNumber(value) {
  if (value === "") {
    emitValue(null);
    return;
  }

  const number = Number(value);
  emitValue(Number.isFinite(number) ? number : value);
}

function emitDateTime(value) {
  if (value === "") {
    emitValue(null);
    return;
  }

  emitValue(localDatetimeToUtcIso(value));
}

function rangeStep(config = {}) {
  const step = Number(config?.step ?? 1);
  return Number.isFinite(step) && step > 0 ? step : 1;
}

function toDatetimeLocal(value) {
  if (!value) return "";
  const date = new Date(String(value));
  if (Number.isNaN(date.getTime())) return String(value);
  const pad = (number) => String(number).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function localDatetimeToUtcIso(value) {
  const date = new Date(String(value));
  if (Number.isNaN(date.getTime())) return value;
  return date.toISOString().replace(/\.\d{3}Z$/, "Z");
}
</script>
