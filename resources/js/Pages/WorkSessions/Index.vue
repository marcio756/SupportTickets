<script setup>
import { ref, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    sessions: Object,
    users: Array,
    filters: Object,
    summary: Object,
});

const filterDate = ref(props.filters.date || '');
const filterUser = ref(props.filters.user_id || '');

// Automatically trigger server-side filtering when inputs change
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
</script>

<template>
    <AppLayout title="Time Tracking">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Work Sessions & Time Tracking
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="text-sm text-gray-500 dark:text-gray-400 font-medium uppercase tracking-wider">
                            Total Worked Time (Filtered Period)
                        </div>
                        <div class="mt-2 text-3xl font-bold text-indigo-600 dark:text-indigo-400">
                            {{ summary.total_hours }}h {{ summary.total_minutes }}m
                        </div>
                        <p class="mt-1 text-xs text-gray-400">Calculated based on completed sessions only.</p>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                    
                    <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row justify-between items-center gap-4">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Audit logs for daily shifts and active attendance.
                        </div>
                        
                        <div class="flex flex-col sm:flex-row items-center gap-4">
                            <div v-if="users && users.length > 0">
                                <select 
                                    v-model="filterUser" 
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm shadow-sm"
                                >
                                    <option value="">All Supporters</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">
                                        {{ user.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <input 
                                    type="date" 
                                    v-model="filterDate" 
                                    class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm shadow-sm" 
                                />
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                                    <th v-if="users && users.length > 0" scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Supporter</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Clock In</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Clock Out</th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pauses</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Work Time</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="session in sessions.data" :key="session.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ session.date }}
                                    </td>
                                    <td v-if="users && users.length > 0" class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        {{ session.user?.name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                            :class="{
                                                'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': session.status === 'active',
                                                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400': session.status === 'paused',
                                                'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300': session.status === 'completed',
                                            }">
                                            {{ session.status.toUpperCase() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        {{ session.started_at }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                        {{ session.ended_at || '---' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300 text-center font-mono">
                                        {{ session.pauses_count }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 dark:text-white text-right">
                                        {{ session.total_time_formatted }}
                                    </td>
                                </tr>

                                <tr v-if="sessions.data.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center justify-center">
                                            <p>No work sessions found for the selected filters.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div v-if="sessions.links && sessions.links.length > 3" class="p-4 border-t border-gray-200 dark:border-gray-700 flex justify-center">
                        <template v-for="(link, k) in sessions.links" :key="k">
                            <div v-if="link.url === null" class="mr-1 mb-1 px-3 py-2 text-sm text-gray-400 border rounded" v-html="link.label"></div>
                            <Link v-else :href="link.url" class="mr-1 mb-1 px-3 py-2 text-sm border rounded hover:bg-white focus:border-indigo-500" :class="{ 'bg-indigo-50 dark:bg-gray-700 border-indigo-500 text-indigo-600 dark:text-white': link.active, 'text-gray-700 dark:text-gray-300': !link.active }" v-html="link.label" preserve-scroll />
                        </template>
                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>