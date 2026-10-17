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
      class="card flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-4"
      :class="{ 'opacity-70': type.visibility === 'private' }"
    >
      <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-theme-50 text-theme-600">
        <Icon :icon="type.icon || 'mdi:file-document-outline'" class="h-5 w-5" />
      </div>

      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <h2 class="font-semibold text-slate-900">{{ type.label }}</h2>
          <span class="badge bg-slate-100 text-slate-600">{{ type.singleton ? t("contentTypes.single") : t("contentTypes.collection") }}</span>
          <span v-if="type.visibility === 'private'" class="badge bg-purple-100 text-purple-700">{{ t("contentTypeEdit.private") }}</span>
          <span v-if="settings.homepage.type === type.name" class="badge bg-theme-100 text-theme-700">{{ t("site.pages.homepage") }}</span>
          <span
            v-if="type.visibility !== 'private'"
            class="badge"
            :class="routes[type.name]?.enabled ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'"
          >
            {{ routes[type.name]?.enabled ? t("site.pages.onWebsite") : t("site.pages.notOnWebsite") }}
          </span>
        </div>
        <p class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500">
          <template v-if="routes[type.name]?.enabled">
            <code class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-slate-700">{{ routes[type.name].pattern }}</code>
            <code v-if="!type.singleton && routes[type.name].index_path" class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-slate-700">{{ routes[type.name].index_path }}</code>
          </template>
          <span v-else-if="type.visibility === 'private'">{{ t("site.pages.privateHint") }}</span>
          <span v-else class="font-mono">{{ type.name }}</span>
        </p>
      </div>

      <div v-if="routes[type.name]?.enabled" class="flex flex-wrap items-center gap-1.5">
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

      <router-link
        v-if="type.visibility !== 'private'"
        :to="`/content-types/${type.name}/edit#website`"
        class="inline-flex items-center gap-1 text-sm font-medium text-theme-700 hover:text-theme-800"
      >
        <Icon icon="mdi:cog-outline" class="h-4 w-4" />
        {{ t("site.pages.settings") }}
      </router-link>
    </section>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { Icon } from "@iconify/vue";
import { useSiteStore } from "../../stores/site.js";
import { useI18n } from "../../i18n/index.js";

const site = useSiteStore();
const { t } = useI18n();
const settings = computed(() => site.draft.settings);
// Saved routes: they are edited in each content type's editor.
const routes = computed(() => site.data.settings.routes);

function variantsFor(type) {
  return type.singleton ? ["detail"] : ["detail", "card", "index"];
}

function variantIcon(variant) {
  return { detail: "mdi:file-document-outline", card: "mdi:card-text-outline", index: "mdi:view-grid-outline" }[variant];
}
</script>
