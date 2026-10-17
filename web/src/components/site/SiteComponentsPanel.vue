<template>
  <div class="space-y-4">
    <p class="text-sm text-slate-500">{{ t("site.component.panelHint") }}</p>

    <div v-if="loading" class="flex justify-center py-12"><LoadingSpinner /></div>
    <div v-else-if="error" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>

    <div v-else-if="components.length === 0" class="card flex flex-col items-center px-6 py-12 text-center">
      <Icon icon="mdi:puzzle-outline" class="h-10 w-10 text-slate-300" />
      <p class="mt-3 text-sm font-medium text-slate-700">{{ t("site.component.emptyTitle") }}</p>
      <p class="mt-1 max-w-md text-sm text-slate-500">{{ t("site.component.emptyHint") }}</p>
    </div>

    <ul v-else class="card divide-y divide-slate-100">
      <li v-for="component in components" :key="component.id" class="flex flex-wrap items-center gap-3 px-4 py-3">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-theme-50 text-theme-600">
          <Icon :icon="KIND_ICONS[component.root] ?? KIND_ICONS.component" class="h-5 w-5" />
        </div>
        <div class="min-w-0 flex-1">
          <p class="truncate font-medium text-slate-900">{{ component.name }}</p>
          <p class="truncate text-xs text-slate-500">
            {{ t(`site.kind.${component.root}`) }}
            <template v-if="component.type"> · {{ typeLabel(component.type) }}</template>
            · {{ t("site.component.usedIn", { count: component.usages }) }}
          </p>
        </div>
        <div class="flex shrink-0 items-center gap-2">
          <router-link :to="`/page-builder/builder/component/${component.id}`" class="btn-primary btn-sm">
            <Icon icon="mdi:pencil-ruler" class="h-4 w-4" />
            {{ t("site.component.edit") }}
          </router-link>
          <button type="button" class="btn-secondary btn-sm" @click="renameTarget = component">{{ t("site.component.rename") }}</button>
          <button type="button" class="btn-secondary btn-sm hover:!text-red-600" @click="deleteTarget = component">{{ t("common.delete") }}</button>
        </div>
      </li>
    </ul>

    <ComponentNameModal
      :model-value="renameTarget !== null"
      :title="t('site.component.renameTitle')"
      :confirm-label="t('site.component.rename')"
      :initial="renameTarget?.name ?? ''"
      :loading="busy"
      @update:model-value="(open) => { if (!open) renameTarget = null }"
      @confirm="rename"
    />

    <ConfirmModal
      :model-value="deleteTarget !== null"
      :title="t('site.component.deleteTitle', { name: deleteTarget?.name ?? '' })"
      :message="t('site.component.deleteMessage')"
      :confirm-label="t('common.delete')"
      :loading="busy"
      @update:model-value="(open) => { if (!open) deleteTarget = null }"
      @confirm="remove"
    />
  </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { Icon } from "@iconify/vue";
import ConfirmModal from "../ConfirmModal.vue";
import LoadingSpinner from "../LoadingSpinner.vue";
import ComponentNameModal from "./ComponentNameModal.vue";
import { KIND_ICONS } from "./builder/kindIcons.js";
import { api } from "../../api/index.js";
import { useSiteStore } from "../../stores/site.js";
import { useToastStore } from "../../stores/toast.js";
import { useI18n } from "../../i18n/index.js";

const site = useSiteStore();
const toast = useToastStore();
const { t } = useI18n();
const components = ref([]);
const loading = ref(true);
const error = ref("");
const busy = ref(false);
const renameTarget = ref(null);
const deleteTarget = ref(null);

async function load() {
  loading.value = true;
  error.value = "";
  try {
    components.value = (await api.site.components()).data;
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
}

function typeLabel(name) {
  return site.data?.types?.find((type) => type.name === name)?.label ?? name;
}

async function rename(name) {
  const target = renameTarget.value;
  busy.value = true;
  try {
    const updated = (await api.site.renameComponent(target.id, name)).data;
    components.value = components.value
      .map((item) => (item.id === target.id ? { ...item, name: updated.name } : item))
      .sort((a, b) => a.name.localeCompare(b.name));
    renameTarget.value = null;
  } catch (err) {
    toast.error(err.message);
  } finally {
    busy.value = false;
  }
}

async function remove() {
  if (busy.value || !deleteTarget.value) return;
  const target = deleteTarget.value;
  busy.value = true;
  try {
    await api.site.deleteComponent(target.id);
    components.value = components.value.filter((item) => item.id !== target.id);
    deleteTarget.value = null;
    await load();
    toast.success(t("site.component.deleted"));
  } catch (err) {
    toast.error(err.message);
  } finally {
    busy.value = false;
  }
}

onMounted(load);
</script>
