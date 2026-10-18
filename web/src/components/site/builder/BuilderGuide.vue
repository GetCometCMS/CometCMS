<template>
  <Teleport to="body">
    <Transition name="guide">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-[70] flex items-center justify-center bg-ink-950/40 p-4 backdrop-blur-[2px]"
        @mousedown.self="close"
      >
        <div
          ref="dialogRef"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="titleId"
          tabindex="-1"
          class="guide-panel flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
        >
          <div class="flex items-start gap-3 border-b border-slate-200 px-6 py-5">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-theme-50 text-theme-600 ring-1 ring-inset ring-theme-600/10">
              <Icon icon="mdi:lightbulb-on-outline" class="h-5 w-5" />
            </span>
            <div class="min-w-0 flex-1">
              <h2 :id="titleId" data-dialog-initial-focus tabindex="-1" class="text-lg font-semibold tracking-tight text-slate-900 focus:outline-none">{{ t("site.builder.guide.title") }}</h2>
              <p class="mt-0.5 text-sm text-slate-500">{{ t("site.builder.guide.intro") }}</p>
            </div>
            <button type="button" class="btn-ghost btn-icon -mr-2" :aria-label="t('site.builder.closePanel')" @click="close">
              <Icon icon="mdi:close" class="h-5 w-5" />
            </button>
          </div>

          <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
            <ol class="grid gap-3 sm:grid-cols-2">
              <li v-for="(step, index) in steps" :key="step.key" class="flex gap-3 rounded-lg border border-slate-200 p-4">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-theme-600 text-xs font-semibold text-white">{{ index + 1 }}</span>
                <div class="min-w-0">
                  <p class="flex items-center gap-1.5 text-sm font-semibold text-slate-900">
                    <Icon :icon="step.icon" class="h-4 w-4 text-slate-400" aria-hidden="true" />
                    {{ t(`site.builder.guide.${step.key}Title`) }}
                  </p>
                  <p class="mt-1 text-sm text-slate-500">{{ t(`site.builder.guide.${step.key}Body`) }}</p>
                </div>
              </li>
            </ol>

            <div class="mt-6 grid gap-6 md:grid-cols-2">
              <section>
                <h3 class="text-sm font-semibold text-slate-900">{{ t("site.builder.guide.structure") }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ t("site.builder.guide.structureBody") }}</p>
                <ul class="mt-3 space-y-2">
                  <li v-for="kind in STRUCTURE" :key="kind.kind" class="flex items-start gap-2.5" :style="{ paddingLeft: `${kind.depth * 14}px` }">
                    <span class="mt-0.5 h-4 w-4 shrink-0 rounded border-2" :style="{ borderColor: kind.color, borderStyle: kind.dashed ? 'dashed' : 'solid' }" aria-hidden="true"></span>
                    <span class="min-w-0 text-sm">
                      <span class="font-medium text-slate-800">{{ t(`site.kind.${kind.kind}`) }}</span>
                      <span class="block text-xs text-slate-500">{{ t(`site.kindHint.${kind.kind}`) }}</span>
                    </span>
                  </li>
                </ul>
              </section>

              <section>
                <h3 class="text-sm font-semibold text-slate-900">{{ t("site.builder.guide.shortcuts") }}</h3>
                <dl class="mt-3 divide-y divide-slate-100 rounded-lg border border-slate-200">
                  <div v-for="shortcut in shortcuts" :key="shortcut.label" class="flex items-center justify-between gap-3 px-3 py-2">
                    <dt class="text-sm text-slate-600">{{ shortcut.label }}</dt>
                    <dd class="flex shrink-0 items-center gap-1">
                      <template v-for="(combo, comboIndex) in shortcut.keys" :key="comboIndex">
                        <span v-if="comboIndex" class="px-0.5 text-xs text-slate-400">/</span>
                        <kbd v-for="key in combo" :key="key" class="kbd">{{ key }}</kbd>
                      </template>
                    </dd>
                  </div>
                </dl>
              </section>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, ref, useId } from "vue";
import { Icon } from "@iconify/vue";
import { useDialogFocus } from "../../../composables/useDialogFocus.js";
import { useI18n } from "../../../i18n/index.js";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
});
const emit = defineEmits(["update:modelValue"]);
const { t } = useI18n();
const titleId = `builder-guide-${useId()}`;
const dialogRef = ref(null);

/** Mirrors the outline colors in BuilderCanvas. */
const STRUCTURE = [
  { kind: "section", color: "#5875c9", depth: 0 },
  { kind: "row", color: "#238d85", depth: 1 },
  { kind: "column", color: "#e07868", depth: 2 },
  { kind: "component", color: "#9464b5", dashed: true, depth: 0 },
];

const steps = [
  { key: "step1", icon: "mdi:plus-box-outline" },
  { key: "step2", icon: "mdi:cursor-default-click-outline" },
  { key: "step3", icon: "mdi:devices" },
  { key: "step4", icon: "mdi:content-save-outline" },
];

const mod = typeof navigator !== "undefined" && /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent) ? "⌘" : "Ctrl";

const shortcuts = computed(() => [
  { label: t("site.builder.save"), keys: [[mod, "S"]] },
  { label: t("site.builder.undo"), keys: [[mod, "Z"]] },
  { label: t("site.builder.redo"), keys: [[mod, "⇧", "Z"], [mod, "Y"]] },
  { label: t("site.builder.duplicate"), keys: [[mod, "D"]] },
  { label: t("site.builder.guide.copyPaste"), keys: [[mod, "C"], [mod, "V"]] },
  { label: t("common.delete"), keys: [["Del"]] },
  { label: t("site.builder.guide.moveElement"), keys: [["Alt", "↑"], ["Alt", "↓"]] },
  { label: t("site.builder.guide.contextMenu"), keys: [["⇧", "F10"]] },
  { label: t("site.builder.guide.deselect"), keys: [["Esc"]] },
  { label: t("site.builder.guide.openGuide"), keys: [["?"]] },
]);

function close() {
  emit("update:modelValue", false);
}

useDialogFocus({ open: computed(() => props.modelValue), container: dialogRef, close });
</script>

<style scoped>
.guide-enter-active,
.guide-leave-active {
  transition: opacity 0.15s ease;
}
.guide-enter-active .guide-panel,
.guide-leave-active .guide-panel {
  transition: transform 0.15s ease, opacity 0.15s ease;
}
.guide-enter-from,
.guide-leave-to {
  opacity: 0;
}
.guide-enter-from .guide-panel,
.guide-leave-to .guide-panel {
  opacity: 0;
  transform: translateY(6px) scale(0.98);
}
</style>
