<template>
  <AppLayout>
    <Head title="Users Management" />

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
      <h1 class="text-2xl font-bold" style="color: var(--va-text-primary)">Users Management</h1>
      <va-button color="primary" icon="add" @click="openCreateModal">
        Add New User
      </va-button>
    </div>

    <va-card class="mb-6">
      <va-card-content>
        <div class="flex items-center gap-4">
          <va-input 
            v-model="search" 
            placeholder="Search users by name or email..." 
            class="w-full md:w-1/3" 
            clearable 
            preset="bordered"
          >
            <template #prependInner>
              <va-icon name="search" color="secondary" />
            </template>
          </va-input>
        </div>
      </va-card-content>
    </va-card>

    <va-card>
      <va-card-content>
        <va-data-table
          :items="users.data"
          :columns="columns"
          :loading="false"
          striped
          hoverable
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
              :color="rowData.role === 'support' ? 'primary' : 'secondary'" 
              :text="rowData.role.toUpperCase()" 
              class="font-semibold"
            />
          </template>

          <template #cell(actions)="{ rowData }">
            <div class="flex gap-2 justify-end">
              <va-button 
                preset="plain" 
                icon="edit" 
                color="info" 
                @click="openEditModal(rowData)" 
              />
              <va-button 
                preset="plain" 
                icon="delete" 
                color="danger" 
                @click="openDeleteModal(rowData)" 
              />
            </div>
          </template>
        </va-data-table>
        
        <div v-if="users.data.length === 0" class="text-center py-8" style="color: var(--va-secondary)">
          No users found matching your search.
        </div>

        <div v-if="users.last_page > 1" class="flex justify-center mt-6">
          <va-pagination
            v-model="page"
            :pages="users.last_page"
            :visible-pages="5"
            color="primary"
            @update:modelValue="changePage"
          />
        </div>
      </va-card-content>
    </va-card>

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
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import UserFormModal from './Partials/UserFormModal.vue';
import UserDeleteModal from './Partials/UserDeleteModal.vue';

const props = defineProps({
  users: {
    type: Object,
    required: true,
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  roles: {
    type: Array,
    default: () => [],
  }
});

const search = ref(props.filters.search || '');
const page = ref(props.users.current_page);

const isFormModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const selectedUser = ref(null);

const columns = [
  { key: 'name', label: 'Name', sortable: false },
  { key: 'email', label: 'Email', sortable: false },
  { key: 'role', label: 'Role', sortable: false },
  { key: 'actions', label: 'Actions', sortable: false, align: 'right' },
];

let searchTimeout;
watch(search, (value) => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    router.get(
      route('users.index'),
      { search: value },
      { preserveState: true, replace: true }
    );
  }, 300);
});

const changePage = (newPage) => {
  router.get(
    route('users.index'),
    { search: search.value, page: newPage },
    { preserveState: true }
  );
};

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