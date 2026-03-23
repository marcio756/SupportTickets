<template>
  <AppLayout>
    <Head :title="$t('users.title')" />

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
      <h1 class="text-2xl font-bold" style="color: var(--va-text-primary)">{{ $t('users.title') }}</h1>
      <va-button 
        v-if="workSessionStatus === 'active'" 
        color="primary" 
        icon="add" 
        @click="openCreateModal"
      >
        {{ $t('users.add_new') }}
      </va-button>
    </div>

    <template v-if="workSessionStatus !== 'active'">
      <WorkSessionBlocker :session-status="workSessionStatus" />
    </template>

    <template v-else>
      <ResourceFilter
          v-model:query="query"
          v-model:role="selectedRole"
          :role-options="roles.length > 1 ? roleOptions : []"
      />

      <ResourceTable
          :resource-data="users"
          :columns="columns"
          :empty-message="$t('users.no_users_found')"
          @page-change="changePage"
      >
        <template #cell(name)="{ rowData }">
          <div class="flex items-center gap-2" :class="{ 'opacity-50': rowData.deleted_at }">
            <UserAvatar :user="rowData" size="36px" />
            <div class="flex flex-col">
              <span class="font-bold" style="color: var(--va-text-primary)">
                <s v-if="rowData.deleted_at">{{ rowData.name }}</s>
                <span v-else>{{ rowData.name }}</span>
              </span>
              <va-badge v-if="rowData.deleted_at" :text="$t('users.status.deactivated')" color="danger" size="small" class="mt-1 w-max" />
            </div>
          </div>
        </template>

        <template #cell(email)="{ rowData }">
          <span style="color: var(--va-secondary)" :class="{ 'opacity-50': rowData.deleted_at }">
            {{ rowData.email }}
          </span>
        </template>

        <template #cell(role)="{ rowData }">
          <div :class="{ 'opacity-50': rowData.deleted_at }">
            <UserRoleBadge :role="rowData.role" />
          </div>
        </template>

        <template #cell(team)="{ rowData }">
          <div v-if="rowData.team" :class="{ 'opacity-50': rowData.deleted_at }">
            <va-badge :text="rowData.team.name" color="secondary" />
            <div class="text-xs mt-1 text-gray-400 capitalize">{{ rowData.team.shift }}</div>
          </div>
          <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell(actions)="{ rowData }">
          <div class="flex gap-2 justify-end">
            <template v-if="rowData.deleted_at">
              <va-button 
                v-if="currentUserIsAdmin"
                preset="plain" 
                icon="restore" 
                color="success" 
                :title="$t('users.actions.restore_title')"
                @click="restoreUser(rowData)" 
              />
            </template>

            <template v-else>
              <va-button preset="plain" icon="edit" color="info" :title="$t('users.actions.edit_title')" @click="openEditModal(rowData)" />
              <va-button preset="plain" icon="block" color="danger" :title="$t('users.actions.deactivate_title')" @click="openDeleteModal(rowData)" />
            </template>
          </div>
        </template>
      </ResourceTable>
    </template>

    <UserFormModal 
      :show="isFormModalOpen" 
      :user="selectedUser" 
      :roles="roles"
      :teams="teams"
      @close="isFormModalOpen = false" 
    />
    
    <UserDeleteModal 
      :show="isDeleteModalOpen" 
      :user="selectedUser" 
      @close="isDeleteModalOpen = false" 
    />
  </AppLayout>
</template>

<script setup>
/**
 * Users Management Index Component.
 * Displays a paginated and filterable list of all registered system users.
 * Supports CRUD operation triggers via embedded contextual actions.
 */
import { ref, computed } from 'vue';
import { Head, usePage, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import ResourceFilter from '@/Components/Filters/ResourceFilter.vue';
import ResourceTable from '@/Components/Common/ResourceTable.vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import UserRoleBadge from '@/Components/Common/UserRoleBadge.vue';
import UserFormModal from './Partials/UserFormModal.vue';
import UserDeleteModal from './Partials/UserDeleteModal.vue';
import WorkSessionBlocker from '@/Components/WorkSession/WorkSessionBlocker.vue';
import { useFilters } from '@/Composables/useFilters';

const props = defineProps({
  users: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  roles: { type: Array, default: () => [] },
  teams: { type: Array, default: () => [] },
  workSessionStatus: { type: String, default: 'active' }
});

const page = usePage();
const { t } = useI18n();

const currentUserIsAdmin = computed(() => {
  const user = page.props.auth?.user;
  return user && user.role === 'admin';
});

const { query, selectedRole, changePage } = useFilters(props.filters, 'users.index', props.users.current_page || 1);

const isFormModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const selectedUser = ref(null);

const restoreUserForm = useForm({});

/**
 * Prepares the role filtering options. 
 * Re-uses the translations from the sidebar namespace if available.
 */
const roleOptions = computed(() => {
  return props.roles.map(role => ({
    text: t(`sidebar.roles.${role}`) || role.charAt(0).toUpperCase() + role.slice(1),
    value: role
  }));
});

/**
 * Computed columns definition to ensure table headers react properly 
 * when the global locale is updated.
 */
const columns = computed(() => [
  { key: 'name', label: t('users.columns.name'), sortable: false },
  { key: 'email', label: t('users.columns.email'), sortable: false },
  { key: 'role', label: t('users.columns.role'), sortable: false },
  { key: 'team', label: t('users.columns.team'), sortable: false },
  { key: 'actions', label: t('users.columns.actions'), sortable: false, align: 'right' },
]);

/**
 * Open modal to create a new user.
 */
const openCreateModal = () => {
  selectedUser.value = null;
  isFormModalOpen.value = true;
};

/**
 * Open modal to edit an existing user.
 * @param {Object} user 
 */
const openEditModal = (user) => {
  selectedUser.value = user;
  isFormModalOpen.value = true;
};

/**
 * Open modal to deactivate an existing user.
 * @param {Object} user 
 */
const openDeleteModal = (user) => {
  selectedUser.value = user;
  isDeleteModalOpen.value = true;
};

/**
 * Send a PATCH request to restore a soft-deleted user.
 * @param {Object} user 
 */
const restoreUser = (user) => {
  if (confirm(t('users.actions.restore_confirm', { name: user.name }))) {
    restoreUserForm.patch(route('users.restore', user.id), {
      preserveScroll: true,
    });
  }
};
</script>