<template>
  <div class="space-y-4">
    <p class="text-xs text-slate-500">{{ t('site.theme.motionHint') }}</p>
    <div class="grid grid-cols-3 gap-2" :aria-label="t('site.theme.motionSpeed')">
      <button
        v-for="option in speeds" :key="option.key" type="button" :aria-pressed="speed === option.key"
        class="min-w-0 rounded-lg border p-2 text-center transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-500"
        :class="speed === option.key ? 'border-theme-500 bg-theme-50 text-theme-800' : 'border-slate-200 text-slate-600 hover:border-theme-400'"
        @click="speed = option.key; replay()"
      >
        <Icon :icon="option.icon" class="mx-auto mb-1.5 h-5 w-5" />
        <span class="block truncate text-xs font-semibold">{{ t(`site.option.${option.key}`) }}</span>
        <span class="mt-1 block font-mono text-[11px] opacity-70">{{ motion[option.key] / 1000 }} s</span>
      </button>
    </div>
    <MotionTimingField :model-value="motion[speed]" :label="t('site.theme.motionDuration', { speed: t(`site.option.${speed}`) })" :min="100" :max="5000" :step="50" @update:model-value="update(speed, $event)" />
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
      <div class="flex items-center justify-between gap-2 px-3 pt-3">
        <h4 class="text-xs font-semibold text-slate-700">{{ t('site.theme.motionEntrance') }}</h4>
        <button type="button" class="flex items-center gap-1 rounded px-1 py-0.5 text-xs text-theme-700 hover:bg-theme-100 focus-visible:ring-2 focus-visible:ring-theme-500" @click="replay">
          <Icon icon="mdi:replay" class="h-4 w-4" />{{ t('site.theme.motionPreview') }}
        </button>
      </div>
      <div :key="preview" class="flex h-24 items-center justify-center gap-3 px-4" aria-hidden="true">
        <span v-for="step in 3" :key="step" class="flex h-12 w-14 items-center justify-center rounded-lg border border-theme-200 bg-white text-sm font-semibold text-theme-600 shadow-sm" :class="{ 'motion-sample': preview > 0 }" :style="{ animationDuration: `${motion[speed]}ms`, animationDelay: `${(step - 1) * motion.stagger}ms` }">{{ step }}</span>
      </div>
      <p class="px-3 pb-3 text-[11px] text-slate-500">{{ t('site.theme.motionSequence', { delay: motion.stagger / 1000 }) }}</p>
    </div>
    <MotionTimingField :model-value="motion.stagger" :label="t('site.theme.motionStagger')" :max="5000" @update:model-value="update('stagger', $event)" />
    <div class="space-y-3 border-t border-slate-100 pt-4">
      <div>
        <h4 class="text-xs font-semibold text-slate-700">{{ t('site.theme.motionHover') }}</h4>
        <p class="mt-1 text-xs text-slate-500">{{ t('site.theme.motionHoverHint') }}</p>
      </div>
      <div class="flex h-24 items-center justify-center rounded-lg border border-slate-200 bg-slate-50 p-4">
        <button type="button" class="hover-sample rounded-lg border-2 border-theme-200 bg-white px-4 py-2 text-xs font-medium text-theme-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-theme-500" :style="{ transitionDuration: `${motion.hover ?? 200}ms` }">{{ t('site.theme.motionHoverTry') }}</button>
      </div>
      <MotionTimingField :model-value="motion.hover ?? 200" :label="t('site.theme.motionTransitionDuration')" :max="1000" @update:model-value="update('hover', $event)" />
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { Icon } from '@iconify/vue';
import MotionTimingField from './MotionTimingField.vue';
import { useI18n } from '../../../i18n/index.js';
const props = defineProps({ motion: { type: Object, required: true } });
const { t } = useI18n();
const speeds = [{ key: 'fast', icon: 'mdi:lightning-bolt-outline' }, { key: 'normal', icon: 'mdi:play-circle-outline' }, { key: 'slow', icon: 'mdi:timer-sand' }];
const speed = ref('normal');
const preview = ref(0);
function replay() { preview.value++; }
function update(key, value) {
  props.motion[key] = value;
  if (key !== 'hover') replay();
}
</script>

<style scoped>
.motion-sample { animation: motion-sample cubic-bezier(0.2, 0.7, 0.2, 1) both; }
@keyframes motion-sample {
  from { opacity: 0; transform: translateY(0.75rem); }
  to { opacity: 1; transform: translateY(0); }
}
.hover-sample { transition-property: background-color, color, border-color, box-shadow; transition-timing-function: ease; }
.hover-sample:is(:hover, :focus-visible) { background: rgb(var(--color-theme-600)); color: white; border-color: rgb(var(--color-theme-600)); box-shadow: 0 6px 16px -4px rgb(var(--color-theme-600) / 0.3); }
@media (prefers-reduced-motion: reduce) {
  .motion-sample { animation: none; }
  .hover-sample { transition: none; }
}
</style>
