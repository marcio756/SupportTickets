<template>
    <AppLayout>
        <Head title="Dashboard" />

        <div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">
                    Welcome, {{ user?.name }}!
                </h1>
                <p class="text-gray-500 dark:text-gray-400">Here is your daily overview.</p>
            </div>
            <va-badge v-if="user?.role" :text="user.role.toUpperCase()" color="primary" />
        </div>

        <div v-if="!isSupporter" class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <StatCard title="Open Tickets" :value="metrics.openTickets" icon="support_agent" color="warning" />
            <StatCard title="Resolved Tickets" :value="metrics.resolvedTickets" icon="check_circle" color="success" />
            <StatCard 
                title="Time Remaining (Min)" 
                :value="Math.floor(metrics.remainingSeconds / 60)" 
                icon="timer" 
                :color="metrics.remainingSeconds < 300 ? 'danger' : 'info'" 
            />
        </div>

        <div v-else class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <StatCard title="Global Active Tickets" :value="metrics.globalActiveTickets" icon="list_alt" color="primary" />
                <StatCard title="Resolved (All Time)" :value="metrics.globalResolvedTickets" icon="task_alt" color="success" />
                <StatCard title="Time Spent Today (Hrs)" :value="(metrics.totalTimeSpentSeconds / 3600).toFixed(1)" icon="schedule" color="secondary" />
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <TopClientsTable :clients="metrics.topClients" />
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
/**
 * Dashboard view component displaying user-specific metrics and statistics.
 * Refactored to handle potential undefined state during Inertia hydration.
 */
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatCard from '@/Components/Dashboard/StatCard.vue';
import TopClientsTable from '@/Components/Dashboard/TopClientsTable.vue';

defineProps({
    metrics: { 
        type: Object, 
        required: true 
    }
});

const page = usePage();

/**
 * Computed property to safely access the authenticated user.
 * Prevents "Cannot read properties of undefined" errors.
 * * @returns {Object|undefined} The authenticated user object or undefined.
 */
const user = computed(() => page.props.auth?.user);

/**
 * Determines if the current user has elevated privileges (admin or supporter).
 * * @returns {boolean} True if the user is a supporter or admin, false otherwise.
 */
const isSupporter = computed(() => {
    if (!user.value?.role) return false;
    return ['admin', 'supporter'].includes(user.value.role);
});
</script>