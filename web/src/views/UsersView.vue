<template>
  <div>
    <PageHeader :title="t('app.nav.usersRoles')" :description="t('users.description')">
      <template #actions>
        <button type="button" @click="showNewUser = !showNewUser" class="btn-primary">
          <Icon icon="mdi:account-plus-outline" class="h-4 w-4" />
          {{ t("users.newUser") }}
        </button>
      </template>
    </PageHeader>

    <UsersRolesTabs active="users" />

    <!-- New user form -->
    <div v-if="showNewUser" class="card p-5 mb-6">
      <h2 class="card-title mb-4">
        {{ t("users.createUser") }}
      </h2>
      <form @submit.prevent="handleCreateUser" class="grid gap-4 sm:grid-cols-3">
        <div>
          <label class="form-label">{{ t("users.username") }}</label>
          <input
            v-model="newUser.username"
            type="text"
            required
            class="form-input w-full rounded-lg border-slate-300 text-sm"
          />
        </div>
        <div>
          <label class="form-label">{{ t("users.password") }}</label>
          <input
            v-model="newUser.password"
            type="password"
            required
            minlength="8"
            class="form-input w-full rounded-lg border-slate-300 text-sm"
          />
        </div>
        <div>
          <label class="form-label">{{ t("users.rolePreset") }}</label>
          <select
            v-model="newUser.role"
            class="form-select w-full rounded-lg border-slate-300 text-sm"
            required
          >
            <option v-for="role in roles" :key="role.id" :value="role.id">
              {{ role.label }}
            </option>
          </select>
        </div>
        <div v-if="createError" class="sm:col-span-3 text-red-600 text-sm">
          {{ createError }}
        </div>
        <div class="sm:col-span-3 flex gap-2">
          <button type="submit" class="btn-primary">
            {{ t("users.create") }}
          </button>
          <button
            type="button"
            @click="showNewUser = false"
            class="btn-secondary"
          >
            {{ t("common.cancel") }}
          </button>
        </div>
      </form>
    </div>

    <!-- Users list -->
    <LoadingSpinner v-if="loading" />

    <template v-else>
      <div class="card overflow-hidden">
      <template v-for="role in roleGroups" :key="role.id">
        <template v-if="sortedUsers.some((u) => u.role === role.id)">
          <h2
            class="flex items-center gap-2 border-b border-slate-200 bg-slate-50/70 px-5 py-2 text-xs font-medium text-slate-500 [&:not(:first-child)]:border-t"
          >
            {{ role.label }}
            <span class="text-slate-400">{{ sortedUsers.filter((u) => u.role === role.id).length }}</span>
          </h2>
          <div class="divide-y divide-slate-100">
            <div
              v-for="user in sortedUsers.filter((u) => u.role === role.id)"
              :key="user.id"
              class="px-5 py-3.5"
            >
              <div class="flex flex-wrap items-center justify-between gap-3">
                <component
                  :is="user.id === currentUser?.id ? 'router-link' : 'div'"
                  :to="user.id === currentUser?.id ? '/profile' : undefined"
                  class="flex min-w-0 items-center gap-3 rounded-md"
                  :class="
                    user.id === currentUser?.id
                      ? 'transition-colors hover:text-theme-700 focus:outline-none focus:ring-2 focus:ring-theme-500 focus:ring-offset-2'
                      : ''
                  "
                >
                  <div
                    class="w-10 h-10 rounded-full overflow-hidden bg-theme-600 flex items-center justify-center text-white text-sm font-semibold select-none shrink-0 ring-2 ring-white shadow-sm"
                  >
                    <img
                      v-if="user.has_avatar"
                      :src="`${ADMIN_API_BASE}/users/${user.id}/avatar`"
                      class="w-full h-full object-cover"
                      :alt="user.username"
                    />
                    <span v-else>{{ user.username?.[0]?.toUpperCase() }}</span>
                  </div>
                  <div class="min-w-0">
                    <p class="flex items-center gap-2 text-sm font-medium text-slate-900">
                      <span class="truncate">{{ user.display_name || user.username }}</span>
                      <span v-if="user.id === currentUser?.id" class="badge bg-theme-50 text-theme-700 ring-theme-600/20">{{ t("users.you") }}</span>
                    </p>
                    <p class="truncate text-sm text-slate-500">
                      @{{ user.username }}<template v-if="user.email"> · {{ user.email }}</template>
                    </p>
                  </div>
                </component>
                <div class="flex items-center gap-2">
                  <router-link
                    v-if="user.id === currentUser?.id"
                    to="/profile"
                    class="btn-secondary btn-sm"
                  >
                    {{ t("users.myProfile") }}
                  </router-link>
                  <button
                    v-if="user.id !== currentUser?.id"
                    @click="openEditUser(user)"
                    class="btn-secondary btn-sm"
                  >
                    {{ t("users.edit") }}
                  </button>
                  <button
                    v-if="user.id !== currentUser?.id"
                    @click="handleDeleteUser(user.id)"
                    class="btn-danger-subtle btn-sm"
                  >
                    {{ t("users.delete") }}
                  </button>
                </div>
              </div>

              <!-- Edit user form -->
              <div
                v-if="editForm.userId === user.id"
                class="mt-4 rounded-lg border border-slate-200 bg-slate-50/60 p-4"
              >
                <h3 class="mb-3 text-sm font-semibold text-slate-900">
                  {{ t("users.editUser") }}
                </h3>
                <div class="grid gap-3 sm:grid-cols-2">
                  <div>
                    <label class="form-label">{{
                      t("users.displayName")
                    }}</label>
                    <input
                      v-model="editForm.display_name"
                      type="text"
                      class="form-input w-full rounded-lg border-slate-300 text-sm"
                    />
                  </div>
                  <div>
                    <label class="form-label">{{
                      t("users.email")
                    }}</label>
                    <input
                      v-model="editForm.email"
                      type="email"
                      class="form-input w-full rounded-lg border-slate-300 text-sm"
                    />
                  </div>
                  <div>
                    <label class="form-label">{{
                      t("users.rolePreset")
                    }}</label>
                    <select
                      v-model="editForm.role"
                      class="form-select w-full rounded-lg border-slate-300 text-sm"
                      required
                    >
                      <option
                        v-for="role in roles"
                        :key="role.id"
                        :value="role.id"
                      >
                        {{ role.label }}
                      </option>
                    </select>
                  </div>
                  <div>
                    <label class="form-label"
                      >{{ t("users.newPassword") }}
                      <span class="text-slate-400">{{
                        t("users.leaveBlank")
                      }}</span></label
                    >
                    <input
                      v-model="editForm.password"
                      type="password"
                      minlength="8"
                      autocomplete="new-password"
                      class="form-input w-full rounded-lg border-slate-300 text-sm"
                    />
                  </div>
                </div>
                <div v-if="editError" class="mt-2 text-xs text-red-600">
                  {{ editError }}
                </div>
                <div class="mt-3 flex gap-2">
                  <button
                    @click="handleUpdateUser(user.id)"
                    class="btn-primary btn-sm"
                  >
                    {{ t("users.saveChanges") }}
                  </button>
                  <button
                    @click="editForm.userId = null"
                    class="btn-secondary btn-sm"
                  >
                    {{ t("common.cancel") }}
                  </button>
                </div>
              </div>
            </div>
            <!-- end card -->
          </div>
          <!-- end space-y-4 --> </template
        ><!-- end role group --> </template
      ><!-- end role loop -->
      </div>
    </template
    ><!-- end v-else -->

    <ConfirmModal
      v-model="showDeleteUserModal"
      :title="t('users.deleteConfirm')"
      :confirm-label="t('users.delete')"
      :loading="deletingUser"
      @confirm="executeDeleteUser"
    />
  </div>
</template>

<script setup>
import { ADMIN_API_BASE } from "../basePath.js";
import LoadingSpinner from "../components/LoadingSpinner.vue";
import ConfirmModal from "../components/ConfirmModal.vue";
import PageHeader from "../components/PageHeader.vue";
import UsersRolesTabs from "../components/UsersRolesTabs.vue";
import { Icon } from "@iconify/vue";
import { ref, computed, onBeforeUnmount, onMounted } from "vue";
import { api } from "../api/index.js";
import { usersAdminEndpoint } from "../composables/apiEndpoint.js";
import { useApiEndpointStore } from "../stores/apiEndpoint.js";
import { useAuthStore } from "../stores/auth.js";
import { useToastStore } from "../stores/toast.js";
import { useI18n } from "../i18n/index.js";

const auth = useAuthStore();
const apiEndpointStore = useApiEndpointStore();
const toast = useToastStore();
const { t } = useI18n();
const apiEndpointOwner = "users";
const loading = ref(true);
const users = ref([]);
const roles = ref([]);
const currentUser = auth.user;

const roleOrder = computed(() =>
  Object.fromEntries(roles.value.map((role, index) => [role.id, index])),
);
const sortedUsers = computed(() =>
  [...users.value].sort(
    (a, b) =>
      (roleOrder.value[a.role] ?? 100) - (roleOrder.value[b.role] ?? 100),
  ),
);

const roleGroups = computed(() => {
  const known = roles.value.filter((role) =>
    users.value.some((user) => user.role === role.id),
  );
  const unknown = [...new Set(users.value.map((user) => user.role))]
    .filter((id) => id && !roles.value.some((role) => role.id === id))
    .map((id) => ({ id, label: id }));

  return [...known, ...unknown];
});

const showNewUser = ref(false);
const newUser = ref({
  username: "",
  password: "",
  role: "viewer",
});
const createError = ref("");

const editForm = ref({
  userId: null,
  display_name: "",
  email: "",
  role: "viewer",
  password: "",
});
const editError = ref("");

function openEditUser(user) {
  editError.value = "";
  editForm.value = {
    userId: user.id,
    display_name: user.display_name ?? "",
    email: user.email ?? "",
    role: user.role,
    password: "",
  };
}

async function handleUpdateUser(id) {
  editError.value = "";
  try {
    const payload = {
      display_name: editForm.value.display_name,
      email: editForm.value.email,
      role: editForm.value.role,
    };
    if (editForm.value.password) payload.password = editForm.value.password;
    await api.users.update(id, payload);
    toast.success(t("users.updated"));
    editForm.value.userId = null;
    await load();
  } catch (err) {
    editError.value = err.message;
  }
}

async function load() {
  loading.value = true;
  try {
    const userRes = await api.users.list();
    users.value = userRes.data;

    try {
      roles.value = (await api.roles.list()).data ?? [];
    } catch {
      roles.value = [...new Set(users.value.map((user) => user.role))]
        .filter(Boolean)
        .map((id) => ({ id, label: id }));
    }
  } finally {
    loading.value = false;
  }
}

async function handleCreateUser() {
  createError.value = "";
  try {
    await api.users.create({
      username: newUser.value.username,
      password: newUser.value.password,
      role: newUser.value.role,
    });
    toast.success(t("users.userCreated"));
    showNewUser.value = false;
    newUser.value = {
      username: "",
      password: "",
      role: roles.value.some((role) => role.id === "viewer")
        ? "viewer"
        : (roles.value[0]?.id ?? ""),
    };
    await load();
  } catch (err) {
    createError.value = err.message;
  }
}

const showDeleteUserModal = ref(false);
const deleteTargetUserId = ref(null);
const deletingUser = ref(false);

function handleDeleteUser(id) {
  deleteTargetUserId.value = id;
  showDeleteUserModal.value = true;
}

async function executeDeleteUser() {
  deletingUser.value = true;
  try {
    await api.users.delete(deleteTargetUserId.value);
    showDeleteUserModal.value = false;
    toast.success(t("users.deleted"));
    await load();
  } catch (err) {
    toast.error(err.message);
  } finally {
    deletingUser.value = false;
  }
}

onMounted(load);

onMounted(() => {
  apiEndpointStore.setEndpoint(
    {
      label: "Users",
      authLabel: "AUTH",
      url: usersAdminEndpoint(),
    },
    apiEndpointOwner,
  );
});

onBeforeUnmount(() => {
  apiEndpointStore.clearEndpoint(apiEndpointOwner);
});
</script>
