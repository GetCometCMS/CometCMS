<template>
  <div>
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-bold text-slate-900">
        {{ t("apiExplorer.title") }}
      </h1>
    </div>

    <ApiQueryBuilder :api-base="apiBase" :collections="collections" />
  </div>
</template>

<script setup>
import { onMounted, ref } from "vue";
import ApiQueryBuilder from "../components/ApiQueryBuilder.vue";
import { api } from "../api/index.js";
import { workspacedApiBase } from "../composables/apiEndpoint.js";
import { useI18n } from "../i18n/index.js";

const apiBase = workspacedApiBase(window.location.origin);
const collections = ref([]);
const { t } = useI18n();

onMounted(async () => {
  try {
    const res = await api.contentTypes.list();
    collections.value = res.data ?? [];
  } catch {
    // The builder remains useful for manually entered endpoints.
  }
});
</script>
