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
      class="flex shrink-0 touch-none select-none items-center gap-1 border-b border-slate-200 bg-slate-50 py-1.5 pl-2 pr-1.5"
      :class="state.mode === 'floating' ? 'cursor-move' : 'cursor-grab'"
      :title="t(state.mode === 'floating' ? 'site.builder.floatingHeaderHint' : 'site.builder.dockedHeaderHint')"
      @pointerdown="$emit('move', $event)"
      @dblclick="toggleDock"
    >
      <Icon :icon="icon" class="h-4 w-4 shrink-0 text-slate-400" />
      <h2 class="min-w-0 flex-1 truncate text-xs font-semibold text-slate-800">{{ title }}</h2>
      <div class="placement" role="group" :aria-label="t('site.builder.panelPlacement')">
        <button
          v-for="option in placements"
          :key="option.value"
          type="button"
          class="window-action"
          :class="state.mode === option.value ? 'bg-white !text-theme-600 shadow-xs' : ''"
          :aria-pressed="state.mode === option.value"
          :title="option.label"
          :aria-label="option.label"
          @click="$emit('dock', option.value)"
        >
          <Icon :icon="option.icon" class="h-3.5 w-3.5" />
        </button>
      </div>
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
import { computed } from 'vue'
import { Icon } from '@iconify/vue'
import ResizableSidebar from './ResizableSidebar.vue'
import { useI18n } from '../../../i18n/index.js'
const props = defineProps({
  state: { type: Object, required: true },
  limits: { type: Object, required: true },
  title: { type: String, required: true },
  icon: { type: String, required: true },
})
const emit = defineEmits(['dock', 'toggle-dock', 'close', 'resize', 'resize-start', 'move', 'focus'])
const { t } = useI18n()
/** Ordered like the editor itself: left dock, floating over the canvas, right dock. */
const placements = computed(() => [
  { value: 'left', icon: 'mdi:dock-left', label: t('site.builder.dockLeft') },
  { value: 'floating', icon: 'mdi:dock-window', label: t('site.builder.floatPanel') },
  { value: 'right', icon: 'mdi:dock-right', label: t('site.builder.dockRight') },
])
function toggleDock(event) {
  if (event.target?.closest?.('button')) return
  emit('toggle-dock')
}
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
.window-action:not([aria-pressed="true"]):hover {
  background: rgb(var(--color-slate-200));
  color: rgb(var(--color-slate-900));
}
.placement {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 1px;
  border-radius: 0.5rem;
  background: rgb(var(--color-slate-200) / 0.7);
  padding: 2px;
}
.placement .window-action {
  width: 1.375rem;
  height: 1.375rem;
}
</style>
