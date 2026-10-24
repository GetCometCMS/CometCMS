<template>
  <div class="space-y-6">
    <section class="card space-y-4 p-6">
      <div>
        <h2 class="card-title">{{ t('site.forms.title') }}</h2>
        <p class="mt-1 text-sm text-slate-500">{{ t('site.forms.websiteHint') }}</p>
      </div>
      <ul class="divide-y divide-slate-100">
        <li v-for="type in types" :key="type.name" class="flex flex-wrap items-center justify-between gap-3 py-3">
          <div>
            <p class="font-medium text-slate-900">{{ type.label || type.name }}</p>
            <p class="text-xs text-slate-500">{{ providerName(type.submission_settings?.captcha || 'none') }}</p>
          </div>
          <router-link v-if="auth.can('schema.update')" :to="`/content-types/${type.name}/edit#submissions`" class="text-sm text-theme-600 hover:underline">
            {{ t('site.forms.editForm') }}
          </router-link>
        </li>
      </ul>
    </section>

    <section v-for="(provider, key) in settings.providers" :key="key" class="card space-y-4 p-6" :aria-labelledby="`captcha-${key}-title`">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 :id="`captcha-${key}-title`" class="card-title">{{ providerName(key) }}</h2>
        <span class="badge" :class="isReady(provider) ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800'">
          {{ t(isReady(provider) ? 'site.forms.ready' : 'site.forms.notConfigured') }}
        </span>
      </div>
      <div class="max-w-sm">
        <label class="form-label" :for="`captcha-${key}-mode`">{{ t('site.forms.configuration') }}</label>
        <select :id="`captcha-${key}-mode`" v-model="provider.mode" class="form-select w-full rounded-lg border-slate-300 text-sm">
          <option value="workspace">{{ t('site.forms.websiteKeys') }}</option>
          <option value="server">{{ t('site.forms.serverKeys') }}</option>
        </select>
      </div>
      <p v-if="provider.mode === 'server'" class="text-sm text-slate-500">
        {{ t(provider.server_configured ? 'site.forms.serverConfigured' : 'site.forms.serverMissing') }}
      </p>
      <template v-else>
        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="form-label" :for="`captcha-${key}-site`">{{ t('site.forms.siteKey') }}</label>
            <input :id="`captcha-${key}-site`" v-model="provider.site_key" type="text" autocomplete="off" spellcheck="false" class="form-input w-full rounded-lg border-slate-300 text-sm" />
            <p v-if="fieldError(key, 'site_key')" role="alert" class="mt-1 text-xs text-red-600">{{ fieldError(key, 'site_key') }}</p>
          </div>
          <div>
            <label class="form-label" :for="`captcha-${key}-secret`">{{ t('site.forms.secretKey') }}</label>
            <input :id="`captcha-${key}-secret`" v-model="provider.secret_key" type="password" autocomplete="new-password" spellcheck="false" :placeholder="t(provider.secret_configured ? 'site.forms.secretSaved' : 'site.forms.secretEnter')" class="form-input w-full rounded-lg border-slate-300 text-sm" />
            <p class="mt-1 text-xs text-slate-500">{{ t(provider.secret_configured ? 'site.forms.secretKeep' : 'site.forms.secretHint') }}</p>
            <p v-if="fieldError(key, 'secret_key')" role="alert" class="mt-1 text-xs text-red-600">{{ fieldError(key, 'secret_key') }}</p>
          </div>
          <div>
            <label class="form-label" :for="`captcha-${key}-hosts`">{{ t('site.forms.hostnames') }}</label>
            <textarea :id="`captcha-${key}-hosts`" v-model="provider.hostnames" rows="3" spellcheck="false" placeholder="example.com" class="form-textarea w-full rounded-lg border-slate-300 text-sm"></textarea>
            <p class="mt-1 text-xs text-slate-500">{{ t('site.forms.hostnamesHint') }}</p>
            <p v-if="fieldError(key, 'hostnames')" role="alert" class="mt-1 text-xs text-red-600">{{ fieldError(key, 'hostnames') }}</p>
          </div>
          <div v-if="key === 'recaptcha_v3'" class="max-w-sm">
            <label class="form-label" :for="`captcha-${key}-score`">{{ t('site.forms.minScore') }}</label>
            <input :id="`captcha-${key}-score`" v-model.number="provider.min_score" type="number" min="0" max="1" step="0.05" class="form-input w-full rounded-lg border-slate-300 text-sm" />
            <p class="mt-1 text-xs text-slate-500">{{ t('site.forms.minScoreHint') }}</p>
            <p v-if="fieldError(key, 'min_score')" role="alert" class="mt-1 text-xs text-red-600">{{ fieldError(key, 'min_score') }}</p>
          </div>
        </div>
        <p class="text-xs text-slate-500">{{ t(key === 'recaptcha_v3' ? 'site.forms.v3KeysHint' : key === 'recaptcha' ? 'site.forms.v2KeysHint' : 'site.forms.turnstileKeysHint') }}</p>
      </template>
    </section>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useSiteStore } from '../../stores/site.js';
import { useAuthStore } from '../../stores/auth.js';
import { useI18n } from '../../i18n/index.js';

const site = useSiteStore();
const auth = useAuthStore();
const { t } = useI18n();
const settings = computed(() => site.draft.form_settings);
const types = computed(() => (site.data.types ?? []).filter((type) => type.allow_submissions));
const names = { turnstile: 'Cloudflare Turnstile', recaptcha: 'Google reCAPTCHA v2', recaptcha_v3: 'Google reCAPTCHA v3' };
const providerName = (key) => names[key] || t('site.forms.captchaNone');
const fieldError = (provider, field) => site.fieldErrors[`providers.${provider}.${field}`];
const isReady = (provider) => provider.mode === 'server' ? provider.server_configured
  : !!provider.site_key?.trim() && (!!provider.secret_key?.trim() || provider.secret_configured)
    && (provider.min_score === undefined || (typeof provider.min_score === 'number' && provider.min_score >= 0 && provider.min_score <= 1));
</script>
