<template>
    <AppLayout>
        <Head title="Tickets Management" />

        <div class="page-header flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Support Tickets</h1>
                <p class="text-gray-500 text-sm">Manage and track your support requests.</p>
            </div>
            
            <Link :href="route('tickets.create')">
                <va-button color="primary" icon="add">
                    Open New Ticket
                </va-button>
            </Link>
        </div>

        <va-card>
            <va-card-content>
                <div v-if="tickets.data.length === 0" class="text-center py-8 text-gray-500">
                    No tickets found.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="va-table w-full">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Customer</th>
                                <th>Assigned To</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ticket in tickets.data" :key="ticket.id">
                                <td>#{{ ticket.id }}</td>
                                <td class="font-medium">{{ ticket.title }}</td>
                                <td>{{ ticket.customer?.name || 'Unknown' }}</td>
                                <td>
                                    <span v-if="ticket.assignee" class="text-sm text-gray-600">
                                        {{ ticket.assignee.name }}
                                    </span>
                                    <span v-else class="text-sm text-gray-400 italic">Unassigned</span>
                                </td>
                                <td>
                                    <TicketStatusBadge :status="ticket.status" />
                                </td>
                                <td class="text-sm text-gray-500">
                                    {{ new Date(ticket.created_at).toLocaleDateString() }}
                                </td>
                                <td>
                                    <Link :href="route('tickets.show', ticket.id)">
                                        <va-button preset="plain" icon="visibility" color="primary" />
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div v-if="tickets.last_page > 1" class="mt-6 flex justify-center">
                    <va-pagination 
                        v-model="currentPage" 
                        :pages="tickets.last_page" 
                        color="primary" 
                    />
                </div>
            </va-card-content>
        </va-card>
    </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TicketStatusBadge from '@/Components/Tickets/TicketStatusBadge.vue';
import { VaCard, VaCardContent, VaButton, VaPagination } from 'vuestic-ui';

const props = defineProps({
    tickets: {
        type: Object,
        required: true,
    }
});

const currentPage = ref(props.tickets.current_page);

/**
 * Watch for pagination changes and trigger an Inertia visit to fetch the new page data.
 * preserveScroll ensures the page doesn't uncomfortably jump to the top.
 */
watch(currentPage, (newPage) => {
    router.get(
        route('tickets.index'), 
        { page: newPage }, 
        { 
            preserveState: true, 
            preserveScroll: true 
        }
    );
});
</script>

<style scoped>
.va-table {
    width: 100%;
    border-collapse: collapse;
}
.va-table th {
    text-align: left;
    padding: 0.75rem 1rem;
    border-bottom: 2px solid #eef2f5;
    color: #6b7280;
    font-weight: 600;
}
.va-table td {
    padding: 1rem;
    border-bottom: 1px solid #eef2f5;
    vertical-align: middle;
}
</style>