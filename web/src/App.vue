<template>
  <AppLayout v-if="$route.meta.requiresAuth">
    <router-view v-slot="{ Component }">
      <transition name="page" mode="out-in">
        <component :is="Component" :key="$route.path" />
      </transition>
    </router-view>
  </AppLayout>

  <router-view v-else v-slot="{ Component }">
    <transition name="page" mode="out-in">
      <component :is="Component" :key="$route.path" />
    </transition>
  </router-view>
</template>

<script setup>
import { onBeforeUnmount, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import AppLayout from "./components/AppLayout.vue";
import { useAuthStore } from "./stores/auth.js";

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

// The server ended the admin session: return to sign-in, then come back here.
function handleSessionExpired() {
  if (!auth.isAuthenticated) return;
  auth.expire();
  router.push({ path: "/login", query: { expired: "1", redirect: route.fullPath } });
}

onMounted(() => window.addEventListener("cometcms:session-expired", handleSessionExpired));
onBeforeUnmount(() => window.removeEventListener("cometcms:session-expired", handleSessionExpired));
</script>

<style>
.page-enter-active {
  transition: opacity 0.12s ease;
}
.page-leave-active {
  transition: none;
}
.page-enter-from {
  opacity: 0;
}
</style>
