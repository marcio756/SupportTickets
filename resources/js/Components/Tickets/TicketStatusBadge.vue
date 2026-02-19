<template>
    <va-badge :text="formattedStatus" :color="badgeColor" class="text-xs uppercase" />
</template>

<script setup>
import { computed } from 'vue';
import { VaBadge } from 'vuestic-ui';

/**
 * Props definition
 */
const props = defineProps({
    status: {
        type: String,
        required: true,
    },
});

/**
 * Computes the color based on the strict ticket status enum values.
 */
const badgeColor = computed(() => {
    switch (props.status) {
        case 'open':
            return 'success';
        case 'in_progress':
            return 'warning';
        case 'resolved':
            return 'info';
        case 'closed':
            return 'danger';
        default:
            return 'primary';
    }
});

/**
 * Formats the raw snake_case status into a readable format.
 */
const formattedStatus = computed(() => {
    return props.status.replace('_', ' ');
});
</script>