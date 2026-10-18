<template>
  <div class="app-shell flex h-screen overflow-hidden">
    <!-- Mobile sidebar backdrop -->
    <Transition name="backdrop">
      <div
        v-if="sidebarOpen"
        class="fixed inset-0 z-30 bg-black/50 lg:hidden"
        aria-hidden="true"
        @click="sidebarOpen = false"
      />
    </Transition>

    <!-- Sidebar -->
    <Transition name="sidebar">
      <aside
        id="primary-navigation"
        class="fixed inset-y-0 left-0 z-40 w-64 flex-shrink-0 bg-sidebar border-r border-sidebar-border flex flex-col lg:static lg:translate-x-0 lg:z-auto"
        :class="
          sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
        "
      >
        <div class="flex h-16 shrink-0 items-center justify-between px-5">
          <router-link to="/dashboard" class="flex items-center" @click="sidebarOpen = false">
            <img :src="logoSrc" alt="CometCMS" class="brand-logo h-10 w-auto" />
          </router-link>
          <button
            type="button"
            :aria-label="t('app.actions.closeNavigation')"
            class="sidebar-action lg:hidden transition-colors p-1"
            @click="sidebarOpen = false"
          >
            <Icon icon="mdi:close" class="w-5 h-5" />
          </button>
        </div>
        <div
          v-if="workspaces.length > 0 && auth.can('workspaces.read')"
          class="px-3 pb-2 relative"
          ref="switcherRef"
        >
          <button
            type="button"
            :aria-expanded="workspaceSwitcherOpen"
            aria-haspopup="menu"
            @click="workspaceSwitcherOpen = !workspaceSwitcherOpen"
            class="sidebar-surface w-full flex items-center gap-2.5 rounded-lg px-2 py-1.5 group"
          >
            <div
              class="w-7 h-7 shrink-0 rounded-md overflow-hidden bg-theme-600 flex items-center justify-center text-white text-[10px] font-bold select-none leading-none shadow-sm"
            >
              <img
                v-if="activeWorkspace.has_icon"
                :src="`${ADMIN_API_BASE}/workspaces/${selectedWorkspace}/icon?v=${iconVersions[selectedWorkspace] ?? 0}`"
                class="w-full h-full object-cover"
                :alt="activeWorkspaceLabel"
              />
              <span v-else>{{ workspaceInitials }}</span>
            </div>
            <div class="flex-1 min-w-0 text-left">
              <div class="sidebar-label !p-0 !text-[10px] leading-tight">
                {{ t("app.nav.workspace") }}
              </div>
              <div class="text-[13px] font-semibold truncate leading-tight">
                {{ activeWorkspaceLabel }}
              </div>
            </div>
            <Icon
              icon="mdi:unfold-more-horizontal"
              class="w-4 h-4 opacity-50 shrink-0 group-hover:opacity-80 transition-opacity"
            />
          </button>

          <Transition name="ws-dropdown">
            <div
              v-if="workspaceSwitcherOpen"
              role="menu"
              class="absolute left-3 right-3 top-full z-50 mt-1 rounded-xl border border-slate-200 bg-white p-1 shadow-xl"
            >
              <div class="px-2.5 pb-1 pt-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-400">
                {{ t("app.nav.switchWorkspace") }}
              </div>
              <button
                v-for="workspace in workspaces"
                :key="workspace.slug"
                type="button"
                role="menuitemradio"
                :aria-checked="workspace.slug === selectedWorkspace"
                @click="switchWorkspace(workspace.slug)"
                class="w-full flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-left transition-colors"
                :class="
                  workspace.slug === selectedWorkspace
                    ? 'bg-theme-50 text-theme-700'
                    : 'text-slate-700 hover:bg-slate-50'
                "
              >
                <div
                  class="w-6 h-6 shrink-0 rounded-md overflow-hidden bg-theme-600 flex items-center justify-center text-white text-[10px] font-bold select-none leading-none"
                >
                  <img
                    v-if="workspace.has_icon"
                    :src="`${ADMIN_API_BASE}/workspaces/${workspace.slug}/icon?v=${iconVersions[workspace.slug] ?? 0}`"
                    class="w-full h-full object-cover"
                    :alt="workspace.label"
                  />
                  <span v-else>{{
                    workspaceInitials_(workspace.label)
                  }}</span>
                </div>
                <span class="flex-1 truncate font-medium">{{
                  workspace.label
                }}</span>
                <Icon
                  v-if="workspace.slug === selectedWorkspace"
                  icon="mdi:check"
                  class="w-4 h-4 shrink-0 text-theme-600"
                />
              </button>
              <template v-if="auth.can('workspaces.manage')">
                <div class="my-1 border-t border-slate-100" />
                <router-link
                  to="/workspaces"
                  @click="
                    workspaceSwitcherOpen = false;
                    sidebarOpen = false;
                  "
                  class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition-colors"
                >
                  <Icon icon="mdi:cog-outline" class="w-4 h-4 text-slate-400" />
                  {{ t("workspaces.manageCta") }}
                </router-link>
              </template>
            </div>
          </Transition>
        </div>

        <div class="px-3 pb-1">
          <button
            type="button"
            class="sidebar-surface flex h-9 w-full items-center gap-2.5 rounded-lg px-2.5 text-sm"
            aria-haspopup="dialog"
            @click="paletteOpen = true; sidebarOpen = false"
          >
            <Icon icon="mdi:magnify" class="h-[18px] w-[18px] shrink-0 sidebar-muted" />
            <span class="flex-1 text-left sidebar-muted">{{ t("app.search.open") }}</span>
            <span class="sidebar-kbd">{{ shortcutLabel }}</span>
          </button>
        </div>

        <nav
          :aria-label="t('app.actions.primaryNavigation')"
          class="nav-scrollbar flex-1 overflow-y-auto px-3 pb-4"
        >
          <div v-for="group in navGroups" :key="group.key" role="group" :aria-label="group.label">
            <div v-if="group.label" class="sidebar-label" aria-hidden="true">{{ group.label }}</div>
            <div class="space-y-0.5" :class="{ 'pt-2': !group.label }">
              <router-link
                v-for="item in group.items"
                :key="item.key"
                :to="item.to"
                class="nav-link"
                :class="{ 'is-active': isActive(item, route.path) }"
                :aria-current="isActive(item, route.path) ? 'page' : undefined"
                @click="sidebarOpen = false"
              >
                <Icon :icon="item.icon" class="nav-icon" />
                <span class="truncate">{{ item.label }}</span>
              </router-link>
              <router-link
                v-if="group.key === 'content' && allTypes.length === 0 && auth.can('schema.create')"
                to="/content-types/new"
                class="nav-link opacity-70"
                @click="sidebarOpen = false"
              >
                <Icon icon="mdi:plus" class="nav-icon" />
                <span class="truncate">{{ t("app.nav.addType") }}</span>
              </router-link>
            </div>
          </div>
        </nav>

        <!-- User footer -->
        <div
          ref="accountMenuRef"
          class="relative border-t border-sidebar-border p-3"
        >
          <Transition name="ws-dropdown">
            <div
              v-if="accountMenuOpen"
              role="menu"
              class="absolute bottom-full left-3 right-3 z-50 mb-2 overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-xl"
            >
              <div class="border-b border-slate-100 px-2.5 pb-2.5 pt-1.5 mb-1">
                <div class="truncate text-sm font-semibold text-slate-900">
                  {{ auth.user?.display_name || auth.user?.username }}
                </div>
                <div class="truncate text-xs text-slate-500">
                  {{ auth.user?.email || auth.user?.username }}
                </div>
              </div>
              <RouterLink
                to="/profile"
                role="menuitem"
                class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-50"
                @click="closeAccountMenu"
              >
                <Icon
                  icon="mdi:account-outline"
                  class="h-4 w-4 text-slate-400"
                />
                {{ t("app.account.profile") }}
              </RouterLink>
              <RouterLink
                to="/update"
                role="menuitem"
                class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-50"
                @click="closeAccountMenu"
              >
                <Icon
                  icon="mdi:information-outline"
                  class="h-4 w-4 text-slate-400"
                />
                <span class="flex-1">{{ t("app.account.version") }}</span>
                <span v-if="appVersion" class="text-xs text-slate-400"
                  >v{{ appVersion }}</span
                >
              </RouterLink>
              <div class="my-1 border-t border-slate-100" />
              <button
                type="button"
                role="menuitem"
                class="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-sm text-slate-700 transition-colors hover:bg-slate-50"
                @click="handleLogout"
              >
                <Icon icon="mdi:logout" class="h-4 w-4 text-slate-400" />
                {{ t("app.actions.logout") }}
              </button>
            </div>
          </Transition>

          <button
            type="button"
            class="sidebar-profile-link flex w-full min-w-0 items-center gap-2.5 rounded-lg p-1.5 text-left transition-colors hover:bg-sidebar-hover"
            aria-haspopup="menu"
            :aria-expanded="accountMenuOpen"
            @click="accountMenuOpen = !accountMenuOpen"
          >
            <div
              class="w-8 h-8 shrink-0 rounded-full overflow-hidden bg-theme-600 flex items-center justify-center text-white text-xs font-semibold select-none"
            >
              <img
                v-if="auth.user?.has_avatar"
                :src="`${ADMIN_API_BASE}/users/${auth.user.id}/avatar?v=${avatarVersion}`"
                class="w-full h-full object-cover"
                :alt="auth.user?.username"
              />
              <span v-else>{{ auth.user?.username?.[0]?.toUpperCase() }}</span>
            </div>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-sm font-medium leading-tight">{{
                auth.user?.display_name || auth.user?.username
              }}</span>
              <span class="sidebar-muted block truncate text-xs leading-tight">{{
                roleLabel
              }}</span>
            </span>
            <Icon
              icon="mdi:chevron-up"
              class="h-4 w-4 shrink-0 opacity-50 transition-transform"
              :class="{ 'rotate-180': accountMenuOpen }"
            />
          </button>
        </div>
      </aside>
    </Transition>

    <!-- Main area -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Mobile header bar -->
      <header
        class="lg:hidden flex items-center gap-3 px-4 py-3 bg-sidebar border-b border-sidebar-border shrink-0"
      >
        <button
          type="button"
          :aria-label="t('app.actions.openNavigation')"
          aria-controls="primary-navigation"
          :aria-expanded="sidebarOpen"
          class="sidebar-action transition-colors p-1 -ml-1"
          @click="sidebarOpen = true"
        >
          <Icon icon="mdi:menu" class="w-6 h-6" />
        </button>
        <img :src="logoSrc" alt="CometCMS" class="brand-logo h-7 w-auto" />
        <button
          type="button"
          class="sidebar-action ml-auto p-1 transition-colors"
          :aria-label="t('app.search.open')"
          @click="paletteOpen = true"
        >
          <Icon icon="mdi:magnify" class="h-6 w-6" />
        </button>
      </header>

      <ToastStack />
      <CommandPalette v-model:open="paletteOpen" />

      <main class="flex-1 overflow-y-auto">
        <div class="max-w-7xl mx-auto w-full px-4 py-6 sm:px-8 sm:py-10">
          <slot />
        </div>
      </main>
      <ApiEndpointFooter />
    </div>
  </div>
</template>

<script setup>
import { ADMIN_API_BASE, adminAsset } from "../basePath.js";
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Icon } from "@iconify/vue";
import { useAuthStore } from "../stores/auth.js";
import { useContentTypesStore } from "../stores/contentTypes.js";
import { useSiteStore } from "../stores/site.js";
import {
  api,
  getActiveWorkspace,
  setActiveWorkspace,
  setDefaultWorkspace,
} from "../api/index.js";
import { logoForTheme } from "../theme.js";
import { useI18n } from "../i18n/index.js";
import ApiEndpointFooter from "./ApiEndpointFooter.vue";
import ToastStack from "./ToastStack.vue";
import CommandPalette from "./CommandPalette.vue";
import { useNavigation } from "../composables/useNavigation.js";

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const { groups: navGroups, allTypes, isActive } = useNavigation();
const paletteOpen = ref(false);
const isMac = typeof navigator !== "undefined" && /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
const shortcutLabel = isMac ? "⌘K" : "Ctrl K";
const roleLabel = computed(() => {
  const role = auth.user?.role;
  if (!role) return "";
  return typeof role === "string" ? role.charAt(0).toUpperCase() + role.slice(1) : role.label ?? "";
});
const typesStore = useContentTypesStore();
const site = useSiteStore();
const { t } = useI18n();
const avatarVersion = ref(Date.now());
const appVersion = ref("");
const sidebarOpen = ref(false);
const workspaces = ref([]);
const selectedWorkspace = ref(getActiveWorkspace());
const workspaceSwitcherOpen = ref(false);
const switcherRef = ref(null);
const accountMenuOpen = ref(false);
const accountMenuRef = ref(null);
const iconVersions = ref({});
const workspaceSyncEvent = "cometcms:workspaces-updated";
const activeWorkspace = computed(
  () =>
    workspaces.value.find((w) => w.slug === selectedWorkspace.value) ?? {
      slug: selectedWorkspace.value,
      has_icon: false,
    },
);
const activeWorkspaceLabel = computed(
  () => activeWorkspace.value?.label ?? selectedWorkspace.value,
);
const workspaceInitials = computed(() =>
  workspaceInitials_(activeWorkspaceLabel.value),
);

function workspaceInitials_(label) {
  return String(label ?? "")
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0].toUpperCase())
    .join("");
}

function handleSwitcherOutsideClick(e) {
  if (switcherRef.value && !switcherRef.value.contains(e.target)) {
    workspaceSwitcherOpen.value = false;
  }
  if (accountMenuRef.value && !accountMenuRef.value.contains(e.target)) {
    accountMenuOpen.value = false;
  }
}

function handleGlobalShortcut(event) {
  if ((event.metaKey || event.ctrlKey) && !event.altKey && event.key.toLowerCase() === "k") {
    event.preventDefault();
    paletteOpen.value = !paletteOpen.value;
  }
}

function closeAccountMenu() {
  accountMenuOpen.value = false;
  sidebarOpen.value = false;
}

function handleWorkspaceSync() {
  fetchWorkspaces();
}
// Resolve against this module so dev assets stay on Vite's origin even though
// PHP serves the document from a different port.
const assetBase = adminAsset("");
const logoSrc = computed(() => logoForTheme(auth.user?.theme, assetBase));

// Types are refetched after creating, deleting or switching workspaces.
watch(
  () => typesStore.list,
  () => {
    if (auth.can("site.manage")) site.loadStatus().catch(() => {});
  },
  { immediate: true },
);


onMounted(() => {
  if (auth.can("workspaces.read")) {
    fetchWorkspaces();
  }
  typesStore.fetch();
  fetchAppInfo();
  document.addEventListener("click", handleSwitcherOutsideClick);
  document.addEventListener("keydown", handleGlobalShortcut);
  window.addEventListener(workspaceSyncEvent, handleWorkspaceSync);
});

onBeforeUnmount(() => {
  document.removeEventListener("click", handleSwitcherOutsideClick);
  document.removeEventListener("keydown", handleGlobalShortcut);
  window.removeEventListener(workspaceSyncEvent, handleWorkspaceSync);
});

async function fetchWorkspaces() {
  try {
    const res = await api.workspaces.list();
    workspaces.value = res.data ?? [];
    const defaultWs = workspaces.value.find((w) => w.default);
    if (defaultWs) {
      setDefaultWorkspace(defaultWs.slug);
    }
    if (
      !workspaces.value.some(
        (workspace) => workspace.slug === selectedWorkspace.value,
      )
    ) {
      selectedWorkspace.value =
        defaultWs?.slug ?? workspaces.value[0]?.slug ?? selectedWorkspace.value;
      setActiveWorkspace(selectedWorkspace.value);
    }
  } catch {
    workspaces.value = [];
  }
}

async function switchWorkspace(slug) {
  workspaceSwitcherOpen.value = false;
  if (slug === selectedWorkspace.value) return;
  selectedWorkspace.value = slug;
  setActiveWorkspace(slug);
  typesStore.invalidate({ clear: true });
  await auth.refresh();
  await typesStore.fetch();
  router.push("/dashboard");
}

async function fetchAppInfo() {
  try {
    const res = await api.appInfo();
    appVersion.value = res.data?.version ?? "";
  } catch {
    appVersion.value = "";
  }
}

async function handleLogout() {
  accountMenuOpen.value = false;
  await auth.logout();
  router.push("/login");
}
</script>

<style scoped>
/* Sidebar slide animation (mobile) */
.sidebar-enter-active,
.sidebar-leave-active {
  transition: transform 0.25s ease;
}

.sidebar-enter-from,
.sidebar-leave-to {
  transform: translateX(-100%);
}

/* Workspace dropdown */
.ws-dropdown-enter-active,
.ws-dropdown-leave-active {
  transition:
    opacity 0.15s ease,
    transform 0.15s ease;
}

.ws-dropdown-enter-from,
.ws-dropdown-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

/* Backdrop fade */
.backdrop-enter-active,
.backdrop-leave-active {
  transition: opacity 0.25s ease;
}

.backdrop-enter-from,
.backdrop-leave-to {
  opacity: 0;
}

/* Modern sidebar scrollbar */
.nav-scrollbar {
  scrollbar-width: thin;
  scrollbar-color: rgb(var(--color-sidebar-text) / 0.22) transparent;
  scrollbar-gutter: stable;
}

.nav-scrollbar::-webkit-scrollbar {
  width: 10px;
}

.nav-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}

.nav-scrollbar::-webkit-scrollbar-thumb {
  background: rgb(var(--color-sidebar-text) / 0.16);
  border: 3px solid transparent;
  border-radius: 999px;
  background-clip: padding-box;
}

.nav-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgb(var(--color-sidebar-text) / 0.28);
  border: 3px solid transparent;
  background-clip: padding-box;
}

.nav-scrollbar::-webkit-scrollbar-thumb:active {
  background: rgb(var(--color-sidebar-text) / 0.38);
  border: 3px solid transparent;
  background-clip: padding-box;
}
</style>
