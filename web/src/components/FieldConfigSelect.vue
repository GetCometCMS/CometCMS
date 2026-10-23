<template>
  <div class="space-y-3">
    <div class="space-y-2">
      <p class="text-xs text-slate-500">{{ t("fieldConfig.options") }}</p>
      <div v-for="option in options" :key="option.id" class="flex items-start gap-2">
        <div class="min-w-0 flex-1 space-y-1">
          <LocalizedInput
            :id="`${optionsId}-${option.id}-label`"
            :label="t('fieldBuilder.label')"
            :model-value="option.label"
            @update:model-value="updateLabel(option, $event)"
          />
          <details class="text-xs text-slate-500">
            <summary class="cursor-pointer break-all">{{ t("fieldConfig.optionValue") }}<span v-if="option.key" class="font-mono">: {{ option.key }}</span></summary>
            <label :for="`${optionsId}-${option.id}-key`" class="sr-only">{{ t("fieldConfig.optionValue") }}</label>
            <input
              :id="`${optionsId}-${option.id}-key`"
              :value="option.key"
              type="text"
              :placeholder="t('fieldConfig.optionValuePlaceholder')"
              class="form-input mt-1 w-full rounded-lg border-slate-300 text-sm font-mono"
              @change="updateKey(option, $event.target)"
            />
          </details>
        </div>
        <div class="flex shrink-0 gap-1 pt-6">
          <button type="button" class="rounded-lg border border-slate-300 p-2 text-slate-500 transition-colors hover:border-theme-400 hover:text-theme-600" :title="t('fieldConfig.addOption')" :aria-label="t('fieldConfig.addOption')" @click="addOption(option)">
            <Icon icon="mdi:plus" class="h-4 w-4" />
          </button>
          <button type="button" class="rounded-lg border border-slate-300 p-2 text-slate-400 transition-colors hover:border-red-400 hover:text-red-500" :title="t('fieldConfig.removeOption')" :aria-label="t('fieldConfig.removeOption')" @click="removeOption(option)">
            <Icon icon="mdi:minus" class="h-4 w-4" />
          </button>
        </div>
      </div>
      <button type="button" class="w-full rounded-lg border border-dashed border-slate-300 py-1.5 text-xs text-slate-500 transition-colors hover:border-theme-400 hover:text-theme-600" @click="addOption()">
        + {{ t("fieldConfig.addOption") }}
      </button>
    </div>
    <label class="inline-flex items-center gap-2">
      <input type="checkbox" v-model="field.multiple" class="form-checkbox rounded border-slate-300 text-theme-600" />
      <span class="text-sm text-slate-600">{{ t("fieldConfig.allowMultiple") }}</span>
    </label>
  </div>
</template>

<script setup>
import { nextTick, ref, useId, watch } from "vue";
import { Icon } from "@iconify/vue";
import { optionsToText, uniqueKey } from "../composables/fieldBuilderUtils.js";
import { resolveLocalized } from "../composables/localizedValues.js";
import LocalizedInput from "./LocalizedInput.vue";
import { useLanguages } from "../composables/useLanguages.js";
import { useI18n } from "../i18n/index.js";

const { t } = useI18n();
const optionsId = useId();
const languages = useLanguages();
const props = defineProps({ field: { type: Object, required: true } });
let uid = 0;
let syncedOptions;
const options = ref([]);

watch(() => props.field.options, (value) => {
  if (value === syncedOptions) return;
  const entries = Array.isArray(value) ? value.map((key) => [key, key]) : Object.entries(value ?? {});
  options.value = entries.map(([key, label]) => ({ id: uid++, key: String(key), label, autoKey: false }));
  if (!options.value.length) options.value.push(newOption());
}, { immediate: true, deep: true });

function newOption() {
  return { id: uid++, key: "", label: "", autoKey: true };
}

function syncOptions() {
  const entries = options.value.filter((option) => option.key).map(({ key, label }) => [key, label]);
  props.field.options = entries.every(([key, label]) => key === label)
    ? entries.map(([key]) => key)
    : Object.fromEntries(entries);
  syncedOptions = props.field.options;
  // Keep the builder's default picker and schema serialization in sync.
  props.field._optionsText = optionsToText(props.field.options, languages.defaultLocale.value);
}

function availableKey(value, option) {
  const base = String(value ?? "").trim();
  if (!base) return "";
  return uniqueKey(base, new Set(options.value.filter((item) => item !== option).map((item) => item.key)));
}

function updateKey(option, input) {
  option.key = availableKey(input.value, option);
  option.autoKey = !option.key;
  if (option.autoKey) option.key = availableKey(resolveLocalized(option.label, languages.defaultLocale.value, languages.defaultLocale.value), option);
  if (!option.label) option.label = option.key;
  input.value = option.key;
  syncOptions();
}

function updateLabel(option, value) {
  option.label = value;
  if (option.autoKey) option.key = availableKey(resolveLocalized(value, languages.defaultLocale.value, languages.defaultLocale.value), option);
  syncOptions();
}

async function addOption(after) {
  const option = newOption();
  const index = after ? options.value.indexOf(after) + 1 : options.value.length;
  options.value.splice(index, 0, option);
  await nextTick();
  document.getElementById(`${optionsId}-${option.id}-label`)?.focus();
}

function removeOption(option) {
  options.value.splice(options.value.indexOf(option), 1);
  syncOptions();
}
</script>
