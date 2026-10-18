<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/40 p-4 backdrop-blur-[2px]" @mousedown.self="close">
        <section ref="dialogRef" role="dialog" aria-modal="true" :aria-labelledby="titleId" tabindex="-1" class="flex h-[min(52rem,90vh)] w-full max-w-4xl flex-col rounded-2xl bg-white shadow-xl">
          <header class="flex items-start gap-4 border-b border-slate-200 px-6 py-4">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-theme-50 text-theme-600"><Icon icon="mdi:format-font" class="h-5 w-5" /></div>
            <div class="min-w-0 flex-1">
              <h2 :id="titleId" class="text-base font-semibold text-slate-900">{{ t('site.fonts.manage') }}</h2>
              <p class="text-sm text-slate-500">{{ t('site.fonts.libraryHint') }}</p>
            </div>
            <button type="button" class="icon-btn" :disabled="busy" :aria-label="t('common.close')" @click="close"><Icon icon="mdi:close" class="h-5 w-5" /></button>
          </header>
          <FontLibrary class="min-h-0 flex-1 px-6 pb-5 pt-4" :draft-nodes="draftNodes" @busy="busy = $event" />
        </section>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, toRef, useId, watch } from 'vue'
import { Icon } from '@iconify/vue'
import FontLibrary from './FontLibrary.vue'
import { useSiteStore } from '../../stores/site.js'
import { useI18n } from '../../i18n/index.js'
import { useDialogFocus } from '../../composables/useDialogFocus.js'

const props = defineProps({ modelValue: Boolean, draftNodes: { type: Array, default: () => [] } })
const emit = defineEmits(['update:modelValue'])
const { t } = useI18n()
const site = useSiteStore()
const dialogRef = ref(null)
const titleId = `font-manager-${useId()}`
const busy = ref(false)

function close() {
  if (!busy.value) emit('update:modelValue', false)
}
useDialogFocus({ open: toRef(props, 'modelValue'), container: dialogRef, close, closeBlocked: () => busy.value })
watch(() => props.modelValue, () => { busy.value = false })
watch(() => site.data?.workspace, () => emit('update:modelValue', false))
</script>
