<script setup>
/**
 * WorkSessions Index Page
 * Dashboard view integrating the DayPilot Weekly Calendar implementation.
 * Fully internationalized using the vue-i18n instance.
 */
import { ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import WeeklyCalendar from '@/Components/WorkSession/WeeklyCalendar.vue';

const props = defineProps({
    sessions: Array,
    users: Array,
    filters: Object,
    summary: Object,
});

const page = usePage();

/**
 * Calculates the start date (Monday) of a given date safely.
 * @param {Date|string} dateObj - The reference date.
 * @returns {string} The ISO formatted date string.
 */
const getStartOfWeek = (dateObj) => {
    const date = new Date(dateObj);
    const day = date.getDay();
    const diff = date.getDate() - day + (day === 0 ? -6 : 1);
    date.setDate(diff);
    return date.toISOString().split('T')[0];
};

const filterWeekStart = ref(props.filters.week_start || getStartOfWeek(new Date()));
const filterUser = ref(props.filters.user_id || '');

// Listen for filter changes and reload via Inertia to fetch the new week
watch([filterWeekStart, filterUser], () => {
    router.get(route('work-sessions.index'), {
        week_start: filterWeekStart.value,
        user_id: filterUser.value,
    }, { 
        preserveState: true, 
        preserveScroll: true,
        replace: true
    });
});
</script>

<template>
    <AppLayout :title="$t('work_sessions.index.page_title')">
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <h2 class="font-bold text-2xl text-gray-800 dark:text-gray-100 leading-tight">
                    {{ $t('work_sessions.index.header_title') }}
                </h2>
                
                <div class="flex items-center gap-4">
                    <div v-if="users && users.length > 0" class="w-56">
                        <select 
                            v-model="filterUser" 
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-sm transition-colors duration-200"
                        >
                            <option value="">{{ $t('work_sessions.index.all_supporters') }}</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <div class="bg-indigo-600 p-6 rounded-2xl shadow-lg text-white mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-sm font-medium opacity-80 uppercase tracking-wider">{{ $t('work_sessions.index.total_worked_week') }}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="text-4xl font-extrabold">
                        {{ summary.total_hours }}h {{ summary.total_minutes }}m
                    </div>
                </div>

                <WeeklyCalendar 
                    :week-start-date="filterWeekStart" 
                    :sessions="sessions" 
                    @update:weekStartDate="filterWeekStart = $event"
                />

            </div>
        </div>
    </AppLayout>
</template>