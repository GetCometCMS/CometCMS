<template>
  <button type="button" class="btn-secondary btn-sm" :disabled="busy || restoring" @click="openHistory">
    <Icon icon="mdi:history" class="h-4 w-4" />
    {{ t('site.history.title') }}
  </button>
  <SlidePanel :model-value="open" :title="title" :subtitle="t('site.history.hint')" @update:model-value="(value) => { if (!restoring) open = value; }">
    <p v-if="error" role="alert" class="m-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
    <p v-if="loading" class="p-6 text-sm text-slate-500">{{ t('common.loading') }}</p>
    <p v-else-if="!revisions.length && !error" class="p-6 text-sm text-slate-500">{{ t('site.history.empty') }}</p>
    <div v-else class="divide-y divide-slate-100">
      <article v-for="revision in revisions" :key="revision.id" class="flex items-start justify-between gap-4 p-5">
        <div class="min-w-0 space-y-1">
          <p class="text-sm font-semibold text-slate-800">{{ formatDate(revision.created_at) }}</p>
          <p class="text-xs text-slate-500">
            {{ t(`site.history.event.${revision.event}`) }}
            <span v-if="revision.created_by_name || revision.created_by"> · {{ revision.created_by_name || revision.created_by }}</span>
          </p>
          <p v-if="revision.customized === false" class="text-xs text-slate-500">{{ t('site.history.defaultLayout') }}</p>
        </div>
        <button type="button" class="btn-secondary btn-sm shrink-0" :disabled="busy || restoring" @click="pending = revision">{{ t('site.history.restore') }}</button>
      </article>
    </div>
  </SlidePanel>
  <ConfirmModal
    :model-value="!!pending"
    :title="t('site.history.restoreTitle')"
    :message="t(dirty ? 'site.history.restoreDirtyMessage' : 'site.history.restoreMessage')"
    :confirm-label="t('site.history.restore')"
    :loading="restoring"
    variant="warning"
    @update:model-value="(value) => { if (!value) pending = null; }"
    @confirm="restore"
  />
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from "vue";
import { Icon } from "@iconify/vue";
import SlidePanel from "../SlidePanel.vue";
import ConfirmModal from "../ConfirmModal.vue";
import { useToastStore } from "../../stores/toast.js";
import { useI18n } from "../../i18n/index.js";

const props = defineProps({
  title: { type: String, required: true },
  fetchRevisions: { type: Function, required: true },
  restoreRevision: { type: Function, required: true },
  dirty: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
});
const emit = defineEmits(["restored"]);
const { t, locale } = useI18n();
const toast = useToastStore();
const open = ref(false);
const loading = ref(false);
const restoring = ref(false);
const revisions = ref([]);
const error = ref("");
const pending = ref(null);
let sequence = 0;

function formatDate(value) {
  return new Date(value).toLocaleString(locale.value, { dateStyle: "medium", timeStyle: "medium" });
}

async function openHistory() {
  open.value = true;
  loading.value = true;
  error.value = "";
  revisions.value = [];
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

async function restore() {
  if (!pending.value || restoring.value || props.busy) return;
  const request = ++sequence;
  restoring.value = true;
  error.value = "";
  try {
    const result = await props.restoreRevision(pending.value.id);
    if (request !== sequence) return;
    emit("restored", result.data);
    toast.success(t("site.history.restored"));
    open.value = false;
    pending.value = null;
  } catch (err) {
    if (request === sequence) {
      error.value = err.message;
      pending.value = null;
    }
  } finally {
    if (request === sequence) restoring.value = false;
  }
}

function reset() {
  sequence++;
  open.value = false;
  pending.value = null;
  restoring.value = false;
  revisions.value = [];
}

onMounted(() => window.addEventListener("cometcms:workspace-changed", reset));
onBeforeUnmount(() => {
  sequence++;
  window.removeEventListener("cometcms:workspace-changed", reset);
});
</script>
