<template>
  <AppLayout :title="$t('vacations.title')">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
      <h1 class="text-2xl font-bold" style="color: var(--va-text-primary)">{{ $t('vacations.title') }}</h1>
      
      <va-button v-if="!isAdmin" color="primary" icon="add" @click="showBookModal = true">
        {{ $t('vacations.book_vacation') }}
      </va-button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
      <StatCard :title="isAdmin ? $t('vacations.summary.global_total') : $t('vacations.summary.annual_total')" :value="summary.total_allowed" icon="event" style="border-left: 4px solid var(--va-info);" />
      <StatCard :title="isAdmin ? $t('vacations.summary.global_used') : $t('vacations.summary.used_days')" :value="summary.used_days" icon="event_busy" style="border-left: 4px solid var(--va-danger);" />
      <StatCard :title="isAdmin ? $t('vacations.summary.global_remaining') : $t('vacations.summary.remaining_days')" :value="summary.remaining_days" icon="event_available" style="border-left: 4px solid var(--va-success);" />
    </div>

    <div v-if="isAdmin" class="mb-6 p-4 rounded-lg border border-solid flex flex-col md:flex-row gap-4 items-start md:items-end" style="background-color: var(--va-background-secondary); border-color: var(--va-background-border);">
        <va-select
            v-model="filters.status"
            :options="statusOptions"
            value-by="value"
            text-by="text"
            :label="$t('vacations.filters.status')"
            class="w-full md:w-48"
            clearable
        />
        <va-select
            v-model="filters.supporter_id"
            :options="supporterOptions"
            value-by="value"
            text-by="text"
            :label="$t('vacations.filters.supporter')"
            class="w-full md:w-56"
            clearable
        />
        <va-input
            v-model="filters.date_from"
            type="date"
            :label="$t('vacations.filters.date_from')"
            class="w-full md:w-40"
            clearable
        />
        <va-input
            v-model="filters.date_to"
            type="date"
            :label="$t('vacations.filters.date_to')"
            class="w-full md:w-40"
            clearable
        />
        <va-button preset="secondary" icon="clear" @click="clearFilters" class="mb-1">
            {{ $t('vacations.filters.clear') }}
        </va-button>
    </div>

    <div v-if="isAdmin" class="mb-6 p-6 rounded-lg border border-solid" style="background-color: var(--va-background-secondary); border-color: var(--va-background-border);">
        <h3 class="text-lg font-bold mb-4" style="color: var(--va-text-primary)">{{ $t('vacations.table.manage') }}</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr style="border-bottom: 1px solid var(--va-background-border);">
                        <th class="py-2 px-4 text-sm font-semibold" style="color: var(--va-secondary)">{{ $t('vacations.table.supporter') }}</th>
                        <th class="py-2 px-4 text-sm font-semibold" style="color: var(--va-secondary)">{{ $t('vacations.table.dates') }}</th>
                        <th class="py-2 px-4 text-sm font-semibold" style="color: var(--va-secondary)">{{ $t('vacations.table.days') }}</th>
                        <th class="py-2 px-4 text-sm font-semibold" style="color: var(--va-secondary)">{{ $t('vacations.table.status') }}</th>
                        <th class="py-2 px-4 text-sm font-semibold text-right" style="color: var(--va-secondary)">{{ $t('vacations.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="vacation in vacations?.data" :key="vacation.id" class="hover:bg-gray-50/5" style="border-bottom: 1px solid var(--va-background-border);">
                        <td class="py-3 px-4" style="color: var(--va-text-primary)">
                            {{ vacation.supporter?.name }}
                            <va-badge v-if="vacation.supporter?.team" :text="vacation.supporter.team.name" color="secondary" size="small" class="ml-2" />
                        </td>
                        <td class="py-3 px-4 font-mono text-sm" style="color: var(--va-text-primary)">
                            {{ vacation.start_date.substring(0, 10) }} <span class="mx-1 text-gray-400">&rarr;</span> {{ vacation.end_date.substring(0, 10) }}
                        </td>
                        <td class="py-3 px-4" style="color: var(--va-text-primary)">{{ vacation.total_days }}</td>
                        <td class="py-3 px-4">
                            <va-badge :text="$t(`vacations.filters.${vacation.status}`)" :color="statusColor(vacation.status)" size="small" class="uppercase" />
                        </td>
                        <td class="py-3 px-4 text-right space-x-2">
                            <va-button v-if="vacation.status === 'pending'" preset="primary" color="success" size="small" :title="$t('vacations.table.quick_approve')" @click="updateStatus(vacation.id, 'approved')">
                                <va-icon name="check" size="small" />
                            </va-button>
                            <va-button v-if="vacation.status === 'pending'" preset="primary" color="warning" size="small" :title="$t('vacations.table.quick_reject')" @click="updateStatus(vacation.id, 'rejected')">
                                <va-icon name="close" size="small" />
                            </va-button>
                            <va-button preset="primary" color="info" size="small" icon="edit" :title="$t('vacations.table.edit_request')" @click="openEditModal(vacation)" />
                            <va-button preset="primary" color="danger" size="small" icon="delete" :title="$t('vacations.table.delete_record')" @click="deleteVacation(vacation.id)" />
                        </td>
                    </tr>
                    <tr v-if="!vacations?.data || vacations.data.length === 0">
                        <td colspan="5" class="py-6 text-center" style="color: var(--va-secondary)">{{ $t('vacations.table.no_results') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="vacations && vacations.last_page > 1" class="flex justify-center mt-6">
            <va-pagination
                v-model="currentPage"
                :pages="vacations.last_page"
                :visible-pages="5"
                color="primary"
                @update:modelValue="changePage"
            />
        </div>
    </div>

    <div class="p-6 rounded-lg border border-solid overflow-hidden" style="background-color: var(--va-background-secondary); border-color: var(--va-background-border);">
      <VacationCalendar :supporters="filteredSupporters" :vacations="calendarVacations" />
    </div>

    <BookVacationModal v-if="!isAdmin" :show="showBookModal" @close="showBookModal = false" />
    <AdminEditVacationModal v-if="isAdmin" :show="showEditModal" :vacation="selectedVacation" @close="showEditModal = false" />
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatCard from '@/Components/Dashboard/StatCard.vue';
import VacationCalendar from '@/Components/Vacations/VacationCalendar.vue';
import BookVacationModal from '@/Components/Vacations/BookVacationModal.vue';
import AdminEditVacationModal from '@/Components/Vacations/AdminEditVacationModal.vue';

const props = defineProps({
    supporters: Array,
    vacations: Object, // Agora é um objeto paginado
    calendarVacations: Array, // Lista isolada para o calendário (Limitada a 1 ano)
    summary: Object,
    filters: Object
});

const { t } = useI18n();
const page = usePage();
const showBookModal = ref(false);
const showEditModal = ref(false);
const selectedVacation = ref(null);

const currentPage = ref(props.vacations?.current_page || 1);

watch(() => props.vacations, (newVal) => {
    if (newVal) currentPage.value = newVal.current_page;
});

const isAdmin = computed(() => {
    const role = page.props.auth?.user?.role;
    if (!role) return false;
    return (typeof role === 'object' ? role.value : role) === 'admin';
});

// ==========================================
// SERVER-SIDE FILTERS & PAGINATION
// ==========================================

const filters = ref({
    status: props.filters?.status || '',
    supporter_id: props.filters?.supporter_id || '',
    date_from: props.filters?.date_from || '',
    date_to: props.filters?.date_to || ''
});

let filterTimeout;

// Executa requisições de forma reativa mas usando Debounce para não sobrecarregar
watch(filters, () => {
    clearTimeout(filterTimeout);
    filterTimeout = setTimeout(() => {
        router.get(route('vacations.index'), { ...filters.value, page: 1 }, { 
            preserveState: true, 
            preserveScroll: true 
        });
    }, 400);
}, { deep: true });

const changePage = (page) => {
    router.get(route('vacations.index'), { ...filters.value, page }, { 
        preserveState: true, 
        preserveScroll: true 
    });
};

const clearFilters = () => {
    filters.value = { status: '', supporter_id: '', date_from: '', date_to: '' };
};

const supporterOptions = computed(() => {
    return props.supporters.map(s => ({
        text: s.name,
        value: s.id
    }));
});

const statusOptions = computed(() => [
    { text: t('vacations.filters.all'), value: '' },
    { text: t('vacations.filters.pending'), value: 'pending' },
    { text: t('vacations.filters.approved'), value: 'approved' },
    { text: t('vacations.filters.completed'), value: 'completed' },
    { text: t('vacations.filters.rejected'), value: 'rejected' }
]);

const filteredSupporters = computed(() => {
    if (filters.value.supporter_id) {
        return props.supporters.filter(s => s.id == filters.value.supporter_id);
    }
    return props.supporters;
});

// ==========================================
// ACTIONS AND COLORS
// ==========================================

const statusColor = (status) => {
    const colors = { 
        'pending': 'warning', 
        'approved': 'success', 
        'completed': 'secondary', 
        'rejected': 'danger' 
    };
    return colors[status] || 'info';
};

const updateStatus = (id, status) => {
    router.patch(route('vacations.status', id), { status: status }, { preserveScroll: true });
};

const openEditModal = (vacation) => {
    selectedVacation.value = vacation;
    showEditModal.value = true;
};

const deleteVacation = (id) => {
    if (confirm(t('vacations.table.confirm_delete'))) {
        router.delete(route('vacations.destroy', id), { preserveScroll: true });
    }
};
</script>