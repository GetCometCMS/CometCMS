<template>
  <aside :id="panelId" class="relative flex min-h-0 shrink-0 flex-col bg-white" :style="{ width: `${modelValue}px` }">
    <slot />
    <div
      ref="handleRef"
      role="separator"
      tabindex="0"
      aria-orientation="vertical"
      :aria-label="label"
      :aria-controls="panelId"
      :aria-valuemin="min"
      :aria-valuemax="max"
      :aria-valuenow="modelValue"
      :title="label"
      class="absolute inset-y-0 z-20 w-2 touch-none cursor-col-resize transition-colors hover:bg-theme-400/40 focus-visible:bg-theme-400/40"
      :class="[side === 'left' ? '-right-1' : '-left-1', { 'bg-theme-400/40': dragging }]"
      @pointerdown="startResize"
      @pointermove="resize"
      @pointerup="stopResize"
      @pointercancel="stopResize"
      @lostpointercapture="stopResize"
      @keydown="onKeydown"
    ></div>
    <Teleport to="body">
      <div v-if="dragging" class="fixed inset-0 z-[100] cursor-col-resize select-none" aria-hidden="true"></div>
    </Teleport>
  </aside>
</template>

<script setup>
import { ref, useId } from "vue";

const props = defineProps({
  modelValue: { type: Number, required: true },
  side: { type: String, required: true, validator: (value) => ["left", "right"].includes(value) },
  label: { type: String, required: true },
  min: { type: Number, default: 224 },
  max: { type: Number, default: 480 },
});
const emit = defineEmits(["update:modelValue"]);
const panelId = `builder-sidebar-${useId()}`;
const handleRef = ref(null);
const dragging = ref(false);
let pointerId = null;
let startX = 0;
let startWidth = 0;

function setWidth(width) {
  emit("update:modelValue", Math.round(Math.max(props.min, Math.min(props.max, width))));
}

function startResize(event) {
  if (event.button !== 0 || dragging.value) return;
  event.preventDefault();
  event.currentTarget.focus();
  pointerId = event.pointerId;
  startX = event.clientX;
  startWidth = props.modelValue;
  event.currentTarget.setPointerCapture(pointerId);
  dragging.value = true;
}

function resize(event) {
  if (!dragging.value || event.pointerId !== pointerId) return;
  const delta = (event.clientX - startX) * (props.side === "left" ? 1 : -1);
  setWidth(startWidth + delta);
}

function stopResize(event) {
  if (event.pointerId !== pointerId) return;
  dragging.value = false;
  if (handleRef.value?.hasPointerCapture(pointerId)) handleRef.value.releasePointerCapture(pointerId);
  pointerId = null;
}

function onKeydown(event) {
  if (!["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key)) return;
  event.preventDefault();
  event.stopPropagation();
  if (event.key === "Home") setWidth(props.min);
  else if (event.key === "End") setWidth(props.max);
  else {
    const direction = (event.key === "ArrowRight" ? 1 : -1) * (props.side === "left" ? 1 : -1);
    setWidth(props.modelValue + direction * (event.shiftKey ? 50 : 10));
  }
}
</script>
