<template>
    <va-card>
        <va-card-title>{{ title }}</va-card-title>
        <va-card-content>
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="py-2">{{ $t('dashboard.rankings.user') }}</th>
                        <th class="py-2">{{ $t('dashboard.rankings.email') }}</th>
                        <th class="py-2 text-right">{{ metricLabel }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr 
                        v-for="item in items" 
                        :key="item.id" 
                        class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50"
                    >
                        <td class="py-3">
                            <div class="flex items-center gap-3">
                                <UserAvatar :user="item" size="32px" />
                                <span class="font-medium" style="color: var(--va-text-primary)">
                                    {{ item.name }}
                                </span>
                            </div>
                        </td>
                        <td class="py-3 text-gray-500">{{ item.email }}</td>
                        <td class="py-3 text-right font-bold text-blue-600">
                            {{ item[metricKey] }}
                        </td>
                    </tr>
                    <tr v-if="!items.length">
                        <td colspan="3" class="py-4 text-center text-gray-500">
                            {{ $t('dashboard.rankings.no_data') }}
                        </td>
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
 * Generic component for Ranking tables (Top X).
 * Adheres to the Single Responsibility Principle (SRP) by strictly handling tabular data presentation.
 */
defineProps({
    title: { type: String, required: true },
    items: { type: Array, required: true, default: () => [] },
    metricLabel: { type: String, required: true }, // e.g., "Tickets" or "Resolved"
    metricKey: { type: String, required: true }    // e.g., "tickets_count" or "resolved_count"
});
</script>