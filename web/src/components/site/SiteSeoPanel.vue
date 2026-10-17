<template>
  <div class="space-y-6">
    <section class="card space-y-4 p-6">
      <h2 class="text-sm font-semibold text-slate-700">{{ t("site.overview.seo") }}</h2>
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
import MediaSelect from "./MediaSelect.vue";
import ToggleSwitch from "../ToggleSwitch.vue";
import { useSiteStore } from "../../stores/site.js";
import { useI18n } from "../../i18n/index.js";

/** Site-wide defaults for search engines and link previews. */
const site = useSiteStore();
const { t } = useI18n();
const settings = computed(() => site.draft.settings);
</script>
