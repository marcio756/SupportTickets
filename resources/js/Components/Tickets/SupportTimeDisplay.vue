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
 * Display component for countdown timers on tickets.
 * Architect Note: Removed 'required: true' and added a default '0' fallback.
 * Mathematical safety prevents the UI from breaking if backend payload delays.
 */
const props = defineProps({
    seconds: {
        type: Number,
        default: 0,
    }
});

/**
 * Creates an immutable, valid number ensuring no NaN operations occur.
 */
const safeSeconds = computed(() => {
    return (typeof props.seconds === 'number' && !isNaN(props.seconds)) 
        ? Math.max(0, props.seconds) 
        : 0;
});

/**
 * Formats the safe seconds into a readable MM:SS layout.
 */
const formattedTime = computed(() => {
    const m = Math.floor(safeSeconds.value / 60).toString().padStart(2, '0');
    const s = (safeSeconds.value % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
});

/**
 * Computes dynamic Tailwind utility classes based on urgency.
 */
const badgeColorClass = computed(() => {
    if (safeSeconds.value <= 0) return 'bg-red-100 text-red-700';
    if (safeSeconds.value <= 300) return 'bg-yellow-100 text-yellow-700'; // 5 mins warning
    return 'bg-blue-100 text-blue-700';
});
</script>