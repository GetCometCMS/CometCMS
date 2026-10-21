<template>
  <section id="website" class="card scroll-mt-24 space-y-4 p-6">
    <div class="flex flex-wrap items-start gap-3">
      <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-theme-50 text-theme-600">
        <Icon icon="mdi:web" class="h-5 w-5" />
      </div>
      <div class="min-w-0 flex-1">
        <h2 class="card-title">{{ t("site.typeCard.title") }}</h2>
        <p class="mt-0.5 text-sm text-slate-500">{{ summary }}</p>
      </div>
    </div>

    <!-- The first frontend pages take a headless workspace online. -->
    <div
      v-if="editable && route.enabled && !status.enabled"
      class="flex flex-wrap items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
    >
      <Icon icon="mdi:information-outline" class="h-4 w-4 shrink-0" />
      <span class="flex-1">{{ t("site.typeCard.siteOff") }}</span>
      <router-link to="/website/general" class="font-medium underline">{{ t("site.typeCard.openWebsite") }}</router-link>
    </div>

    <template v-if="editable && route.enabled">
      <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <div>
          <LocalizedInput
            id="type-route-pattern"
            :label="t('site.pages.entryUrl')"
            :model-value="route.pattern"
            class="w-full"
            :invalid="!!errors.pattern"
            @update:model-value="(value) => update({ pattern: value })"
          />
          <p v-if="errors.pattern" class="mt-1 text-xs text-red-600">{{ errors.pattern }}</p>
          <p v-else class="mt-1 text-xs text-slate-500">{{ t("site.pages.entryUrlHint") }}</p>
        </div>

        <div>
          <LocalizedInput
            id="type-route-index"
            :label="t('site.pages.listUrl')"
            :model-value="route.index_path"
            class="w-full"
            :invalid="!!errors.index_path"
            :placeholder="t('site.pages.listUrlNone')"
            @update:model-value="(value) => update({ index_path: value })"
          />
          <p v-if="errors.index_path" class="mt-1 text-xs text-red-600">{{ errors.index_path }}</p>
          <p v-else class="mt-1 text-xs text-slate-500">{{ t("site.pages.listUrlHint") }}</p>
        </div>
      </div>

      <details class="group border-t border-slate-100 pt-4">
        <summary class="cursor-pointer list-none text-sm font-medium text-slate-600 hover:text-slate-900">
          <Icon icon="mdi:chevron-right" class="-ml-1 inline h-4 w-4 transition-transform group-open:rotate-90" />
          {{ t("site.pages.seo") }}
        </summary>
        <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-3">
          <div>
            <label class="form-label" for="type-route-index-title">{{ t("site.pages.listTitle") }}</label>
            <LocalizedInput id="type-route-index-title" :model-value="route.index_title" class="w-full" :placeholder="label" @update:model-value="(value) => update({ index_title: value })" />
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
    </template>
  </section>
</template>

<script setup>
import LocalizedInput from "../LocalizedInput.vue";
import { resolveLocalized } from "../../composables/localizedValues.js";
import { useLanguages } from "../../composables/useLanguages.js";
import { computed } from "vue";
import { Icon } from "@iconify/vue";
import ToggleSwitch from "../ToggleSwitch.vue";
import { useI18n } from "../../i18n/index.js";

const props = defineProps({
  /** Website settings of this type, or null when they cannot be edited. */
  modelValue: { type: Object, default: null },
  /** Website status: { enabled (live), can_manage, types }. */
  status: { type: Object, default: null },
  name: { type: String, default: "" },
  label: { type: String, default: "" },
  visibility: { type: String, default: "public" },
  /** [{ key, type, label }] of the type's own fields. */
  fields: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue", "touched"]);
const { t } = useI18n();

const route = computed(() => props.modelValue ?? props.status?.types?.[props.name] ?? {});
const editable = computed(() => !!props.modelValue && !!props.status?.can_manage && props.visibility !== "private");

// Follow the current route, including changes not yet saved.
const languages = useLanguages();
const pathText = (value) => resolveLocalized(value, languages.defaultLocale.value, languages.defaultLocale.value);
const summary = computed(() => {
  if (props.visibility === "private") return t("site.typeCard.private");
  if (route.value.enabled || route.value.pages_enabled) {
    const where = !pathText(route.value.index_path) ? pathText(route.value.pattern) : `${pathText(route.value.index_path)} · ${pathText(route.value.pattern)}`;
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
