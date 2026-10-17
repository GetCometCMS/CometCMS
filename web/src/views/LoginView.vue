<template>
  <div class="login-page flex items-center justify-center px-4 py-10 sm:py-12">
    <div class="login-stars" aria-hidden="true"></div>
    <div class="login-orbit" aria-hidden="true"></div>
    <div class="login-comets" aria-hidden="true">
      <span class="login-comet"></span><span class="login-comet"></span>
    </div>
    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <img
          v-if="!logoFailed"
          :src="logoSrc"
          alt="CometCMS"
          class="login-logo mx-auto mb-4 h-auto w-72 max-w-full object-contain"
          width="1190"
          height="259"
          @error="logoFailed = true"
        />
        <h1 v-else class="mb-4 text-2xl font-bold text-white">CometCMS</h1>
        <p class="login-copy mt-1 text-sm">{{ t("login.subtitle") }}</p>
      </div>

      <div class="login-panel rounded-xl p-6">
        <form @submit.prevent="handleLogin" class="space-y-4">
          <div
            v-if="sessionExpired && !errorMsg"
            role="status"
            class="login-notice p-3 rounded-lg text-sm"
          >
            {{ t("login.sessionExpired") }}
          </div>
          <div
            v-if="errorMsg"
            role="alert"
            class="p-3 rounded-lg text-sm"
            :class="
              rateLimited
                ? 'login-notice'
                : 'login-error'
            "
          >
            {{ errorMsg }}
          </div>

          <div>
            <label for="login-username" class="login-label">{{ t("login.username") }}</label>
            <input
              id="login-username"
              v-model="username"
              type="text"
              required
              autocomplete="username"
              autofocus
              class="login-input w-full rounded-lg"
            />
          </div>

          <div>
            <label for="login-password" class="login-label">{{ t("login.password") }}</label>
            <input
              id="login-password"
              v-model="password"
              type="password"
              required
              autocomplete="current-password"
              class="login-input w-full rounded-lg"
            />
          </div>

          <label class="flex cursor-pointer items-center gap-2 text-sm login-copy">
            <input
              v-model="remember"
              type="checkbox"
              class="login-checkbox rounded"
            />
            <span>{{ t("login.stayLoggedIn") }}</span>
          </label>

          <button
            type="submit"
            :disabled="loading"
            class="btn-primary w-full justify-center"
          >
            <span v-if="loading">{{ t("login.signingIn") }}</span>
            <span v-else>{{ t("login.signIn") }}</span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { adminAsset } from "../basePath.js";
import { ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "../stores/auth.js";
import { useI18n } from "../i18n/index.js";

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const sessionExpired = route.query.expired === "1";

// Only return to paths inside the admin, never to another site.
function redirectTarget() {
  const target = typeof route.query.redirect === "string" ? route.query.redirect : "";
  return target.startsWith("/") && !target.startsWith("//") && !target.startsWith("/login")
    ? target
    : "/dashboard";
}
const { t } = useI18n();
const username = ref("");
const password = ref("");
const remember = ref(false);
const loading = ref(false);
const errorMsg = ref("");
const rateLimited = ref(false);
const logoSrc = adminAsset("img/cms-logo-white.png");
const logoFailed = ref(false);

async function handleLogin() {
  errorMsg.value = "";
  rateLimited.value = false;
  loading.value = true;
  try {
    await auth.login(username.value, password.value, remember.value);
    router.push(redirectTarget());
  } catch (err) {
    rateLimited.value = err.status === 429;
    errorMsg.value = rateLimited.value
      ? rateLimitMessage(err.retryAfter)
      : (err.message ?? t("login.failed"));
  } finally {
    loading.value = false;
  }
}

function rateLimitMessage(seconds) {
  const retryAfter = Number(seconds);

  if (!Number.isFinite(retryAfter) || retryAfter <= 0) {
    return t("login.rateLimited");
  }

  const minutes = Math.ceil(retryAfter / 60);

  return t("login.rateLimitedMinutes", {
    minutes,
    unit: t(minutes === 1 ? "login.minute" : "login.minutes"),
  });
}
</script>

<style scoped>
:global(html:has(.login-page)) {
  color-scheme: dark;
  background: #060914;
}

.login-page {
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

.login-stars, .login-comets {
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: -1;
}

.login-stars::before, .login-stars::after {
  content: "";
  position: absolute;
  inset: 0;
  background-repeat: repeat;
}

.login-stars::before {
  opacity: .48;
  background-image:
    radial-gradient(1px 1px at 19px 31px, #dce5ff 90%, transparent),
    radial-gradient(1px 1px at 137px 173px, #dce5ff 90%, transparent),
    radial-gradient(1px 1px at 239px 83px, #dce5ff 90%, transparent);
  background-size: 307px 269px, 419px 347px, 503px 433px;
}

.login-stars::after {
  opacity: .65;
  background-image:
    radial-gradient(1.5px 1.5px at 113px 87px, #eff4ff 60%, transparent),
    radial-gradient(1.5px 1.5px at 331px 219px, #bccdff 60%, transparent);
  background-size: 557px 449px, 713px 587px;
}

.login-orbit {
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

.login-logo { filter: drop-shadow(0 0 36px #b1c5ff20); }

.login-copy { color: #b0bdd3; }

.login-panel {
  background: #101729ed;
  border: 1px solid #b5c5ff24;
  box-shadow: 0 24px 80px #00000040, inset 0 1px 0 #ffffff05;
}

.login-label {
  display: block;
  margin-bottom: .375rem;
  font-size: .875rem;
  font-weight: 500;
  color: #d9e2f3;
}

.login-input {
  background: #090f1e;
  border: 1px solid #b5c5ff30;
  padding: .625rem .75rem;
  color: #f1f5ff;
  caret-color: #93c5fd;
  transition: border-color .15s, box-shadow .15s;
}

.login-input:focus {
  border-color: #60a5fa;
  outline: 2px solid transparent;
  outline-offset: 2px;
  box-shadow: 0 0 0 3px #3b82f626;
}


.login-input:autofill { box-shadow: 0 0 0 1000px #111d35 inset; }

.login-checkbox { background-color: #090f1e; border-color: #96a5bf; color: #3b82f6; }
.login-checkbox:checked { background-color: #3b82f6; border-color: transparent; }
.login-checkbox:focus { --tw-ring-color: #60a5fa; --tw-ring-offset-color: #101729; }

.login-notice { color: #fde68a; background: #78350f30; border: 1px solid #fbbf2440; }
.login-error { color: #fecaca; background: #7f1d1d30; border: 1px solid #f8717140; }

.login-page .btn-primary {
  background: #2563eb;
  color: #fff;
  border: 1px solid #60a5fa40;
  --tw-ring-color: #60a5fa;
  --tw-ring-offset-color: #101729;
}
.login-page .btn-primary:hover { background: #1d4ed8; }
.login-page .btn-primary:disabled { background: #1e3a6b; color: #96a5bf; cursor: not-allowed; }

.login-comet {
  position: absolute;
  top: 12%;
  left: 65%;
  width: 180px;
  height: 1px;
  opacity: 0;
  background: linear-gradient(90deg, transparent, #a9caff80 85%, #fff);
  transform: rotate(145deg);
  animation: login-comet 14s linear 2s infinite;
}

.login-comet::after {
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

.login-comet:nth-child(2) { top: 35%; left: 95%; width: 120px; animation-delay: 9s; animation-duration: 19s; }

@keyframes login-comet {
  0% { opacity: 0; transform: translate(0, 0) rotate(145deg); }
  2% { opacity: .85; }
  8% { opacity: 0; transform: translate(-380px, 266px) rotate(145deg); }
  100% { opacity: 0; transform: translate(-380px, 266px) rotate(145deg); }
}

@media (prefers-reduced-motion: reduce) {
  .login-comet { animation: none; }
  .login-input, .login-page .btn-primary { transition: none; }
}
</style>
