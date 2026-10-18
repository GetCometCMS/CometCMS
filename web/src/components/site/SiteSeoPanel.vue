<template>
  <div class="space-y-6">
    <!-- Identity -->
    <section class="card space-y-4 p-6">
      <h2 class="card-title">{{ t("site.overview.identity") }}</h2>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
          <label class="form-label" for="site-title">{{ t("site.overview.siteTitle") }}</label>
          <input id="site-title" v-model="settings.title" type="text" class="form-input w-full rounded-lg border-slate-300 text-sm" :placeholder="site.data.workspace_label" />
        </div>
        <div>
          <label class="form-label" for="site-tagline">{{ t("site.overview.tagline") }}</label>
          <input id="site-tagline" v-model="settings.tagline" type="text" class="form-input w-full rounded-lg border-slate-300 text-sm" :placeholder="t('site.overview.taglinePlaceholder')" />
        </div>
        <div>
          <span class="form-label">{{ t("site.overview.logo") }}</span>
          <MediaSelect v-model="settings.logo" name="site-logo" />
        </div>
        <div>
          <span class="form-label">{{ t("site.overview.favicon") }}</span>
          <MediaSelect v-model="settings.favicon" name="site-favicon" />
          <p class="mt-1 text-xs text-slate-500">{{ t("site.overview.faviconHint") }}</p>
        </div>
      </div>
    </section>

    <section class="card space-y-4 p-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="card-title">{{ t("site.overview.seo") }}</h2>
        <a
          :href="site.data.site_url + '/sitemap.xml'"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-1 text-sm text-theme-600 hover:underline"
        >
          sitemap.xml
          <Icon icon="mdi:open-in-new" class="h-4 w-4" />
        </a>
      </div>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
          <label class="form-label" for="site-title-template">{{ t("site.overview.titleTemplate") }}</label>
          <input id="site-title-template" v-model="settings.title_template" type="text" class="form-input w-full rounded-lg border-slate-300 font-mono text-sm" />
          <p class="mt-1 text-xs text-slate-500">{{ t("site.overview.titleTemplateHint") }}</p>
        </div>
        <div>
          <span class="form-label">{{ t("site.overview.shareImage") }}</span>
          <MediaSelect v-model="settings.og_image" name="site-og-image" />
        </div>
        <div class="md:col-span-2">
          <label class="form-label" for="site-description">{{ t("site.overview.metaDescription") }}</label>
          <textarea id="site-description" v-model="settings.description" rows="2" maxlength="300" class="form-textarea w-full rounded-lg border-slate-300 text-sm"></textarea>
        </div>
        <label class="flex items-start gap-3 md:col-span-2">
          <ToggleSwitch v-model="settings.noindex" />
          <span>
            <span class="block text-sm font-medium text-slate-700">{{ t("site.overview.noindex") }}</span>
            <span class="block text-xs text-slate-500">{{ t("site.overview.noindexHint") }}</span>
          </span>
        </label>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { Icon } from "@iconify/vue";
import MediaSelect from "./MediaSelect.vue";
import ToggleSwitch from "../ToggleSwitch.vue";
import { useSiteStore } from "../../stores/site.js";
import { useI18n } from "../../i18n/index.js";

/** Site identity and site-wide defaults for search engines and link previews. */
const site = useSiteStore();
const { t } = useI18n();
const settings = computed(() => site.draft.settings);
</script>
