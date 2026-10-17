<template>
  <div class="space-y-6">

    <!-- Identity -->
    <section class="card space-y-4 p-6">
      <h2 class="text-sm font-semibold text-slate-700">{{ t("site.overview.identity") }}</h2>
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

    <!-- Homepage -->
    <section class="card space-y-4 p-6">
      <div>
        <h2 class="text-sm font-semibold text-slate-700">{{ t("site.overview.homepage") }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ t("site.overview.homepageHint") }}</p>
      </div>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
          <label class="form-label" for="site-home-type">{{ t("site.overview.homepageType") }}</label>
          <select id="site-home-type" v-model="settings.homepage.type" class="form-select w-full rounded-lg border-slate-300 text-sm" @change="settings.homepage.entry = ''">
            <option value="">{{ t("site.overview.homepageNone") }}</option>
            <option v-for="type in publicTypes" :key="type.name" :value="type.name">{{ type.label }}</option>
          </select>
        </div>
        <div v-if="homeType && !homeType.singleton">
          <label class="form-label" for="site-home-entry">{{ t("site.overview.homepageEntry") }}</label>
          <select id="site-home-entry" v-model="settings.homepage.entry" class="form-select w-full rounded-lg border-slate-300 text-sm" :disabled="loadingEntries">
            <option v-for="option in homeEntryOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
        </div>
      </div>
    </section>

    <!-- Languages -->
    <section class="card space-y-4 p-6">
      <div>
        <h2 class="text-sm font-semibold text-slate-700">{{ t("site.overview.languages") }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ t("site.overview.languagesHint") }}</p>
      </div>
      <p v-if="localeOptions.length === 0" class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-500">
        {{ t("site.overview.noLocales") }}
      </p>
      <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
          <label class="form-label">{{ t("site.overview.websiteLanguages") }}</label>
          <SearchableSelect v-model="settings.locales" :options="localeOptions" multiple />
        </div>
        <div>
          <label class="form-label" for="site-default-locale">{{ t("site.overview.defaultLanguage") }}</label>
          <select id="site-default-locale" v-model="settings.default_locale" class="form-select w-full rounded-lg border-slate-300 text-sm" :disabled="settings.locales.length === 0">
            <option v-for="locale in settings.locales" :key="locale" :value="locale">{{ localeLabel(locale) }}</option>
          </select>
          <p class="mt-1 text-xs text-slate-500">{{ t("site.overview.defaultLanguageHint") }}</p>
        </div>
      </div>
    </section>

    <!-- SEO -->
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

    <!-- Domains -->
    <section class="card space-y-4 p-6">
      <div>
        <h2 class="text-sm font-semibold text-slate-700">{{ t("site.overview.domains") }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ t("site.overview.domainsHint") }}</p>
      </div>
      <textarea
        v-model="domainsText"
        rows="2"
        class="form-textarea w-full rounded-lg border-slate-300 font-mono text-sm"
        placeholder="example.com&#10;www.example.com"
      ></textarea>
    </section>

    <!-- 404 -->
    <section class="card space-y-4 p-6">
      <h2 class="text-sm font-semibold text-slate-700">{{ t("site.overview.notFound") }}</h2>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
          <label class="form-label" for="site-404-title">{{ t("site.overview.notFoundTitle") }}</label>
          <input id="site-404-title" v-model="settings.not_found_title" type="text" class="form-input w-full rounded-lg border-slate-300 text-sm" />
        </div>
        <div>
          <label class="form-label" for="site-404-message">{{ t("site.overview.notFoundMessage") }}</label>
          <input id="site-404-message" v-model="settings.not_found_message" type="text" class="form-input w-full rounded-lg border-slate-300 text-sm" />
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import MediaSelect from "./MediaSelect.vue";
import SearchableSelect from "../SearchableSelect.vue";
import ToggleSwitch from "../ToggleSwitch.vue";
import { api } from "../../api/index.js";
import { localeLabel } from "../../composables/localeOptions.js";
import { useSiteStore } from "../../stores/site.js";
import { useI18n } from "../../i18n/index.js";

const site = useSiteStore();
const { t } = useI18n();
const settings = computed(() => site.draft.settings);
const homeEntries = ref([]);
const loadingEntries = ref(false);

const publicTypes = computed(() => site.data.types.filter((type) => type.visibility !== "private"));
const pageTypes = computed(() => publicTypes.value.filter((type) => settings.value.routes[type.name]?.enabled));
const homeType = computed(() => publicTypes.value.find((type) => type.name === settings.value.homepage.type) ?? null);
const homeEntryOptions = computed(() => [
  { value: "", label: t("site.overview.homepageList") },
  ...homeEntries.value,
]);
const localeOptions = computed(() =>
  site.data.available_locales.map((locale) => ({ value: locale, label: localeLabel(locale) })),
);

const domainsText = computed({
  get: () => settings.value.domains.join("\n"),
  set: (value) => {
    settings.value.domains = value.split(/[\s,]+/).map((domain) => domain.trim()).filter(Boolean);
  },
});

watch(
  () => settings.value.locales,
  (locales) => {
    if (locales.length === 0) settings.value.default_locale = "";
    else if (!locales.includes(settings.value.default_locale)) settings.value.default_locale = locales[0];
  },
  { deep: true },
);

watch(
  homeType,
  async (type) => {
    homeEntries.value = [];
    if (!type || type.singleton) return;
    loadingEntries.value = true;
    try {
      const res = await api.content.list(type.name, { limit: 200, sort: "-updated_at" });
      homeEntries.value = (res.data ?? []).map((entry) => ({ value: entry.slug ?? entry.id, label: entry.title || entry.slug }));
    } catch {
      homeEntries.value = [];
    } finally {
      loadingEntries.value = false;
    }
  },
  { immediate: true },
);
</script>
