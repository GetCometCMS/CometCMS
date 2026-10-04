<template>
  <div class="mx-auto max-w-lg py-12">
    <div class="card p-8 text-center">
      <Icon icon="mdi:map-marker-question-outline" class="mx-auto h-10 w-10 text-slate-400" />
      <h1 class="mt-4 text-xl font-bold text-slate-900">{{ t("notFound.title") }}</h1>
      <p class="mt-2 text-sm text-slate-500">{{ t("notFound.body") }}</p>
      <p class="mt-3 break-all font-mono text-xs text-slate-400">{{ route.fullPath }}</p>
      <div class="mt-6 flex flex-wrap justify-center gap-2">
        <button type="button" class="btn-secondary" @click="router.back()">
          {{ t("notFound.back") }}
        </button>
        <router-link to="/dashboard" class="btn-primary">
          {{ t("notFound.dashboard") }}
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Icon } from "@iconify/vue";
import { useRoute, useRouter } from "vue-router";
import { useI18n } from "../i18n/index.js";

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
</script>
