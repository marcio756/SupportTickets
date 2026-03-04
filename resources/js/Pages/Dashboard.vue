<template>
    <AppLayout>
        <Head title="Dashboard" />

        <div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">
                    Welcome, {{ user?.name }}!
                </h1>
                <p class="text-gray-500 dark:text-gray-400">Your daily control panel.</p>
            </div>
            <UserRoleBadge v-if="user?.role" :role="user.role" />
        </div>

        <div v-if="isCustomer" class="grid grid-cols-1 md:grid-cols-3 gap-6">
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
                <StatCard title="Resolved (Global)" :value="metrics.globalResolvedTickets" icon="task_alt" color="success" />
                
                <StatCard 
                    v-if="isAdmin"
                    title="Total Supporters" 
                    :value="metrics.totalSupporters" 
                    icon="people" 
                    color="info" 
                />
                <StatCard 
                    v-else
                    title="Time Worked Today (Hrs)" 
                    :value="(metrics.totalTimeSpentSeconds / 3600).toFixed(1)" 
                    icon="schedule" 
                    color="secondary" 
                />
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                
                <RankingTable 
                    title="Top Clients (By Tickets)"
                    :items="metrics.topClients"
                    metric-label="Tickets"
                    metric-key="tickets_count"
                />
                
                <RankingTable 
                    v-if="isAdmin"
                    title="Top 5 Productive Supporters"
                    :items="metrics.topSupporters"
                    metric-label="Resolved"
                    metric-key="resolved_count"
                />

            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatCard from '@/Components/Dashboard/StatCard.vue';
import RankingTable from '@/Components/Dashboard/RankingTable.vue';
import UserRoleBadge from '@/Components/Common/UserRoleBadge.vue';

defineProps({
    metrics: { type: Object, required: true }
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

const normalizedRole = computed(() => {
    const role = user.value?.role;
    return (typeof role === 'object' ? role.value : role)?.toLowerCase();
});

const isAdmin = computed(() => normalizedRole.value === 'admin');
const isCustomer = computed(() => normalizedRole.value === 'customer');
</script>