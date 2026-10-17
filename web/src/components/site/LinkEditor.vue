<template>
  <div class="space-y-2">
    <select
      :value="link.type"
      class="form-select w-full rounded-lg border-slate-300 text-sm"
      :aria-label="t('site.link.type')"
      @change="setType($event.target.value)"
    >
      <option v-for="type in allowedTypes" :key="type" :value="type">
        {{ t(`site.link.${type}`) }}
      </option>
    </select>

    <input
      v-if="link.type === 'url'"
      :value="link.url"
      type="text"
      class="form-input w-full rounded-lg border-slate-300 text-sm"
      placeholder="https://… or /about"
      :aria-label="t('site.link.url')"
      @input="update({ url: $event.target.value })"
    />

    <select
      v-if="link.type === 'index' || link.type === 'entry'"
      :value="link.collection"
      class="form-select w-full rounded-lg border-slate-300 text-sm"
      :aria-label="t('site.link.collection')"
      @change="update({ collection: $event.target.value, entry: '' })"
    >
      <option value="">{{ t("site.link.chooseType") }}</option>
      <option v-for="type in collectionOptions" :key="type.name" :value="type.name">
        {{ type.label }}
      </option>
    </select>

    <SearchableSelect
      v-if="link.type === 'entry' && link.collection"
      :model-value="link.entry || null"
      :options="entryOptions"
      :loading="loadingEntries"
      :placeholder="t('site.link.chooseEntry')"
      :aria-label="t('site.link.entry')"
      @update:model-value="update({ entry: $event ?? '' })"
    />

    <label v-if="link.type !== 'none'" class="flex items-center gap-2 text-xs text-slate-600">
      <input
        type="checkbox"
        class="rounded border-slate-300 text-theme-600"
        :checked="link.new_tab"
        @change="update({ new_tab: $event.target.checked })"
      />
      {{ t("site.link.newTab") }}
    </label>
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import SearchableSelect from "../SearchableSelect.vue";
import { api } from "../../api/index.js";
import { useI18n } from "../../i18n/index.js";

const props = defineProps({
  modelValue: { type: Object, default: () => ({ type: "none" }) },
  types: { type: Array, default: () => [] },
  allow: {
    type: Array,
    default: () => ["none", "url", "current", "home", "index", "entry"],
  },
});

const emit = defineEmits(["update:modelValue"]);
const { t } = useI18n();
const entriesCache = new Map();
const entryOptions = ref([]);
const loadingEntries = ref(false);

const link = computed(() => ({ type: "none", url: "", collection: "", entry: "", new_tab: false, ...props.modelValue }));
const allowedTypes = computed(() => props.allow);
const collectionOptions = computed(() =>
  props.types.filter((type) =>
    type.visibility !== "private" && (link.value.type !== "index" || !type.singleton),
  ),
);

function setType(type) {
  emit("update:modelValue", { type, new_tab: link.value.new_tab ?? false });
}

function update(patch) {
  emit("update:modelValue", { ...link.value, ...patch });
}

async function loadEntries(collection) {
  if (!collection) return;
  if (entriesCache.has(collection)) {
    entryOptions.value = entriesCache.get(collection);
    return;
  }

  loadingEntries.value = true;
  try {
    const res = await api.content.list(collection, { limit: 200, sort: "-updated_at" });
    const options = (res.data ?? []).map((entry) => ({
      value: entry.slug ?? entry.id,
      label: entry.title || entry.slug || entry.id,
    }));
    entriesCache.set(collection, options);
    if (link.value.collection === collection) entryOptions.value = options;
  } catch {
    entryOptions.value = [];
  } finally {
    loadingEntries.value = false;
  }
}

watch(
  () => [link.value.type, link.value.collection],
  ([type, collection]) => {
    if (type === "entry") loadEntries(collection);
  },
  { immediate: true },
);
</script>
