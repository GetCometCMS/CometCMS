<template>
  <div
    v-if="groups.length > 0"
    class="grid grid-cols-2 gap-x-5 gap-y-3 md:grid-cols-3 lg:grid-cols-5"
  >
    <div v-for="group in groups" :key="group.area">
      <p
        class="text-[10px] font-semibold uppercase tracking-wide mb-1.5"
        :class="group.headingClass"
      >
        {{ group.label }}
      </p>
      <div class="flex flex-wrap gap-1 lg:flex-col">
        <span
          v-for="badge in group.badges"
          :key="badge.key"
          class="inline-block rounded px-1.5 py-0.5 text-xs font-medium leading-none"
          :class="group.badgeClass"
        >
          {{ badge.label
          }}<span v-if="badge.scope" class="ml-0.5 opacity-60">
            · {{ badge.scope }}</span
          >
        </span>
      </div>
    </div>
  </div>
  <p v-else class="text-xs text-slate-400 italic">{{ t("perm.none") }}</p>
</template>

<script setup>
import { computed } from "vue";
import { useI18n } from "../i18n/index.js";
import {
  PERMISSION_AREAS,
  permissionActionLabel,
  permissionArea,
  permissionAreaLabel,
} from "../composables/permissionLabels.js";

const { t } = useI18n();

const props = defineProps({
  permissions: {
    type: Array,
    default: () => [],
  },
});

const AREA_STYLES = {
  system: {
    headingClass: "text-slate-400",
    badgeClass: "bg-slate-100 text-slate-600",
  },
  schema: {
    headingClass: "text-violet-500",
    badgeClass: "bg-violet-50 text-violet-700",
  },
  content: {
    headingClass: "text-sky-500",
    badgeClass: "bg-sky-50 text-sky-700",
  },
  media: {
    headingClass: "text-emerald-500",
    badgeClass: "bg-emerald-50 text-emerald-700",
  },
  users: {
    headingClass: "text-amber-500",
    badgeClass: "bg-amber-50 text-amber-700",
  },
};

function formatScope(resources) {
  if (!resources?.length) return null;
  const joined = resources.join(", ");
  return joined === "*" ? null : joined;
}

const groups = computed(() => {
  if (!Array.isArray(props.permissions) || props.permissions.length === 0)
    return [];

  const areaMap = new Map();

  for (const grant of props.permissions) {
    const scope = formatScope(grant.resources);
    for (const action of grant.actions ?? []) {
      const area = permissionArea(action);
      if (!areaMap.has(area)) areaMap.set(area, new Map());
      const key = scope ? `${action}@${scope}` : action;
      if (!areaMap.get(area).has(key)) {
        areaMap.get(area).set(key, { key, label: permissionActionLabel(action), scope });
      }
    }
  }

  return PERMISSION_AREAS.filter((area) => areaMap.has(area)).map((area) => ({
    area,
    label: permissionAreaLabel(area),
    ...AREA_STYLES[area],
    badges: Array.from(areaMap.get(area).values()),
  }));
});
</script>
