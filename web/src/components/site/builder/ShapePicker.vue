<template>
  <!-- Each divider drawn as a solid band on a hatched section. -->
  <div class="grid grid-cols-3 gap-1.5" role="radiogroup" :aria-label="label">
    <button
      v-for="option in options"
      :key="option"
      type="button"
      role="radio"
      :aria-checked="modelValue === option"
      class="flex min-w-0 flex-col items-center gap-1 rounded-lg border p-1.5 transition-colors"
      :class="modelValue === option ? 'border-theme-300 bg-theme-50 text-theme-800 ring-1 ring-theme-300' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50'"
      @click="emit('update:modelValue', option)"
    >
      <svg
        viewBox="0 0 120 60"
        class="w-full aspect-[2/1] overflow-hidden rounded border border-slate-300 bg-white text-slate-900"
        aria-hidden="true"
        focusable="false"
      >
        <defs>
          <pattern :id="`${uid}-hatch`" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
            <line x1="0" y1="0" x2="0" y2="6" stroke="currentColor" stroke-width="1.2" />
          </pattern>
        </defs>

        <rect width="120" height="60" :fill="`url(#${uid}-hatch)`" opacity="0.45" />
        <svg
          v-if="option !== 'none'"
          x="0"
          :y="edge === 'bottom' ? 60 - DIVIDER_BAND : 0"
          width="120"
          :height="DIVIDER_BAND"
          :viewBox="`0 0 ${divider.width} ${divider.height}`"
          preserveAspectRatio="none"
        >
          <path :d="dividerPath(option)" :transform="dividerTransform" fill="currentColor" fill-rule="evenodd" />
        </svg>
      </svg>
      <span class="w-full truncate text-center text-[10px] font-medium">{{ t(`site.shape.${option}`) }}</span>
    </button>
  </div>
</template>

<script setup>
import { computed, useId } from "vue";
import { useSiteBuilder } from "../../../composables/useSiteBuilder.js";
import { useI18n } from "../../../i18n/index.js";

/** Height of the divider band in the 120×60 preview. */
const DIVIDER_BAND = 24;

const props = defineProps({
  options: { type: Array, required: true },
  modelValue: { type: String, default: "none" },
  label: { type: String, default: "" },
  /** A divider's edge and options, so its previews show it as it will look. */
  edge: { type: String, default: "top" },
  flip: { type: Boolean, default: false },
  invert: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);
const builder = useSiteBuilder();
const { t } = useI18n();
const uid = useId();

const divider = computed(() => builder.spec.value?.shapes?.divider ?? { width: 1200, height: 120, paths: {} });

/** Inverted dividers fill the rest of their box, as the site renders them (see Shapes::dividerSvg()). */
function dividerPath(option) {
  const path = divider.value.paths[option] ?? "";
  const { width, height } = divider.value;
  return props.invert ? `M0,0H${width}V${height}H0Z${path}` : path;
}

/** Mirrored like the site's stylesheet does: bottom and inverted dividers turn towards their edge. */
const dividerTransform = computed(() => {
  const { width, height } = divider.value;
  const x = props.flip ? -1 : 1;
  const y = (props.edge === "bottom") !== props.invert ? -1 : 1;
  return `translate(${width / 2} ${height / 2}) scale(${x} ${y}) translate(${-width / 2} ${-height / 2})`;
});
</script>
