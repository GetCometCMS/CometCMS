<template>
  <TabNavigation
    v-if="items.length > 1"
    class="mb-6"
    :items="items"
    :model-value="active"
    :aria-label="t('app.nav.usersRoles')"
  />
</template>

<script setup>
import { computed } from "vue";
import TabNavigation from "./TabNavigation.vue";
import { useAuthStore } from "../stores/auth.js";
import { useI18n } from "../i18n/index.js";

defineProps({
  active: { type: String, required: true },
});

const auth = useAuthStore();
const { t } = useI18n();

const items = computed(() =>
  [
    auth.can("users.read") && { value: "users", label: t("app.nav.users"), icon: "mdi:account-outline", to: "/users" },
    auth.can("roles.read") && { value: "roles", label: t("app.nav.roles"), icon: "mdi:shield-account-outline", to: "/roles" },
  ].filter(Boolean),
);
</script>
