<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/40 p-4" @mousedown.self="close">
      <form ref="dialog" role="dialog" aria-modal="true" :aria-labelledby="titleId" tabindex="-1" class="max-h-[90vh] w-full max-w-lg space-y-5 overflow-y-auto rounded-xl bg-white p-6 shadow-xl" @submit.prevent="submit">
        <div>
          <h2 :id="titleId" class="text-lg font-semibold text-slate-900">{{ t(adding ? 'site.menus.addItem' : 'site.menus.changeDestination') }}</h2>
          <p v-if="parentLabel" class="mt-1 text-sm text-slate-500">{{ t('site.menus.addUnder', { name: parentLabel }) }}</p>
        </div>
        <div>
          <h3 class="form-label">{{ t('site.menus.destination') }}</h3>
          <LinkEditor :model-value="draft" :types="types" :settings="settings" :context="titleId" group-heading task-focused :allow="['entry', 'index', 'home', 'url', 'anchor', 'none']" @update:model-value="draft = $event" @destination-label="prefill" />
        </div>
        <div v-if="adding">
          <label :for="`${titleId}-label`" class="form-label">{{ t('site.menus.displayText') }}</label>
          <input :id="`${titleId}-label`" v-model="label" required maxlength="120" class="form-input w-full rounded-lg border-slate-300 text-sm" />
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="close">{{ t('common.cancel') }}</button>
          <button type="submit" class="btn-primary" :disabled="!!issue || (adding && !label.trim())">{{ t(adding ? 'site.menus.addItem' : 'site.menus.useDestination') }}</button>
        </div>
      </form>
    </div>
  </Teleport>
</template>
<script setup>
import { computed, ref, toRef, useId, watch } from 'vue'
import LinkEditor from './LinkEditor.vue'
import { useDialogFocus } from '../../composables/useDialogFocus.js'
import { linkIssue } from '../../composables/siteLinks.js'
import { useI18n } from '../../i18n/index.js'
const props = defineProps({ modelValue: Boolean, adding: Boolean, link: { type: Object, default: () => ({ type: 'entry', new_tab: false }) }, parentLabel: { type: String, default: '' }, types: { type: Array, required: true }, settings: { type: Object, required: true } })
const emit = defineEmits(['update:modelValue', 'confirm'])
const { t } = useI18n()
const titleId = useId()
const dialog = ref(null)
const draft = ref({})
const label = ref('')
const suggestedLabel = ref('')
const issue = computed(() => linkIssue(draft.value, props.adding ? label.value : 'group'))
watch(() => props.modelValue, (open) => {
  if (open) { draft.value = JSON.parse(JSON.stringify(props.link)); label.value = ''; suggestedLabel.value = '' }
}, { immediate: true })
function prefill(value) {
  if (!label.value.trim() || label.value === suggestedLabel.value) label.value = value
  suggestedLabel.value = value
}
function close() { emit('update:modelValue', false) }
function submit() { if (!issue.value && (!props.adding || label.value.trim())) { emit('confirm', { link: draft.value, label: label.value.trim() || suggestedLabel.value }); close() } }
useDialogFocus({ open: toRef(props, 'modelValue'), container: dialog, close })
</script>
