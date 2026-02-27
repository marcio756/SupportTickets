<template>
  <AppLayout>
    <Head title="Tickets" />

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
      <h1 class="text-2xl font-bold" style="color: var(--va-text-primary)">Support Tickets</h1>
      <va-button color="primary" icon="add" @click="openCreateModal">
        Open New Ticket
      </va-button>
    </div>

    <ResourceFilter
      v-model:query="query"
      v-model:status="selectedStatus"
      v-model:customers="selectedCustomers"
      v-model:assignees="selectedAssignees"
      v-model:tags="selectedTags"
      :status-options="statusOptions"
      :customer-options="customersList"
      :assignee-options="assigneeOptions"
      :available-tags="availableTags"
      :is-supporter="isSupporter"
    />

    <ResourceTable
      :resource-data="tickets"
      :columns="columns"
      empty-message="No tickets found matching your filters."
      :clickable="true"
      @row:click="navigateToTicket"
      @page-change="changePage"
    >
      <template #cell(id)="{ rowData }">
        <span class="font-bold" style="color: var(--va-primary)">#{{ rowData.id }}</span>
      </template>

      <template #cell(tags)="{ rowData }">
        <div class="flex flex-wrap gap-1 max-w-[200px]">
          <TagBadge v-for="tag in rowData.tags" :key="tag.id" :tag="tag" />
          <span v-if="!rowData.tags || rowData.tags.length === 0" class="text-xs text-gray-400">No tags</span>
        </div>
      </template>

      <template #cell(status)="{ rowData }">
        <TicketStatusBadge :status="rowData.status" />
      </template>

      <template #cell(created_at)="{ rowData }">
        {{ formatDate(rowData.created_at) }}
      </template>

      <template #cell(customer)="{ rowData }">
        <div class="flex items-center gap-2">
          <UserAvatar :user="rowData.customer" size="36px" />
          <span style="color: var(--va-text-primary)">{{ rowData.customer.name }}</span>
        </div>
      </template>

      <template #cell(assignee)="{ rowData }">
        <span v-if="rowData.assignee" class="text-sm flex items-center gap-2" style="color: var(--va-secondary)">
          <UserAvatar :user="rowData.assignee" size="24px" />
          {{ rowData.assignee.name }}
        </span>
        <span v-else class="text-sm font-bold text-red-500">Unassigned</span>
      </template>
    </ResourceTable>

    <TicketFormModal
      :show="isCreateModalOpen"
      :customers="customersList"
      @close="isCreateModalOpen = false"
    />
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ResourceFilter from '@/Components/Filters/ResourceFilter.vue';
import ResourceTable from '@/Components/Common/ResourceTable.vue';
import TicketStatusBadge from '@/Components/Tickets/TicketStatusBadge.vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import TagBadge from '@/Components/Common/TagBadge.vue';
import TicketFormModal from './Partials/TicketFormModal.vue';
import { useFilters } from '@/Composables/useFilters';

const props = defineProps({
  tickets: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  customersList: { type: Array, default: () => [] },
  availableTags: { type: Array, default: () => [] }
});

const page = usePage();
const isSupporter = page.props.auth.user.role !== 'customer';

const isCreateModalOpen = ref(false);

const { query, selectedStatus, selectedCustomers, selectedAssignees, selectedTags, changePage } = useFilters(
  props.filters, 
  'tickets.index', 
  props.tickets.current_page
);

const statusOptions = [
  { text: 'Open', value: 'open' },
  { text: 'In Progress', value: 'in_progress' },
  { text: 'Resolved', value: 'resolved' },
  { text: 'Closed', value: 'closed' },
];

const assigneeOptions = [
  { text: 'Unassigned', value: 'unassigned' },
  { text: 'Assigned to Me', value: 'me' }
];

const columns = computed(() => {
  const baseColumns = [
    { key: 'id', label: 'ID', sortable: false },
    { key: 'title', label: 'Subject', sortable: false },
    { key: 'tags', label: 'Tags', sortable: false },
    { key: 'status', label: 'Status', sortable: false },
    { key: 'created_at', label: 'Created On', sortable: false },
  ];

  if (isSupporter) {
    baseColumns.splice(4, 0, { key: 'customer', label: 'Customer', sortable: false });
    baseColumns.push({ key: 'assignee', label: 'Assigned To', sortable: false });
  }

  return baseColumns;
});

const openCreateModal = () => isCreateModalOpen.value = true;
const navigateToTicket = (event) => router.get(route('tickets.show', event.item.id));

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric'
  });
};
</script>