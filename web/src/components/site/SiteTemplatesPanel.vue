<template>
  <section class="card p-6">
    <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
      <div class="min-w-0 flex-1">
        <h2 class="card-title">{{ t("site.templates.title") }}</h2>
        <p class="card-description">{{ t("site.templates.hint") }}</p>
      </div>
      <div v-if="types.length" class="flex w-full flex-wrap items-end gap-3 sm:w-auto">
        <div class="w-full sm:w-56">
          <label :for="typeSelectId" class="mb-1 block text-xs font-semibold text-slate-700">{{ t("site.templates.type") }}</label>
          <select :id="typeSelectId" v-model="selectedType" class="form-select w-full rounded-lg border-slate-300 text-sm">
            <option v-for="type in types" :key="type.name" :value="type.name">{{ type.label }}</option>
          </select>
        </div>
        <div v-if="variants.length > 1" class="w-full sm:w-56">
          <span class="mb-1 block text-xs font-semibold text-slate-700">{{ t("site.templates.page") }}</span>
          <SegmentedControl v-model="selectedVariant" :options="variants.map((variant) => ({ value: variant, label: t(`site.variant.${variant}`) }))" />
        </div>
      </div>
    </div>

    <p v-if="!types.length" class="py-6 text-sm text-slate-500">{{ t("site.templates.empty") }}</p>
    <div v-else-if="error" role="alert" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">
      <p>{{ error }}</p>
      <button type="button" class="btn-secondary btn-sm mt-3" @click="loadComponents">{{ t("common.retry") }}</button>
    </div>

    <div v-else class="mx-auto max-w-3xl space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 sm:p-4" @keydown.esc="openPicker = null">
      <template v-for="part in ['header', 'content', 'footer']" :key="part">
        <router-link
          v-if="part === 'content'"
          :to="builderPath"
          class="group relative flex min-h-56 flex-col items-center justify-center gap-2 overflow-hidden rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center transition hover:border-theme-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-500"
        >
          <span class="pointer-events-none absolute inset-0 bg-theme-500/0 transition group-hover:bg-theme-500/10" aria-hidden="true"></span>
          <Icon :icon="selectedVariant === 'index' ? 'mdi:view-grid-outline' : 'mdi:file-document-outline'" class="h-8 w-8 text-slate-400 group-hover:text-theme-600" aria-hidden="true" />
          <span class="text-xs text-slate-500">{{ t("site.templates.content") }}</span>
          <span class="text-sm font-semibold text-slate-900">{{ typeLabel }} · {{ t(`site.variant.${selectedVariant}`) }}</span>
          <span class="text-xs text-slate-500">{{ layoutCustomized ? t("site.templates.customLayout") : t("site.templates.defaultLayout") }}</span>
          <span class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-theme-700">
            {{ t("site.region.edit") }}<Icon icon="mdi:arrow-right" class="h-3.5 w-3.5" aria-hidden="true" />
          </span>
        </router-link>

        <div v-else class="relative" :data-frame-part="part">
          <button
            type="button"
            class="group relative flex w-full items-center gap-3 overflow-hidden rounded-xl border bg-white px-4 text-left transition hover:border-theme-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-500"
            :class="[part === 'header' ? 'min-h-16' : 'min-h-14', openPicker === part ? 'border-theme-400' : 'border-slate-200']"
            :aria-expanded="openPicker === part"
            :aria-controls="`${pickerId}-${part}`"
            @click="openPicker = openPicker === part ? null : part"
          >
            <span class="pointer-events-none absolute inset-0 transition group-hover:bg-theme-500/10" :class="openPicker === part ? 'bg-theme-500/10' : 'bg-theme-500/0'" aria-hidden="true"></span>
            <Icon :icon="part === 'header' ? 'mdi:page-layout-header' : 'mdi:page-layout-footer'" class="h-5 w-5 shrink-0 text-slate-400 group-hover:text-theme-600" aria-hidden="true" />
            <span class="min-w-0 flex-1 py-3">
              <span class="block text-xs text-slate-500">{{ t(`site.region.${part}`) }}</span>
              <span class="block truncate text-sm font-semibold" :class="value(part) === FRAME_NONE ? 'text-slate-500' : 'text-slate-900'">{{ label(part) }}</span>
            </span>
            <span class="shrink-0 text-xs font-medium text-theme-700">{{ t("site.templates.change") }}</span>
          </button>

          <div
            v-if="openPicker === part"
            :id="`${pickerId}-${part}`"
            class="absolute inset-x-0 z-20 mt-1 rounded-xl border border-slate-200 bg-white p-1 shadow-lg"
            :class="part === 'footer' ? 'bottom-full mb-1' : 'top-full'"
          >
            <p class="px-3 pb-1 pt-2 text-xs text-slate-500">{{ t("site.frame.scope", { scope: `${typeLabel} · ${t(`site.variant.${selectedVariant}`)}` }) }}</p>
            <ul role="listbox" :aria-label="t('site.frame.assign')">
              <li v-for="option in options(part)" :key="option.value">
                <button
                  type="button"
                  role="option"
                  :aria-selected="value(part) === option.value"
                  class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition hover:bg-theme-50"
                  :class="value(part) === option.value ? 'font-semibold text-theme-700' : 'text-slate-700'"
                  @click="assign(part, option.value)"
                >
                  <Icon :icon="option.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
                  <span class="min-w-0 flex-1 truncate">{{ option.label }}</span>
                  <Icon v-if="value(part) === option.value" icon="mdi:check" class="h-4 w-4 shrink-0" aria-hidden="true" />
                </button>
              </li>
            </ul>
            <p v-if="loading" class="px-3 py-2 text-xs text-slate-500">{{ t("common.loading") }}</p>
            <p v-else-if="!frameComponents(components, selectedType).length" class="px-3 py-2 text-xs text-slate-500">{{ t("site.frame.noComponents") }}</p>
            <div v-if="value(part) !== FRAME_NONE" class="mt-1 border-t border-slate-100 pt-1">
              <router-link :to="editPath(part)" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                <Icon icon="mdi:pencil-outline" class="h-4 w-4" aria-hidden="true" />
                {{ value(part) === FRAME_DEFAULT ? t("site.builder.editTemplatePart", { name: t(`site.region.${part}`) }) : t("site.frame.editComponent", { name: label(part) }) }}
              </router-link>
            </div>
          </div>
        </div>
      </template>
    </div>
  </section>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, useId, watch } from "vue";
import { Icon } from "@iconify/vue";
import SegmentedControl from "./SegmentedControl.vue";
import { api } from "../../api/index.js";
import { FRAME_DEFAULT, FRAME_NONE, frameComponents, frameLabel, frameValue, withFrame } from "../../composables/frames.js";
import { useSiteStore } from "../../stores/site.js";
import { useI18n } from "../../i18n/index.js";

const site = useSiteStore();
const { t } = useI18n();
const typeSelectId = useId();
const pickerId = useId();
const BACK = "/page-builder/templates";

const components = ref([]);
const loading = ref(false);
const error = ref("");
const openPicker = ref(null);
let active = true;

/** Types with website pages: the ones that have layouts. */
const types = computed(() => (site.data?.types ?? []).filter((type) => site.data?.layouts?.[type.name]));
const homepageType = site.data?.settings?.homepage?.type;
const selectedType = ref(types.value.some((type) => type.name === homepageType) ? homepageType : types.value[0]?.name ?? "");
const variants = computed(() => ["detail", "index"].filter((variant) => variant in (site.data?.layouts?.[selectedType.value] ?? {})));
const selectedVariant = ref("detail");
watch(variants, (list) => {
  if (!list.includes(selectedVariant.value)) selectedVariant.value = list[0] ?? "detail";
}, { immediate: true });
watch([selectedType, selectedVariant], () => { openPicker.value = null; });

const typeLabel = computed(() => types.value.find((type) => type.name === selectedType.value)?.label ?? selectedType.value);
const layoutCustomized = computed(() => !!site.data?.layouts?.[selectedType.value]?.[selectedVariant.value]);
const builderPath = computed(() => ({
  path: `/page-builder/builder/layout/${encodeURIComponent(selectedType.value)}/${selectedVariant.value}`,
  query: { back: BACK },
}));

const value = (part) => frameValue(site.draft?.frames, selectedType.value, selectedVariant.value, part);
const label = (part) => frameLabel(t, value(part), part, components.value);

function options(part) {
  return [
    { value: FRAME_DEFAULT, label: t(`site.frame.default.${part}`), icon: part === "header" ? "mdi:page-layout-header" : "mdi:page-layout-footer" },
    { value: FRAME_NONE, label: t(`site.frame.none.${part}`), icon: "mdi:cancel" },
    ...frameComponents(components.value, selectedType.value).map((component) => ({ value: component.id, label: component.name, icon: "mdi:puzzle-outline" })),
  ];
}

function assign(part, next) {
  if (!site.draft) return;
  site.draft.frames = withFrame(site.draft.frames, selectedType.value, selectedVariant.value, part, next);
  openPicker.value = null;
}

function editPath(part) {
  const current = value(part);
  const path = current === FRAME_DEFAULT ? `/page-builder/builder/region/${part}` : `/page-builder/builder/component/${encodeURIComponent(current)}`;
  return { path, query: { back: BACK } };
}

async function loadComponents() {
  loading.value = true;
  error.value = "";
  try {
    const response = await api.site.components();
    if (active) components.value = response.data;
  } catch (err) {
    if (active) error.value = err.message;
  } finally {
    if (active) loading.value = false;
  }
}

/** A click outside the open picker's part closes it. */
function onPointerDown(event) {
  if (openPicker.value && !event.target.closest?.(`[data-frame-part="${openPicker.value}"]`)) openPicker.value = null;
}

onMounted(() => {
  loadComponents();
  document.addEventListener("pointerdown", onPointerDown);
});
onBeforeUnmount(() => {
  active = false;
  document.removeEventListener("pointerdown", onPointerDown);
});
</script>
