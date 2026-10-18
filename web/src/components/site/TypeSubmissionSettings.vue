<template>
  <section id="submissions" class="scroll-mt-24 space-y-6">
    <div class="card space-y-4 p-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="card-title">{{ t("site.forms.title") }}</h2>
        <router-link v-if="name" :to="`/content/${name}`" class="text-sm text-theme-600 hover:underline">{{ t("site.forms.viewSubmissions") }}</router-link>
      </div>
      <div>
        <label class="form-label" for="submission-status">{{ t("site.forms.status") }}</label>
        <select id="submission-status" :value="settings.status" class="form-select rounded-lg border-slate-300 text-sm" @change="update({ status: $event.target.value })">
          <option value="draft">{{ t("status.draft") }}</option>
          <option value="published">{{ t("status.published") }}</option>
        </select>
        <p class="mt-1 text-xs text-slate-500">{{ t("site.forms.statusHint") }}</p>
      </div>
      <p class="text-xs text-slate-500">{{ t("contentTypeEdit.submissionFieldsHint") }}</p>
      <p v-if="!fillable.length" class="text-xs text-amber-700">{{ t("site.forms.noFields") }}</p>
      <p v-if="unsupported.length" class="text-xs text-amber-700">{{ t("site.forms.unsupported", { fields: unsupported.join(", ") }) }}</p>
    </div>
    <!-- Texts -->
    <section class="card space-y-4 p-6">
      <h2 class="card-title">{{ t("site.forms.texts") }}</h2>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
          <label class="form-label" for="form-submit">{{ t("site.forms.submitLabel") }}</label>
          <input id="form-submit" :value="settings.submit_label" @input="update({ submit_label: $event.target.value })" type="text" maxlength="80" class="form-input w-full rounded-lg border-slate-300 text-sm" :placeholder="t('site.forms.submitDefault')" />
        </div>
        <div class="md:col-span-2">
          <label class="form-label" for="form-success">{{ t("site.forms.successMessage") }}</label>
          <textarea id="form-success" :value="settings.success_message" @input="update({ success_message: $event.target.value })" rows="2" maxlength="1000" class="form-textarea w-full rounded-lg border-slate-300 text-sm" :placeholder="t('site.forms.successDefault')"></textarea>
        </div>
        <div class="md:col-span-2">
          <label class="form-label" for="form-error">{{ t("site.forms.errorMessage") }}</label>
          <textarea id="form-error" :value="settings.error_message" @input="update({ error_message: $event.target.value })" rows="2" maxlength="1000" class="form-textarea w-full rounded-lg border-slate-300 text-sm" :placeholder="t('site.forms.errorDefault')"></textarea>
        </div>
      </div>
      <p class="text-xs text-slate-500">{{ t("site.forms.textsHint") }}</p>
    </section>

    <!-- Spam protection -->
    <section class="card space-y-4 p-6">
      <h2 class="card-title">{{ t("site.forms.spam") }}</h2>
      <ul class="space-y-2 text-sm text-slate-600">
        <li class="flex items-start gap-2">
          <Icon icon="mdi:check-circle" class="mt-0.5 h-4 w-4 shrink-0 text-green-600" />
          <span>{{ t("site.forms.honeypot") }}</span>
        </li>
        <li class="flex items-start gap-2">
          <Icon icon="mdi:check-circle" class="mt-0.5 h-4 w-4 shrink-0 text-green-600" />
          <span>{{ t("site.forms.rateLimit") }}</span>
        </li>
      </ul>
      <div class="max-w-sm">
        <label class="form-label" for="form-captcha">{{ t("site.forms.captcha") }}</label>
        <select id="form-captcha" :value="settings.captcha" @change="update({ captcha: $event.target.value })" class="form-select w-full rounded-lg border-slate-300 text-sm">
          <option value="none">{{ t("site.forms.captchaNone") }}</option>
          <option value="turnstile" disabled>Cloudflare Turnstile ({{ t("site.forms.comingSoon") }})</option>
          <option value="recaptcha" disabled>Google reCAPTCHA ({{ t("site.forms.comingSoon") }})</option>
        </select>
      </div>
    </section>

  </section>
</template>

<script setup>
import { computed } from "vue";
import { Icon } from "@iconify/vue";
import { useI18n } from "../../i18n/index.js";

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
  name: { type: String, default: "" },
  fields: { type: Array, default: () => [] },
});
const emit = defineEmits(["update:modelValue"]);
const { t } = useI18n();
const settings = computed(() => ({ status: "draft", captcha: "none", ...props.modelValue }));
const supported = new Set(["text", "textarea", "markdown", "number", "range", "boolean", "select", "date", "datetime", "color"]);
const fillable = computed(() => props.fields.filter((field) => supported.has(field.type ?? "text")));
const unsupported = computed(() => props.fields.filter((field) => !supported.has(field.type ?? "text")).map((field) => field.label || field.key));
function update(patch) {
  emit("update:modelValue", { ...settings.value, ...patch });
}
</script>
