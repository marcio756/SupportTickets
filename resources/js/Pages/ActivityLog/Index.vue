<template>
  <AppLayout>
    <Head title="System Activity Logs" />

    <div class="mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--va-text-primary)">Activity Logs</h1>
        <p class="text-sm text-gray-500 mt-1">Audit trail of system creations, updates, and deletions.</p>
    </div>

    <ResourceFilter
      hide-search
      :additional-filter-count="customActiveCount"
      @clear-all="clearCustomFilters"
    >
        <template #append>
            <va-select
                v-model="customFilters.user"
                :options="userOptions"
                value-by="value"
                label="Filter by User"
                multiple
                clearable
                class="w-full xl:w-48 flex-none"
                preset="bordered"
            >
                <template #content="{ valueArray }">
                    <span v-if="valueArray.length === 1">{{ valueArray[0].text || valueArray[0] }}</span>
                    <span v-else-if="valueArray.length > 1" class="font-bold text-sm">{{ valueArray.length }} selected</span>
                    <span v-else class="text-gray-400">Filter by User</span>
                </template>
            </va-select>

            <va-select
                v-model="customFilters.event"
                :options="options.events"
                label="Filter by Event"
                multiple
                clearable
                class="w-full xl:w-48 flex-none"
                preset="bordered"
            >
                <template #content="{ valueArray }">
                    <span v-if="valueArray.length === 1">{{ valueArray[0] }}</span>
                    <span v-else-if="valueArray.length > 1" class="font-bold text-sm">{{ valueArray.length }} selected</span>
                    <span v-else class="text-gray-400">Filter by Event</span>
                </template>
            </va-select>

            <va-select
                v-model="customFilters.target"
                :options="mappedTargets"
                value-by="value"
                label="Filter by Target Model"
                multiple
                clearable
                class="w-full xl:w-56 flex-none"
                preset="bordered"
            >
                <template #content="{ valueArray }">
                    <span v-if="valueArray.length === 1">{{ valueArray[0].text || valueArray[0] }}</span>
                    <span v-else-if="valueArray.length > 1" class="font-bold text-sm">{{ valueArray.length }} selected</span>
                    <span v-else class="text-gray-400">Filter by Target Model</span>
                </template>
            </va-select>

            <va-input
                v-model="customFilters.date_start"
                type="date"
                label="Date From"
                :max="customFilters.date_end"
                clearable
                class="w-full xl:w-40 flex-none"
                preset="bordered"
            />

            <va-input
                v-model="customFilters.date_end"
                type="date"
                label="Date To"
                :min="customFilters.date_start"
                clearable
                class="w-full xl:w-40 flex-none"
                preset="bordered"
            />
        </template>
    </ResourceFilter>

    <ResourceTable
        :resource-data="logs"
        :columns="tableColumns"
        empty-message="No activity logs match your filter criteria."
        @page-change="changePage"
    >
      <template #cell(created_at)="{ rowData }">
        <span class="text-sm text-gray-600 whitespace-nowrap">
          {{ formatDateTime(rowData.created_at) }}
        </span>
      </template>

      <template #cell(causer)="{ rowData }">
        <div v-if="rowData.causer" class="flex items-center gap-2">
          <UserAvatar :user="rowData.causer" size="24px" />
          <span class="text-sm font-medium">{{ rowData.causer.name }}</span>
        </div>
        <span v-else class="text-sm text-gray-400 italic">System / Automator</span>
      </template>

      <template #cell(event)="{ rowData }">
        <va-badge 
          :text="rowData.event" 
          :color="getEventColor(rowData.event)" 
          class="uppercase text-[10px]"
        />
      </template>

      <template #cell(subject_type)="{ rowData }">
        <span class="text-sm font-mono text-gray-600">
          {{ extractModelName(rowData.subject_type) }} #{{ rowData.subject_id }}
        </span>
      </template>

      <template #cell(details)="{ rowData }">
        <div v-if="rowData.properties" class="text-xs max-w-xs overflow-hidden text-ellipsis whitespace-nowrap cursor-pointer text-blue-500 hover:text-blue-700 font-medium" @click="viewDetails(rowData)">
            <va-icon name="visibility" size="small" class="mr-1" /> View Changes
        </div>
        <span v-else class="text-gray-400 text-xs">N/A</span>
      </template>
    </ResourceTable>

    <va-modal v-model="isModalOpen" title="Activity Details" hide-default-actions size="large">
      <div v-if="selectedLog" class="p-2">
        <div class="mb-4">
          <div class="text-xs font-semibold text-gray-400 uppercase mb-1">Description</div>
          <p class="text-md text-gray-700 font-medium">{{ selectedLog.description }}</p>
        </div>

        <div class="mb-2">
          <div class="text-xs font-semibold text-gray-400 uppercase mb-3">Changes</div>
          <ActivityLogDetails 
            :properties="selectedLog.properties" 
            :users="options.users"
          />
        </div>
      </div>
      <template #footer>
        <va-button @click="isModalOpen = false" color="secondary" preset="secondary">Close</va-button>
      </template>
    </va-modal>

  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import ResourceTable from '@/Components/Common/ResourceTable.vue';
import ResourceFilter from '@/Components/Filters/ResourceFilter.vue';
import ActivityLogDetails from '@/Components/ActivityLog/ActivityLogDetails.vue';
import { useFilters } from '@/Composables/useFilters.js';
import { VaBadge, VaModal, VaButton, VaIcon, VaInput, VaSelect } from 'vuestic-ui';

const props = defineProps({
  logs: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  options: { type: Object, default: () => ({ users: [], events: [], targets: [] }) }
});

const isModalOpen = ref(false);
const selectedLog = ref(null);

/**
 * Safely parse incoming query arrays to prevent Vue from throwing type errors on multiple selects.
 */
const safeArray = (val) => {
    if (!val) return [];
    return Array.isArray(val) ? val : [val];
};

const initialFilters = {
    ...props.filters,
    user: safeArray(props.filters.user),
    event: safeArray(props.filters.event),
    target: safeArray(props.filters.target),
};

const { customFilters, changePage } = useFilters(
    initialFilters, 
    'activity-logs.index', 
    props.logs.current_page, 
    ['user', 'event', 'target', 'date_start', 'date_end']
);

const tableColumns = [
  { key: 'created_at', label: 'Date / Time' },
  { key: 'causer', label: 'User (Causer)' },
  { key: 'event', label: 'Event' },
  { key: 'subject_type', label: 'Target Model' },
  { key: 'details', label: 'Details' }
];

const userOptions = computed(() => {
  const opts = props.options.users.map(u => ({ text: u.name, value: String(u.id) }));
  opts.unshift({ text: 'System / Automator', value: 'system' });
  return opts;
});

const extractModelName = (fullyQualifiedName) => {
  if (!fullyQualifiedName) return 'Unknown';
  const parts = fullyQualifiedName.split('\\');
  return parts[parts.length - 1];
};

const mappedTargets = computed(() => {
  return props.options.targets.map(t => ({ text: extractModelName(t), value: t }));
});

/**
 * Accurately tracks active filters recognizing empty arrays as inactive.
 */
const customActiveCount = computed(() => {
    return Object.values(customFilters).filter(val => {
        if (Array.isArray(val)) return val.length > 0;
        return val !== null && val !== '';
    }).length;
});

const clearCustomFilters = () => {
    customFilters.user = [];
    customFilters.event = [];
    customFilters.target = [];
    customFilters.date_start = null;
    customFilters.date_end = null;
};

const viewDetails = (log) => {
  selectedLog.value = log;
  isModalOpen.value = true;
};

const formatDateTime = (dateString) => {
  return new Date(dateString).toLocaleString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit'
  });
};

const getEventColor = (event) => {
  switch (event?.toLowerCase()) {
    case 'created': return 'success';
    case 'updated': return 'warning';
    case 'deleted': return 'danger';
    default: return 'info';
  }
};
</script>