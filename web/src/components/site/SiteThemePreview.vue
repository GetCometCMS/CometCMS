<template>
  <section class="card" :aria-label="t('site.theme.previewTitle')">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
      <div>
        <h2 class="card-title">{{ t('site.theme.previewTitle') }}</h2>
        <p class="card-description">{{ t('site.theme.previewHint') }}</p>
      </div>
      <DeviceToggle v-model="device" allow-full-width />
    </div>
    <div class="h-[min(44rem,70vh)] min-h-[24rem] rounded-b-xl bg-slate-100 p-3">
      <SitePreviewFrame :body="previewBody" :device="device" :title="t('site.theme.previewTitle')" :head-css="specimenCss" />
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import DeviceToggle from './DeviceToggle.vue';
import SitePreviewFrame from './SitePreviewFrame.vue';
import { useSiteStore } from '../../stores/site.js';
import { useI18n } from '../../i18n/index.js';

const site = useSiteStore();
const { t } = useI18n();
const device = ref('full');

// Render a standalone specimen through the existing region preview. Hide the
// surrounding page and saved footer; the sample never changes a saved region.
const specimenCss = 'body > main, body > .c-footer { display: none; }';

const previewBody = computed(() => {
  if (!site.draft?.theme) return null;
  let id = 0;
  const node = (kind, props = {}, style = {}, children = []) => ({ id: `theme-preview-${++id}`, kind, props, style, children });
  const heading = (level, text) => node('heading', { level, text });
  const button = (variant) => node('button', { variant, label: t(`site.theme.previewButton.${variant}`), link: { type: 'current' } });
  const card = (color) => node('column', {}, { background: color, padding_y: 'md', padding_x: 'md', radius: 'theme', border: ['left', 'top', 'right', 'bottom'] }, [
    heading('h3', t(`site.theme.color.${color}`)),
    node('text', { html: `<p>${t('site.theme.previewCard')}</p>` }),
  ]);
  const customColors = Object.entries(site.draft.theme.custom_colors ?? {});

  return {
    target: 'region',
    region: 'header',
    locale: site.draft.settings.default_locale,
    theme: site.draft.theme,
    font_version: site.data?.font_version,
    nodes: [
      node('section', { width: 'contained' }, { padding_y: 'lg' }, [
        node('row', {}, {}, [node('column', {}, {}, [
          heading('h1', t('site.theme.previewHeading')),
          node('text', { html: `<p>${t('site.theme.previewText')}</p>` }),
          node('row', {}, {}, [node('column', { direction: 'row' }, {}, [button('primary'), button('secondary'), button('outline'), button('link')])]),
          node('divider'),
          heading('h2', t('site.theme.typography')),
          ...['h3', 'h4', 'h5', 'h6'].map((level) => heading(level, `${level.toUpperCase()} · ${t('site.theme.headingSample')}`)),
        ])]),
      ]),
      node('section', { width: 'narrow' }, { padding_y: 'lg' }, [
        node('row', {}, {}, [card('surface'), card('accent')]),
        ...(customColors.length ? [node('row', {}, {}, customColors.map(([key, color]) =>
          node('column', {}, { background: key, padding_y: 'md', padding_x: 'md', radius: 'theme' }, [heading('h3', color.label)]),
        ))] : []),
      ]),
    ],
  };
});
</script>
