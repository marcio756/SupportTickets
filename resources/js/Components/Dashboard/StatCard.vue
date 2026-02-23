<template>
    <va-card>
        <va-card-content class="flex flex-col items-center justify-center p-4">
            <va-icon :name="icon" size="large" :color="color" class="mb-2" />
            <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ value }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 text-center">{{ title }}</p>
        </va-card-content>
    </va-card>
</template>

<script setup>
import { VaCard, VaCardContent, VaIcon } from 'vuestic-ui';

/**
 * Visual card to present numeric KPIs.
 * * @property {string} title - The title of the statistic
 * @property {string|number} value - The value to display
 * @property {string} icon - Material icon name
 * @property {string} color - Theme color for the icon
 */
defineProps({
    title: { type: String, required: true },
    value: { type: [String, Number], required: true },
    icon: { type: String, default: 'analytics' },
    color: { type: String, default: 'primary' }
});
</script>