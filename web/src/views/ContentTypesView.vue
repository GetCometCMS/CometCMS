<template>
  <div>
    <PageHeader
      :title="t('contentTypes.title')"
      :description="t('contentTypes.description')"
    >
      <template #actions>
        <router-link v-if="canSchema('schema.create')" to="/content-types/new" class="btn-primary">
          <Icon icon="mdi:plus" class="h-4 w-4" />
          {{ t("contentTypes.new") }}
        </router-link>
      </template>
    </PageHeader>

    <LoadingSpinner v-if="loading" />

    <div v-else-if="types.length === 0" class="card">
      <EmptyState
        icon="mdi:shape-outline"
        :title="t('contentTypes.empty')"
        :description="t('contentTypes.emptyHint')"
      >
        <router-link v-if="canSchema('schema.create')" to="/content-types/new" class="btn-primary">
          <Icon icon="mdi:plus" class="h-4 w-4" />
          {{ t("contentTypes.new") }}
        </router-link>
      </EmptyState>
    </div>

    <div v-else class="card overflow-x-auto">
      <table class="data-table">
        <thead>
          <tr>
            <th class="w-10 !px-2"><span class="sr-only">{{ t("contentTypes.reorderHint") }}</span></th>
            <th>{{ t("contentTypes.label") }}</th>
            <th>{{ t("contentTypes.model") }}</th>
            <th>{{ t("contentTypes.fields") }}</th>
            <th>{{ t("site.typeCard.title") }}</th>
            <th><span class="sr-only">{{ t("common.moreActions") }}</span></th>
          </tr>
        </thead>
        <TransitionGroup tag="tbody" name="ct-sort">
          <tr
            v-for="type in types"
            :key="type.name"
            class="cursor-pointer"
            :class="{
              'opacity-50': draggedName === type.name,
              '!bg-theme-50':
                dropTargetName === type.name && draggedName !== type.name,
            }"
            @click="openType(type.name)"
            @dragover.prevent="dropTargetName = type.name"
            @dragleave="clearDropTarget(type.name)"
            @drop.prevent="handleDrop(type.name)"
          >
            <td class="!px-2 text-slate-400">
              <button
                v-if="canSchema('schema.update')"
                type="button"
                class="inline-flex h-8 w-8 cursor-grab items-center justify-center rounded-lg hover:bg-slate-100 hover:text-slate-700 disabled:opacity-50"
                :title="t('contentTypes.reorderHint')"
                :aria-label="t('contentTypes.reorderNamed', { name: type.label })"
                draggable="true"
                :disabled="savingOrder"
                @click.stop
                @keydown.up.prevent="moveByKeyboard(type.name, -1)"
                @keydown.down.prevent="moveByKeyboard(type.name, 1)"
                @dragstart.stop="handleDragStart(type.name, $event)"
                @dragend="handleDragEnd"
              >
                <Icon icon="mdi:drag-vertical" class="w-5 h-5" />
              </button>
            </td>
            <td>
              <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200">
                  <Icon :icon="type.icon || defaultIcon" class="h-[18px] w-[18px]" />
                </span>
                <div class="min-w-0">
                  <div class="truncate font-medium text-slate-900">{{ type.label }}</div>
                  <div class="truncate font-mono text-xs text-slate-500">{{ type.name }}</div>
                </div>
              </div>
            </td>
            <td>
              <span :class="modelPillClass(type)">
                <Icon :icon="type.singleton ? 'mdi:file-outline' : 'mdi:file-multiple-outline'" class="h-3.5 w-3.5" />
                {{ modelLabel(type) }}
              </span>
            </td>
            <td class="tabular-nums text-slate-600">
              {{ Object.keys(type.fields ?? {}).length }}
            </td>
            <td>
              <span v-if="type.visibility === 'private'" class="text-slate-500" :title="t('site.typeCard.private')">
                {{ t("contentTypeEdit.private") }}
              </span>
              <span
                v-else-if="websiteStatus?.types?.[type.name]"
                class="badge badge-dot"
                :class="websiteStatus.types[type.name].pages_enabled ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-500 ring-slate-500/15'"
              >
                {{ websiteStatus.types[type.name].pages_enabled ? t("contentTypes.frontendEnabled") : t("contentTypes.frontendDisabled") }}
              </span>
              <span v-else class="text-slate-400" :title="t('contentTypes.frontendUnavailable')">—</span>
            </td>
            <td class="text-right">
              <div class="flex items-center justify-end gap-2" @click.stop>
                <router-link :to="contentLink(type)" class="btn-secondary btn-sm">
                  {{ type.singleton ? t("contentTypes.open") : t("contentTypes.browse") }}
                </router-link>
                <router-link
                  v-if="canSchema('schema.update', type.name)"
                  :to="`/content-types/${type.name}/edit`"
                  class="btn-secondary btn-sm"
                >
                  <Icon icon="mdi:pencil-outline" class="h-3.5 w-3.5" />
                  {{ t("contentTypes.edit") }}
                </router-link>
              </div>
            </td>
          </tr>
        </TransitionGroup>
      </table>
    </div>
  </div>
</template>

<script setup>
import LoadingSpinner from "../components/LoadingSpinner.vue";
import PageHeader from "../components/PageHeader.vue";
import EmptyState from "../components/EmptyState.vue";
import { ref, onBeforeUnmount, onMounted } from "vue";
import { Icon } from "@iconify/vue";
import { useRouter } from "vue-router";
import { api } from "../api/index.js";
import { contentTypesEndpoint } from "../composables/apiEndpoint.js";
import { useApiEndpointStore } from "../stores/apiEndpoint.js";
import { useContentTypesStore } from "../stores/contentTypes.js";
import { useToastStore } from "../stores/toast.js";
import { useI18n } from "../i18n/index.js";
import { usePermissions } from "../composables/usePermissions.js";

const loading = ref(true);
const types = ref([]);
const defaultIcon = "mdi:file-document-outline";
const router = useRouter();
const apiEndpointStore = useApiEndpointStore();
const typesStore = useContentTypesStore();
const toast = useToastStore();
const { t } = useI18n();
const { canSchema } = usePermissions();
const apiEndpointOwner = "content-types-list";
const draggedName = ref("");
const dropTargetName = ref("");
const savingOrder = ref(false);
const websiteStatus = ref(null);
let clickAfterDrag = false;

onMounted(async () => {
  apiEndpointStore.setEndpoint(
    {
      label: "Content types",
      url: contentTypesEndpoint(),
    },
    apiEndpointOwner,
  );

  try {
    const [typeResult, siteResult] = await Promise.allSettled([
      api.contentTypes.list(),
      api.site.status(),
    ]);
    if (typeResult.status === "rejected") throw typeResult.reason;
    types.value = typeResult.value.data ?? [];
    typesStore.setList(types.value);
    if (siteResult.status === "fulfilled") websiteStatus.value = siteResult.value.data;
  } catch (err) {
    toast.error(err.message);
  } finally {
    loading.value = false;
  }
});

onBeforeUnmount(() => {
  apiEndpointStore.clearEndpoint(apiEndpointOwner);
});

function openType(name) {
  if (clickAfterDrag) {
    clickAfterDrag = false;
    return;
  }

  const type = types.value.find((item) => item.name === name);
  router.push(
    canSchema("schema.update", name) || !type
      ? `/content-types/${name}/edit`
      : contentLink(type),
  );
}

// Keyboard alternative to drag-and-drop: arrow keys move a type one position.
function moveByKeyboard(name, delta) {
  const index = types.value.findIndex((type) => type.name === name);
  const target = types.value[index + delta];
  if (index < 0 || !target) return;
  draggedName.value = name;
  handleDrop(target.name);
}

function handleDragStart(name, event) {
  draggedName.value = name;
  clickAfterDrag = true;
  event.dataTransfer.effectAllowed = "move";
  event.dataTransfer.setData("text/plain", name);
}

function handleDragEnd() {
  draggedName.value = "";
  dropTargetName.value = "";
  window.setTimeout(() => {
    clickAfterDrag = false;
  }, 0);
}

function clearDropTarget(name) {
  if (dropTargetName.value === name) {
    dropTargetName.value = "";
  }
}

function modelLabel(type) {
  return type.singleton
    ? t("contentTypes.single")
    : t("contentTypes.collection");
}

function modelPillClass(type) {
  return [
    "badge",
    type.singleton
      ? "bg-violet-50 text-violet-700 ring-violet-600/20"
      : "bg-slate-100 text-slate-700 ring-slate-500/15",
  ];
}

function contentLink(type) {
  return `/content/${type.name}`;
}

async function handleDrop(targetName) {
  const fromName = draggedName.value;
  draggedName.value = "";
  dropTargetName.value = "";

  if (!fromName || fromName === targetName || savingOrder.value) return;

  const previous = [...types.value];
  const reordered = moveType(types.value, fromName, targetName);
  if (reordered === null) return;

  types.value = reordered;
  typesStore.setList(reordered);
  savingOrder.value = true;

  try {
    const res = await api.contentTypes.reorder(
      reordered.map((type) => type.name),
    );
    types.value = res.data ?? reordered;
    typesStore.setList(types.value);
    toast.success(t("contentTypes.orderSaved"));
  } catch (err) {
    types.value = previous;
    typesStore.setList(previous);
    toast.error(err.message ?? t("contentTypes.orderSaveFailed"));
  } finally {
    savingOrder.value = false;
  }
}

function moveType(items, fromName, targetName) {
  const fromIndex = items.findIndex((type) => type.name === fromName);
  const targetIndex = items.findIndex((type) => type.name === targetName);

  if (fromIndex < 0 || targetIndex < 0) return null;

  const next = [...items];
  const [moved] = next.splice(fromIndex, 1);
  const targetAfterRemoval = next.findIndex((type) => type.name === targetName);
  const insertIndex =
    fromIndex < targetIndex ? targetAfterRemoval + 1 : targetAfterRemoval;
  next.splice(insertIndex, 0, moved);

  return next;
}
</script>

<style scoped>
.ct-sort-move {
  transition: transform 0.25s ease;
}
</style>
