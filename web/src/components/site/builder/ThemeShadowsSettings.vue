<template>
  <div class="space-y-4">
    <p class="text-xs text-slate-500">{{ t('site.theme.shadowsHint') }}</p>
    <div class="grid grid-cols-3 gap-2" :aria-label="t('site.shadow.sizes')">
      <button
        v-for="size in FIXED_SHADOWS" :key="size" type="button"
        class="rounded-lg border px-3 py-2 text-center transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-500"
        :class="selected === size ? 'border-theme-500 bg-theme-50 text-theme-800' : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-theme-400'"
        :aria-pressed="selected === size" @click="selected = size"
      >
        <span class="block text-xs font-semibold">{{ t(`site.option.${size}`) }}</span>
      </button>
    </div>
    <div class="rounded-lg border border-slate-200 p-3">
      <div class="mb-3 flex items-center justify-between gap-2">
        <h4 class="text-xs font-semibold text-slate-700">{{ t('site.theme.shadowLevel', { level: t(`site.option.${selected}`) }) }}</h4>
        <button type="button" class="text-xs text-slate-500 hover:text-theme-700" @click="resetLevel">{{ t('site.theme.resetLevel') }}</button>
      </div>
      <div class="mb-3 flex h-32 items-center justify-center rounded-md bg-ink-100" aria-hidden="true">
        <span class="flex h-16 min-w-24 items-center justify-center rounded-lg bg-ink-50 px-4 text-xs font-medium text-ink-800" :style="preview(selected)">{{ t('site.style.shadow') }}</span>
      </div>
      <ShadowFields :id="`${id}-${selected}`" :model-value="settings" :options="colors" @update:model-value="updateLevel" />
    </div>
    <div class="space-y-3 border-t border-slate-100 pt-4">
      <div class="flex items-center justify-between gap-2">
        <h4 class="text-xs font-semibold text-slate-700">{{ t('site.shadow.themePresets') }}</h4>
        <button type="button" class="btn-secondary btn-sm" :disabled="Object.keys(theme.shadows ?? {}).length >= MAX_SHADOWS" @click="addShadow">
          <Icon icon="mdi:plus" class="h-4 w-4" />{{ t('site.theme.addShadow') }}
        </button>
      </div>
      <details v-for="(shadow, key) in theme.shadows" :key="key" :open="newShadow === key" class="rounded-lg border border-slate-200 p-3">
        <summary class="flex cursor-pointer list-none items-center gap-3 text-xs font-medium text-slate-700">
          <span class="h-8 w-8 shrink-0 rounded-md bg-white ring-1 ring-black/5" :style="preview(key)" aria-hidden="true"></span>
          <span class="min-w-0 flex-1 truncate">{{ shadowLabel(key, shadow, t) }}</span>
          <Icon icon="mdi:pencil-outline" class="h-4 w-4 text-slate-400" />
        </summary>
        <div class="mt-3 space-y-3 border-t border-slate-100 pt-3">
          <div class="flex items-center gap-2">
            <input v-model="shadow.label" type="text" maxlength="80" :placeholder="shadowLabel(key, null, t)" :aria-label="t('site.theme.shadowName')" class="form-input min-w-0 flex-1 rounded-lg border-slate-300 py-1.5 text-xs" />
            <button type="button" class="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600" :aria-label="t('select.remove', { name: shadowLabel(key, shadow, t) })" @click="delete theme.shadows[key]">
              <Icon icon="mdi:trash-can-outline" class="h-4 w-4" />
            </button>
          </div>
          <div class="flex h-32 items-center justify-center rounded-md bg-ink-100" aria-hidden="true">
            <span class="flex h-16 min-w-24 items-center justify-center rounded-lg bg-ink-50 px-4 text-xs font-medium text-ink-800" :style="preview(key)">{{ shadowLabel(key, shadow, t) }}</span>
          </div>
          <ShadowFields :id="`${id}-${key}`" :model-value="shadowSettings(shadow)" :options="colors" @update:model-value="theme.shadows[key] = { label: shadow.label, ...$event }" />
        </div>
      </details>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, useId } from 'vue';
import { Icon } from '@iconify/vue';
import ShadowFields from './ShadowFields.vue';
import { useI18n } from '../../../i18n/index.js';
import { FIXED_SHADOWS, MAX_SHADOWS, shadowLabel, shadowPreview, shadowSettings } from '../../../composables/shadows.js';
import { newId } from '../../../composables/layoutTree.js';

const props = defineProps({
  theme: { type: Object, required: true },
  colors: { type: Array, default: () => [] },
});
const { t } = useI18n();
const id = `design-shadows-${useId()}`;
const selected = ref('md');
const newShadow = ref('');
const settings = computed(() => shadowSettings(selected.value, false, {}, props.theme.shadow_scale));
const preview = (key) => shadowPreview(key, false, props.colors, props.theme.shadows, props.theme.shadow_scale);
function updateLevel(value) {
  props.theme.shadow_scale = { ...props.theme.shadow_scale, [selected.value]: value };
}
function resetLevel() {
  updateLevel(shadowSettings(selected.value));
}
function addShadow() {
  const shadows = props.theme.shadows ?? {};
  if (Object.keys(shadows).length >= MAX_SHADOWS) return;
  newShadow.value = `shadow-${newId()}`;
  props.theme.shadows = {
    ...shadows,
    [newShadow.value]: { label: t('site.theme.shadowPresetName', { count: Object.keys(shadows).length + 1 }), ...shadowSettings(selected.value, false, {}, props.theme.shadow_scale) },
  };
}
</script>
