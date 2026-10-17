<template>
  <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">
    <div class="space-y-6">
      <!-- Presets -->
      <section class="card space-y-3 p-5">
        <h2 class="text-sm font-semibold text-slate-700">{{ t("site.theme.presets") }}</h2>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
          <button
            v-for="preset in PRESETS"
            :key="preset.key"
            type="button"
            class="group rounded-lg border border-slate-200 p-2 text-left transition hover:border-theme-400 hover:shadow-sm"
            @click="applyPreset(preset)"
          >
            <span class="flex h-8 overflow-hidden rounded-md ring-1 ring-black/5">
              <span
                v-for="color in presetSwatches(preset)"
                :key="color"
                class="flex-1"
                :style="{ background: color }"
              ></span>
            </span>
            <span class="mt-1.5 block text-xs font-medium text-slate-700">{{ t(`site.theme.preset.${preset.key}`) }}</span>
          </button>
        </div>
      </section>

      <!-- Colors -->
      <section class="card space-y-4 p-5">
        <div class="flex items-center justify-between gap-3">
          <h2 class="text-sm font-semibold text-slate-700">{{ t("site.theme.colors") }}</h2>
          <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5">
            <button
              v-for="mode in ['light', 'dark', 'auto']"
              :key="mode"
              type="button"
              class="rounded-md px-2.5 py-1 text-xs font-medium"
              :class="theme.mode === mode ? 'bg-theme-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
              @click="theme.mode = mode"
            >
              {{ t(`site.theme.mode.${mode}`) }}
            </button>
          </div>
        </div>

        <div v-for="palette in palettes" :key="palette.key">
          <p v-if="palettes.length > 1" class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
            {{ t(`site.theme.palette.${palette.key}`) }}
          </p>
          <div class="grid grid-cols-2 gap-3">
            <label v-for="key in COLOR_KEYS" :key="key" class="flex items-center gap-2">
              <input
                type="color"
                class="h-9 w-9 shrink-0 cursor-pointer rounded-md border border-slate-300 bg-white p-0.5"
                :value="theme[palette.key][key]"
                @input="theme[palette.key][key] = $event.target.value"
              />
              <span class="min-w-0">
                <span class="block text-xs font-medium text-slate-700">{{ t(`site.theme.color.${key}`) }}</span>
                <input
                  type="text"
                  class="w-full border-0 bg-transparent p-0 font-mono text-xs text-slate-500 focus:ring-0"
                  :value="theme[palette.key][key]"
                  maxlength="7"
                  @change="setHex(palette.key, key, $event.target.value)"
                />
              </span>
            </label>
          </div>
        </div>
      </section>

      <!-- Typography -->
      <section class="card space-y-4 p-5">
        <h2 class="text-sm font-semibold text-slate-700">{{ t("site.theme.typography") }}</h2>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="form-label" for="theme-font-heading">{{ t("site.theme.headingFont") }}</label>
            <select id="theme-font-heading" v-model="theme.fonts.heading" class="form-select w-full rounded-lg border-slate-300 text-sm">
              <option v-for="font in site.data.fonts" :key="font" :value="font">{{ t(`site.font.${font}`) }}</option>
            </select>
          </div>
          <div>
            <label class="form-label" for="theme-font-body">{{ t("site.theme.bodyFont") }}</label>
            <select id="theme-font-body" v-model="theme.fonts.body" class="form-select w-full rounded-lg border-slate-300 text-sm">
              <option v-for="font in site.data.fonts" :key="font" :value="font">{{ t(`site.font.${font}`) }}</option>
            </select>
          </div>
          <div>
            <label class="form-label" for="theme-scale">{{ t("site.theme.typeScale") }}</label>
            <select id="theme-scale" v-model="theme.type_scale" class="form-select w-full rounded-lg border-slate-300 text-sm">
              <option value="1.125">{{ t("site.theme.scale.subtle") }}</option>
              <option value="1.2">{{ t("site.theme.scale.balanced") }}</option>
              <option value="1.25">{{ t("site.theme.scale.classic") }}</option>
              <option value="1.333">{{ t("site.theme.scale.bold") }}</option>
            </select>
          </div>
          <div>
            <label class="form-label" for="theme-heading-weight">{{ t("site.theme.headingWeight") }}</label>
            <select id="theme-heading-weight" v-model.number="theme.heading_weight" class="form-select w-full rounded-lg border-slate-300 text-sm">
              <option v-for="weight in [400, 500, 600, 700, 800, 900]" :key="weight" :value="weight">{{ weight }}</option>
            </select>
          </div>
        </div>
        <RangeRow v-model="theme.base_size" :label="t('site.theme.baseSize')" :min="12" :max="24" :step="1" unit="px" />
        <RangeRow v-model="theme.line_height" :label="t('site.theme.lineHeight')" :min="1.1" :max="2.2" :step="0.05" />
        <label class="flex items-center gap-3">
          <ToggleSwitch v-model="theme.link_underline" />
          <span class="text-sm text-slate-700">{{ t("site.theme.linkUnderline") }}</span>
        </label>
      </section>

      <!-- Layout -->
      <section class="card space-y-4 p-5">
        <h2 class="text-sm font-semibold text-slate-700">{{ t("site.theme.layout") }}</h2>
        <div>
          <span class="form-label">{{ t("site.theme.radius") }}</span>
          <SegmentedControl v-model="theme.radius" :options="['none', 'sm', 'md', 'lg', 'xl'].map((value) => ({ value, label: t(`site.option.${value}`) }))" />
        </div>
        <div>
          <span class="form-label">{{ t("site.theme.spacing") }}</span>
          <SegmentedControl v-model="theme.spacing" :options="['compact', 'normal', 'relaxed'].map((value) => ({ value, label: t(`site.theme.spacingOption.${value}`) }))" />
        </div>
        <RangeRow v-model="theme.container_width" :label="t('site.theme.containerWidth')" :min="720" :max="1920" :step="20" unit="px" />
        <RangeRow v-model="theme.narrow_width" :label="t('site.theme.narrowWidth')" :min="480" :max="1200" :step="20" unit="px" />
      </section>

      <!-- Custom CSS -->
      <section class="card space-y-3 p-5">
        <div>
          <h2 class="text-sm font-semibold text-slate-700">{{ t("site.theme.customCss") }}</h2>
          <p class="mt-1 text-xs text-slate-500">
            {{ site.data.can_edit_css ? t("site.theme.customCssHint") : t("site.theme.customCssAdminOnly") }}
          </p>
        </div>
        <textarea
          v-model="theme.custom_css"
          rows="8"
          spellcheck="false"
          :disabled="!site.data.can_edit_css"
          class="form-textarea w-full rounded-lg border-slate-300 font-mono text-xs disabled:bg-slate-50 disabled:text-slate-500"
          placeholder=".c-site-name { letter-spacing: 0.05em; }"
        ></textarea>
      </section>

      <button type="button" class="btn-secondary w-full justify-center" @click="resetTheme">
        <Icon icon="mdi:restore" class="h-4 w-4" />
        {{ t("site.theme.reset") }}
      </button>
    </div>

    <!-- Preview -->
    <div class="xl:sticky xl:top-24 xl:self-start">
      <div class="card overflow-hidden">
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <span class="text-sm font-semibold text-slate-700">{{ t("site.preview.title") }}</span>
          <select v-model="previewPath" class="form-select ml-auto rounded-lg border-slate-300 py-1 text-xs" :aria-label="t('site.preview.page')">
            <option v-for="option in previewPaths" :key="option.path" :value="option.path">{{ option.label }}</option>
          </select>
          <DeviceToggle v-model="device" />
        </div>
        <div class="h-[70vh] bg-slate-100 p-3">
          <SitePreviewFrame :body="previewBody" :device="device" :title="t('site.preview.title')" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from "vue";
import { Icon } from "@iconify/vue";
import DeviceToggle from "./DeviceToggle.vue";
import RangeRow from "./RangeRow.vue";
import SegmentedControl from "./SegmentedControl.vue";
import SitePreviewFrame from "./SitePreviewFrame.vue";
import ToggleSwitch from "../ToggleSwitch.vue";
import { useSiteStore } from "../../stores/site.js";
import { useI18n } from "../../i18n/index.js";

const COLOR_KEYS = ["primary", "secondary", "accent", "background", "surface", "text", "muted", "border"];

const PRESETS = [
  { key: "clean", theme: {} },
  {
    key: "warm",
    theme: {
      colors: { primary: "#c2410c", secondary: "#292524", accent: "#0d9488", background: "#fffbf5", surface: "#f5efe6", text: "#1c1917", muted: "#78716c", border: "#e7e0d6" },
      fonts: { heading: "old-style", body: "humanist" },
      radius: "lg",
    },
  },
  {
    key: "editorial",
    theme: {
      colors: { primary: "#b91c1c", secondary: "#111827", accent: "#1d4ed8", background: "#ffffff", surface: "#f6f6f4", text: "#111111", muted: "#6b6b6b", border: "#e5e5e5" },
      fonts: { heading: "didone", body: "transitional" },
      radius: "none",
      spacing: "relaxed",
      type_scale: "1.333",
    },
  },
  {
    key: "forest",
    theme: {
      colors: { primary: "#15803d", secondary: "#14532d", accent: "#ca8a04", background: "#fbfdf9", surface: "#eef5ea", text: "#10200f", muted: "#5b6b58", border: "#dbe7d4" },
      fonts: { heading: "slab", body: "humanist" },
    },
  },
  {
    key: "midnight",
    theme: {
      mode: "dark",
      dark_colors: { primary: "#a78bfa", secondary: "#e9e5ff", accent: "#f472b6", background: "#0f0d1a", surface: "#1a1730", text: "#ede9fe", muted: "#a1a1c2", border: "#2a2545" },
      fonts: { heading: "geometric", body: "system" },
      radius: "xl",
    },
  },
  {
    key: "studio",
    theme: {
      mode: "auto",
      colors: { primary: "#0f172a", secondary: "#334155", accent: "#06b6d4", background: "#ffffff", surface: "#f8fafc", text: "#0f172a", muted: "#64748b", border: "#e2e8f0" },
      fonts: { heading: "neo-grotesque", body: "neo-grotesque" },
      heading_weight: 800,
      radius: "sm",
    },
  },
];

const site = useSiteStore();
const { t } = useI18n();
const theme = computed(() => site.draft.theme);
const device = ref("desktop");
const previewPath = ref("/");

const palettes = computed(() => {
  if (theme.value.mode === "dark") return [{ key: "dark_colors" }];
  if (theme.value.mode === "auto") return [{ key: "colors" }, { key: "dark_colors" }];
  return [{ key: "colors" }];
});

const previewPaths = computed(() => {
  const options = [{ path: "/", label: t("site.preview.home") }];
  for (const type of site.data.types) {
    const route = site.draft.settings.routes[type.name];
    if (!route?.enabled) continue;
    const path = type.singleton ? route.pattern : route.index_path;
    if (path) options.push({ path, label: type.label });
  }
  return options;
});

const previewBody = computed(() => ({
  target: "page",
  path: previewPath.value,
  theme: theme.value,
  menus: site.draft.menus,
}));

function presetSwatches(preset) {
  const defaults = site.data.theme_defaults;
  const key = preset.theme.mode === "dark" ? "dark_colors" : "colors";
  const colors = { ...defaults[key], ...(preset.theme[key] ?? {}) };
  return [colors.background, colors.surface, colors.primary, colors.accent, colors.text];
}

function applyPreset(preset) {
  const defaults = JSON.parse(JSON.stringify(site.data.theme_defaults));
  const next = {
    ...defaults,
    ...preset.theme,
    colors: { ...defaults.colors, ...(preset.theme.colors ?? {}) },
    dark_colors: { ...defaults.dark_colors, ...(preset.theme.dark_colors ?? {}) },
    fonts: { ...defaults.fonts, ...(preset.theme.fonts ?? {}) },
    custom_css: theme.value.custom_css,
  };
  site.draft.theme = { ...theme.value, ...next };
}

function resetTheme() {
  site.draft.theme = { ...JSON.parse(JSON.stringify(site.data.theme_defaults)), custom_css: theme.value.custom_css, updated_at: theme.value.updated_at };
}

function setHex(palette, key, value) {
  const hex = String(value).trim().toLowerCase();
  if (/^#[0-9a-f]{6}$/.test(hex)) theme.value[palette][key] = hex;
}
</script>
