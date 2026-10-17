<template>
  <section id="website" class="card scroll-mt-24 space-y-4 p-6">
    <div class="flex flex-wrap items-start gap-3">
      <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-theme-50 text-theme-600">
        <Icon icon="mdi:web" class="h-5 w-5" />
      </div>
      <div class="min-w-0 flex-1">
        <h2 class="text-sm font-semibold text-slate-700">{{ t("site.typeCard.title") }}</h2>
        <p class="mt-0.5 text-sm text-slate-500">{{ summary }}</p>
      </div>
      <label v-if="editable" class="flex items-center gap-2 text-sm font-medium text-slate-700">
        {{ t("site.pages.onWebsite") }}
        <ToggleSwitch :model-value="!!route.enabled" @update:model-value="update({ enabled: $event })" />
      </label>
    </div>

    <div
      v-if="status && !status.enabled && (editable || route?.enabled)"
      class="flex flex-wrap items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
    >
      <Icon icon="mdi:information-outline" class="h-4 w-4 shrink-0" />
      <span class="flex-1">{{ t("site.typeCard.siteOff") }}</span>
      <router-link v-if="status.can_manage" to="/website/overview" class="font-medium underline">{{ t("site.typeCard.openWebsite") }}</router-link>
    </div>

    <template v-if="editable && route.enabled">
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
          <label class="form-label" for="type-route-pattern">{{ singleton ? t("site.pages.pageUrl") : t("site.pages.entryUrl") }}</label>
          <input
            id="type-route-pattern"
            :value="route.pattern"
            type="text"
            class="form-input w-full rounded-lg font-mono text-sm"
            :class="errors.pattern ? 'border-red-400' : 'border-slate-300'"
            @input="update({ pattern: $event.target.value })"
          />
          <p v-if="errors.pattern" class="mt-1 text-xs text-red-600">{{ errors.pattern }}</p>
          <p v-else class="mt-1 text-xs text-slate-500">{{ singleton ? t("site.pages.pageUrlHint") : t("site.pages.entryUrlHint") }}</p>
        </div>

        <div v-if="!singleton">
          <label class="form-label" for="type-route-index">{{ t("site.pages.listUrl") }}</label>
          <input
            id="type-route-index"
            :value="route.index_path"
            type="text"
            class="form-input w-full rounded-lg font-mono text-sm"
            :class="errors.index_path ? 'border-red-400' : 'border-slate-300'"
            :placeholder="t('site.pages.listUrlNone')"
            @input="update({ index_path: $event.target.value })"
          />
          <p v-if="errors.index_path" class="mt-1 text-xs text-red-600">{{ errors.index_path }}</p>
          <p v-else class="mt-1 text-xs text-slate-500">{{ t("site.pages.listUrlHint") }}</p>
        </div>
      </div>

      <details class="group">
        <summary class="cursor-pointer list-none text-sm font-medium text-slate-600 hover:text-slate-900">
          <Icon icon="mdi:chevron-right" class="-ml-1 inline h-4 w-4 transition-transform group-open:rotate-90" />
          {{ t("site.pages.seo") }}
        </summary>
        <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-3">
          <div v-if="!singleton">
            <label class="form-label" for="type-route-index-title">{{ t("site.pages.listTitle") }}</label>
            <input id="type-route-index-title" :value="route.index_title" type="text" class="form-input w-full rounded-lg border-slate-300 text-sm" :placeholder="label" @input="update({ index_title: $event.target.value })" />
          </div>
          <div>
            <label class="form-label" for="type-route-description">{{ t("site.pages.descriptionField") }}</label>
            <select id="type-route-description" :value="route.seo_description_field" class="form-select w-full rounded-lg border-slate-300 text-sm" @change="update({ seo_description_field: $event.target.value })">
              <option value="">{{ t("site.pages.automatic") }}</option>
              <option v-for="field in fieldsOf(['text', 'textarea', 'markdown', 'html'])" :key="field.key" :value="field.key">{{ field.label || field.key }}</option>
            </select>
          </div>
          <div>
            <label class="form-label" for="type-route-image">{{ t("site.pages.imageField") }}</label>
            <select id="type-route-image" :value="route.seo_image_field" class="form-select w-full rounded-lg border-slate-300 text-sm" @change="update({ seo_image_field: $event.target.value })">
              <option value="">{{ t("site.pages.automatic") }}</option>
              <option v-for="field in fieldsOf(['media'])" :key="field.key" :value="field.key">{{ field.label || field.key }}</option>
            </select>
          </div>
          <label class="flex items-center gap-3">
            <ToggleSwitch :model-value="route.sitemap !== false" @update:model-value="update({ sitemap: $event })" />
            <span class="text-sm text-slate-700">{{ t("site.pages.inSitemap") }}</span>
          </label>
        </div>
      </details>

      <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
        <span class="mr-1 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t("site.pages.layouts") }}</span>
        <template v-if="!isNew">
          <router-link
            v-for="variant in variants"
            :key="variant"
            :to="`/website/builder/layout/${name}/${variant}`"
            class="btn-secondary btn-sm"
          >
            <Icon :icon="VARIANT_ICONS[variant]" class="h-4 w-4" />
            {{ t(`site.variant.${variant}`) }}
          </router-link>
        </template>
        <span v-else class="text-xs text-slate-500">{{ t("site.typeCard.layoutsAfterSave") }}</span>
      </div>
    </template>
  </section>
</template>

<script setup>
import { computed } from "vue";
import { Icon } from "@iconify/vue";
import ToggleSwitch from "../ToggleSwitch.vue";
import { useI18n } from "../../i18n/index.js";

const VARIANT_ICONS = { detail: "mdi:file-document-outline", card: "mdi:card-text-outline", index: "mdi:view-grid-outline" };

const props = defineProps({
  /** Website settings of this type, or null when they cannot be edited. */
  modelValue: { type: Object, default: null },
  /** Website status: { enabled, can_manage, types }. */
  status: { type: Object, default: null },
  name: { type: String, default: "" },
  label: { type: String, default: "" },
  singleton: { type: Boolean, default: false },
  visibility: { type: String, default: "public" },
  /** [{ key, type, label }] of the type's own fields. */
  fields: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
  isNew: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "touched"]);
const { t } = useI18n();

const route = computed(() => props.modelValue ?? props.status?.types?.[props.name] ?? {});
const editable = computed(() => !!props.modelValue && !!props.status?.can_manage && props.visibility !== "private");
const variants = computed(() => (props.singleton ? ["detail"] : ["detail", "card", "index"]));

// The summary describes what is live, not unsaved edits.
const saved = computed(() => props.status?.types?.[props.name] ?? null);
const summary = computed(() => {
  if (props.visibility === "private") return t("site.typeCard.private");
  if (saved.value?.enabled) {
    const where = props.singleton || !saved.value.index_path ? saved.value.pattern : `${saved.value.index_path} · ${saved.value.pattern}`;
    return `${t("site.typeCard.published")} ${where}`;
  }
  return t("site.typeCard.notPublished");
});

function update(patch) {
  emit("update:modelValue", { ...props.modelValue, ...patch });
  if ("pattern" in patch || "index_path" in patch) emit("touched");
}

function fieldsOf(types) {
  return props.fields.filter((field) => field.key && types.includes(field.type));
}

</script>
