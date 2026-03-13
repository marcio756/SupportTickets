<template>
  <AppLayout title="Vacation Map">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
      <h1 class="text-2xl font-bold" style="color: var(--va-text-primary)">Vacation Map</h1>
      
      <va-button v-if="!isAdmin" color="primary" icon="add" @click="showBookModal = true">
        Book Vacation
      </va-button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
      <StatCard :title="isAdmin ? 'Global Annual Total' : 'Annual Total'" :value="summary.total_allowed" icon="event" style="border-left: 4px solid var(--va-info);" />
      <StatCard :title="isAdmin ? 'Total Used Days' : 'Used Days'" :value="summary.used_days" icon="event_busy" style="border-left: 4px solid var(--va-danger);" />
      <StatCard :title="isAdmin ? 'Total Remaining Days' : 'Remaining Days'" :value="summary.remaining_days" icon="event_available" style="border-left: 4px solid var(--va-success);" />
    </div>

    <div v-if="isAdmin" class="mb-6 p-4 rounded-lg border border-solid flex flex-col md:flex-row gap-4 items-start md:items-end" style="background-color: var(--va-background-secondary); border-color: var(--va-background-border);">
        <va-select
            v-model="filters.status"
            :options="statusOptions"
            value-by="value"
            label="Filter by Status"
            class="w-full md:w-48"
            clearable
        />
        <va-select
            v-model="filters.supporter_id"
            :options="supporterOptions"
            value-by="value"
            label="Filter by Supporter"
            class="w-full md:w-56"
            clearable
        />
        <va-input
            v-model="filters.date_from"
            type="date"
            label="From Date"
            class="w-full md:w-40"
            clearable
        />
        <va-input
            v-model="filters.date_to"
            type="date"
            label="To Date"
            class="w-full md:w-40"
            clearable
        />
        <va-button preset="secondary" icon="clear" @click="clearFilters" class="mb-1">
            Clear Filters
        </va-button>
    </div>

    <div v-if="isAdmin" class="mb-6 p-6 rounded-lg border border-solid" style="background-color: var(--va-background-secondary); border-color: var(--va-background-border);">
        <h3 class="text-lg font-bold mb-4" style="color: var(--va-text-primary)">Manage Vacations</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr style="border-bottom: 1px solid var(--va-background-border);">
                        <th class="py-2 px-4 text-sm font-semibold" style="color: var(--va-secondary)">Supporter</th>
                        <th class="py-2 px-4 text-sm font-semibold" style="color: var(--va-secondary)">Dates</th>
                        <th class="py-2 px-4 text-sm font-semibold" style="color: var(--va-secondary)">Days</th>
                        <th class="py-2 px-4 text-sm font-semibold" style="color: var(--va-secondary)">Status</th>
                        <th class="py-2 px-4 text-sm font-semibold text-right" style="color: var(--va-secondary)">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="vacation in filteredVacations" :key="vacation.id" class="hover:bg-gray-50/5" style="border-bottom: 1px solid var(--va-background-border);">
                        <td class="py-3 px-4" style="color: var(--va-text-primary)">
                            {{ vacation.supporter?.name }}
                            <va-badge v-if="vacation.supporter?.team" :text="vacation.supporter.team.name" color="secondary" size="small" class="ml-2" />
                        </td>
                        <td class="py-3 px-4 font-mono text-sm" style="color: var(--va-text-primary)">
                            {{ vacation.start_date.substring(0, 10) }} <span class="mx-1 text-gray-400">&rarr;</span> {{ vacation.end_date.substring(0, 10) }}
                        </td>
                        <td class="py-3 px-4" style="color: var(--va-text-primary)">{{ vacation.total_days }}</td>
                        <td class="py-3 px-4">
                            <va-badge :text="vacation.status" :color="statusColor(vacation.status)" size="small" class="uppercase" />
                        </td>
                        <td class="py-3 px-4 text-right space-x-2">
                            <va-button v-if="vacation.status === 'pending'" preset="primary" color="success" size="small" title="Quick Approve" @click="updateStatus(vacation.id, 'approved')">
                                <va-icon name="check" size="small" />
                            </va-button>
                            <va-button v-if="vacation.status === 'pending'" preset="primary" color="warning" size="small" title="Quick Reject" @click="updateStatus(vacation.id, 'rejected')">
                                <va-icon name="close" size="small" />
                            </va-button>
                            <va-button preset="primary" color="info" size="small" icon="edit" title="Edit Request" @click="openEditModal(vacation)" />
                            <va-button preset="primary" color="danger" size="small" icon="delete" title="Delete Record" @click="deleteVacation(vacation.id)" />
                        </td>
                    </tr>
                    <tr v-if="filteredVacations.length === 0">
                        <td colspan="5" class="py-6 text-center" style="color: var(--va-secondary)">No vacation requests match your current filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="p-6 rounded-lg border border-solid overflow-hidden" style="background-color: var(--va-background-secondary); border-color: var(--va-background-border);">
      <VacationCalendar :supporters="filteredSupporters" :vacations="filteredVacations" />
    </div>

    <BookVacationModal v-if="!isAdmin" :show="showBookModal" @close="showBookModal = false" />
    <AdminEditVacationModal v-if="isAdmin" :show="showEditModal" :vacation="selectedVacation" @close="showEditModal = false" />
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatCard from '@/Components/Dashboard/StatCard.vue';
import VacationCalendar from '@/Components/Vacations/VacationCalendar.vue';
import BookVacationModal from '@/Components/Vacations/BookVacationModal.vue';
import AdminEditVacationModal from '@/Components/Vacations/AdminEditVacationModal.vue';

const props = defineProps({
    supporters: Array,
    vacations: Array,
    summary: Object
});

const page = usePage();
const showBookModal = ref(false);
const showEditModal = ref(false);
const selectedVacation = ref(null);

const isAdmin = computed(() => {
    const role = page.props.auth?.user?.role;
    if (!role) return false;
    return (typeof role === 'object' ? role.value : role) === 'admin';
});

// ==========================================
// FILTROS INSTANTÂNEOS
// ==========================================

const filters = ref({
    status: '',
    supporter_id: '',
    date_from: '',
    date_to: ''
});

const supporterOptions = computed(() => {
    return props.supporters.map(s => ({
        text: s.name,
        value: s.id
    }));
});

const statusOptions = [
    { text: 'All', value: '' },
    { text: 'Pending', value: 'pending' },
    { text: 'Approved', value: 'approved' },
    { text: 'Completed', value: 'completed' },
    { text: 'Rejected', value: 'rejected' }
];

const clearFilters = () => {
    filters.value = { status: '', supporter_id: '', date_from: '', date_to: '' };
};

const filteredVacations = computed(() => {
    return props.vacations.filter(v => {
        let match = true;

        if (filters.value.status && v.status !== filters.value.status) match = false;
        if (filters.value.supporter_id && v.supporter_id !== filters.value.supporter_id) match = false;
        
        const sDate = v.start_date.substring(0, 10);
        const eDate = v.end_date.substring(0, 10);
        
        if (filters.value.date_from && eDate < filters.value.date_from) match = false;
        if (filters.value.date_to && sDate > filters.value.date_to) match = false;
        
        return match;
    });
});

const filteredSupporters = computed(() => {
    if (filters.value.supporter_id) {
        return props.supporters.filter(s => s.id === filters.value.supporter_id);
    }
    return props.supporters;
});

// ==========================================
// AÇÕES E CORES
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
    if (confirm('Are you sure you want to completely delete this vacation record?')) {
        router.delete(route('vacations.destroy', id), { preserveScroll: true });
    }
};
</script>