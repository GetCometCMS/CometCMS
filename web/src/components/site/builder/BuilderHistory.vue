<template>
  <div>
    <p class="px-4 pt-3 text-xs text-slate-500">{{ t('site.history.hint') }}</p>
    <p v-if="error" role="alert" class="m-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
    <div v-if="loading" class="flex justify-center p-6"><LoadingSpinner /></div>
    <p v-else-if="!revisions.length && !error" class="p-4 text-sm text-slate-500">{{ t('site.history.empty') }}</p>
    <div v-else class="mt-2 divide-y divide-slate-100">
      <article v-for="revision in revisions" :key="revision.id" class="flex items-start justify-between gap-3 px-4 py-3">
        <div class="min-w-0 space-y-0.5">
          <p class="text-sm font-semibold text-slate-800">{{ formatDate(revision.created_at) }}</p>
          <p class="text-xs text-slate-500">
            {{ t(`site.history.event.${revision.event}`) }}
            <span v-if="revision.created_by_name || revision.created_by"> · {{ revision.created_by_name || revision.created_by }}</span>
          </p>
          <p v-if="revision.customized === false" class="text-xs text-slate-500">{{ t('site.history.defaultLayout') }}</p>
        </div>
        <button type="button" class="btn-secondary btn-sm shrink-0" :disabled="busy || loadingRevision" @click="loadRevision(revision)">{{ t('site.history.load') }}</button>
      </article>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from "vue";
import LoadingSpinner from "../../LoadingSpinner.vue";
import { useToastStore } from "../../../stores/toast.js";
import { useI18n } from "../../../i18n/index.js";

const props = defineProps({
  fetchRevisions: { type: Function, required: true },
  fetchRevision: { type: Function, required: true },
  busy: { type: Boolean, default: false },
});
const emit = defineEmits(["loaded"]);
const { t, locale } = useI18n();
const toast = useToastStore();
const loading = ref(false);
const loadingRevision = ref(false);
const revisions = ref([]);
const error = ref("");
let sequence = 0;

function formatDate(value) {
  return new Date(value).toLocaleString(locale.value, { dateStyle: "medium", timeStyle: "medium" });
}

async function load() {
  loading.value = true;
  error.value = "";
  revisions.value = [];
  loadingRevision.value = false;
  const request = ++sequence;
  try {
    const result = await props.fetchRevisions();
    if (request === sequence) revisions.value = result.data ?? [];
  } catch (err) {
    if (request === sequence) error.value = err.message;
  } finally {
    if (request === sequence) loading.value = false;
  }
}

async function loadRevision(revision) {
  if (loadingRevision.value || props.busy) return;
  const request = ++sequence;
  loadingRevision.value = true;
  error.value = "";
  try {
    const result = await props.fetchRevision(revision.id);
    if (request !== sequence || props.busy) return;
    emit("loaded", result.data, revision);
    toast.success(t("site.history.loaded"));
  } catch (err) {
    if (request === sequence) error.value = err.message;
  } finally {
    if (request === sequence) loadingRevision.value = false;
  }
}

watch(() => props.fetchRevisions, load, { immediate: true });
defineExpose({ reload: load });

onMounted(() => window.addEventListener("cometcms:workspace-changed", load));
onBeforeUnmount(() => {
  sequence++;
  window.removeEventListener("cometcms:workspace-changed", load);
});
</script>
