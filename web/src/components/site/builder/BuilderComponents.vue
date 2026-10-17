<template>
  <div class="space-y-3 p-3">
    <div class="sticky top-0 z-10 -mx-3 bg-white px-3 pb-2">
      <label class="sr-only" :for="searchId">{{ t("site.component.search") }}</label>
      <input
        :id="searchId"
        v-model="search"
        type="search"
        :placeholder="t('site.component.search')"
        class="form-input w-full rounded-lg border-slate-300 text-sm"
        @keydown.esc.stop.prevent="search = ''"
      />
    </div>
    <p class="px-1 text-xs text-slate-500">{{ t("site.builder.paletteHint") }}</p>
    <ul class="space-y-2">
      <li v-for="component in components" :key="component.id" class="space-y-1">
        <div class="flex items-center gap-1">
          <PaletteItem
            class="min-w-0 flex-1"
            :icon="KIND_ICONS.component"
            :label="component.name"
            :disabled="busy || !builder.canInsertComponent(component)"
            :title="builder.canInsertComponent(component) ? component.name : t('site.component.unavailable')"
            @add="add(component)"
            @drag="start($event, component)"
            @dragend="end"
          />
          <button type="button" class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-theme-700" :disabled="busy" :title="t('site.component.edit')" @click="builder.editComponent(component.id)">
            <Icon icon="mdi:pencil-outline" class="h-4 w-4" />
            <span class="sr-only">{{ t("site.component.edit") }}: {{ component.name }}</span>
          </button>
          <button type="button" class="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600" :disabled="busy" :title="t('common.delete')" @click="deleteTarget = component">
            <Icon icon="mdi:trash-can-outline" class="h-4 w-4" />
            <span class="sr-only">{{ t("common.delete") }}: {{ component.name }}</span>
          </button>
        </div>
        <p class="px-2 text-[11px] text-slate-400">
          {{ builder.kindLabel(component.root) }}<template v-if="component.type"> · {{ typeLabel(component.type) }}</template>
        </p>
      </li>
    </ul>
    <p v-if="!builder.components.value.length" class="px-1 text-xs text-slate-500">{{ t("site.component.paletteEmpty") }}</p>
    <p v-else-if="!components.length" role="status" class="px-1 text-sm text-slate-500">{{ t("site.builder.noSearchResults") }}</p>

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
import { computed, ref, useId } from "vue";
import { Icon } from "@iconify/vue";
import ConfirmModal from "../../ConfirmModal.vue";
import PaletteItem from "./PaletteItem.vue";
import { KIND_ICONS } from "./kindIcons.js";
import { instanceNode } from "../../../composables/layoutTree.js";
import { useSiteBuilder } from "../../../composables/useSiteBuilder.js";
import { useToastStore } from "../../../stores/toast.js";
import { useI18n } from "../../../i18n/index.js";

const emit = defineEmits(["deleted"]);
const builder = useSiteBuilder();
const toast = useToastStore();
const { t } = useI18n();
const search = ref("");
const searchId = `builder-components-search-${useId()}`;
const busy = ref(false);
const deleteTarget = ref(null);
const components = computed(() => {
  const terms = search.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
  return [...builder.components.value]
    .filter((component) => {
      const text = [component.name, builder.kindLabel(component.root), typeLabel(component.type)].join(" ").toLowerCase();
      return terms.every((term) => text.includes(term));
    })
    .sort((a, b) => a.name.localeCompare(b.name));
});

function typeLabel(name) {
  return builder.types.value.find((type) => type.name === name)?.label ?? name;
}

function add(component) {
  if (busy.value || !builder.canInsertComponent(component)) return;
  builder.insert(instanceNode(builder.spec.value, component));
}

function start(event, component) {
  if (busy.value || !builder.canInsertComponent(component)) {
    event.preventDefault();
    return;
  }
  builder.drag.value = { source: "palette", create: () => instanceNode(builder.spec.value, component) };
  event.dataTransfer.effectAllowed = "copy";
  event.dataTransfer.setData("text/plain", "cometcms-node");
}

function end() {
  setTimeout(() => { builder.drag.value = null; }, 0);
}

async function remove() {
  if (busy.value || !deleteTarget.value) return;
  busy.value = true;
  try {
    const id = deleteTarget.value.id;
    await builder.deleteComponent(id);
    deleteTarget.value = null;
    toast.success(t("site.component.deleted"));
    emit("deleted", id);
  } catch (err) {
    toast.error(err.message);
  } finally {
    busy.value = false;
  }
}
</script>
