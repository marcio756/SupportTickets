<template>
    <div :class="['support-timer flex items-center gap-2 px-3 py-1 rounded-full', badgeColorClass]">
        <va-icon name="timer" size="small" />
        <span class="font-mono font-bold">{{ formattedTime }}</span>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { VaIcon } from 'vuestic-ui';

/**
 * @property {Number} seconds - The remaining seconds to display.
 */
const props = defineProps({
    seconds: {
        type: Number,
        required: true,
    }
});

/**
 * Formats the raw seconds into a readable MM:SS layout.
 * * @returns {String}
 */
const formattedTime = computed(() => {
    const m = Math.floor(props.seconds / 60).toString().padStart(2, '0');
    const s = (props.seconds % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
});

/**
 * Computes dynamic Vuestic utility classes based on urgency.
 * * @returns {String}
 */
const badgeColorClass = computed(() => {
    if (props.seconds <= 0) return 'bg-red-100 text-red-700';
    if (props.seconds <= 300) return 'bg-yellow-100 text-yellow-700'; // 5 mins warning
    return 'bg-blue-100 text-blue-700';
});
</script>