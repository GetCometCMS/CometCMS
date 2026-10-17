<template>
  <div class="fixed top-4 right-4 z-50 space-y-2 pointer-events-none">
    <transition-group name="toast">
      <div
        v-for="toast in toastStore.toasts"
        :key="toast.id"
        :role="toast.type === 'error' ? 'alert' : 'status'"
        :aria-live="toast.type === 'error' ? 'assertive' : 'polite'"
        class="pointer-events-auto flex max-w-sm items-center gap-3 px-4 py-3 rounded-xl shadow-lg text-sm font-medium"
        :class="
          toast.type === 'error'
            ? 'bg-red-600 text-white'
            : 'bg-slate-900 text-white'
        "
      >
        <span class="min-w-0 flex-1">{{ toast.message }}</span>
        <button
          type="button"
          class="-mr-1 shrink-0 rounded-md p-1 text-white/70 transition hover:bg-white/10 hover:text-white"
          :aria-label="t('common.dismiss')"
          @click="toastStore.remove(toast.id)"
        >
          <Icon icon="mdi:close" class="h-4 w-4" />
        </button>
      </div>
    </transition-group>
  </div>
</template>

<script setup>
import { Icon } from "@iconify/vue";
import { useToastStore } from "../stores/toast.js";
import { useI18n } from "../i18n/index.js";

const toastStore = useToastStore();
const { t } = useI18n();
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.25s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
