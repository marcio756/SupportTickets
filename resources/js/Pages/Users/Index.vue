<template>
  <AppLayout>
    <Head title="Users Management" />

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
      <h1 class="text-2xl font-bold" style="color: var(--va-text-primary)">Users Management</h1>
      <va-button 
        v-if="workSessionStatus === 'active'" 
        color="primary" 
        icon="add" 
        @click="openCreateModal"
      >
        Add New User
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
          empty-message="No users found matching your filters."
          @page-change="changePage"
      >
        <template #cell(name)="{ rowData }">
          <div class="flex items-center gap-2">
            <UserAvatar :user="rowData" size="36px" />
            <span class="font-bold" style="color: var(--va-text-primary)">{{ rowData.name }}</span>
          </div>
        </template>

        <template #cell(email)="{ rowData }">
          <span style="color: var(--va-secondary)">{{ rowData.email }}</span>
        </template>

        <template #cell(role)="{ rowData }">
          <va-badge 
            :color="rowData.role === 'support' || rowData.role === 'supporter' ? 'primary' : 'secondary'" 
            :text="rowData.role.toUpperCase()" 
            class="font-semibold"
          />
        </template>

        <template #cell(actions)="{ rowData }">
          <div class="flex gap-2 justify-end">
            <va-button preset="plain" icon="edit" color="info" @click="openEditModal(rowData)" />
            <va-button preset="plain" icon="delete" color="danger" @click="openDeleteModal(rowData)" />
          </div>
        </template>
      </ResourceTable>
    </template>

    <UserFormModal 
      :show="isFormModalOpen" 
      :user="selectedUser" 
      :roles="roles"
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
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ResourceFilter from '@/Components/Filters/ResourceFilter.vue';
import ResourceTable from '@/Components/Common/ResourceTable.vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import UserFormModal from './Partials/UserFormModal.vue';
import UserDeleteModal from './Partials/UserDeleteModal.vue';
import WorkSessionBlocker from '@/Components/WorkSession/WorkSessionBlocker.vue';
import { useFilters } from '@/Composables/useFilters';

const props = defineProps({
  users: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  roles: { type: Array, default: () => [] },
  workSessionStatus: { type: String, default: 'active' }
});

/**
 * Leverage the useFilters composable to synchronize route state with UI.
 * The role filter is only presented if the current user has access to multiple roles.
 */
const { query, selectedRole, changePage } = useFilters(props.filters, 'users.index', props.users.current_page || 1);

const isFormModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const selectedUser = ref(null);

const roleOptions = computed(() => {
  return props.roles.map(role => ({
    text: role.charAt(0).toUpperCase() + role.slice(1),
    value: role
  }));
});

const columns = [
  { key: 'name', label: 'Name', sortable: false },
  { key: 'email', label: 'Email', sortable: false },
  { key: 'role', label: 'Role', sortable: false },
  { key: 'actions', label: 'Actions', sortable: false, align: 'right' },
];

const openCreateModal = () => {
  selectedUser.value = null;
  isFormModalOpen.value = true;
};

const openEditModal = (user) => {
  selectedUser.value = user;
  isFormModalOpen.value = true;
};

const openDeleteModal = (user) => {
  selectedUser.value = user;
  isDeleteModalOpen.value = true;
};
</script>