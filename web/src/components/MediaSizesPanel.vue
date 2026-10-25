<template>
  <SlidePanel :model-value="modelValue" :title="t('media.sizes.title')" :subtitle="t('media.sizes.subtitle')" width="28rem" @update:model-value="emit('update:modelValue', $event)">
    <form class="space-y-5 p-6" novalidate @submit.prevent="save">
      <div v-for="name in IMAGE_SIZE_NAMES" :key="name">
        <label :for="`media-size-${name}`" class="block text-sm font-medium text-slate-700">{{ t(`media.sizes.${name}`) }}</label>
        <p class="mb-1.5 text-xs text-slate-500">{{ t(`media.sizes.${name}Hint`) }}</p>
        <div class="flex items-center gap-2">
          <input
            :id="`media-size-${name}`"
            v-model.number="draft[name]"
            type="number"
            :min="MIN"
            :max="MAX"
            step="1"
            required
            class="form-input w-32 rounded-lg border-slate-300 text-sm"
            :class="{ 'border-red-400': errors[name] }"
            :aria-invalid="!!errors[name]"
            :aria-describedby="errors[name] ? `media-size-${name}-error` : undefined"
          />
          <span class="text-sm text-slate-500">px</span>
        </div>
        <p v-if="errors[name]" :id="`media-size-${name}-error`" class="mt-1 text-xs text-red-600">{{ errors[name] }}</p>
      </div>

      <p class="text-xs text-slate-500">{{ t("media.sizes.originalHint") }}</p>

      <div class="flex justify-end">
        <button type="submit" class="btn-primary" :disabled="saving || !loaded">{{ saving ? t("common.saving") : t("media.save") }}</button>
      </div>
    </form>

    <div class="space-y-2 border-t border-slate-200 p-6">
      <h3 class="text-sm font-medium text-slate-700">{{ t("media.sizes.regenerateTitle") }}</h3>
      <p class="text-xs text-slate-500">{{ t("media.sizes.regenerateHint") }}</p>
      <div v-if="regenerating" class="space-y-2 pt-1">
        <div class="flex items-center justify-between gap-3 text-xs font-medium text-theme-700">
          <span>{{ progress.total ? t("media.sizes.progress", { done: progress.done, total: progress.total }) : t("media.sizes.collecting") }}</span>
          <span>{{ percent }}%</span>
        </div>
        <div
          class="h-2 overflow-hidden rounded-full bg-theme-100"
          role="progressbar"
          :aria-label="t('media.sizes.regenerateTitle')"
          :aria-valuenow="percent"
          aria-valuemin="0"
          aria-valuemax="100"
        >
          <div class="h-full rounded-full bg-theme-600 transition-[width] duration-150" :style="{ width: `${percent}%` }" />
        </div>
        <button type="button" class="btn-secondary btn-sm" :disabled="stopping" @click="stopping = true">
          {{ stopping ? t("media.sizes.stopping") : t("media.sizes.stop") }}
        </button>
      </div>
      <button v-else type="button" class="btn-secondary" @click="regenerate">
        <Icon icon="mdi:image-refresh-outline" class="h-4 w-4" />
        {{ t("media.sizes.regenerateAll") }}
      </button>
    </div>
  </SlidePanel>
</template>

<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import { Icon } from "@iconify/vue";
import SlidePanel from "./SlidePanel.vue";
import { api } from "../api/index.js";
import { IMAGE_SIZE_NAMES, regenerateInBatches } from "../composables/useMediaSizes.js";
import { useToastStore } from "../stores/toast.js";
import { useI18n } from "../i18n/index.js";

/** The workspace's image sizes, and rebuilding every image's files at once. */
const props = defineProps({
  modelValue: { type: Boolean, required: true },
});

const emit = defineEmits(["update:modelValue", "regenerated"]);
const MIN = 64;
const MAX = 4096;
const { t } = useI18n();
const toast = useToastStore();
const draft = reactive({ thumbnail: null, medium: null, large: null });
const errors = reactive({});
const loaded = ref(false);
const saving = ref(false);
const regenerating = ref(false);
const stopping = ref(false);
const progress = reactive({ done: 0, total: 0 });
const percent = computed(() => (progress.total ? Math.round((progress.done / progress.total) * 100) : 0));

// A run goes on while the panel is closed, but not once the page is left.
onBeforeUnmount(() => {
  stopping.value = true;
});

watch(
  () => props.modelValue,
  async (open) => {
    if (!open) return;
    clearErrors();
    loaded.value = false;
    try {
      Object.assign(draft, (await api.media.sizes()).data);
      loaded.value = true;
    } catch (err) {
      toast.error(err.message ?? t("media.sizes.loadError"));
    }
  },
  { immediate: true },
);

function clearErrors() {
  for (const key of Object.keys(errors)) delete errors[key];
}

async function save() {
  clearErrors();
  saving.value = true;
  try {
    const sizes = (await api.media.saveSizes({ ...draft })).data;
    Object.assign(draft, sizes);
    toast.success(t("media.sizes.saved"));
  } catch (err) {
    Object.assign(errors, err.fields ?? {});
    toast.error(err.message ?? t("media.sizes.saveError"));
  } finally {
    saving.value = false;
  }
}

async function regenerate() {
  regenerating.value = true;
  stopping.value = false;
  Object.assign(progress, { done: 0, total: 0 });
  try {
    const summary = await regenerateInBatches(null, {
      onProgress: (done, total) => Object.assign(progress, { done, total }),
      shouldStop: () => stopping.value,
    });
    if (summary.failed > 0) toast.error(t("media.regenerateThumbsFailed", { count: summary.failed }));
    else if (summary.stopped) toast.success(t("media.sizes.stopped", { count: summary.generated }));
    else toast.success(t("media.regenerateThumbsDone", { count: summary.generated }));
    emit("regenerated");
  } catch (err) {
    toast.error(err.message ?? t("media.regenerateThumbsError"));
  } finally {
    regenerating.value = false;
    stopping.value = false;
  }
}
</script>
