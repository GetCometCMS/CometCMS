<template>
  <span class="inline-flex shrink-0 items-center gap-1">
    <span
      role="img"
      tabindex="0"
      :title="tooltip"
      :aria-label="tooltip"
      class="inline-flex rounded-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-theme-500"
      :class="state === 'review' ? 'text-amber-600' : state === 'translated' ? 'text-theme-600' : 'text-slate-400'"
    >
      <Icon :icon="icons[state]" class="h-3.5 w-3.5" aria-hidden="true" />
    </span>
    <button
      v-if="canReset"
      type="button"
      class="inline-flex rounded-sm text-slate-400 hover:text-theme-600"
      :title="t('localization.reset')"
      :aria-label="t('localization.reset')"
      @click="$emit('reset')"
    >
      <Icon icon="mdi:restore" class="h-3.5 w-3.5" aria-hidden="true" />
    </button>
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { Icon } from '@iconify/vue'
import { useI18n } from '../../../i18n/index.js'

const props = defineProps({ state: { type: String, required: true }, source: { type: String, default: '' }, showSource: Boolean, canReset: Boolean })
defineEmits(['reset'])
const { t } = useI18n()
const icons = { shared: 'mdi:earth', inherited: 'mdi:translate', translated: 'mdi:check-circle-outline', review: 'mdi:alert-circle-outline' }
const tooltip = computed(() => {
  const status = t(`localization.${props.state}`)
  const hint = ['shared', 'translated'].includes(props.state) ? ` — ${t(`localization.${props.state}Hint`)}` : ''
  const source = props.showSource ? `\n${t('localization.source')}: ${props.source}` : ''
  return `${status}${hint}${source}`
})
</script>
