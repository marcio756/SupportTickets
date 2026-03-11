<template>
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4" :class="borderColor">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <slot name="icon">
                    <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </slot>
            </div>
            <div class="ml-4 w-full">
                <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">{{ title }}</h3>
                <div class="mt-1 flex items-baseline">
                    <p class="text-2xl font-semibold text-gray-900">{{ value }}</p>
                    <p v-if="suffix" class="ml-1 text-sm text-gray-500">{{ suffix }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    value: {
        type: [Number, String],
        required: true,
    },
    suffix: {
        type: String,
        default: 'dias',
    },
    color: {
        type: String,
        default: 'blue',
        validator: (value) => ['blue', 'green', 'red', 'yellow'].includes(value),
    }
});

const borderColor = computed(() => {
    const colors = {
        blue: 'border-blue-500',
        green: 'border-green-500',
        red: 'border-red-500',
        yellow: 'border-yellow-500',
    };
    return colors[props.color] || colors.blue;
});
</script>