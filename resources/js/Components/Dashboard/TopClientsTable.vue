<template>
    <va-card>
        <va-card-title>Top Clients (By Tickets)</va-card-title>
        <va-card-content>
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="py-2">Name</th>
                        <th class="py-2">Email</th>
                        <th class="py-2 text-right">Tickets</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="client in clients" :key="client.id" class="border-b border-gray-100 last:border-0 hover:bg-gray-50">
                        <td class="py-3">
                            <div class="flex items-center gap-3">
                                <UserAvatar :user="client" size="32px" />
                                <span class="font-medium">{{ client.name }}</span>
                            </div>
                        </td>
                        <td class="py-3 text-gray-500">{{ client.email }}</td>
                        <td class="py-3 text-right font-bold text-blue-600">{{ client.tickets_count }}</td>
                    </tr>
                    <tr v-if="!clients.length">
                        <td colspan="3" class="py-4 text-center text-gray-500">No client data available.</td>
                    </tr>
                </tbody>
            </table>
        </va-card-content>
    </va-card>
</template>

<script setup>
import { VaCard, VaCardTitle, VaCardContent } from 'vuestic-ui';
import UserAvatar from '@/Components/Common/UserAvatar.vue';

/**
 * Display table for top clients structured by ticket activity.
 * * @property {Array} clients - List of clients with their respective ticket counts
 */
defineProps({
    clients: {
        type: Array,
        required: true,
        default: () => []
    }
});
</script>