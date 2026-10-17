<template>
  <div class="space-y-4">
    <p class="text-sm text-slate-500">{{ t("site.pages.intro") }}</p>

    <div v-if="site.data.types.length === 0" class="card p-6 text-sm text-slate-500">
      {{ t("site.pages.noTypes") }}
      <router-link to="/content-types/new" class="ml-1 font-medium text-theme-700 hover:underline">{{ t("site.pages.createType") }}</router-link>
    </div>

    <section
      v-for="type in site.data.types"
      :key="type.name"
      class="card overflow-hidden"
      :class="{ 'opacity-70': type.visibility === 'private' }"
    >
      <div class="flex flex-wrap items-center gap-3 px-5 py-4">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-theme-50 text-theme-600">
          <Icon :icon="type.icon || 'mdi:file-document-outline'" class="h-5 w-5" />
        </div>
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2">
            <h2 class="font-semibold text-slate-900">{{ type.label }}</h2>
            <span class="badge bg-slate-100 text-slate-600">{{ type.singleton ? t("contentTypes.single") : t("contentTypes.collection") }}</span>
            <span v-if="type.visibility === 'private'" class="badge bg-purple-100 text-purple-700">{{ t("contentTypeEdit.private") }}</span>
            <span v-if="settings.homepage.type === type.name" class="badge bg-theme-100 text-theme-700">{{ t("site.pages.homepage") }}</span>
          </div>
          <p class="mt-0.5 font-mono text-xs text-slate-500">{{ type.name }}</p>
        </div>
        <span v-if="type.visibility === 'private'" class="text-xs text-slate-500">{{ t("site.pages.privateHint") }}</span>
        <label v-else class="flex items-center gap-2 text-sm font-medium text-slate-700">
          {{ t("site.pages.onWebsite") }}
          <ToggleSwitch v-model="routes[type.name].enabled" />
        </label>
      </div>

      <div v-if="routes[type.name].enabled && type.visibility !== 'private'" class="space-y-4 border-t border-slate-100 bg-slate-50/50 px-5 py-4">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label class="form-label" :for="`route-${type.name}`">{{ type.singleton ? t("site.pages.pageUrl") : t("site.pages.entryUrl") }}</label>
            <input
              :id="`route-${type.name}`"
              v-model="routes[type.name].pattern"
              type="text"
              class="form-input w-full rounded-lg font-mono text-sm"
              :class="errorFor(type.name, 'pattern') ? 'border-red-400' : 'border-slate-300'"
            />
            <p v-if="errorFor(type.name, 'pattern')" class="mt-1 text-xs text-red-600">{{ errorFor(type.name, "pattern") }}</p>
            <p v-else class="mt-1 text-xs text-slate-500">{{ type.singleton ? t("site.pages.pageUrlHint") : t("site.pages.entryUrlHint") }}</p>
          </div>

          <div v-if="!type.singleton">
            <label class="form-label" :for="`index-${type.name}`">{{ t("site.pages.listUrl") }}</label>
            <input
              :id="`index-${type.name}`"
              v-model="routes[type.name].index_path"
              type="text"
              class="form-input w-full rounded-lg font-mono text-sm"
              :class="errorFor(type.name, 'index_path') ? 'border-red-400' : 'border-slate-300'"
              :placeholder="t('site.pages.listUrlNone')"
            />
            <p v-if="errorFor(type.name, 'index_path')" class="mt-1 text-xs text-red-600">{{ errorFor(type.name, "index_path") }}</p>
            <p v-else class="mt-1 text-xs text-slate-500">{{ t("site.pages.listUrlHint") }}</p>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <span class="mr-1 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t("site.pages.layouts") }}</span>
          <router-link
            v-for="variant in variantsFor(type)"
            :key="variant"
            :to="`/website/builder/layout/${type.name}/${variant}`"
            class="btn-secondary btn-sm"
          >
            <Icon :icon="variantIcon(variant)" class="h-4 w-4" />
            {{ t(`site.variant.${variant}`) }}
            <span
              class="h-1.5 w-1.5 rounded-full"
              :class="site.data.layouts[type.name]?.[variant] ? 'bg-theme-500' : 'bg-slate-300'"
              :title="site.data.layouts[type.name]?.[variant] ? t('site.pages.customized') : t('site.pages.default')"
            ></span>
          </router-link>
        </div>

        <details class="group">
          <summary class="cursor-pointer list-none text-sm font-medium text-slate-600 hover:text-slate-900">
            <Icon icon="mdi:chevron-right" class="-ml-1 inline h-4 w-4 transition-transform group-open:rotate-90" />
            {{ t("site.pages.seo") }}
          </summary>
          <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div v-if="!type.singleton">
              <label class="form-label" :for="`index-title-${type.name}`">{{ t("site.pages.listTitle") }}</label>
              <input :id="`index-title-${type.name}`" v-model="routes[type.name].index_title" type="text" class="form-input w-full rounded-lg border-slate-300 text-sm" :placeholder="type.label" />
            </div>
            <div>
              <label class="form-label" :for="`desc-${type.name}`">{{ t("site.pages.descriptionField") }}</label>
              <select :id="`desc-${type.name}`" v-model="routes[type.name].seo_description_field" class="form-select w-full rounded-lg border-slate-300 text-sm">
                <option value="">{{ t("site.pages.automatic") }}</option>
                <option v-for="field in fieldsOf(type, ['text', 'textarea', 'markdown', 'html'])" :key="field.key" :value="field.key">{{ field.label }}</option>
              </select>
            </div>
            <div>
              <label class="form-label" :for="`image-${type.name}`">{{ t("site.pages.imageField") }}</label>
              <select :id="`image-${type.name}`" v-model="routes[type.name].seo_image_field" class="form-select w-full rounded-lg border-slate-300 text-sm">
                <option value="">{{ t("site.pages.automatic") }}</option>
                <option v-for="field in fieldsOf(type, ['media'])" :key="field.key" :value="field.key">{{ field.label }}</option>
              </select>
            </div>
            <label class="flex items-center gap-3">
              <ToggleSwitch v-model="routes[type.name].sitemap" />
              <span class="text-sm text-slate-700">{{ t("site.pages.inSitemap") }}</span>
            </label>
          </div>
        </details>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { Icon } from "@iconify/vue";
import ToggleSwitch from "../ToggleSwitch.vue";
import { useSiteStore } from "../../stores/site.js";
import { useI18n } from "../../i18n/index.js";

const site = useSiteStore();
const { t } = useI18n();
const settings = computed(() => site.draft.settings);
const routes = computed(() => settings.value.routes);

function variantsFor(type) {
  return type.singleton ? ["detail"] : ["detail", "card", "index"];
}

function variantIcon(variant) {
  return { detail: "mdi:file-document-outline", card: "mdi:card-text-outline", index: "mdi:view-grid-outline" }[variant];
}

function fieldsOf(type, kinds) {
  return Object.entries(type.fields ?? {})
    .filter(([key, config]) => kinds.includes(config?.type) && key !== "slug")
    .map(([key, config]) => ({ key, label: config.label || key }));
}

function errorFor(name, key) {
  return site.fieldErrors[`routes.${name}.${key}`] ?? "";
}
</script>
