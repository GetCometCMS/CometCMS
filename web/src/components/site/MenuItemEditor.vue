<template>
  <div class="space-y-5">
    <div>
      <label class="form-label" :for="`menu-item-${item.id}`">{{ t('site.menus.itemLabel') }}</label>
      <LocalizedInput :id="`menu-item-${item.id}`" ref="labelInput" v-model="item.label" maxlength="120" />
    </div>
    <div>
      <h3 class="form-label">{{ t('site.menus.target') }}</h3>
      <LocalizedInput v-model="item.link"><template #default="{ value, update }"><LinkEditor :model-value="value" @update:model-value="update" :types="types" :settings="settings" :context="item.id" group-heading
        :allow="['home', 'entry', 'index', 'url', 'anchor', 'none']" @destination-label="prefillLabel" /></template></LocalizedInput>
      <p v-if="resolveLocalized(item.link, settings.default_locale, settings.default_locale)?.type === 'none' && !resolvedLabel(item.label)" class="mt-2 text-xs text-red-700">{{ t('site.link.groupLabel') }}</p>
    </div>
  </div>
</template>

<script setup>
import LocalizedInput from '../LocalizedInput.vue'
import { resolveLocalized } from '../../composables/localizedValues.js'
import { ref } from 'vue'
import LinkEditor from './LinkEditor.vue'
import { useI18n } from '../../i18n/index.js'
const props = defineProps({ item: { type: Object, required: true }, types: { type: Array, default: () => [] }, settings: { type: Object, required: true }, locales: { type: Array, default: () => [] } })
const { t } = useI18n()
const labelInput = ref(null)
function resolvedLabel(value) { return String(resolveLocalized(value, props.settings.default_locale, props.settings.default_locale) ?? '').trim() }
function prefillLabel(label) { if (!resolvedLabel(props.item.label)) props.item.label = label }
defineExpose({ focusLabel: () => labelInput.value?.focus() })
</script>
