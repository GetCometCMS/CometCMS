<template>
  <div>
    <select :id="id" :value="modelValue" class="form-select w-full rounded-lg border-slate-300 text-sm" @change="$emit('update:modelValue', $event.target.value)">
      <option v-if="allowEmpty" value="">{{ emptyLabel || t('site.option.default') }}</option>
      <option v-if="roles" value="body">{{ t('site.theme.bodyFont') }}</option>
      <option v-if="roles" value="heading">{{ t('site.theme.headingFont') }}</option>
      <option v-if="missing" :value="modelValue">{{ modelValue }} · {{ t('site.fonts.unavailable') }}</option>
      <optgroup v-for="group in groups" :key="group.key" :label="t(`site.fonts.${group.key}`)">
        <option v-for="font in group.fonts" :key="font.id" :value="font.id">{{ font.name }}{{ font.available === false ? ` · ${t('site.fonts.unavailable')}` : '' }}</option>
      </optgroup>
    </select>
    <p v-if="missing || selected?.available === false" class="mt-1 text-xs text-amber-700" role="status">{{ t('site.fonts.missingHint') }}</p>
    <p v-else-if="sample && modelValue" class="mt-1 truncate text-base" :style="{ fontFamily: stack }">{{ t('site.fonts.sample') }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useSiteStore } from '../../stores/site.js'
import { useI18n } from '../../i18n/index.js'

const props = defineProps({
  id: { type: String, default: undefined },
  modelValue: { type: String, default: '' },
  allowEmpty: Boolean,
  emptyLabel: { type: String, default: '' },
  roles: Boolean,
  sample: Boolean,
})
defineEmits(['update:modelValue'])
const site = useSiteStore()
const { t } = useI18n()
const families = computed(() => site.data?.font_families ?? [])
const selected = computed(() => families.value.find((font) => font.id === props.modelValue))
const groups = computed(() => [
  { key: 'system', fonts: (site.data?.fonts ?? []).filter((id) => !families.value.some((family) => family.id === id)).map((id) => ({ id, name: t(`site.font.${id}`) })) },
  { key: 'bundled', fonts: families.value.filter((font) => font.source === 'bundled') },
  { key: 'installed', fonts: families.value.filter((font) => font.source !== 'bundled') },
].filter((group) => group.fonts.length))
const missing = computed(() => props.modelValue && !['body', 'heading'].includes(props.modelValue) && !(site.data?.fonts ?? []).includes(props.modelValue))
const stack = computed(() => {
  const id = ['body', 'heading'].includes(props.modelValue) ? site.draft?.theme?.fonts?.[props.modelValue] : props.modelValue
  return site.data?.font_stacks?.[id] ?? 'inherit'
})
</script>
