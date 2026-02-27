<template>
  <AppLayout>
    <Head title="System Activity Logs" />

    <div class="mb-6">
      <h1 class="text-2xl font-bold" style="color: var(--va-text-primary)">Activity Logs</h1>
      <p class="text-sm text-gray-500 mt-1">Audit trail of system creations, updates, and deletions.</p>
    </div>

    <va-card>
      <va-card-content>
        <div v-if="logs.data.length === 0" class="text-center py-10 text-gray-500">
          No activity logs recorded yet.
        </div>

        <div class="overflow-x-auto" v-else>
          <table class="va-table w-full striped border-collapse">
            <thead>
              <tr>
                <th class="text-left font-bold py-3">Date / Time</th>
                <th class="text-left font-bold py-3">User (Causer)</th>
                <th class="text-left font-bold py-3">Event</th>
                <th class="text-left font-bold py-3">Target Model</th>
                <th class="text-left font-bold py-3">Details</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in logs.data" :key="log.id">
                <td class="text-sm text-gray-600 whitespace-nowrap">
                  {{ formatDateTime(log.created_at) }}
                </td>
                
                <td>
                  <div v-if="log.causer" class="flex items-center gap-2">
                    <UserAvatar :user="log.causer" size="24px" />
                    <span class="text-sm font-medium">{{ log.causer.name }}</span>
                  </div>
                  <span v-else class="text-sm text-gray-400 italic">System / Automator</span>
                </td>
                
                <td>
                  <va-badge 
                    :text="log.event" 
                    :color="getEventColor(log.event)" 
                    class="uppercase text-[10px]"
                  />
                </td>
                
                <td class="text-sm font-mono text-gray-600">
                  {{ extractModelName(log.subject_type) }} #{{ log.subject_id }}
                </td>
                
                <td class="text-xs">
                  <div v-if="log.properties" class="max-w-xs overflow-hidden text-ellipsis whitespace-nowrap cursor-pointer hover:text-blue-600" @click="viewDetails(log)">
                     <va-icon name="visibility" size="small" class="mr-1" /> View Changes
                  </div>
                  <span v-else class="text-gray-400">N/A</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mt-6 flex justify-center" v-if="logs.last_page > 1">
          <va-pagination
            v-model="currentPage"
            :pages="logs.last_page"
            :visible-pages="5"
            @update:modelValue="changePage"
          />
        </div>
      </va-card-content>
    </va-card>

    <va-modal
      v-model="isModalOpen"
      title="Activity Properties"
      hide-default-actions
    >
      <div v-if="selectedLog" class="p-4 bg-gray-50 rounded-md">
        <div class="text-sm font-bold mb-2 text-gray-700">Description:</div>
        <p class="text-sm text-gray-600 mb-4">{{ selectedLog.description }}</p>

        <div class="text-sm font-bold mb-2 text-gray-700">Raw Data (JSON):</div>
        <pre class="text-xs bg-gray-800 text-green-400 p-3 rounded overflow-x-auto max-h-64">{{ formatJSON(selectedLog.properties) }}</pre>
      </div>
      <template #footer>
        <va-button @click="isModalOpen = false">Close</va-button>
      </template>
    </va-modal>

  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import { VaBadge, VaPagination, VaModal, VaButton, VaIcon, VaCard, VaCardContent } from 'vuestic-ui';

const props = defineProps({
  logs: {
    type: Object,
    required: true
  }
});

const currentPage = ref(props.logs.current_page);
const isModalOpen = ref(false);
const selectedLog = ref(null);

const changePage = (pageNumber) => {
  router.get(route('activity-logs.index'), { page: pageNumber }, { preserveScroll: true });
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

const extractModelName = (fullyQualifiedName) => {
  if (!fullyQualifiedName) return 'Unknown';
  const parts = fullyQualifiedName.split('\\');
  return parts[parts.length - 1];
};

const getEventColor = (event) => {
  switch (event?.toLowerCase()) {
    case 'created': return 'success';
    case 'updated': return 'warning';
    case 'deleted': return 'danger';
    default: return 'info';
  }
};

const formatJSON = (data) => {
  if (!data) return '{}';
  return JSON.stringify(data, null, 2);
};
</script>