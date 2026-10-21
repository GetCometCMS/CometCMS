<template>
  <div class="group/field">
    <div :id="labelId" class="form-label flex items-center" :class="compact ? 'mb-1 gap-1.5 text-xs' : 'gap-2'" :title="name">
      <span
        class="inline-flex items-center justify-center rounded-full bg-theme-100 shrink-0"
        :class="compact ? 'h-5 w-5' : 'h-6 w-6'"
      >
        <Icon
          :icon="fieldTypeIcon(config.type)"
          class="text-theme-600"
          :class="compact ? 'h-3 w-3' : 'h-3.5 w-3.5'"
        />
      </span>
      <span>{{ config.label || name }}</span>
      <span v-if="config.required" class="text-red-500 ml-0.5">*</span>
      <span
        v-if="config.localized === false"
        class="inline-flex items-center justify-center rounded-full bg-sky-50 text-sky-700 ring-1 ring-sky-100"
        :class="compact ? 'h-4 w-4' : 'h-5 w-5'"
        :title="t('field.universalHint')"
        :aria-label="t('field.universalHint')"
      >
        <Icon icon="mdi:web" :class="compact ? 'h-3 w-3' : 'h-3.5 w-3.5'" />
      </span>
    </div>
    <p v-if="config.description" class="mb-1.5 text-slate-500" :class="compact ? '-mt-0.5 text-[11px] leading-snug' : 'text-xs'">
      {{ config.description }}
    </p>
    <fieldset
      :disabled="readonly"
      :aria-labelledby="labelId"
      :class="readonly ? 'pointer-events-none opacity-60' : ''"
    >
      <slot />
    </fieldset>
    <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed, useId } from "vue";
import { Icon } from "@iconify/vue";
import { useFieldTypeMeta } from "../composables/useFieldTypeMeta.js";
import { resolveLocalizedTree } from "../composables/localizedValues.js";
import { useLanguages } from "../composables/useLanguages.js";
import { useI18n } from "../i18n/index.js";

const { t } = useI18n();

const props = defineProps({
  name: { type: String, required: true },
  config: { type: Object, required: true },
  locale: { type: String, default: "" },
  error: { type: String, default: "" },
  readonly: { type: Boolean, default: false },
  /** Smaller label for narrow side panels. */
  compact: { type: Boolean, default: false },
});

const languages = useLanguages();
const config = computed(() => resolveLocalizedTree(props.config, props.locale || languages.defaultLocale.value, languages.defaultLocale.value));

const { fieldTypeIcon } = useFieldTypeMeta();
const labelId = `${useId()}-label`;
</script>
