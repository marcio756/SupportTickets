<template>
    <AppLayout>
        <Head :title="$t('dashboard.page_title')" />

        <div class="mb-6 flex flex-col md:flex-row md:justify-between md:items-end gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">
                    {{ $t('dashboard.welcome_user', { name: user?.name }) }}
                </h1>
                <p class="text-gray-500 dark:text-gray-400">{{ $t('dashboard.subtitle') }}</p>
            </div>
            <UserRoleBadge v-if="user?.role" :role="user.role" />
        </div>

        <div v-if="isCustomer" class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <StatCard :title="$t('dashboard.metrics.open_tickets')" :value="metrics.openTickets" icon="support_agent" color="warning" />
            <StatCard :title="$t('dashboard.metrics.resolved_tickets')" :value="metrics.resolvedTickets" icon="check_circle" color="success" />
            <StatCard 
                :title="$t('dashboard.metrics.time_remaining_min')" 
                :value="Math.floor(metrics.remainingSeconds / 60)" 
                icon="timer" 
                :color="metrics.remainingSeconds < 300 ? 'danger' : 'info'" 
            />
        </div>

        <div v-else class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <StatCard :title="$t('dashboard.metrics.global_active')" :value="metrics.globalActiveTickets" icon="list_alt" color="primary" />
                <StatCard :title="$t('dashboard.metrics.global_resolved')" :value="metrics.globalResolvedTickets" icon="task_alt" color="success" />
                
                <StatCard 
                    v-if="isAdmin"
                    :title="$t('dashboard.metrics.total_supporters')" 
                    :value="metrics.totalSupporters" 
                    icon="people" 
                    color="info" 
                />
                <StatCard 
                    v-else
                    :title="$t('dashboard.metrics.time_worked_hrs')" 
                    :value="(metrics.totalTimeSpentSeconds / 3600).toFixed(1)" 
                    icon="schedule" 
                    color="secondary" 
                />
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                
                <RankingTable 
                    :title="$t('dashboard.rankings.top_clients')"
                    :items="metrics.topClients"
                    :metric-label="$t('dashboard.rankings.tickets')"
                    metric-key="tickets_count"
                />
                
                <RankingTable 
                    v-if="isAdmin"
                    :title="$t('dashboard.rankings.top_supporters')"
                    :items="metrics.topSupporters"
                    :metric-label="$t('dashboard.rankings.resolved')"
                    metric-key="resolved_count"
                />

            </div>
        </div>
    </AppLayout>
</template>

<script setup>
/**
 * Dashboard Page Component.
 * Acts as the primary landing interface post-authentication.
 * Conditionally renders widgets and metrics based on user roles.
 */
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