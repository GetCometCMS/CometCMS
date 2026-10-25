<template>
  <div class="space-y-4">
    <p class="text-xs text-slate-500">{{ t('site.theme.linksHint') }}</p>
    <div class="rounded-lg border border-slate-200 p-4 text-sm" :style="sampleStyle" aria-hidden="true">
      {{ before }}<a href="#" class="link-sample" tabindex="-1" @click.prevent>{{ t('site.theme.linkSampleText') }}</a>{{ after }}
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div>
        <span class="form-label">{{ t('site.theme.linkColor') }}</span>
        <ThemeColorPicker id="design-link-color" v-model="links.color" :label="t('site.theme.linkColor')" :options="colors" :allow-empty="false" />
      </div>
      <div>
        <span class="form-label">{{ t('site.theme.linkHoverColor') }}</span>
        <ThemeColorPicker id="design-link-hover" v-model="links.hover_color" :label="t('site.theme.linkHoverColor')" :options="colors" />
      </div>
    </div>
    <div>
      <span class="form-label">{{ t('site.theme.linkDecoration') }}</span>
      <SegmentedControl v-model="links.decoration" :options="['underline', 'hover', 'none'].map((value) => ({ value, label: t(`site.theme.linkDecoration.${value}`) }))" />
    </div>
    <template v-if="links.decoration !== 'none'">
      <RangeRow v-model="links.thickness" :label="t('site.theme.linkThickness')" :min="0" :max="6" :step="1" unit="px" />
      <p class="-mt-2 text-xs text-slate-500">{{ t('site.theme.linkThicknessHint') }}</p>
      <RangeRow v-model="links.offset" :label="t('site.theme.linkOffset')" :min="0" :max="0.5" :step="0.02" unit="em" />
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import RangeRow from '../RangeRow.vue';
import SegmentedControl from '../SegmentedControl.vue';
import ThemeColorPicker from './ThemeColorPicker.vue';
import { useI18n } from '../../../i18n/index.js';
const props = defineProps({
  links: { type: Object, required: true },
  colors: { type: Array, default: () => [] },
  hoverMs: { type: Number, default: 200 },
});
const { t } = useI18n();
const sentence = computed(() => t('site.theme.linkSample').split('{link}'));
const before = computed(() => sentence.value[0] ?? '');
const after = computed(() => sentence.value[1] ?? '');
const swatch = (value) => props.colors.find((option) => option.value === value)?.swatch;
const sampleStyle = computed(() => {
  const color = swatch(props.links.color) ?? '#2563eb';
  return {
    backgroundColor: swatch('background'),
    color: swatch('text'),
    '--sample-color': color,
    '--sample-hover': swatch(props.links.hover_color) ?? color,
    '--sample-line': props.links.decoration === 'underline' ? 'underline' : 'none',
    '--sample-hover-line': props.links.decoration === 'none' ? 'none' : 'underline',
    '--sample-thickness': props.links.thickness > 0 ? `${props.links.thickness}px` : 'auto',
    '--sample-offset': `${props.links.offset}em`,
    '--sample-duration': `${props.hoverMs}ms`,
  };
});
</script>

<style scoped>
.link-sample {
  color: var(--sample-color); text-decoration-line: var(--sample-line); text-decoration-thickness: var(--sample-thickness); text-underline-offset: var(--sample-offset);
  transition: color var(--sample-duration) ease;
}
.link-sample:hover { color: var(--sample-hover); text-decoration-line: var(--sample-hover-line); }
@media (prefers-reduced-motion: reduce) {
  .link-sample { transition: none; }
}
</style>
