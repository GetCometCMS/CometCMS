<template>
  <div v-if="loading" class="flex justify-center py-16"><LoadingSpinner /></div>
  <div v-else-if="loadError" class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ loadError }}</div>

  <div v-else class="grid grid-cols-1 gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
    <router-link v-if="backToBuilder" :to="backToBuilder" class="inline-flex items-center gap-1 text-sm text-theme-600 hover:underline lg:col-span-2">
      <Icon icon="mdi:arrow-left" class="h-4 w-4" />
      {{ t("site.forms.backToBuilder") }}
    </router-link>
    <!-- Form list -->
    <section class="card self-start p-3">
      <div class="mb-2 flex items-center justify-between gap-2 px-1">
        <h2 class="text-sm font-semibold text-slate-700">{{ t("site.forms.title") }}</h2>
        <button type="button" class="btn-secondary btn-sm" :disabled="!types.length" @click="select(null)">
          <Icon icon="mdi:plus" class="h-4 w-4" />
          {{ t("site.forms.new") }}
        </button>
      </div>
      <ul v-if="forms.length" class="space-y-1">
        <li v-for="form in forms" :key="form.id">
          <button
            type="button"
            class="flex w-full items-start gap-2 rounded-lg px-2.5 py-2 text-left text-sm transition-colors"
            :class="form.id === draft?.id ? 'bg-theme-50 text-theme-800' : 'text-slate-700 hover:bg-slate-50'"
            :aria-current="form.id === draft?.id ? 'true' : undefined"
            @click="select(form)"
          >
            <Icon icon="mdi:form-select" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
            <span class="min-w-0 flex-1">
              <span class="block truncate font-medium">{{ form.name }}</span>
              <span class="block truncate text-xs text-slate-500">
                {{ typeLabel(form.type) }} · {{ t("site.forms.placedCount", { count: form.usages.length }) }}
              </span>
            </span>
          </button>
        </li>
      </ul>
      <p v-else class="px-1 py-2 text-sm text-slate-500">{{ t("site.forms.empty") }}</p>
      <p v-if="!types.length" class="px-1 py-2 text-xs text-slate-500">{{ t("site.forms.noTypes") }}</p>
    </section>

    <!-- Editor -->
    <div v-if="draft" class="space-y-6">
      <section class="card space-y-4 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-sm font-semibold text-slate-700">{{ draft.id ? t("site.forms.edit") : t("site.forms.new") }}</h2>
          <router-link v-if="draft.id && draft.type" :to="`/content/${draft.type}`" class="inline-flex items-center gap-1 text-sm text-theme-600 hover:underline">
            <Icon icon="mdi:inbox-arrow-down-outline" class="h-4 w-4" />
            {{ t("site.forms.viewSubmissions") }}
          </router-link>
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label class="form-label" for="form-name">{{ t("site.forms.name") }}</label>
            <input id="form-name" v-model="draft.name" type="text" maxlength="120" class="form-input w-full rounded-lg border-slate-300 text-sm" :placeholder="t('site.forms.namePlaceholder')" />
            <p v-if="errors.name" class="mt-1 text-xs text-red-600">{{ errors.name }}</p>
          </div>
          <div>
            <label class="form-label" for="form-type">{{ t("site.forms.type") }}</label>
            <select id="form-type" :value="draft.type" class="form-select w-full rounded-lg border-slate-300 text-sm" @change="changeType($event.target.value)">
              <option value="" disabled>{{ t("site.builder.chooseType") }}</option>
              <option v-for="type in types" :key="type.name" :value="type.name">
                {{ type.label }}{{ type.visibility === "private" ? ` (${t("site.forms.private")})` : "" }}
              </option>
            </select>
            <p v-if="errors.type" class="mt-1 text-xs text-red-600">{{ errors.type }}</p>
            <p v-else class="mt-1 text-xs text-slate-500">{{ t("site.forms.typeHint") }}</p>
          </div>
          <div>
            <label class="form-label" for="form-status">{{ t("site.forms.status") }}</label>
            <select id="form-status" v-model="draft.status" class="form-select w-full rounded-lg border-slate-300 text-sm">
              <option v-for="status in statuses" :key="status" :value="status">{{ t(`status.${status}`) }}</option>
            </select>
            <p class="mt-1 text-xs text-slate-500">{{ t("site.forms.statusHint") }}</p>
          </div>
        </div>
      </section>

      <!-- Fields: always all of the type's fillable fields -->
      <section v-if="type" class="card space-y-4 p-6">
        <div>
          <h2 class="text-sm font-semibold text-slate-700">{{ t("site.forms.fields") }}</h2>
          <p class="mt-1 text-xs text-slate-500">{{ t("site.forms.fieldsHint", { type: type.label }) }}</p>
        </div>
        <ul v-if="acceptedFields.length" class="grid grid-cols-12 gap-2">
          <li
            v-for="field in acceptedFields"
            :key="field.key"
            class="col-span-12 flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2"
            :class="field.span"
          >
            <Icon :icon="fieldTypeIcon(field.type)" class="h-4 w-4 shrink-0 text-slate-400" />
            <span class="min-w-0 flex-1 truncate text-sm text-slate-800">
              {{ field.label }}
              <span v-if="field.required" class="text-red-500" :title="t('site.forms.required')">*</span>
            </span>
            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">{{ field.type }}</span>
          </li>
        </ul>
        <p v-else class="text-sm text-slate-500">{{ t("site.forms.noFields") }}</p>
        <p v-if="unsupported.length" class="text-xs text-slate-400">
          {{ t("site.forms.unsupported", { fields: unsupported.join(", ") }) }}
        </p>
        <router-link v-if="canEditType" :to="`/content-types/${draft.type}/edit`" class="inline-flex items-center gap-1 text-xs text-theme-600 hover:underline">
          <Icon icon="mdi:pencil-outline" class="h-3.5 w-3.5" />
          {{ t("site.forms.editType") }}
        </router-link>
      </section>

      <!-- Texts -->
      <section class="card space-y-4 p-6">
        <h2 class="text-sm font-semibold text-slate-700">{{ t("site.forms.texts") }}</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label class="form-label" for="form-submit">{{ t("site.forms.submitLabel") }}</label>
            <input id="form-submit" v-model="draft.submit_label" type="text" maxlength="80" class="form-input w-full rounded-lg border-slate-300 text-sm" :placeholder="t('site.forms.submitDefault')" />
          </div>
          <div class="md:col-span-2">
            <label class="form-label" for="form-success">{{ t("site.forms.successMessage") }}</label>
            <textarea id="form-success" v-model="draft.success_message" rows="2" maxlength="1000" class="form-textarea w-full rounded-lg border-slate-300 text-sm" :placeholder="t('site.forms.successDefault')"></textarea>
          </div>
          <div class="md:col-span-2">
            <label class="form-label" for="form-error">{{ t("site.forms.errorMessage") }}</label>
            <textarea id="form-error" v-model="draft.error_message" rows="2" maxlength="1000" class="form-textarea w-full rounded-lg border-slate-300 text-sm" :placeholder="t('site.forms.errorDefault')"></textarea>
          </div>
        </div>
        <p class="text-xs text-slate-500">{{ t("site.forms.textsHint") }}</p>
      </section>

      <!-- Spam protection -->
      <section class="card space-y-4 p-6">
        <h2 class="text-sm font-semibold text-slate-700">{{ t("site.forms.spam") }}</h2>
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
          <select id="form-captcha" v-model="draft.captcha" class="form-select w-full rounded-lg border-slate-300 text-sm">
            <option value="none">{{ t("site.forms.captchaNone") }}</option>
            <option value="turnstile" disabled>Cloudflare Turnstile ({{ t("site.forms.comingSoon") }})</option>
            <option value="recaptcha" disabled>Google reCAPTCHA ({{ t("site.forms.comingSoon") }})</option>
          </select>
        </div>
      </section>

      <!-- Placement -->
      <section v-if="draft.id" class="card space-y-3 p-6">
        <h2 class="text-sm font-semibold text-slate-700">{{ t("site.forms.placement") }}</h2>
        <ul v-if="current?.usages.length" class="space-y-1">
          <li v-for="(usage, index) in current.usages" :key="index">
            <router-link :to="usageLink(usage)" class="inline-flex items-center gap-1.5 text-sm text-theme-600 hover:underline">
              <Icon icon="mdi:open-in-app" class="h-4 w-4" />
              {{ usageLabel(usage) }}
            </router-link>
          </li>
        </ul>
        <p v-else class="text-sm text-slate-500">{{ t("site.forms.notPlaced") }}</p>
      </section>

      <div class="flex flex-wrap items-center gap-2">
        <button v-if="draft.id" type="button" class="btn-secondary text-red-600" :disabled="saving" @click="deleteOpen = true">
          <Icon icon="mdi:trash-can-outline" class="h-4 w-4" />
          {{ t("common.delete") }}
        </button>
        <div class="ml-auto flex items-center gap-2">
          <button v-if="dirty" type="button" class="btn-secondary" :disabled="saving" @click="discard">{{ t("site.forms.discard") }}</button>
          <button type="button" class="btn-primary relative" :disabled="saving || !dirty" @click="save">
            {{ saving ? t("common.saving") : draft.id ? t("site.forms.save") : t("site.forms.create") }}
            <span v-if="dirty && !saving" class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-amber-400 ring-2 ring-white"></span>
          </button>
        </div>
      </div>
    </div>

    <section v-else class="card flex flex-col items-center gap-3 p-10 text-center">
      <Icon icon="mdi:form-select" class="h-10 w-10 text-slate-300" />
      <p class="max-w-md text-sm text-slate-500">{{ t("site.forms.intro") }}</p>
      <button v-if="types.length" type="button" class="btn-primary" @click="select(null)">
        <Icon icon="mdi:plus" class="h-4 w-4" />
        {{ t("site.forms.new") }}
      </button>
    </section>

    <ConfirmModal
      v-model="deleteOpen"
      :title="t('site.forms.deleteTitle', { name: current?.name ?? '' })"
      :message="current?.usages.length ? t('site.forms.deletePlaced', { count: current.usages.length }) : t('site.forms.deleteMessage')"
      :loading="saving"
      @confirm="remove"
    />
    <ConfirmModal
      :model-value="pendingSelect !== undefined"
      :title="t('unsaved.title')"
      :message="t('unsaved.message')"
      :confirm-label="t('unsaved.leave')"
      :cancel-label="t('unsaved.stay')"
      variant="warning"
      @update:model-value="(open) => { if (!open) pendingSelect = undefined }"
      @confirm="open(pendingSelect)"
    />
    <ConfirmModal
      v-model="leavePromptOpen"
      :title="t('unsaved.title')"
      :message="t('unsaved.message')"
      :confirm-label="t('unsaved.leave')"
      :cancel-label="t('unsaved.stay')"
      variant="warning"
      @confirm="confirmLeave"
    />
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import { Icon } from "@iconify/vue";
import ConfirmModal from "../ConfirmModal.vue";
import LoadingSpinner from "../LoadingSpinner.vue";
import { api } from "../../api/index.js";
import { useAuthStore } from "../../stores/auth.js";
import { useToastStore } from "../../stores/toast.js";
import { useFieldTypeMeta } from "../../composables/useFieldTypeMeta.js";
import { useUnsavedChangesGuard } from "../../composables/useUnsavedChangesGuard.js";
import { useI18n } from "../../i18n/index.js";

/**
 * Website forms: each one shows fields of a content type and stores every
 * submission as a new entry of it. Forms are placed on pages with the
 * builder's form element and saved here one at a time.
 */
const { t } = useI18n();
const route = useRoute();
const toast = useToastStore();
const auth = useAuthStore();
const { fieldTypeIcon } = useFieldTypeMeta();

const loading = ref(true);
const loadError = ref("");
const saving = ref(false);
const forms = ref([]);
const types = ref([]);
const fieldTypes = ref([]);
const statuses = ref(["draft", "published"]);
/** The form being edited; a new one has no id yet. */
const draft = ref(null);
const saved = ref("");
const errors = ref({});
const deleteOpen = ref(false);
/** A form (or null for a new one) waiting for the unsaved-changes prompt. */
const pendingSelect = ref(undefined);

/** Opened from the builder's forms panel: the way back there. */
const backToBuilder = computed(() => {
  const back = typeof route.query.back === "string" ? route.query.back : "";
  return back.startsWith("/page-builder/builder/") ? back : "";
});
const current = computed(() => forms.value.find((form) => form.id === draft.value?.id) ?? null);
const dirty = computed(() => draft.value !== null && snapshot(draft.value) !== saved.value);
const type = computed(() => types.value.find((item) => item.name === draft.value?.type) ?? null);
const canEditType = computed(() => !!draft.value?.type && auth.can("schema.update", `schema:${draft.value.type}`));
const { leavePromptOpen, confirmLeave } = useUnsavedChangesGuard(dirty, { guardUpdates: true });

const SPANS = { "1/3": "md:col-span-4", "1/2": "md:col-span-6", "2/3": "md:col-span-8" };

/** The fields the form shows: every field of its type visitors can fill in, laid out like on the page. */
const acceptedFields = computed(() => Object.entries(type.value?.fields ?? {})
  .filter(([key, config]) => !isSystemField(key) && fieldTypes.value.includes(config?.type ?? "text"))
  .map(([key, config]) => ({
    key,
    type: config.type ?? "text",
    label: config.label || key.charAt(0).toUpperCase() + key.slice(1).replace(/[_-]/g, " "),
    required: !!config.required,
    span: SPANS[config.layout?.width] ?? "",
  })));
const unsupported = computed(() => Object.entries(type.value?.fields ?? {})
  .filter(([key, config]) => !isSystemField(key) && !fieldTypes.value.includes(config?.type ?? "text"))
  .map(([key, config]) => config.label || key));

/** Title and slug are filled in by the form itself. */
function isSystemField(key) {
  return key === "title" || key === "slug" || key === type.value?.slug_field;
}

function typeLabel(name) {
  return types.value.find((item) => item.name === name)?.label ?? name;
}

function snapshot(form) {
  return JSON.stringify({
    name: form.name,
    type: form.type,
    submit_label: form.submit_label,
    success_message: form.success_message,
    error_message: form.error_message,
    status: form.status,
    captcha: form.captcha,
  });
}

function blank() {
  const first = types.value.find((item) => item.visibility === "private") ?? types.value[0];
  const form = { id: "", name: "", type: "", submit_label: "", success_message: "", error_message: "", status: "draft", captcha: "none" };
  if (first) form.type = first.name;
  return form;
}

function open(form) {
  pendingSelect.value = undefined;
  errors.value = {};
  draft.value = form ? { ...form } : blank();
  // A new form counts as unsaved until it is created.
  saved.value = form ? snapshot(draft.value) : "";
}

function select(form) {
  if (form && form.id === draft.value?.id) return;
  if (dirty.value) {
    pendingSelect.value = form;
    return;
  }
  open(form);
}

function discard() {
  open(current.value);
}

function changeType(name) {
  draft.value.type = name;
  errors.value = {};
}

function usageLink(usage) {
  if (usage.target === "region") return `/page-builder/builder/region/${usage.region}`;
  if (usage.target === "component") return `/page-builder/builder/component/${usage.id}`;
  return `/page-builder/builder/layout/${usage.type}/${usage.variant}`;
}

function usageLabel(usage) {
  if (usage.target === "region") return t(`site.region.${usage.region}`);
  if (usage.target === "component") return `${t("site.component.group")}: ${usage.name}`;
  return `${typeLabel(usage.type)} · ${t(`site.variant.${usage.variant}`)}`;
}

async function load() {
  loading.value = true;
  loadError.value = "";
  try {
    const data = (await api.site.forms()).data;
    forms.value = data.forms ?? [];
    types.value = data.types ?? [];
    fieldTypes.value = data.field_types ?? [];
    statuses.value = data.statuses ?? statuses.value;
    if (!draft.value && forms.value.length) {
      open(forms.value.find((form) => form.id === route.query.form) ?? forms.value[0]);
    }
  } catch (err) {
    loadError.value = err.message;
  } finally {
    loading.value = false;
  }
}

async function save() {
  if (saving.value || !dirty.value) return;
  saving.value = true;
  errors.value = {};
  const body = JSON.parse(snapshot(draft.value));
  try {
    const form = draft.value.id
      ? (await api.site.updateForm(draft.value.id, body)).data
      : (await api.site.createForm(body)).data;
    forms.value = [...forms.value.filter((item) => item.id !== form.id), form].sort((a, b) => a.name.localeCompare(b.name));
    open(form);
    toast.success(t("site.forms.saved"));
  } catch (err) {
    errors.value = err.fields ?? {};
    toast.error(err.message);
  } finally {
    saving.value = false;
  }
}

async function remove() {
  if (saving.value || !current.value) return;
  saving.value = true;
  try {
    await api.site.deleteForm(current.value.id);
    forms.value = forms.value.filter((item) => item.id !== current.value.id);
    deleteOpen.value = false;
    draft.value = null;
    if (forms.value.length) open(forms.value[0]);
    toast.success(t("site.forms.deleted"));
  } catch (err) {
    toast.error(err.message);
  } finally {
    saving.value = false;
  }
}

function onKeydown(event) {
  if (event.target?.closest?.('[role="dialog"], [role="alertdialog"]')) return;
  if ((event.ctrlKey || event.metaKey) && event.key === "s" && dirty.value) {
    event.preventDefault();
    save();
  }
}

function onWorkspaceChanged() {
  draft.value = null;
  load();
}

onMounted(() => {
  load();
  window.addEventListener("keydown", onKeydown);
  window.addEventListener("cometcms:workspace-changed", onWorkspaceChanged);
});

onBeforeUnmount(() => {
  window.removeEventListener("keydown", onKeydown);
  window.removeEventListener("cometcms:workspace-changed", onWorkspaceChanged);
});
</script>

<style scoped>
.field-action {
  display: inline-flex;
  height: 1.75rem;
  width: 1.75rem;
  align-items: center;
  justify-content: center;
  border-radius: 0.375rem;
  color: rgb(var(--color-slate-400));
}
.field-action:hover:not(:disabled) {
  background: rgb(var(--color-slate-100));
  color: rgb(var(--color-slate-900));
}
.field-action:disabled {
  opacity: 0.35;
}
</style>
