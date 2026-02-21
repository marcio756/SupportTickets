<template>
  <AppLayout>
    <Head title="Tickets" />

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
      <h1 class="text-2xl font-bold text-[var(--va-dark)]">Support Tickets</h1>
      <Link :href="route('tickets.create')">
        <va-button color="primary" icon="add">Open New Ticket</va-button>
      </Link>
    </div>

    <va-card class="mb-6">
      <va-card-content>
        <ResourceFilter
          v-model:query="query"
          v-model:status="selectedStatus"
          :status-options="statusOptions"
        />
      </va-card-content>
    </va-card>

    <va-card>
      <va-card-content>
        <va-data-table
          :items="filteredResults"
          :columns="columns"
          :loading="false"
          striped
          hoverable
          clickable
          @row:click="navigateToTicket"
        >
          <template #cell(id)="{ rowData }">
            <span class="font-bold text-[var(--va-primary)]">#{{ rowData.id }}</span>
          </template>

          <template #cell(status)="{ rowData }">
            <TicketStatusBadge :status="rowData.status" />
          </template>

          <template #cell(created_at)="{ rowData }">
            {{ formatDate(rowData.created_at) }}
          </template>

          <template #cell(customer)="{ rowData }">
            <div class="flex items-center gap-2">
              <va-avatar size="small" :color="getAvatarColor(rowData.customer.name)">
                {{ rowData.customer.name.substring(0, 2).toUpperCase() }}
              </va-avatar>
              <span>{{ rowData.customer.name }}</span>
            </div>
          </template>

          <template #cell(assignee)="{ rowData }">
            <span v-if="rowData.assignee" class="text-sm text-gray-600">
              {{ rowData.assignee.name }}
            </span>
            <span v-else class="text-sm italic text-gray-400">Unassigned</span>
          </template>
        </va-data-table>
        
        <div v-if="tickets.data.length === 0" class="text-center py-8 text-gray-500">
          No tickets found.
        </div>
      </va-card-content>
    </va-card>
  </AppLayout>
</template>

<script setup>
import { computed, watch, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ResourceFilter from '@/Components/Filters/ResourceFilter.vue';
import TicketStatusBadge from '@/Components/Tickets/TicketStatusBadge.vue';
import { useFilters } from '@/Composables/useFilters';

const props = defineProps({
  tickets: {
    type: Object,
    required: true,
  },
});

const isSupporter = usePage().props.auth.user.role !== 'customer';

// Initializes the reusable filtering logic
const searchFields = isSupporter ? ['title', 'customer.name'] : ['title'];
const { query, selectedStatus, filteredResults, setSourceData } = useFilters(props.tickets.data, searchFields);

// Dynamic dropdown options for the filter
const statusOptions = [
  { text: 'All Statuses', value: '' },
  { text: 'Open', value: 'open' },
  { text: 'In Progress', value: 'in_progress' },
  { text: 'Resolved', value: 'resolved' },
  { text: 'Closed', value: 'closed' },
];

// Configuration for Vuestic Data Table columns
const columns = computed(() => {
  const baseColumns = [
    { key: 'id', label: 'ID', sortable: true },
    { key: 'title', label: 'Subject', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'created_at', label: 'Created On', sortable: true },
  ];

  if (isSupporter) {
    baseColumns.splice(2, 0, { key: 'customer', label: 'Customer', sortable: false });
    baseColumns.push({ key: 'assignee', label: 'Assigned To', sortable: false });
  }

  return baseColumns;
});

// Reactively update filter data if Inertia passes new props
watch(() => props.tickets.data, (newData) => {
  setSourceData(newData);
});

const navigateToTicket = (event) => {
  router.get(route('tickets.show', event.item.id));
};

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric'
  });
};

const getAvatarColor = (name) => {
  const colors = ['primary', 'success', 'info', 'warning', 'danger'];
  const charCode = name.charCodeAt(0);
  return colors[charCode % colors.length];
};
</script>