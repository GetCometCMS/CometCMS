<template>
  <div class="setup-page flex items-center justify-center px-4 py-10 sm:py-12">
    <div class="setup-stars" aria-hidden="true"></div>
    <div class="setup-orbit" aria-hidden="true"></div>
    <div class="setup-comets" aria-hidden="true">
      <span class="setup-comet"></span><span class="setup-comet"></span>
    </div>
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <!-- Logo / wordmark -->
        <img
          v-if="!logoFailed"
          :src="logoSrc"
          alt="CometCMS"
          class="setup-logo mx-auto h-auto w-72 max-w-full object-contain"
          width="1190"
          height="259"
          @error="logoFailed = true"
        />
        <h1 v-else class="text-2xl font-bold text-white">CometCMS</h1>
      </div>

      <!-- Installation success banner -->
      <div class="setup-success rounded-xl p-4 mb-6" role="status">
        <div class="flex items-start gap-3">
          <Icon
            icon="mdi:check-circle"
            class="h-6 w-6 text-emerald-400 shrink-0"
          />
          <div class="flex-1">
            <p class="text-sm font-medium setup-title">
              {{ t("setup.successTitle") }}
            </p>
            <p class="setup-copy mt-1 text-sm">
              {{ t("setup.successBody") }}
            </p>
          </div>
        </div>
      </div>

      <!-- Step indicator -->
      <div class="flex items-center mb-4 px-1">
        <div class="flex flex-col items-center gap-1">
          <div
            class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold transition-colors"
            :class="
              step === 1
                ? 'bg-blue-500 text-white ring-4 ring-blue-500/15'
                : 'bg-emerald-500/20 text-emerald-300 ring-1 ring-inset ring-emerald-400/40'
            "
          >
            <Icon v-if="step > 1" icon="mdi:check" class="h-4 w-4" />
            <span v-else>1</span>
          </div>
          <span
            class="text-[11px] font-medium leading-none"
            :class="step === 1 ? 'text-blue-300' : 'text-emerald-300'"
          >
            {{ t("setup.step1Title") }}
          </span>
        </div>
        <div
          class="mx-3 flex-1 h-px"
          :class="step > 1 ? 'bg-emerald-400/40' : 'bg-white/10'"
        />
        <div class="flex flex-col items-center gap-1">
          <div
            class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold transition-colors"
            :class="
              step === 2
                ? 'bg-blue-500 text-white ring-4 ring-blue-500/15'
                : 'bg-white/5 setup-muted ring-1 ring-inset ring-white/10'
            "
          >
            2
          </div>
          <span
            class="text-[11px] font-medium leading-none"
            :class="step === 2 ? 'text-blue-300' : 'setup-muted'"
          >
            {{ t("setup.step2Title") }}
          </span>
        </div>
      </div>

      <div class="setup-panel rounded-2xl p-6 sm:p-7">
        <!-- Step 1: Workspace -->
        <form v-if="step === 1" @submit.prevent="handleStep1" class="space-y-4">
          <div>
            <p class="text-sm font-semibold setup-title mb-1">
              {{ t("setup.step1Title") }}
            </p>
            <p class="text-xs setup-copy mb-4">
              {{ t("setup.step1Body") }}
            </p>
          </div>

          <div
            v-if="errorMsg"
            class="setup-error p-3 rounded-lg text-sm"
            role="alert"
          >
            {{ errorMsg }}
          </div>

          <div>
            <label for="setup-workspace" class="setup-label">{{ t("setup.workspace") }}</label>
            <input
              id="setup-workspace"
              v-model="workspaceName"
              type="text"
              required
              autofocus
              class="setup-input w-full rounded-lg"
            />
          </div>

          <div>
            <label for="setup-workspace-slug" class="setup-label">{{ t("setup.workspaceSlug") }}</label>
            <input
              id="setup-workspace-slug"
              v-model="workspaceSlug"
              type="text"
              required
              class="setup-input w-full rounded-lg font-mono text-sm"
              :class="
                slugInvalid
                  ? 'setup-input-invalid'
                  : ''
              "
              pattern="[A-Za-z0-9_\-]+"
              @input="onSlugInput"
            />
            <p v-if="slugInvalid" class="mt-1 text-xs text-red-300">
              {{ t("setup.workspaceSlugInvalid") }}
            </p>
            <p v-else class="mt-1 text-xs setup-muted">
              {{ t("setup.workspaceSlugHelp") }}
            </p>
          </div>

          <WorkspaceLanguageFields v-model="languages" class="setup-languages" />

          <fieldset class="space-y-2" aria-describedby="setup-starting-point-help">
            <legend class="setup-label">{{ t("setup.startingPoint") }}</legend>
            <label
              v-for="option in startingPoints"
              :key="option.value"
              class="setup-option flex cursor-pointer items-start gap-3 rounded-xl border p-3.5"
              :class="starterTemplate === option.value ? 'setup-option-selected' : ''"
            >
              <input
                v-model="starterTemplate"
                type="radio"
                name="starter-template"
                :value="option.value"
                class="setup-radio mt-0.5"
              />
              <span>
                <span class="block text-sm font-medium setup-title">{{ t(option.title) }}</span>
                <span class="mt-1 block text-xs setup-copy">{{ t(option.description) }}</span>
              </span>
            </label>
            <p id="setup-starting-point-help" class="text-xs setup-copy">
              {{ t("setup.startingPointHelp") }}
            </p>
          </fieldset>

          <button
            type="submit"
            class="btn-primary w-full justify-center"
            :disabled="slugInvalid || !languages.locales.length"
          >
            {{ t("setup.continue") }}
          </button>
        </form>

        <!-- Step 2: Admin account -->
        <form v-else @submit.prevent="handleSetup" class="space-y-4">
          <div>
            <p class="text-sm font-semibold setup-title mb-1">
              {{ t("setup.step2Title") }}
            </p>
            <p class="text-xs setup-copy mb-4">
              {{ t("setup.step2Body") }}
            </p>
          </div>

          <div
            v-if="errorMsg"
            class="setup-error p-3 rounded-lg text-sm"
            role="alert"
          >
            {{ errorMsg }}
          </div>

          <div>
            <label for="setup-username" class="setup-label">{{ t("login.username") }}</label>
            <input
              id="setup-username"
              v-model="username"
              type="text"
              required
              autocomplete="username"
              autofocus
              class="setup-input w-full rounded-lg"
            />
          </div>

          <div>
            <label for="setup-password" class="setup-label"
              >{{ t("login.password") }}
              <span class="setup-muted font-normal">{{
                t("setup.passwordMin")
              }}</span>
            </label>
            <input
              id="setup-password"
              v-model="password"
              type="password"
              required
              minlength="8"
              autocomplete="new-password"
              class="setup-input w-full rounded-lg"
            />
          </div>

          <div class="flex gap-2">
            <button
              type="button"
              class="btn-secondary"
              @click="
                step = 1;
                errorMsg = '';
              "
            >
              {{ t("setup.back") }}
            </button>
            <button
              type="submit"
              :disabled="loading"
              class="btn-primary flex-1 justify-center"
            >
              <span v-if="loading">{{ t(installingFonts ? "site.fonts.installingDemo" : "setup.creating") }}</span>
              <span v-else>{{ t("setup.createAccount") }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { adminAsset } from "../basePath.js";
import { ref, computed } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../stores/auth.js";
import { useToastStore } from "../stores/toast.js";
import { getActiveWorkspace } from "../api/index.js";
import { installStarterFonts } from "../composables/starterFonts.js";
import { Icon } from "@iconify/vue";
import { useI18n } from "../i18n/index.js";
import { useAutoSlug } from "../composables/useAutoSlug.js";
import { toSlug } from "../composables/fieldBuilderUtils.js";
import WorkspaceLanguageFields from "../components/WorkspaceLanguageFields.vue";

const auth = useAuthStore();
const toast = useToastStore();
const router = useRouter();
const { t } = useI18n();

const step = ref(1);
const username = ref("admin");
const password = ref("");
const workspaceName = ref("");
const languages = ref({ locales: ['en'], default_locale: 'en' });
const starterTemplate = ref("website");
const startingPoints = [
  { value: "website", title: "setup.startWebsite", description: "setup.startWebsiteBody" },
  { value: "empty", title: "setup.startEmpty", description: "setup.startEmptyBody" },
];
const loading = ref(false);
const installingFonts = ref(false);
const errorMsg = ref("");
const logoFailed = ref(false);
const logoSrc = adminAsset("img/white-text/cms-logo-blue.png");

const {
  slug: workspaceSlug,
  touched: slugTouched,
  onSlugInput,
} = useAutoSlug(workspaceName);

const SLUG_RE = /^[A-Za-z0-9_-]+$/;

const slugInvalid = computed(
  () =>
    slugTouched.value &&
    workspaceSlug.value !== "" &&
    !SLUG_RE.test(workspaceSlug.value),
);

function handleStep1() {
  errorMsg.value = "";
  if (!languages.value.locales.length) return;
  const slug = toSlug(workspaceSlug.value);
  if (!slug || !SLUG_RE.test(slug)) {
    slugTouched.value = true;
    return;
  }
  workspaceSlug.value = slug;
  step.value = 2;
}

async function handleSetup() {
  errorMsg.value = "";
  loading.value = true;
  try {
    await auth.setup(
      username.value,
      password.value,
      workspaceName.value,
      workspaceSlug.value,
      starterTemplate.value,
      languages.value,
    );
    if (starterTemplate.value === "website") {
      installingFonts.value = true;
      try {
        await installStarterFonts(getActiveWorkspace());
      } catch {
        toast.error(t("site.fonts.demoFailed"));
      }
    }
    router.push("/dashboard");
  } catch (err) {
    errorMsg.value = err.message ?? t("setup.failed");
  } finally {
    installingFonts.value = false;
    loading.value = false;
  }
}
</script>

<style scoped>
:global(html:has(.setup-page)) {
  color-scheme: dark;
  background: #060914;
}

.setup-page {
  position: relative;
  min-height: 100vh;
  min-height: 100svh;
  overflow: hidden;
  isolation: isolate;
  color-scheme: dark;
  color: #f1f5ff;
  background:
    radial-gradient(ellipse at 50% 48%, #28367238, transparent 48%),
    radial-gradient(ellipse at 88% 100%, #48387930, transparent 55%),
    radial-gradient(ellipse at 0% 10%, #123c5326, transparent 50%),
    #060914;
}

.setup-stars, .setup-comets {
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: -1;
}

.setup-stars::before, .setup-stars::after {
  content: "";
  position: absolute;
  inset: 0;
  background-repeat: repeat;
}

.setup-stars::before {
  opacity: .48;
  background-image:
    radial-gradient(1px 1px at 19px 31px, #dce5ff 90%, transparent),
    radial-gradient(1px 1px at 137px 173px, #dce5ff 90%, transparent),
    radial-gradient(1px 1px at 239px 83px, #dce5ff 90%, transparent);
  background-size: 307px 269px, 419px 347px, 503px 433px;
}

.setup-stars::after {
  opacity: .65;
  background-image:
    radial-gradient(1.5px 1.5px at 113px 87px, #eff4ff 60%, transparent),
    radial-gradient(1.5px 1.5px at 331px 219px, #bccdff 60%, transparent);
  background-size: 557px 449px, 713px 587px;
}

.setup-orbit {
  position: absolute;
  z-index: -1;
  width: max(1100px, 130vw);
  aspect-ratio: 1;
  left: 30%;
  top: 72%;
  border: 1px solid #b5c5ff12;
  border-radius: 50%;
  box-shadow: 0 -15px 100px #6a7dc508, inset 0 35px 100px #6a7dc505;
  pointer-events: none;
}

.setup-logo { filter: drop-shadow(0 0 36px #b1c5ff20); }
.setup-title { color: #f1f5ff; }
.setup-copy { color: #b0bdd3; }
.setup-muted { color: #96a5bf; }

.setup-success {
  background: #0d2428cc;
  border: 1px solid #6ee7b722;
}

.setup-panel {
  background: #101729ed;
  border: 1px solid #b5c5ff24;
  box-shadow: 0 24px 80px #00000040, inset 0 1px 0 #ffffff05;
}

.setup-label {
  display: block;
  margin-bottom: .375rem;
  font-size: .875rem;
  font-weight: 500;
  color: #d9e2f3;
}

.setup-input {
  background: #090f1e;
  border: 1px solid #b5c5ff30;
  padding: .625rem .75rem;
  color: #f1f5ff;
  caret-color: #93c5fd;
  transition: border-color .15s, box-shadow .15s;
}

.setup-input:focus {
  border-color: #60a5fa;
  outline: 2px solid transparent;
  outline-offset: 2px;
  box-shadow: 0 0 0 3px #3b82f626;
}

.setup-input.setup-input-invalid { border-color: #fca5a5; }
.setup-input.setup-input-invalid:focus { box-shadow: 0 0 0 3px #f8717126; }
.setup-input:autofill { box-shadow: 0 0 0 1000px #111d35 inset; }

.setup-languages :deep(.form-label) { color: #d9e2f3; }
.setup-languages :deep(.text-slate-500) { color: #96a5bf; }
.setup-languages :deep([role="combobox"]) { background: #090f1e; border-color: #b5c5ff30; color: #f1f5ff; }
.setup-languages :deep([role="combobox"] .text-slate-900) { color: #f1f5ff; }

.setup-option {
  background: #090f1e66;
  border-color: #b5c5ff24;
  transition: background-color .15s, border-color .15s;
}

.setup-option:hover { background: #18233b; border-color: #b5c5ff50; }
.setup-option-selected, .setup-option-selected:hover { background: #2563eb16; border-color: #60a5fa99; }
.setup-radio { background-color: #090f1e; border-color: #96a5bf; color: #3b82f6; }
.setup-radio:checked { background-color: #3b82f6; border-color: transparent; }
.setup-radio:focus { --tw-ring-color: #60a5fa; --tw-ring-offset-color: #101729; }

.setup-error {
  color: #fecaca;
  background: #7f1d1d30;
  border: 1px solid #f8717140;
}

.setup-page .btn-primary {
  background: #2563eb;
  color: #fff;
  border: 1px solid #60a5fa40;
  --tw-ring-color: #60a5fa;
  --tw-ring-offset-color: #101729;
}

.setup-page .btn-primary:hover { background: #1d4ed8; }
.setup-page .btn-primary:disabled { background: #1e3a6b; color: #96a5bf; cursor: not-allowed; }
.setup-page .btn-secondary { background: #ffffff05; border-color: #b5c5ff30; color: #d9e2f3; --tw-ring-offset-color: #101729; }
.setup-page .btn-secondary:hover { background: #ffffff0d; }

.setup-comet {
  position: absolute;
  top: 12%;
  left: 65%;
  width: 180px;
  height: 1px;
  opacity: 0;
  background: linear-gradient(90deg, transparent, #a9caff80 85%, #fff);
  transform: rotate(145deg);
  animation: setup-comet 14s linear 2s infinite;
}

.setup-comet::after {
  content: "";
  position: absolute;
  right: 0;
  top: -1px;
  width: 3px;
  height: 3px;
  border-radius: 50%;
  background: #f4f8ff;
  box-shadow: 0 0 8px 2px #c9dcff80;
}

.setup-comet:nth-child(2) { top: 35%; left: 95%; width: 120px; animation-delay: 9s; animation-duration: 19s; }

@keyframes setup-comet {
  0% { opacity: 0; transform: translate(0, 0) rotate(145deg); }
  2% { opacity: .85; }
  8% { opacity: 0; transform: translate(-380px, 266px) rotate(145deg); }
  100% { opacity: 0; transform: translate(-380px, 266px) rotate(145deg); }
}

@media (prefers-reduced-motion: reduce) {
  .setup-comet { animation: none; }
  .setup-input, .setup-option { transition: none; }
}
</style>
