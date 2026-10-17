<template>
  <div class="space-y-5">
    <div>
      <label class="form-label" :for="`menu-item-${item.id}`">{{ t('site.menus.itemLabel') }}</label>
      <input :id="`menu-item-${item.id}`" ref="labelInput" v-model="item.label" type="text" maxlength="120"
        class="form-input w-full rounded-lg border-slate-300 text-sm" :placeholder="t('site.menus.itemLabelPlaceholder')" />
      <details v-if="locales.length" class="mt-3 text-xs text-slate-600">
        <summary class="cursor-pointer font-medium">{{ t('site.menus.translations') }}</summary>
        <div class="mt-2 space-y-2">
          <label v-for="locale in locales" :key="locale" class="flex items-center gap-2">
            <span class="w-8 shrink-0 font-mono uppercase">{{ locale }}</span>
            <input :value="item.labels?.[locale] ?? ''" type="text" maxlength="120"
              class="form-input min-w-0 flex-1 rounded-lg border-slate-300 py-1 text-xs"
              :placeholder="item.label" @input="setLabel(locale, $event.target.value)" />
          </label>
        </div>
      </details>
    </div>
    <div>
      <h3 class="form-label">{{ t('site.menus.target') }}</h3>
      <LinkEditor v-model="item.link" :types="types" :settings="settings" :context="item.id" group-heading
        :allow="['home', 'entry', 'index', 'url', 'anchor', 'none']" @destination-label="prefillLabel" />
      <p v-if="item.link.type === 'none' && !item.label.trim()" class="mt-2 text-xs text-red-700">{{ t('site.link.groupLabel') }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import LinkEditor from './LinkEditor.vue'
import { useI18n } from '../../i18n/index.js'
const props = defineProps({ item: { type: Object, required: true }, types: { type: Array, default: () => [] }, settings: { type: Object, required: true }, locales: { type: Array, default: () => [] } })
const { t } = useI18n()
const labelInput = ref(null)
function setLabel(locale, value) { props.item.labels = { ...(props.item.labels ?? {}), [locale]: value } }
function prefillLabel(label) { if (!props.item.label.trim()) props.item.label = label }
defineExpose({ focusLabel: () => labelInput.value?.focus() })
</script>
