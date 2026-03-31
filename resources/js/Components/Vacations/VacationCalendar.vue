<template>
    <div class="overflow-hidden flex flex-col h-full">
        <div class="flex flex-col sm:flex-row justify-between items-center mb-4 px-2 gap-4">
            <h3 class="text-lg font-medium font-mono capitalize" style="color: var(--va-text-primary);">
                {{ formatMonthYear(currentDate) }}
            </h3>
            
            <div class="flex items-center space-x-2">
                <va-button preset="plain" size="small" icon="west" @click="previousMonth" color="primary">
                    {{ $t('common.previous') }}
                </va-button>
                <va-button preset="plain" size="small" icon-right="east" @click="nextMonth" color="primary">
                    {{ $t('common.next') }}
                </va-button>
            </div>
        </div>

        <div class="overflow-x-auto border rounded-lg border-solid mb-4" style="border-color: var(--va-background-border);">
            <table class="min-w-full text-sm border-collapse">
                <thead style="background-color: var(--va-background-element); border-bottom: 1px solid var(--va-background-border);">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium sticky left-0 z-10 w-48" style="color: var(--va-secondary); background-color: var(--va-background-element);">
                            {{ $t('vacations.table.supporter') || 'Apoiador' }}
                        </th>
                        <th class="px-4 py-3 text-left font-medium" style="color: var(--va-secondary);">
                            {{ $t('vacations.table.team') || 'Equipa' }}
                        </th>
                        <th class="px-3 py-3 text-center font-medium" style="color: var(--va-secondary);">
                            {{ $t('vacations.table.change') || 'Mudança' }}
                        </th>
                        <th class="px-3 py-3 text-center font-medium" style="color: var(--va-secondary);">
                            {{ $t('vacations.table.used') || 'Usado' }}
                        </th>
                        <th class="px-3 py-3 text-center font-medium" style="color: var(--va-secondary);">
                            {{ $t('vacations.table.left') || 'Restante' }}
                        </th>
                        <th v-for="day in daysInMonth" :key="day" class="px-1 py-3 text-center font-medium min-w-[32px]" style="color: var(--va-secondary);">
                            {{ day }}
                        </th>
                    </tr>
                </thead>
                <tbody style="background-color: var(--va-background-secondary);">
                    <tr v-for="supporter in paginatedSupporters" :key="supporter.id" style="border-bottom: 1px solid var(--va-background-border);">
                        <td class="px-4 py-2 font-medium sticky left-0 z-10 truncate border-r border-solid" style="color: var(--va-text-primary); background-color: var(--va-background-secondary); border-color: var(--va-background-border);">
                            {{ supporter.name }}
                        </td>
                        <td class="px-4 py-2 truncate max-w-[120px]" style="color: var(--va-secondary);">
                            {{ supporter.team ? supporter.team.name : '-' }}
                        </td>
                        <td class="px-3 py-2 text-center capitalize" style="color: var(--va-secondary);">
                            <span>-</span>
                        </td>
                        
                        <td class="px-3 py-2 text-center font-bold" style="color: var(--va-danger);">
                            {{ calculateUsedDays(supporter.id) }}
                        </td>
                        <td class="px-3 py-2 text-center font-bold border-r border-solid" style="color: var(--va-success); border-color: var(--va-background-border);">
                            {{ Math.max(0, 22 - calculateUsedDays(supporter.id)) }}
                        </td>
                        
                        <td v-for="day in daysInMonth" :key="day" class="p-1 border-r border-solid relative" style="border-color: var(--va-background-border);">
                            <div v-if="isWeekend(day)" class="absolute inset-0" style="background-color: rgba(0, 0, 0, 0.05); pointer-events: none;"></div>
                            
                            <va-popover v-if="getVacationForDay(supporter.id, day)" :message="$t(`vacations.filters.${getVacationForDay(supporter.id, day).status}`)">
                                <div 
                                    class="w-full h-6 rounded-sm cursor-pointer shadow-sm" 
                                    :class="getStatusColorClass(getVacationForDay(supporter.id, day).status)"
                                ></div>
                            </va-popover>
                        </td>
                    </tr>

                    <tr v-if="paginatedSupporters.length === 0">
                        <td :colspan="5 + daysInMonth" class="py-8 text-center text-gray-500 font-medium">
                            {{ $t('vacations.table.no_results') || 'Nenhum resultado encontrado.' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="totalPages > 1" class="flex flex-col sm:flex-row justify-between items-center px-2 py-2 gap-4 border-t border-solid pt-4" style="border-color: var(--va-background-border);">
            <span class="text-sm font-medium" style="color: var(--va-secondary);">
                A mostrar {{ paginationStart + 1 }} a {{ paginationEnd }} de {{ supporters.length }} elementos
            </span>
            <va-pagination
                v-model="currentPage"
                :pages="totalPages"
                :visible-pages="5"
                color="primary"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    supporters: { type: Array, default: () => [] },
    vacations: { type: Array, default: () => [] }
});

// ==========================================
// PAGINATION LOGIC (PERFORMANCE OPTIMIZATION)
// ==========================================
const currentPage = ref(1);
const itemsPerPage = ref(10); // Carrega apenas 10 por página para garantir rendering rápido

// Reseta a página quando os filtros ou apoiadores mudam
watch(() => props.supporters, () => {
    currentPage.value = 1;
}, { deep: true });

const totalPages = computed(() => {
    return Math.ceil(props.supporters.length / itemsPerPage.value) || 1;
});

const paginatedSupporters = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return props.supporters.slice(start, end);
});

const paginationStart = computed(() => (currentPage.value - 1) * itemsPerPage.value);
const paginationEnd = computed(() => Math.min(currentPage.value * itemsPerPage.value, props.supporters.length));


// ==========================================
// CALENDAR & DATE LOGIC
// ==========================================
const currentDate = ref(new Date());

const formatMonthYear = (date) => {
    const options = { month: 'long', year: 'numeric' };
    // Presumindo ambiente PT
    return date.toLocaleDateString('pt-PT', options);
};

const daysInMonth = computed(() => {
    const year = currentDate.value.getFullYear();
    const month = currentDate.value.getMonth() + 1;
    return new Date(year, month, 0).getDate();
});

const isWeekend = (day) => {
    const year = currentDate.value.getFullYear();
    const month = currentDate.value.getMonth();
    const date = new Date(year, month, day);
    const dayOfWeek = date.getDay();
    return dayOfWeek === 0 || dayOfWeek === 6; // 0 = Domingo, 6 = Sábado
};

const previousMonth = () => {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1);
};

const nextMonth = () => {
    currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1);
};


// ==========================================
// VACATIONS DATA MAPPING
// ==========================================
const calculateUsedDays = (supporterId) => {
    return props.vacations
        .filter(v => v.supporter_id === supporterId && v.status !== 'rejected')
        .reduce((sum, v) => sum + (v.total_days || 0), 0);
};

const getVacationForDay = (supporterId, day) => {
    const year = currentDate.value.getFullYear();
    const month = currentDate.value.getMonth();
    // Forçar a hora a meio do dia para evitar bugs de fuso horário (T00:00 vs GMT)
    const targetDate = new Date(year, month, day, 12, 0, 0).toISOString().split('T')[0];

    return props.vacations.find(v => {
        if (v.supporter_id !== supporterId || v.status === 'rejected') return false;
        
        // Assegurar compatibilidade estrutural com o formato string da DB 'YYYY-MM-DD'
        const start = typeof v.start_date === 'string' ? v.start_date.substring(0, 10) : v.start_date;
        const end = typeof v.end_date === 'string' ? v.end_date.substring(0, 10) : v.end_date;
        
        return targetDate >= start && targetDate <= end;
    });
};

const getStatusColorClass = (status) => {
    switch(status) {
        case 'approved': return 'bg-green-500 shadow-green-200';
        case 'pending': return 'bg-yellow-400 shadow-yellow-200';
        case 'completed': return 'bg-gray-500 shadow-gray-200';
        case 'rejected': return 'bg-red-500 shadow-red-200';
        default: return 'bg-blue-500';
    }
};
</script>