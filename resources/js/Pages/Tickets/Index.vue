<template>
  <AppLayout>
    <Head :title="$t('tickets.page_title')" />

    <Transition name="fade-slide" mode="out-in" appear>
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <h1 class="text-2xl font-bold" style="color: var(--va-text-primary)">{{ $t('tickets.title') }}</h1>
        
        <div class="flex items-center gap-3 flex-wrap">
          
          <div class="flex items-center gap-2 bg-white dark:bg-gray-800 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm text-sm">
            <span class="text-gray-500 font-medium">Mostrar:</span>
            <select 
              v-model="perPage" 
              @change="updatePerPage"
              class="border-none bg-transparent text-gray-700 dark:text-gray-200 font-bold focus:ring-0 cursor-pointer p-0 pr-4 outline-none"
            >
              <option :value="10" class="text-gray-900 bg-white dark:text-gray-200 dark:bg-gray-800">10</option>
              <option :value="25" class="text-gray-900 bg-white dark:text-gray-200 dark:bg-gray-800">25</option>
              <option :value="50" class="text-gray-900 bg-white dark:text-gray-200 dark:bg-gray-800">50</option>
              <option :value="100" class="text-gray-900 bg-white dark:text-gray-200 dark:bg-gray-800">100</option>
            </select>
          </div>

          <div v-if="isSupporter" class="bg-gray-100 dark:bg-gray-800 p-1 rounded-lg flex shadow-inner">
            <button 
              @click="setViewMode('list')"
              class="p-1.5 rounded-md transition-all flex items-center justify-center"
              :class="viewMode === 'list' ? 'bg-white dark:bg-gray-700 shadow-sm text-primary' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
              title="Lista"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
              </svg>
            </button>
            <button 
              @click="setViewMode('board')"
              class="p-1.5 rounded-md transition-all flex items-center justify-center"
              :class="viewMode === 'board' ? 'bg-white dark:bg-gray-700 shadow-sm text-primary' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
              title="Quadro (Kanban)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
              </svg>
            </button>
          </div>

          <va-button 
            v-if="!isSupporter || workSessionStatus === 'active'" 
            color="primary" 
            icon="add" 
            @click="openCreateModal"
          >
            {{ $t('tickets.create_new') }}
          </va-button>
        </div>
      </div>
    </Transition>

    <Transition name="context-transition" mode="out-in" appear>
      <template v-if="isSupporter && workSessionStatus !== 'active'">
        <WorkSessionBlocker :session-status="workSessionStatus" />
      </template>

      <div v-else class="flex flex-col gap-4">
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

        <Transition name="fade" mode="out-in">
          <div v-if="isLoading">
            <SkeletonScreen :lines="5" :with-header="true" :with-avatar="true" />
          </div>
          
          <div v-else class="flex flex-col gap-4">
            
            <ResourceTable
              v-if="viewMode === 'list' || !isSupporter"
              :resource-data="tickets"
              :columns="columns"
              :empty-message="$t('tickets.no_tickets_found')"
              :clickable="true"
              :hide-pagination="true"
              @row:click="navigateToTicketEvent"
            >
              <template #cell(id)="{ rowData }">
                <span class="font-bold transition-colors hover:text-primary" style="color: var(--va-primary)">#{{ rowData.id }}</span>
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

            <TicketBoard 
              v-else
              :tickets="tickets.data"
              @update-status="handleBoardStatusUpdate"
              @ticket-click="navigateToTicketDirect"
            />

            <div v-if="tickets.links && tickets.links.length > 3" class="flex flex-col sm:flex-row justify-between items-center bg-white dark:bg-gray-900 p-4 rounded-xl border border-gray-100 dark:border-gray-800 shadow-sm mt-2">
              <span class="text-sm text-gray-500 mb-4 sm:mb-0">
                A mostrar <span class="font-bold text-gray-700 dark:text-gray-200">{{ tickets.from }}</span> a 
                <span class="font-bold text-gray-700 dark:text-gray-200">{{ tickets.to }}</span> de 
                <span class="font-bold text-gray-700 dark:text-gray-200">{{ tickets.total }}</span> resultados
              </span>
              
              <div class="flex flex-wrap gap-1 justify-center">
                <template v-for="(link, index) in tickets.links" :key="index">
                  <div
                    v-if="link.url === null"
                    class="px-3 py-1.5 text-sm rounded-md border border-gray-200 dark:border-gray-700 text-gray-400 bg-gray-50 dark:bg-gray-800 cursor-not-allowed"
                    v-html="link.label"
                  />
                  <button
                    v-else
                    @click="changePageGlobal(link.url)"
                    class="px-3 py-1.5 text-sm rounded-md border transition-colors hover:bg-gray-50 dark:hover:bg-gray-800"
                    :class="link.active ? 'bg-primary text-white border-primary shadow-sm hover:bg-primary' : 'bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700'"
                    v-html="link.label"
                  />
                </template>
              </div>
            </div>

          </div>
        </Transition>
      </div>
    </Transition>

    <TicketFormModal
      :show="isCreateModalOpen"
      :customers="customersList"
      @close="isCreateModalOpen = false"
    />
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import ResourceFilter from '@/Components/Filters/ResourceFilter.vue';
import ResourceTable from '@/Components/Common/ResourceTable.vue';
import TicketBoard from '@/Components/Tickets/TicketBoard.vue';
import TicketStatusBadge from '@/Components/Tickets/TicketStatusBadge.vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import TagBadge from '@/Components/Common/TagBadge.vue';
import TicketFormModal from './Partials/TicketFormModal.vue';
import WorkSessionBlocker from '@/Components/WorkSession/WorkSessionBlocker.vue';
import SkeletonScreen from '@/Components/Common/SkeletonScreen.vue';
import { useFilters } from '@/Composables/useFilters';

const props = defineProps({
  tickets: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  customersList: { type: Array, default: () => [] },
  availableTags: { type: Array, default: () => [] },
  workSessionStatus: { type: String, default: 'active' }
});

const page = usePage();
const { t, locale } = useI18n();

const isSupporter = page.props.auth.user.role !== 'customer';
const isCreateModalOpen = ref(false);
const isLoading = ref(false);

const viewMode = ref(localStorage.getItem('ticket_view_mode') || 'list');
const setViewMode = (mode) => {
  viewMode.value = mode;
  localStorage.setItem('ticket_view_mode', mode);
};

// Variável para controlar a Quantidade por Página
const perPage = ref(props.filters.per_page ? parseInt(props.filters.per_page) : 10);

const startLoading = () => isLoading.value = true;
const stopLoading = () => isLoading.value = false;

onMounted(() => {
  router.on('start', startLoading);
  router.on('finish', stopLoading);
});

const { query, selectedStatus, selectedSource, selectedCustomers, selectedAssignees, selectedTags, changePage } = useFilters(
  props.filters, 
  'tickets.index', 
  props.tickets.current_page
);

// Atualiza e dispara a query com o novo limite por página
const updatePerPage = () => {
  router.get(route('tickets.index'), {
    ...props.filters,
    per_page: perPage.value,
    page: 1 
  }, {
    preserveState: true,
    preserveScroll: true
  });
};

// Navegação de paginação global
const changePageGlobal = (url) => {
  if (!url) return;
  router.get(url, { per_page: perPage.value }, {
    preserveState: true,
    preserveScroll: true
  });
};

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
const navigateToTicketEvent = (event) => router.get(route('tickets.show', event.item.id));
const navigateToTicketDirect = (ticket) => router.get(route('tickets.show', ticket.id));

// Atualização de estado via Kanban D&D com o verbo PATCH estrito
const handleBoardStatusUpdate = ({ ticketId, newStatus, revertCallback }) => {
  router.patch(route('tickets.update-status', ticketId), {
    status: newStatus
  }, {
    preserveState: true,
    preserveScroll: true,
    onError: () => {
      if (revertCallback) revertCallback();
    }
  });
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  return new Date(dateString).toLocaleDateString(locale.value, {
    month: 'short', day: 'numeric', year: 'numeric'
  });
};
</script>

<style scoped>
/* Transições UI Fluídas */
.fade-slide-enter-active, .fade-slide-leave-active {
  transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.fade-slide-enter-from {
  opacity: 0;
  transform: translateY(-10px);
}
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(10px);
}

.context-transition-enter-active, .context-transition-leave-active {
  transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
}
.context-transition-enter-from {
  opacity: 0;
  transform: translateY(15px) scale(0.98);
}
.context-transition-leave-to {
  opacity: 0;
  transform: translateY(-15px) scale(0.98);
}

.fade-enter-active, .fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

/* Esconder aparência padrão do browser para um visual mais limpo */
select {
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
}
</style>