<template>
  <AppLayout>
    <Head :title="$t('activity_log.page_title')" />

    <div class="mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--va-text-primary)">{{ $t('activity_log.title') }}</h1>
        <p class="text-sm text-gray-500 mt-1">{{ $t('activity_log.subtitle') }}</p>
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
                :label="$t('activity_log.filters.user')"
                multiple
                clearable
                virtual-scroller
                class="w-full xl:w-48 flex-none"
                preset="bordered"
            >
                <template #content="{ valueArray }">
                    <span v-if="valueArray.length === 1">{{ valueArray[0].text || valueArray[0] }}</span>
                    <span v-else-if="valueArray.length > 1" class="font-bold text-sm">{{ valueArray.length }} {{ $t('common.selected') }}</span>
                    <span v-else class="text-gray-400">{{ $t('activity_log.filters.user') }}</span>
                </template>
            </va-select>

            <va-select
                v-model="customFilters.event"
                :options="options.events"
                :label="$t('activity_log.filters.event')"
                multiple
                clearable
                virtual-scroller
                class="w-full xl:w-48 flex-none"
                preset="bordered"
            >
                <template #content="{ valueArray }">
                    <span v-if="valueArray.length === 1">{{ valueArray[0] }}</span>
                    <span v-else-if="valueArray.length > 1" class="font-bold text-sm">{{ valueArray.length }} {{ $t('common.selected') }}</span>
                    <span v-else class="text-gray-400">{{ $t('activity_log.filters.event') }}</span>
                </template>
            </va-select>

            <va-select
                v-model="customFilters.target"
                :options="mappedTargets"
                value-by="value"
                :label="$t('activity_log.filters.target')"
                multiple
                clearable
                virtual-scroller
                class="w-full xl:w-56 flex-none"
                preset="bordered"
            >
                <template #content="{ valueArray }">
                    <span v-if="valueArray.length === 1">{{ valueArray[0].text || valueArray[0] }}</span>
                    <span v-else-if="valueArray.length > 1" class="font-bold text-sm">{{ valueArray.length }} {{ $t('common.selected') }}</span>
                    <span v-else class="text-gray-400">{{ $t('activity_log.filters.target') }}</span>
                </template>
            </va-select>

            <va-input
                v-model="customFilters.date_start"
                type="date"
                :label="$t('activity_log.filters.date_from')"
                :max="customFilters.date_end"
                clearable
                class="w-full xl:w-40 flex-none"
                preset="bordered"
            />

            <va-input
                v-model="customFilters.date_end"
                type="date"
                :label="$t('activity_log.filters.date_to')"
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
        :empty-message="$t('activity_log.empty_message')"
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
        <span v-else class="text-sm text-gray-400 italic">{{ $t('activity_log.system_automator') }}</span>
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
            <va-icon name="visibility" size="small" class="mr-1" /> {{ $t('activity_log.view_changes') }}
        </div>
        <span v-else class="text-gray-400 text-xs">{{ $t('common.na') }}</span>
      </template>
    </ResourceTable>

    <va-modal v-model="isModalOpen" :title="$t('activity_log.details_title')" hide-default-actions size="large">
      <div v-if="selectedLog" class="p-2">
        <div class="mb-4">
          <div class="text-xs font-semibold text-gray-400 uppercase mb-1">{{ $t('activity_log.description') }}</div>
          <p class="text-md text-gray-700 font-medium">{{ selectedLog.description }}</p>
        </div>

        <div class="mb-2">
          <div class="text-xs font-semibold text-gray-400 uppercase mb-3">{{ $t('activity_log.changes') }}</div>
          <ActivityLogDetails 
            :properties="selectedLog.properties" 
            :users="options.users"
          />
        </div>
      </div>
      <template #footer>
        <va-button @click="isModalOpen = false" color="secondary" preset="secondary">{{ $t('common.close') }}</va-button>
      </template>
    </va-modal>

  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
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

const { t } = useI18n();
const isModalOpen = ref(false);
const selectedLog = ref(null);

/**
 * Ensures query parameters meant to be arrays are properly structured.
 * Prevents Vue selection components from throwing type errors.
 * * @param {any} val - The incoming filter value.
 * @returns {Array} A safely typed array.
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

/**
 * Computed property for table columns to ensure reactivity with i18n.
 * If language changes dynamically, headers will immediately reflect the new locale.
 */
const tableColumns = computed(() => [
  { key: 'created_at', label: t('activity_log.columns.date_time') },
  { key: 'causer', label: t('activity_log.columns.user_causer') },
  { key: 'event', label: t('activity_log.columns.event') },
  { key: 'subject_type', label: t('activity_log.columns.target_model') },
  { key: 'details', label: t('activity_log.columns.details') }
]);

/**
 * Builds the user dropdown options, prepending a system/automator entry
 * to allow filtering by automated tasks where 'causer' is null.
 */
const userOptions = computed(() => {
  const opts = props.options.users.map(u => ({ text: u.name, value: String(u.id) }));
  opts.unshift({ text: t('activity_log.system_automator'), value: 'system' });
  return opts;
});

/**
 * Extracts the base model name from a fully qualified PHP namespace.
 * Isolates presentation logic from raw backend architecture structures.
 * * @param {string} fullyQualifiedName - The namespace string (e.g., App\Models\User).
 * @returns {string} The base class name.
 */
const extractModelName = (fullyQualifiedName) => {
  if (!fullyQualifiedName) return t('common.unknown');
  const parts = fullyQualifiedName.split('\\');
  return parts[parts.length - 1];
};

const mappedTargets = computed(() => {
  return props.options.targets.map(t => ({ text: extractModelName(t), value: t }));
});

/**
 * Calculates the total number of actively applied custom filters.
 * Differentiates between empty strings, nulls, and empty arrays to maintain UI accuracy.
 */
const customActiveCount = computed(() => {
    return Object.values(customFilters).filter(val => {
        if (Array.isArray(val)) return val.length > 0;
        return val !== null && val !== '';
    }).length;
});

/**
 * Resets all active custom filters back to their initial unselected state.
 */
const clearCustomFilters = () => {
    customFilters.user = [];
    customFilters.event = [];
    customFilters.target = [];
    customFilters.date_start = null;
    customFilters.date_end = null;
};

/**
 * Opens the detailed activity modal with the selected log entry context.
 * * @param {Object} log - The log record selected from the table.
 */
const viewDetails = (log) => {
  selectedLog.value = log;
  isModalOpen.value = true;
};

/**
 * Standardizes the display format of log timestamps based on the current locale.
 * * @param {string} dateString - The raw ISO date string from the backend.
 * @returns {string} A human-readable date and time.
 */
const formatDateTime = (dateString) => {
  // Assuming a generic locale usage, could be tied to i18n locale state for perfection
  return new Date(dateString).toLocaleString(undefined, {
    month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit'
  });
};

/**
 * Maps the backend event type to a semantic UI color theme for badges.
 * * @param {string} event - The type of event (created, updated, deleted).
 * @returns {string} The corresponding Vuestic UI theme color.
 */
const getEventColor = (event) => {
  switch (event?.toLowerCase()) {
    case 'created': return 'success';
    case 'updated': return 'warning';
    case 'deleted': return 'danger';
    default: return 'info';
  }
};
</script>