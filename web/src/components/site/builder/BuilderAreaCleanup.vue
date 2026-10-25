<template>
  <div v-if="unused.length">
    <button type="button" class="text-[11px] font-medium text-slate-500 underline-offset-2 hover:text-red-600 hover:underline" @click="open = true">
      {{ t("site.page.cleanUpAreas", { count: unused.length }) }}
    </button>
    <ConfirmModal
      v-model="open"
      :title="t('site.page.cleanUpTitle')"
      :message="t('site.page.cleanUpMessage')"
      :confirm-label="t('site.page.deleteSelected')"
      :loading="deleting"
      @confirm="remove"
    >
      <ul class="mt-3 space-y-1.5">
        <li v-for="area in unused" :key="area.key">
          <label class="flex items-start gap-2 text-sm text-slate-700">
            <input v-model="chosen" type="checkbox" class="form-checkbox mt-0.5 rounded" :value="area.key" />
            <span>
              <span class="font-medium">{{ label(area) }}</span>
              <span class="block text-xs text-slate-500">{{ t("site.page.areaEntries", { count: area.entries }) }}</span>
            </span>
          </label>
        </li>
      </ul>
    </ConfirmModal>
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import ConfirmModal from "../../ConfirmModal.vue";
import { api } from "../../../api/index.js";
import { useSiteBuilder } from "../../../composables/useSiteBuilder.js";
import { resolveLocalized } from "../../../composables/localizedValues.js";
import { useToastStore } from "../../../stores/toast.js";
import { useI18n } from "../../../i18n/index.js";

/** Delete content areas no template shows, with the entries' designs in them. */
const builder = useSiteBuilder();
const toast = useToastStore();
const { t } = useI18n();
const areas = ref([]);
const open = ref(false);
const chosen = ref([]);
const deleting = ref(false);

/** Unplaced in every saved template and not just placed on this canvas. */
const unused = computed(() => {
  const shown = new Set();
  const collect = (node) => {
    if (node.kind === "page_content" && node.props?.field) shown.add(node.props.field);
    node.children?.forEach(collect);
  };
  builder.nodes.value.forEach(collect);
  return areas.value.filter((area) => !area.templates.length && !shown.has(area.key));
});

function label(area) {
  return resolveLocalized(area.label, builder.locale.value, builder.defaultLocale.value) || area.key;
}

async function load() {
  const type = builder.type.value?.name;
  if (!type || !builder.canAddPageField()) return (areas.value = []);
  try {
    areas.value = (await api.site.areas(type)).data ?? [];
  } catch {
    areas.value = [];
  }
}

async function remove() {
  const type = builder.type.value.name;
  deleting.value = true;
  try {
    for (const key of chosen.value) areas.value = (await api.site.deleteArea(type, key)).data ?? [];
    const fields = Object.fromEntries(Object.entries(builder.type.value.fields ?? {}).filter(([key]) => !chosen.value.includes(key)));
    builder.type.value = { ...builder.type.value, fields };
    open.value = false;
  } catch (error) {
    toast.error(error.fields?.field ?? error.message);
    await load();
  } finally {
    deleting.value = false;
  }
}

watch(open, (value) => { if (value) chosen.value = []; });
// The type's fields change when areas are added, renamed or saved back.
watch(() => builder.type.value?.fields, load, { immediate: true });
</script>
