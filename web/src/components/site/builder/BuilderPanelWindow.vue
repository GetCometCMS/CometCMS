<template>
  <ResizableSidebar
    :model-value="state.width"
    :side="state.mode === 'right' ? 'right' : 'left'"
    :min="limits.min"
    :max="limits.max"
    :label="t(state.mode === 'right' ? 'site.builder.resizeRight' : 'site.builder.resizeLeft')"
    :resizable="state.mode !== 'floating'"
    :aria-label="title"
    :class="state.mode === 'floating' ? 'panel-floating overflow-hidden rounded-xl border border-slate-200 shadow-2xl' : state.mode === 'right' ? 'order-2 border-l border-slate-200' : 'order-0 border-r border-slate-200'"
    :style="state.mode === 'floating' ? { position: 'absolute', left: `${state.x}px`, top: `${state.y}px`, height: `${state.height}px`, zIndex: 30 + state.z } : undefined"
    @update:model-value="$emit('resize', $event)"
    @pointerdown.capture="$emit('focus')"
  >
    <div
      class="flex shrink-0 touch-none select-none items-center gap-1 border-b border-slate-200 bg-slate-50 px-2 py-2"
      :class="state.mode === 'floating' ? 'cursor-move' : 'cursor-grab'"
      @pointerdown="$emit('move', $event)"
    >
      <Icon :icon="icon" class="h-4 w-4 shrink-0 text-slate-400" />
      <h2 class="min-w-0 flex-1 truncate text-xs font-semibold text-slate-800">{{ title }}</h2>
      <button v-if="state.mode !== 'floating'" type="button" class="window-action" :title="t('site.builder.floatPanel')" :aria-label="t('site.builder.floatPanel')" @click="$emit('dock', 'floating')">
        <Icon icon="mdi:open-in-new" class="h-4 w-4" />
      </button>
      <button v-if="state.mode !== 'left'" type="button" class="window-action" :title="t('site.builder.dockLeft')" :aria-label="t('site.builder.dockLeft')" @click="$emit('dock', 'left')">
        <Icon icon="mdi:dock-left" class="h-4 w-4" />
      </button>
      <button v-if="state.mode !== 'right'" type="button" class="window-action" :title="t('site.builder.dockRight')" :aria-label="t('site.builder.dockRight')" @click="$emit('dock', 'right')">
        <Icon icon="mdi:dock-right" class="h-4 w-4" />
      </button>
      <button type="button" class="window-action" :title="t('site.builder.closePanel')" :aria-label="t('site.builder.closePanel')" @click="$emit('close')">
        <Icon icon="mdi:close" class="h-4 w-4" />
      </button>
    </div>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden"><slot /></div>
    <div
      v-if="state.mode === 'floating'"
      role="separator"
      tabindex="0"
      :aria-label="t('site.builder.resizeWindow')"
      :title="t('site.builder.resizeWindow')"
      class="absolute bottom-0 right-0 z-20 flex h-5 w-5 touch-none cursor-se-resize items-end justify-end rounded-tl bg-white/90 text-slate-400 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-theme-500"
      @pointerdown="$emit('resize-start', $event)"
      @keydown="resizeWithKeyboard"
    >
      <Icon icon="mdi:resize-bottom-right" class="h-4 w-4" />
    </div>
  </ResizableSidebar>
</template>

<script setup>
import { Icon } from '@iconify/vue'
import ResizableSidebar from './ResizableSidebar.vue'
import { useI18n } from '../../../i18n/index.js'
const props = defineProps({
  state: { type: Object, required: true },
  limits: { type: Object, required: true },
  title: { type: String, required: true },
  icon: { type: String, required: true },
})
const emit = defineEmits(['dock', 'close', 'resize', 'resize-start', 'move', 'focus'])
const { t } = useI18n()
function resizeWithKeyboard(event) {
  if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return
  event.preventDefault()
  event.stopPropagation()
  const step = event.shiftKey ? 50 : 10
  emit('resize', props.state.width + (event.key === 'ArrowRight' ? step : event.key === 'ArrowLeft' ? -step : 0),
    props.state.height + (event.key === 'ArrowDown' ? step : event.key === 'ArrowUp' ? -step : 0))
}
</script>

<style scoped>
.window-action {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 0.375rem;
  color: rgb(var(--color-slate-500));
}
.window-action:hover {
  background: rgb(var(--color-slate-200));
  color: rgb(var(--color-slate-900));
}
</style>
