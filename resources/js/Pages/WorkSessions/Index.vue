<script setup>
/**
 * WorkSessions Index Page
 * Provides a dashboard view for attendance records with administrative controls.
 * Uses custom ActionConfirmModal for safe deletion.
 */
import { ref, watch } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ActionConfirmModal from '@/Components/Common/ActionConfirmModal.vue';

const props = defineProps({
    sessions: Object,
    users: Array,
    filters: Object,
    summary: Object,
});

const page = usePage();

// Get local today's date formatted as YYYY-MM-DD
const today = new Date();
const localDate = new Date(today.getTime() - (today.getTimezoneOffset() * 60000)).toISOString().split('T')[0];

const filterDate = ref(props.filters.date || localDate);
const filterUser = ref(props.filters.user_id || '');

const showDeleteModal = ref(false);
const sessionToDelete = ref(null);

// Listen for filter changes and reload via Inertia
watch([filterDate, filterUser], () => {
    router.get(route('work-sessions.index'), {
        date: filterDate.value,
        user_id: filterUser.value,
    }, { 
        preserveState: true, 
        preserveScroll: true,
        replace: true
    });
});

/**
 * Triggers the modal UI and stores the targeted ID.
 * @param {number} id 
 */
const confirmDelete = (id) => {
    sessionToDelete.value = id;
    showDeleteModal.value = true;
};

/**
 * Executes the deletion request and closes the modal.
 */
const executeDelete = () => {
    if (!sessionToDelete.value) return;
    
    router.delete(route('work-sessions.destroy', sessionToDelete.value), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false;
            sessionToDelete.value = null;
        }
    });
};

/**
 * UI helper for status badge styling.
 */
const getStatusClasses = (status) => {
    const map = {
        active: 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-400 dark:border-green-800',
        paused: 'bg-yellow-100 text-yellow-700 border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-400 dark:border-yellow-800',
        completed: 'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-900/20 dark:text-blue-300 dark:border-blue-800',
    };
    return map[status] || 'bg-gray-100 text-gray-700 border-gray-200';
};
</script>

<template>
    <AppLayout title="Time Tracking">
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <h2 class="font-bold text-2xl text-gray-800 dark:text-gray-100 leading-tight">
                    Attendance History
                </h2>
                
                <div class="flex items-center gap-3">
                    <div v-if="users && users.length > 0" class="w-48">
                        <select 
                            v-model="filterUser" 
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-sm"
                        >
                            <option value="">All Supporters</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                    </div>

                    <div class="w-40">
                        <input 
                            type="date" 
                            v-model="filterDate" 
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:ring-indigo-500 focus:border-indigo-500 text-sm shadow-sm" 
                        />
                    </div>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-indigo-600 p-6 rounded-2xl shadow-lg text-white">
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-sm font-medium opacity-80 uppercase tracking-wider">Total Worked Time</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div class="text-4xl font-extrabold">
                            {{ summary.total_hours }}h {{ summary.total_minutes }}m
                        </div>
                        <p class="mt-2 text-xs opacity-70 italic">Calculated for the current filtered period.</p>
                    </div>

                    <div v-if="!page.props.auth.user.role.includes('admin')" class="bg-orange-50 dark:bg-orange-900/30 p-6 rounded-2xl shadow-sm border border-orange-200 dark:border-orange-800">
                        <div class="flex items-center gap-2 mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-orange-600 dark:text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span class="text-sm font-black text-orange-800 dark:text-orange-300 uppercase tracking-wider">Strict Policy Warning</span>
                        </div>
                        <p class="mt-2 text-sm text-orange-800 dark:text-orange-400 font-medium">
                            Only <strong>one shift</strong> is allowed per day. Once concluded, the day is locked and cannot be restarted. Use "Pause" for short breaks.
                        </p>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-2xl border border-gray-200 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr class="bg-gray-50/50 dark:bg-gray-800">
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">Date</th>
                                    <th v-if="users && users.length > 0" class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">Supporter</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">Status</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">Clock In</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">Clock Out</th>
                                    <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">Net Time</th>
                                    <th v-if="page.props.auth.user.role.includes('admin')" class="px-6 py-4 text-center text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="session in sessions.data" :key="session.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-white">
                                        {{ session.date }}
                                    </td>
                                    <td v-if="users && users.length > 0" class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-7 w-7 rounded-full bg-indigo-100 dark:bg-indigo-900/40 flex items-center justify-center text-indigo-700 dark:text-indigo-300 text-[10px] font-bold mr-2 uppercase">
                                                {{ session.user?.name.charAt(0) }}
                                            </div>
                                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ session.user?.name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase border" :class="getStatusClasses(session.status)">
                                            {{ session.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                        {{ session.started_at }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                        {{ session.ended_at || '---' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-black text-gray-900 dark:text-white">
                                        {{ session.total_time_formatted }}
                                    </td>
                                    <td v-if="page.props.auth.user.role.includes('admin')" class="px-6 py-4 whitespace-nowrap text-center">
                                        <button @click="confirmDelete(session.id)" class="p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition" title="Delete Log">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="sessions.data.length === 0">
                                    <td :colspan="page.props.auth.user.role.includes('admin') ? 7 : 6" class="px-6 py-16 text-center text-gray-500 dark:text-gray-400">
                                        No attendance records found for the selected date.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <ActionConfirmModal 
            :show="showDeleteModal"
            title="Delete Attendance Record"
            message="Are you sure you want to permanently delete this record? This action cannot be undone and will be logged in the system audit trail."
            confirm-text="Delete Record"
            cancel-text="Cancel"
            intent="danger"
            @confirm="executeDelete"
            @close="showDeleteModal = false"
        />
    </AppLayout>
</template>