<template>
  <section class="card page-builder-cta p-6 space-y-4" :aria-labelledby="headingId">
    <h2 :id="headingId" class="card-title">{{ title }}</h2>

    <div class="flex flex-col items-center gap-4 py-2 text-center">
      <p v-if="description" :id="hintId" class="max-w-2xl text-sm text-slate-500">{{ description }}</p>
      <router-link
        :to="to"
        class="btn-primary justify-center"
        :aria-describedby="description ? hintId : undefined"
      >
        {{ actionLabel }}
        <Icon icon="mdi:arrow-right" class="h-4 w-4 shrink-0" aria-hidden="true" />
      </router-link>
    </div>
  </section>
</template>

<script setup>
import { useId } from "vue";
import { Icon } from "@iconify/vue";

defineProps({
  title: { type: String, required: true },
  description: { type: String, default: "" },
  to: { type: [String, Object], required: true },
  actionLabel: { type: String, required: true },
});

const headingId = `builder-cta-${useId()}`;
const hintId = `${headingId}-hint`;
</script>

<style scoped>
.page-builder-cta {
  background-color: #fff;
  background-image:
    linear-gradient(90deg, #ffffff 0%, #ffffffed 35%, #ffffffa6 60%, #ffffff26 100%),
    url("../../assets/page-builder-background.svg");
  background-position: center, right center;
  background-repeat: no-repeat;
  background-size: cover, auto 100%;
}

:root[data-theme="dark"] .page-builder-cta {
  background-color: rgb(var(--color-surface));
  background-image:
    linear-gradient(90deg, rgb(var(--color-surface)) 0%, rgb(var(--color-surface) / 0.94) 35%, rgb(var(--color-surface) / 0.88) 60%, rgb(var(--color-surface) / 0.8) 100%),
    url("../../assets/page-builder-background.svg");
}
</style>
