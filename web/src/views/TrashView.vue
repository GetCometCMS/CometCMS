<template>
  <div>
    <PageHeader
      :title="t('trash.heading')"
      :description="t('trash.description')"
      :back="{ to: `/content/${collection}`, label: typeLabel }"
    >
      <template #actions>
        <button
          v-if="items.length > 0"
          type="button"
          class="btn-danger-subtle"
          @click="showEmptyModal = true"
        >
          <Icon icon="mdi:trash-can-outline" class="h-4 w-4" />
          {{ t("trash.emptyAction") }}
        </button>
      </template>
    </PageHeader>

    <LoadingSpinner v-if="loading" />

    <div
      v-else-if="error"
      class="p-3 bg-red-50 text-red-700 rounded-lg text-sm"
    >
      {{ error }}
    </div>

    <template v-else>
      <div v-if="items.length === 0" class="card">
        <EmptyState
          icon="mdi:trash-can-outline"
          :title="t('trash.emptyForCollection')"
          :description="t('trash.emptyHint')"
        />
      </div>
      <div v-else class="card overflow-x-auto">
        <table class="data-table">
          <thead>
            <tr>
              <th>{{ t("trash.titleColumn") }}</th>
              <th>{{ t("trash.deleted") }}</th>
              <th>{{ t("trash.status") }}</th>
              <th><span class="sr-only">{{ t("common.moreActions") }}</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in items" :key="item.id">
              <td class="font-medium text-slate-900">
                {{ item.title ?? item.id }}
              </td>
              <td class="whitespace-nowrap text-slate-500">
                {{ formatDate(item.deleted_at) }}
              </td>
              <td>
                <span :class="`badge-${item.status}`">{{ formatStatus(item.status) }}</span>
              </td>
              <td class="text-right">
                <div class="flex items-center justify-end gap-2">
                  <button
                    type="button"
                    class="btn-secondary btn-sm"
                    :disabled="restoringId === item.id"
                    @click="restore(item)"
                  >
                    <Icon icon="mdi:restore" class="h-3.5 w-3.5" />
                    {{
                      restoringId === item.id
                        ? t("trash.restoring")
                        : t("trash.restore")
                    }}
                  </button>
                  <button
                    type="button"
                    class="btn-danger-subtle btn-sm"
                    @click="confirmPurge(item)"
                  >
                    {{ t("trash.deletePermanently") }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <ConfirmModal
      v-model="showPurgeModal"
      :title="t('trash.purgeTitle')"
      :message="
        t('trash.purgeMessage', { name: purgeTarget?.title ?? purgeTarget?.id })
      "
      :confirm-label="t('trash.deletePermanently')"
      :loading="purging"
      @confirm="purge"
    />

    <ConfirmModal
      v-model="showEmptyModal"
      :title="t('trash.emptyTitle')"
      :message="
        t('trash.emptyMessage', {
          count: items.length,
          itemLabel: t(items.length === 1 ? 'trash.item' : 'trash.items'),
        })
      "
      :confirm-label="t('trash.emptyAction')"
      :loading="emptying"
      @confirm="emptyTrash"
    />
  </div>
</template>

<script setup>
import EmptyState from "../components/EmptyState.vue";
import { useContentTypesStore } from "../stores/contentTypes.js";
import PageHeader from "../components/PageHeader.vue";
import LoadingSpinner from "../components/LoadingSpinner.vue";
import { computed, ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import { Icon } from "@iconify/vue";
import ConfirmModal from "../components/ConfirmModal.vue";
import { api } from "../api/index.js";
import { useToastStore } from "../stores/toast.js";
import { useI18n } from "../i18n/index.js";

const route = useRoute();
const toast = useToastStore();
const { t } = useI18n();
const collection = route.params.collection;
const typesStore = useContentTypesStore();
const typeLabel = computed(
  () => typesStore.list.find((type) => type.name === collection)?.label ?? collection,
);

const loading = ref(true);
const error = ref("");
const items = ref([]);

const restoringId = ref("");
const showPurgeModal = ref(false);
const purgeTarget = ref(null);
const purging = ref(false);
const showEmptyModal = ref(false);
const emptying = ref(false);

function formatStatus(status) {
  if (!status) return t("contentList.unknownStatus");
  const key = `status.${status}`;
  const label = t(key);
  return label === key ? status : label;
}

async function load() {
  loading.value = true;
  error.value = "";
  try {
    const res = await api.trash.list(collection);
    items.value = res.data ?? [];
  } catch (err) {
    error.value = err.message;
  } finally {
    loading.value = false;
  }
}

async function restore(item) {
  restoringId.value = item.id;
  try {
    await api.trash.restore(collection, item.id);
    items.value = items.value.filter((i) => i.id !== item.id);
    toast.success(t("trash.restored", { name: item.title ?? item.id }));
  } catch (err) {
    toast.error(err.message);
  } finally {
    restoringId.value = "";
  }
}

function confirmPurge(item) {
  purgeTarget.value = item;
  showPurgeModal.value = true;
}

async function purge() {
  const item = purgeTarget.value;
  purging.value = true;
  try {
    await api.trash.purge(collection, item.id);
    items.value = items.value.filter((i) => i.id !== item.id);
    toast.success(
      t("trash.permanentlyDeleted", { name: item.title ?? item.id }),
    );
    showPurgeModal.value = false;
  } catch (err) {
    toast.error(err.message);
    showPurgeModal.value = false;
  } finally {
    purging.value = false;
    purgeTarget.value = null;
  }
}

async function emptyTrash() {
  emptying.value = true;
  try {
    await api.trash.empty(collection);
    items.value = [];
    toast.success(t("trash.emptied"));
    showEmptyModal.value = false;
  } catch (err) {
    toast.error(err.message);
    showEmptyModal.value = false;
  } finally {
    emptying.value = false;
  }
}

function formatDate(iso) {
  if (!iso) return "—";
  return new Date(iso).toLocaleDateString(undefined, { dateStyle: "medium" });
}

onMounted(load);
</script>
