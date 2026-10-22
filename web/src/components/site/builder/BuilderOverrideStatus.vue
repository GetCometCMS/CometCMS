<template>
  <span v-if="target || others.length" class="inline-flex shrink-0 items-center gap-1">
    <span
      v-if="target"
      role="img"
      tabindex="0"
      :title="tooltip"
      :aria-label="tooltip"
      class="inline-flex rounded-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-theme-500"
      :class="own ? 'text-theme-600' : 'text-slate-400'"
    >
      <Icon :icon="icon" class="h-3.5 w-3.5" aria-hidden="true" />
    </span>
    <button
      v-if="own"
      type="button"
      class="inline-flex rounded-sm text-slate-400 hover:text-theme-600"
      :title="t('site.override.reset')"
      :aria-label="t('site.override.reset')"
      @click="builder.resetOverride(node.id, target.group, target.variant, group, name)"
    >
      <Icon icon="mdi:restore" class="h-3.5 w-3.5" aria-hidden="true" />
    </button>
    <!-- Seen with the normal value: where it differs. -->
    <span
      v-for="other in others"
      :key="other"
      role="img"
      tabindex="0"
      :title="t('site.override.differsOn', { variant: variantLabel(other) })"
      :aria-label="t('site.override.differsOn', { variant: variantLabel(other) })"
      class="inline-flex rounded-sm text-slate-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-theme-500"
    >
      <Icon :icon="DEVICE_ICONS[other] ?? STATE_ICONS[other]" class="h-3 w-3" aria-hidden="true" />
    </span>
  </span>
</template>

<script setup>
import { computed } from "vue";
import { Icon } from "@iconify/vue";
import { DEVICE_ICONS, STATE_ICONS } from "./controlOptions.js";
import { useSiteBuilder } from "../../../composables/useSiteBuilder.js";
import { useI18n } from "../../../i18n/index.js";

/** Whether a value differs on tablet or mobile, or while hovered or focused, beside its label. */
const props = defineProps({
  node: { type: Object, required: true },
  group: { type: String, required: true },
  name: { type: String, required: true },
});

const builder = useSiteBuilder();
const { t } = useI18n();
const target = computed(() => builder.overrideTarget?.(props.node, props.group, props.name) ?? null);
const overrides = computed(() => builder.overridesOf?.(props.node, props.group, props.name) ?? { devices: [], states: [] });
const own = computed(() => !!target.value && [...overrides.value.devices, ...overrides.value.states].includes(target.value.variant));
const others = computed(() => (target.value ? [] : [...overrides.value.devices, ...overrides.value.states]));
const icon = computed(() => DEVICE_ICONS[target.value?.variant] ?? STATE_ICONS[target.value?.variant]);

function variantLabel(variant) {
  return t(variant in DEVICE_ICONS ? `site.device.${variant}` : `site.state.${variant}`);
}

const tooltip = computed(() => {
  const variant = target.value?.variant;
  if (!variant) return "";
  if (own.value) return t("site.override.own", { variant: variantLabel(variant) });
  if (variant === "mobile" && overrides.value.devices.includes("tablet")) return t("site.override.followsTablet");
  return t(target.value.group === "states" ? "site.override.unchangedState" : "site.override.sameAsDesktop", { variant: variantLabel(variant) });
});
</script>
