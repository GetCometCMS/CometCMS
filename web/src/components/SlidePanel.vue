<template>
  <Teleport to="body">
    <Transition name="slide-panel">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-40 flex justify-end bg-slate-900/30"
        @mousedown.self="close"
      >
        <aside
          ref="panelRef"
          class="h-full w-full flex flex-col bg-white shadow-xl"
          :style="{ maxWidth: width }"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="titleId"
          :aria-describedby="subtitle ? subtitleId : undefined"
          tabindex="-1"
        >
          <!-- Header -->
          <div
            class="px-6 py-4 border-b border-slate-200 flex items-start justify-between gap-3 shrink-0"
          >
            <div class="min-w-0">
              <h2
                :id="titleId"
                class="text-lg font-semibold text-slate-900 leading-tight truncate"
              >
                {{ title }}
              </h2>
              <p v-if="subtitle" :id="subtitleId" class="text-xs text-slate-500 mt-0.5">
                {{ subtitle }}
              </p>
            </div>
            <button
              type="button"
              data-dialog-initial-focus
              class="btn-secondary py-1.5 px-3 shrink-0"
              @click="close"
            >
              Close
            </button>
          </div>

          <!-- Body -->
          <div class="flex-1 overflow-y-auto">
            <slot />
          </div>
        </aside>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, toRef, useId } from "vue";
import { useDialogFocus } from "../composables/useDialogFocus.js";

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  title: { type: String, default: "" },
  subtitle: { type: String, default: "" },
  width: { type: String, default: "36rem" },
});

const emit = defineEmits(["update:modelValue"]);
const panelRef = ref(null);
const componentId = useId();
const titleId = `${componentId}-title`;
const subtitleId = `${componentId}-subtitle`;

function close() {
  emit("update:modelValue", false);
}

useDialogFocus({
  open: toRef(props, "modelValue"),
  container: panelRef,
  close,
});
</script>

<style scoped>
.slide-panel-enter-active,
.slide-panel-leave-active {
  transition: opacity 0.2s ease;
}
.slide-panel-enter-active aside,
.slide-panel-leave-active aside {
  transition: transform 0.2s ease;
}
.slide-panel-enter-from,
.slide-panel-leave-to {
  opacity: 0;
}
.slide-panel-enter-from aside,
.slide-panel-leave-to aside {
  transform: translateX(100%);
}
</style>
