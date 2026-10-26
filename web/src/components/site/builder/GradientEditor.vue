<template>
  <div class="space-y-2">
    <select :id="id" :value="modelValue?.type || ''" class="form-select w-full rounded-lg border-slate-300 py-1.5 text-xs" @change="setType($event.target.value)">
      <option value="">{{ t('site.option.none') }}</option>
      <option value="linear">{{ t('site.gradient.linear') }}</option>
      <option value="radial">{{ t('site.gradient.radial') }}</option>
    </select>
    <template v-if="modelValue?.type">
      <div class="h-8 rounded-md ring-1 ring-black/10" :style="{ backgroundImage: preview }" aria-hidden="true"></div>
      <div v-for="key in ['from', 'to']" :key="key">
        <label :for="`${id}-${key}`" class="mb-1 block text-xs text-slate-600">{{ t(`site.gradient.${key}`) }}</label>
        <ThemeColorPicker :id="`${id}-${key}`" :label="t(`site.gradient.${key}`)" :model-value="modelValue[key]" :options="options" :allow-empty="false" @update:model-value="update({ [key]: $event })" />
      </div>
      <div v-if="modelValue.type === 'linear'">
        <label :for="`${id}-angle`" class="mb-1 block text-xs text-slate-600">{{ t('site.gradient.angle') }}</label>
        <div class="flex items-center gap-2">
          <div
            role="slider" tabindex="0" :aria-label="t('site.gradient.angle')"
            aria-valuemin="0" aria-valuemax="360" :aria-valuenow="modelValue.angle"
            :aria-valuetext="`${modelValue.angle}°`"
            class="angle-dial relative h-16 w-16 shrink-0 touch-none select-none rounded-full border border-slate-300 bg-white text-theme-600 shadow-sm outline-none focus-visible:ring-2 focus-visible:ring-theme-600 focus-visible:ring-offset-2"
            @pointerdown="startDrag" @pointermove="drag" @pointerup="endDrag" @pointercancel="endDrag" @lostpointercapture="dragging = false"
            @keydown="onAngleKeydown"
          >
            <svg viewBox="0 0 64 64" class="h-full w-full" aria-hidden="true">
              <path d="M32 5v5 M59 32h-5 M32 59v-5 M5 32h5" class="stroke-slate-400" stroke-width="1.5" />
              <circle cx="32" cy="32" r="22" fill="none" class="stroke-slate-200" />
              <g :transform="`rotate(${modelValue.angle || 0} 32 32)`">
                <path d="M32 47V14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                <path d="m27 20 5-7 5 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <circle cx="32" cy="12" r="3" fill="currentColor" />
              </g>
              <circle cx="32" cy="32" r="3" fill="currentColor" />
            </svg>
          </div>
          <input :id="`${id}-angle`" type="number" min="0" max="360" :value="modelValue.angle" class="form-input w-20 rounded-lg border-slate-300 py-1.5 text-xs" @change="setAngle($event.target.value)" />
          <span class="text-xs text-slate-500">°</span>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, ref } from "vue";
import ThemeColorPicker from "./ThemeColorPicker.vue";
import { useI18n } from "../../../i18n/index.js";

const props = defineProps({
  id: { type: String, required: true },
  modelValue: { type: [Object, Array], default: () => [] },
  options: { type: Array, default: () => [] },
});
const emit = defineEmits(["update:modelValue"]);
const { t } = useI18n();
const dragging = ref(false);

function angleAtPointer(event) {
  const bounds = event.currentTarget.getBoundingClientRect();
  const x = event.clientX - bounds.left - bounds.width / 2;
  const y = event.clientY - bounds.top - bounds.height / 2;
  if (Math.hypot(x, y) < 3) return;
  setAngle(Math.round((Math.atan2(x, -y) * 180 / Math.PI + 360) % 360));
}

function startDrag(event) {
  if (event.button !== 0 || !event.isPrimary) return;
  event.preventDefault();
  event.currentTarget.focus();
  event.currentTarget.setPointerCapture(event.pointerId);
  dragging.value = true;
  angleAtPointer(event);
}

function drag(event) {
  if (dragging.value) angleAtPointer(event);
}

function endDrag(event) {
  dragging.value = false;
  if (event.currentTarget.hasPointerCapture(event.pointerId)) event.currentTarget.releasePointerCapture(event.pointerId);
}

function onAngleKeydown(event) {
  const step = event.shiftKey ? 10 : 1;
  const changes = { ArrowUp: step, ArrowRight: step, ArrowDown: -step, ArrowLeft: -step, PageUp: 10, PageDown: -10 };
  if (event.key === 'Home' || event.key === 'End') {
    event.preventDefault();
    setAngle(event.key === 'Home' ? 0 : 360);
  } else if (Object.hasOwn(changes, event.key)) {
    event.preventDefault();
    setAngle(((Number(props.modelValue.angle) || 0) + changes[event.key] + 360) % 360);
  }
}
const preview = computed(() => {
  const color = (key, fallback) => props.options.find((option) => option.value === props.modelValue[key])?.swatch
    ?? props.options.find((option) => option.value === fallback)?.swatch ?? '#cccccc';
  const colors = `${color('from', 'primary')}, ${color('to', 'accent')}`;
  return props.modelValue.type === 'radial' ? `radial-gradient(ellipse at center, ${colors})` : `linear-gradient(${props.modelValue.angle}deg, ${colors})`;
});

function update(values) {
  emit("update:modelValue", { ...props.modelValue, ...values });
}

function setType(type) {
  if (!type) return emit("update:modelValue", []);
  const value = { type, from: props.modelValue.from || 'primary', to: props.modelValue.to || 'accent' };
  if (type === 'linear') value.angle = props.modelValue.angle ?? 135;
  emit("update:modelValue", value);
}

function setAngle(value) {
  update({ angle: Math.max(0, Math.min(360, Math.trunc(Number(value) || 0))) });
}
</script>
