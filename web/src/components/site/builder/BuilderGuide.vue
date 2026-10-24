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
                  <button
                    :id="`${videoId}-${step.key}`"
                    type="button"
                    class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-theme-700 hover:underline"
                    :aria-controls="activeStep ? videoId : undefined"
                    :aria-expanded="activeStep?.key === step.key"
                    :aria-label="`${t('site.builder.guide.watchVideo')}: ${t(`site.builder.guide.${step.key}Title`)}`"
                    @click="showVideo(step)"
                  >
                    <Icon icon="mdi:play-circle-outline" class="h-4 w-4" aria-hidden="true" />
                    {{ t('site.builder.guide.watchVideo') }}
                  </button>
                </div>
              </li>
            </ol>

            <section v-if="activeStep" :id="videoId" ref="videoPanelRef" class="mt-5 overflow-hidden rounded-lg border border-slate-200" :aria-label="t(`site.builder.guide.${activeStep.key}Title`)">
              <div class="flex items-center justify-between gap-3 bg-slate-50 px-4 py-3">
                <div>
                  <h3 class="text-sm font-semibold text-slate-900">{{ t(`site.builder.guide.${activeStep.key}Title`) }}</h3>
                  <p class="mt-0.5 text-xs text-slate-500">{{ t('site.builder.guide.videoHint') }}</p>
                </div>
                <button type="button" class="btn-ghost btn-icon" :aria-label="t('site.builder.guide.closeVideo')" @click="closeVideo">
                  <Icon icon="mdi:close" class="h-4 w-4" />
                </button>
              </div>
              <video
                :key="activeStep.key"
                class="block aspect-[8/5] w-full bg-slate-100"
                controls
                playsinline
                preload="none"
                :poster="activeStep.poster"
                :aria-label="t(`site.builder.guide.${activeStep.key}Title`)"
                :aria-description="t(`site.builder.guide.${activeStep.key}Body`)"
              >
                <source :src="activeStep.video" type="video/mp4" />
                {{ t('site.builder.guide.videoFallback') }}
                <a :href="activeStep.video">{{ t('site.builder.guide.watchVideo') }}</a>
              </video>
            </section>

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
import { computed, nextTick, ref, useId, watch } from "vue";
import { Icon } from "@iconify/vue";
import { useDialogFocus } from "../../../composables/useDialogFocus.js";
import { useI18n } from "../../../i18n/index.js";
import addVideo from "../../../assets/builder-guide/add-elements.mp4";
import addPoster from "../../../assets/builder-guide/add-elements.webp";
import editVideo from "../../../assets/builder-guide/edit-content.mp4";
import editPoster from "../../../assets/builder-guide/edit-content.webp";
import responsiveVideo from "../../../assets/builder-guide/responsive-preview.mp4";
import responsivePoster from "../../../assets/builder-guide/responsive-preview.webp";
import saveVideo from "../../../assets/builder-guide/save-history.mp4";
import savePoster from "../../../assets/builder-guide/save-history.webp";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
});
const emit = defineEmits(["update:modelValue"]);
const { t } = useI18n();
const titleId = `builder-guide-${useId()}`;
const dialogRef = ref(null);
const videoId = `${titleId}-video`;
const videoPanelRef = ref(null);
const activeStep = ref(null);
watch(() => props.modelValue, (open) => {
  if (!open) activeStep.value = null;
});

/** Mirrors the outline colors in BuilderCanvas. */
const STRUCTURE = [
  { kind: "section", color: "#4b5fc8", depth: 0 },
  { kind: "row", color: "#4c8a2b", depth: 1 },
  { kind: "column", color: "#b7653e", depth: 2 },
  { kind: "component", color: "#8650b1", dashed: true, depth: 0 },
];

const steps = [
  { key: "step1", icon: "mdi:plus-box-outline", video: addVideo, poster: addPoster },
  { key: "step2", icon: "mdi:cursor-default-click-outline", video: editVideo, poster: editPoster },
  { key: "step3", icon: "mdi:devices", video: responsiveVideo, poster: responsivePoster },
  { key: "step4", icon: "mdi:content-save-outline", video: saveVideo, poster: savePoster },
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

async function showVideo(step) {
  activeStep.value = activeStep.value?.key === step.key ? null : step;
  await nextTick();
  videoPanelRef.value?.scrollIntoView({ block: "nearest" });
}

async function closeVideo() {
  const key = activeStep.value?.key;
  activeStep.value = null;
  await nextTick();
  document.getElementById(`${videoId}-${key}`)?.focus();
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
