<template>
  <component :is="taskFocused && link.type !== 'anchor' ? 'details' : 'div'" v-if="link.type !== 'none'" :key="link.type" :open="taskFocused && (!!link.anchor || !!link.new_tab)" class="space-y-3">
    <summary v-if="taskFocused && link.type !== 'anchor'" class="cursor-pointer text-sm font-medium text-slate-600">{{ t('site.menus.moreOptions') }}</summary>
    <label v-if="['anchor', 'home', 'entry', 'index'].includes(link.type)" class="block">
      <span :class="labelClass">{{ t(link.type === 'anchor' ? 'site.link.anchor' : 'site.link.optionalAnchor') }}</span>
      <div class="flex items-center rounded-lg border border-slate-300 bg-white focus-within:ring-1 focus-within:ring-theme-500">
        <span class="pl-3 text-slate-400" :class="fieldClass" aria-hidden="true">#</span>
        <input :value="link.anchor || ''" type="text" :class="fieldClass" class="form-input min-w-0 flex-1 rounded-lg border-0 focus:ring-0"
          :title="compact ? t(link.type === 'anchor' ? 'site.link.anchorHint' : 'site.link.optionalAnchorHint') : undefined"
          :placeholder="t('site.link.anchorPlaceholder')" @input="$emit('update', { anchor: $event.target.value })" />
      </div>
      <span v-if="!compact" class="mt-1 block text-xs text-slate-500">{{ t(link.type === 'anchor' ? 'site.link.anchorHint' : 'site.link.optionalAnchorHint') }}</span>
    </label>
    <label v-if="allowModal && ['current', 'home', 'entry', 'index'].includes(link.type)" class="flex items-center gap-2 text-xs text-slate-600">
      <input type="checkbox" class="rounded border-slate-300 text-theme-600" :checked="!!link.open_modal" @change="$emit('update', { open_modal: $event.target.checked, new_tab: false })" />
      {{ t('site.link.openModal') }}
    </label>
    <label v-if="!compact && !['none', 'anchor'].includes(link.type)" class="flex items-center gap-2 text-xs text-slate-600">
      <input type="checkbox" class="rounded border-slate-300 text-theme-600" :checked="link.new_tab" @change="$emit('update', { new_tab: $event.target.checked, open_modal: false })" />
      {{ t('site.link.newTab') }}
    </label>
  </component>
</template>
<script setup>
import { computed } from 'vue'
import { useI18n } from '../../i18n/index.js'
const props = defineProps({ link: { type: Object, required: true }, compact: Boolean, taskFocused: Boolean, allowModal: Boolean })
defineEmits(['update'])
const { t } = useI18n()
const labelClass = computed(() => props.compact ? 'mb-1 block text-xs font-medium text-slate-600' : 'form-label')
const fieldClass = computed(() => props.compact ? 'py-1.5 text-xs' : 'text-sm')
</script>
