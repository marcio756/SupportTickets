<script setup>
/**
 * WorkSessions Index Page
 * Dashboard view integrating the DayPilot Weekly Calendar implementation.
 * Fully internationalized using the vue-i18n instance.
 */
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import WeeklyCalendar from '@/Components/WorkSession/WeeklyCalendar.vue';

const props = defineProps({
    sessions: {
        type: Array,
        required: true
    },
    users: Array,
    filters: Object,
    summary: Object,
});

const page = usePage();
const isLoading = ref(false);

let removeStartListener;
let removeFinishListener;

onMounted(() => {
  removeStartListener = router.on('start', () => isLoading.value = true);
  removeFinishListener = router.on('finish', () => isLoading.value = false);
});

onUnmounted(() => {
  if (removeStartListener) removeStartListener();
  if (removeFinishListener) removeFinishListener();
});

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
                    <div v-if="users && users.length > 0" class="w-56 relative">
                        <select 
                            v-model="filterUser" 
                            :disabled="isLoading"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-sm transition-all duration-200"
                            :class="{ 'opacity-60 cursor-wait': isLoading }"
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

        <div class="py-8 relative">
            
            <div v-if="isLoading" class="absolute inset-0 bg-white/40 dark:bg-gray-900/40 z-10 flex items-center justify-center backdrop-blur-[1px] transition-all duration-300 rounded-xl">
                <div class="h-10 w-10 rounded-full border-4 border-indigo-600 border-t-transparent animate-spin"></div>
            </div>

            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <div class="bg-indigo-600 p-6 rounded-2xl shadow-lg text-white mb-6 transition-opacity duration-300" :class="{ 'opacity-70': isLoading }">
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

                <div :class="{ 'pointer-events-none': isLoading }">
                    <WeeklyCalendar 
                        :week-start-date="filterWeekStart" 
                        :sessions="sessions" 
                        @update:weekStartDate="filterWeekStart = $event"
                    />
                </div>

            </div>
        </div>
    </AppLayout>
</template>