<template>
  <AppLayout>
    <Head :title="$t('tickets.page_title')" />

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
      <h1 class="text-2xl font-bold" style="color: var(--va-text-primary)">{{ $t('tickets.title') }}</h1>
      
      <va-button 
        v-if="!isSupporter || workSessionStatus === 'active'" 
        color="primary" 
        icon="add" 
        @click="openCreateModal"
      >
        {{ $t('tickets.create_new') }}
      </va-button>
    </div>

    <template v-if="isSupporter && workSessionStatus !== 'active'">
      <WorkSessionBlocker :session-status="workSessionStatus" />
    </template>

    <template v-else>
      <ResourceFilter
        v-model:query="query"
        v-model:status="selectedStatus"
        v-model:source="selectedSource"
        v-model:customers="selectedCustomers"
        v-model:assignees="selectedAssignees"
        v-model:tags="selectedTags"
        :status-options="statusOptions"
        :source-options="sourceOptions"
        :customer-options="customersList"
        :assignee-options="assigneeOptions"
        :available-tags="availableTags"
        :is-supporter="isSupporter"
      />

      <ResourceTable
        :resource-data="tickets"
        :columns="columns"
        :empty-message="$t('tickets.no_tickets_found')"
        :clickable="true"
        @row:click="navigateToTicket"
        @page-change="changePage"
      >
        <template #cell(id)="{ rowData }">
          <span class="font-bold" style="color: var(--va-primary)">#{{ rowData.id }}</span>
        </template>

        <template #cell(title)="{ rowData }">
          <div class="flex items-center gap-2">
            <span style="color: var(--va-text-primary)">{{ rowData.title }}</span>
            <span v-if="rowData.source === 'email'"
                  class="inline-flex items-center justify-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                  :title="$t('tickets.received_via_email')">
              📧 {{ $t('tickets.email') }}
            </span>
          </div>
        </template>

        <template #cell(tags)="{ rowData }">
          <div class="flex flex-wrap gap-1 max-w-[200px]">
            <TagBadge v-for="tag in rowData.tags" :key="tag.id" :tag="tag" />
            <span v-if="!rowData.tags || rowData.tags.length === 0" class="text-xs text-gray-400">
                {{ $t('tickets.no_tags') }}
            </span>
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
            <template v-if="rowData.customer">
              <UserAvatar :user="rowData.customer" size="36px" />
              <span style="color: var(--va-text-primary)">{{ rowData.customer.name }}</span>
            </template>
            <template v-else>
              <div class="flex items-center justify-center w-[36px] h-[36px] rounded-full bg-gray-200 text-gray-500" :title="$t('tickets.unregistered_user')">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                  <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                  <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                </svg>
              </div>
              <span style="color: var(--va-text-primary)">{{ rowData.sender_email }}</span>
            </template>
          </div>
        </template>

        <template #cell(assignee)="{ rowData }">
          <span v-if="rowData.assignee" class="text-sm flex items-center gap-2" style="color: var(--va-secondary)">
            <UserAvatar :user="rowData.assignee" size="24px" />
            {{ rowData.assignee.name }}
          </span>
          <span v-else class="text-sm font-bold text-red-500">{{ $t('tickets.unassigned') }}</span>
        </template>
      </ResourceTable>
    </template>

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
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import ResourceFilter from '@/Components/Filters/ResourceFilter.vue';
import ResourceTable from '@/Components/Common/ResourceTable.vue';
import TicketStatusBadge from '@/Components/Tickets/TicketStatusBadge.vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import TagBadge from '@/Components/Common/TagBadge.vue';
import TicketFormModal from './Partials/TicketFormModal.vue';
import WorkSessionBlocker from '@/Components/WorkSession/WorkSessionBlocker.vue';
import { useFilters } from '@/Composables/useFilters';

const props = defineProps({
  tickets: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  customersList: { type: Array, default: () => [] },
  availableTags: { type: Array, default: () => [] },
  workSessionStatus: { type: String, default: 'active' }
});

const page = usePage();

// Extracts both translation function and the current active locale string
const { t, locale } = useI18n();

const isSupporter = page.props.auth.user.role !== 'customer';
const isCreateModalOpen = ref(false);

const { query, selectedStatus, selectedSource, selectedCustomers, selectedAssignees, selectedTags, changePage } = useFilters(
  props.filters, 
  'tickets.index', 
  props.tickets.current_page
);

/**
 * Filter options are wrapped in computed properties so they react instantly 
 * when the global i18n locale changes.
 */
const statusOptions = computed(() => [
  { text: t('tickets.status.open'), value: 'open' },
  { text: t('tickets.status.in_progress'), value: 'in_progress' },
  { text: t('tickets.status.resolved'), value: 'resolved' },
  { text: t('tickets.status.closed'), value: 'closed' },
]);

const sourceOptions = computed(() => [
  { text: t('tickets.source.web'), value: 'web' },
  { text: t('tickets.source.email'), value: 'email' },
]);

const assigneeOptions = computed(() => [
  { text: t('tickets.assignee.unassigned'), value: 'unassigned' },
  { text: t('tickets.assignee.me'), value: 'me' }
]);

/**
 * Table columns must also be reactive to reflect header translation changes.
 */
const columns = computed(() => {
  const baseColumns = [
    { key: 'id', label: t('tickets.columns.id'), sortable: false },
    { key: 'title', label: t('tickets.columns.subject'), sortable: false },
    { key: 'tags', label: t('tickets.columns.tags'), sortable: false },
    { key: 'status', label: t('tickets.columns.status'), sortable: false },
    { key: 'created_at', label: t('tickets.columns.created_at'), sortable: false },
  ];

  if (isSupporter) {
    baseColumns.splice(4, 0, { key: 'customer', label: t('tickets.columns.customer'), sortable: false });
    baseColumns.push({ key: 'assignee', label: t('tickets.columns.assigned_to'), sortable: false });
  }

  return baseColumns;
});

const openCreateModal = () => isCreateModalOpen.value = true;
const navigateToTicket = (event) => router.get(route('tickets.show', event.item.id));

/**
 * Formats the given date string dynamically based on the active application locale.
 * Ensures international users see dates in their native structural format.
 * * @param {string} dateString 
 * @returns {string} Localized date string.
 */
const formatDate = (dateString) => {
  if (!dateString) return '';
  // Utilizes the global i18n locale value instead of a hardcoded 'en-US'
  return new Date(dateString).toLocaleDateString(locale.value, {
    month: 'short', day: 'numeric', year: 'numeric'
  });
};
</script>