<template>
  <div class="space-y-6">
    <!-- Identity -->
    <section class="card space-y-4 p-6">
      <h2 class="card-title">{{ t("site.overview.identity") }}</h2>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
          <label class="form-label" for="site-title">{{ t("site.overview.siteTitle") }}</label>
          <LocalizedInput id="site-title" v-model="settings.title" class="w-full" :placeholder="site.data.workspace_label"  />
        </div>
        <div>
          <label class="form-label" for="site-tagline">{{ t("site.overview.tagline") }}</label>
          <LocalizedInput id="site-tagline" v-model="settings.tagline" class="w-full" :placeholder="t('site.overview.taglinePlaceholder')"  />
        </div>
        <div>
          <span class="form-label">{{ t("site.overview.logo") }}</span>
          <LocalizedInput v-model="settings.logo"><template #default="{ value, update }"><MediaSelect :model-value="value" name="site-logo" @update:model-value="update" /></template></LocalizedInput>
        </div>
        <div>
          <span class="form-label">{{ t("site.overview.favicon") }}</span>
          <MediaSelect v-model="settings.favicon" name="site-favicon" />
          <p class="mt-1 text-xs text-slate-500">{{ t("site.overview.faviconHint") }}</p>
        </div>
      </div>
    </section>

    <!-- Homepage -->
    <section id="homepage" class="card scroll-mt-24 space-y-4 p-6">
      <div>
        <h2 class="card-title">{{ t("site.overview.homepage") }}</h2>
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
        <div v-if="homeType">
          <label class="form-label" for="site-home-entry">{{ t("site.overview.homepageEntry") }}</label>
          <select id="site-home-entry" v-model="settings.homepage.entry" class="form-select w-full rounded-lg border-slate-300 text-sm" :disabled="loadingEntries">
            <option v-for="option in homeEntryOptions" :key="option.value" :value="option.value" :disabled="option.disabled">{{ option.label }}</option>
          </select>
        </div>
      </div>
    </section>

    <!-- Maintenance -->
    <section id="maintenance" class="card scroll-mt-24 space-y-4 p-6">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="card-title">{{ t("site.overview.maintenance") }}</h2>
          <p class="mt-1 text-sm text-slate-500">{{ t("site.overview.maintenanceHint") }}</p>
        </div>
        <router-link to="/page-builder/builder/region/maintenance" class="text-sm text-theme-600 hover:underline">{{ t("site.overview.maintenanceEdit") }}</router-link>
      </div>
      <label class="flex items-start gap-3">
        <ToggleSwitch v-model="settings.maintenance" />
        <span>
          <span class="block text-sm font-medium text-slate-700">{{ t("site.overview.maintenanceToggle") }}</span>
          <span class="block text-xs text-slate-500">{{ t("site.overview.maintenanceToggleHint") }}</span>
        </span>
      </label>
    </section>

    <!-- Languages -->
    <section class="card space-y-4 p-6">
      <div>
        <h2 class="card-title">{{ t("site.overview.languages") }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ t("site.overview.languagesHint") }}</p>
        <p class="mt-1 text-sm text-slate-500">{{ t("site.overview.deactivateLanguageHint") }}</p>
      </div>
      <div v-if="auth.can('workspaces.manage')" class="grid gap-4 sm:grid-cols-3">
        <div class="min-w-0 sm:col-span-2">
          <div class="form-label">{{ t("localization.languages") }}</div>
          <SearchableSelect
            v-model="languages.locales"
            :options="LOCALE_OPTIONS"
            :aria-label="t('localization.languages')"
            :class="site.fieldErrors.locales ? 'rounded-lg ring-1 ring-red-500' : ''"
            multiple
          />
          <p v-if="site.fieldErrors.locales" role="alert" class="mt-1 text-sm text-red-600">{{ site.fieldErrors.locales }}</p>
        </div>
        <div class="min-w-0">
          <div class="form-label">{{ t("localization.default") }}</div>
          <SearchableSelect
            v-model="languages.default_locale"
            :options="defaultLocaleOptions"
            :aria-label="t('localization.default')"
          />
        </div>
      </div>
      <template v-else>
        <p class="text-sm text-slate-700">{{ settings.locales.map(localeLabel).join(", ") }}</p>
        <p class="text-xs text-slate-500">{{ t("site.overview.defaultLanguage") }}: {{ localeLabel(settings.default_locale) }}</p>
      </template>
    </section>

    <!-- Domains -->
    <section class="card space-y-4 p-6">
      <div>
        <h2 class="card-title">{{ t("site.overview.domains") }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ t("site.overview.domainsHint") }}</p>
      </div>
      <textarea
        v-model="domainsText"
        rows="2"
        class="form-textarea w-full rounded-lg border-slate-300 font-mono text-sm"
        placeholder="example.com&#10;www.example.com"
      ></textarea>
    </section>

  </div>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import LocalizedInput from "../LocalizedInput.vue";
import MediaSelect from "./MediaSelect.vue";
import SearchableSelect from "../SearchableSelect.vue";
import ToggleSwitch from "../ToggleSwitch.vue";
import { useAuthStore } from "../../stores/auth.js";
import { api } from "../../api/index.js";
import { LOCALE_OPTIONS, localeLabel } from "../../composables/localeOptions.js";
import { useSiteStore } from "../../stores/site.js";
import { useI18n } from "../../i18n/index.js";
import { entryIdentifier, hasListPage } from "../../composables/siteLinks.js";

const site = useSiteStore();
const { t } = useI18n();
const auth = useAuthStore();
const settings = computed(() => site.draft.settings);
// Languages belong to the workspace; they are edited here and saved with the settings.
const languages = computed(() => site.draft.languages);
const defaultLocaleOptions = computed(() => LOCALE_OPTIONS.filter((option) => languages.value.locales.includes(option.value)));
watch(
  () => languages.value.locales,
  (locales) => {
    if (locales.length && !locales.includes(languages.value.default_locale)) languages.value.default_locale = locales[0];
  },
  { deep: true },
);
const homeEntries = ref([]);
const loadingEntries = ref(false);

const publicTypes = computed(() => site.data.types.filter((type) => type.visibility !== "private"));
const homeType = computed(() => publicTypes.value.find((type) => type.name === settings.value.homepage.type) ?? null);
// The list of all entries is offered only when the type has its list page turned on.
const homeEntryOptions = computed(() => [
  hasListPage(settings.value, homeType.value?.name)
    ? { value: "", label: t("site.overview.homepageList") }
    : { value: "", label: t("site.overview.homepageChooseEntry"), disabled: true },
  ...homeEntries.value,
]);
const domainsText = computed({
  get: () => settings.value.domains.join("\n"),
  set: (value) => {
    settings.value.domains = value.split(/[\s,]+/).map((domain) => domain.trim()).filter(Boolean);
  },
});

watch(
  homeType,
  async (type) => {
    homeEntries.value = [];
    if (!type) return;
    loadingEntries.value = true;
    try {
      const res = await api.content.list(type.name, { limit: 200, sort: "-updated_at", "filter[status]": "published" });
      homeEntries.value = (res.data ?? []).map((entry) => ({ value: entryIdentifier(entry), label: entry.title || entry.slug }));
    } catch {
      homeEntries.value = [];
    } finally {
      loadingEntries.value = false;
    }
  },
  { immediate: true },
);
</script>
