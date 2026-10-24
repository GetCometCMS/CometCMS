<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center bg-ink-900/50 p-4" @mousedown.self="close">
      <form
        ref="dialogRef"
        class="w-full max-w-md space-y-4 rounded-xl bg-white p-6 shadow-xl"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        tabindex="-1"
        @submit.prevent="submit"
      >
        <div class="flex items-start gap-3">
          <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-theme-50">
            <Icon :icon="icon" class="h-5 w-5 text-theme-600" />
          </div>
          <div class="min-w-0 flex-1">
            <h3 :id="titleId" class="text-base font-semibold text-slate-900">{{ title }}</h3>
            <p v-if="message" class="mt-1 text-sm text-slate-600">{{ message }}</p>
          </div>
        </div>

        <div>
          <label :for="inputId" class="mb-1 block text-xs font-medium text-slate-700">{{ label || t("site.component.name") }}</label>
          <input
            :id="inputId"
            v-model="name"
            data-dialog-initial-focus
            type="text"
            :maxlength="maxlength"
            required
            class="form-input w-full rounded-lg border-slate-300 text-sm"
            :placeholder="placeholder || t('site.component.namePlaceholder')"
          />
        </div>

        <div class="flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="close">{{ t("common.cancel") }}</button>
          <button type="submit" class="btn-primary" :disabled="loading || !name.trim()">
            {{ loading ? t("common.saving") : confirmLabel }}
          </button>
        </div>
      </form>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, toRef, useId, watch } from "vue";
import { Icon } from "@iconify/vue";
import { useDialogFocus } from "../../composables/useDialogFocus.js";
import { useI18n } from "../../i18n/index.js";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  title: { type: String, required: true },
  message: { type: String, default: "" },
  confirmLabel: { type: String, required: true },
  /** Name to start with, e.g. the current one when renaming. */
  initial: { type: String, default: "" },
  loading: { type: Boolean, default: false },
  maxlength: { type: Number, default: 120 },
  icon: { type: String, default: "mdi:puzzle-outline" },
  label: { type: String, default: "" },
  placeholder: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue", "confirm"]);

const { t } = useI18n();
const dialogRef = ref(null);
const id = useId();
const titleId = `${id}-title`;
const inputId = `${id}-name`;
const name = ref("");

watch(() => props.modelValue, (open) => {
  if (open) name.value = props.initial;
}, { immediate: true });

function close() {
  if (!props.loading) emit("update:modelValue", false);
}

function submit() {
  const value = name.value.trim();
  if (value && !props.loading) emit("confirm", value);
}

useDialogFocus({ open: toRef(props, "modelValue"), container: dialogRef, close, closeBlocked: () => props.loading });
</script>
