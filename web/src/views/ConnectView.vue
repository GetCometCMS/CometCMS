<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-slate-950">{{ t("connect.title") }}</h1>
      <p class="mt-1 text-sm text-slate-500">{{ t("connect.description") }}</p>
    </div>

    <nav
      class="mb-6 flex gap-1 border-b border-slate-200"
      :aria-label="t('connect.navigation')"
    >
      <RouterLink
        v-for="tab in tabs"
        :key="tab.value"
        :to="`/connect/${tab.value}`"
        class="-mb-px flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold transition-colors"
        :class="
          activeTab === tab.value
            ? 'border-theme-600 text-theme-700'
            : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800'
        "
      >
        <Icon :icon="tab.icon" class="h-4 w-4" />
        {{ tab.label }}
      </RouterLink>
    </nav>

    <template v-if="activeTab === 'api'">
      <ApiTokensView v-if="auth.can('tokens.read')" embedded />
      <div v-else class="card p-6 text-sm text-slate-500">
        {{ t("connect.apiUnavailable") }}
      </div>

      <section class="card mt-6 flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="font-semibold text-slate-900">{{ t("connect.exploreApiTitle") }}</h2>
          <p class="mt-1 text-sm text-slate-500">{{ t("connect.exploreApiDescription") }}</p>
        </div>
        <RouterLink to="/api-explorer" class="btn-secondary shrink-0">
          <Icon icon="mdi:api" class="h-4 w-4" />
          {{ t("connect.exploreApi") }}
        </RouterLink>
      </section>
    </template>

    <section v-else-if="activeTab === 'mcp'" class="card overflow-hidden">
      <div
        class="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between"
      >
        <div>
          <div class="flex items-center gap-2">
            <Icon icon="mdi:server-network" class="h-5 w-5 text-theme-600" />
            <h2 class="text-base font-semibold text-slate-900">
              {{ t("connect.mcpEndpoint") }}
            </h2>
          </div>
          <p class="mt-1 text-sm text-slate-500">{{ t("connect.mcpDescription") }}</p>
        </div>
        <button type="button" class="btn-secondary shrink-0" @click="copy(mcpUrl)">
          <Icon icon="mdi:content-copy" class="h-4 w-4" />
          {{ t("connect.copyUrl") }}
        </button>
      </div>

      <div class="bg-slate-50/60 p-5">
        <div class="flex min-h-12 items-center gap-3 rounded-lg bg-slate-950 px-3 py-2 text-slate-100">
          <span class="shrink-0 font-mono text-xs font-semibold text-theme-300">POST</span>
          <code class="min-w-0 flex-1 overflow-x-auto whitespace-nowrap font-mono text-xs leading-6">
            {{ mcpUrl }}
          </code>
        </div>
        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex flex-wrap gap-2 text-xs text-slate-500">
            <span class="rounded-full bg-white px-2 py-1 ring-1 ring-slate-200">HTTP</span>
            <span class="rounded-full bg-white px-2 py-1 ring-1 ring-slate-200">Bearer token</span>
            <span class="rounded-full bg-white px-2 py-1 ring-1 ring-slate-200">{{ activeWorkspace }}</span>
          </div>
          <button
            type="button"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-theme-700 hover:text-theme-800"
            @click="copy(mcpCurlCommand)"
          >
            <Icon icon="mdi:console-line" class="h-4 w-4" />
            {{ t("connect.copyCurl") }}
          </button>
        </div>
      </div>
    </section>

    <template v-else>
      <WebhooksView v-if="auth.can('webhooks.manage')" embedded />
      <div v-else class="card p-6 text-sm text-slate-500">
        {{ t("connect.webhooksUnavailable") }}
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { Icon } from "@iconify/vue";
import { useRoute } from "vue-router";
import ApiTokensView from "./ApiTokensView.vue";
import WebhooksView from "./WebhooksView.vue";
import { getActiveWorkspace } from "../api/index.js";
import { workspacedMcpEndpoint } from "../composables/apiEndpoint.js";
import { useAuthStore } from "../stores/auth.js";
import { useToastStore } from "../stores/toast.js";
import { useI18n } from "../i18n/index.js";

const route = useRoute();
const auth = useAuthStore();
const toast = useToastStore();
const { t } = useI18n();
const activeWorkspace = getActiveWorkspace();
const mcpUrl = workspacedMcpEndpoint(window.location.origin);
const validTabs = new Set(["api", "mcp", "webhooks"]);
const activeTab = computed(() =>
  validTabs.has(route.params.tab) ? route.params.tab : "api",
);
const tabs = computed(() => [
  { value: "api", label: t("connect.api"), icon: "mdi:api" },
  { value: "mcp", label: t("connect.mcp"), icon: "mdi:server-network" },
  { value: "webhooks", label: t("connect.webhooks"), icon: "mdi:webhook" },
]);
const mcpCurlCommand = computed(
  () =>
    `curl -X POST -H "Content-Type: application/json" -H "Authorization: Bearer YOUR_TOKEN_HERE" \\\n+  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' \\\n+  "${mcpUrl}"`,
);

async function copy(value) {
  try {
    await navigator.clipboard.writeText(value);
    toast.success(t("connect.copied"));
  } catch {
    toast.error(t("connect.copyFailed"));
  }
}
</script>
