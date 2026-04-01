<template>
    <div class="animate-pulse flex flex-col gap-3 w-full" aria-hidden="true">
        <div v-if="withHeader" class="h-10 bg-gray-200 dark:bg-gray-700 rounded-md w-full mb-4"></div>
        
        <div 
            v-for="i in lines" 
            :key="i" 
            class="h-16 bg-gray-100 dark:bg-gray-800 rounded-md w-full flex items-center px-4 gap-4"
        >
            <div v-if="withAvatar" class="h-10 w-10 bg-gray-200 dark:bg-gray-700 rounded-full shrink-0"></div>
            <div class="flex-1 flex flex-col gap-2">
                <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4"></div>
                <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/2"></div>
            </div>
            <div class="h-6 w-20 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    lines: {
        type: Number,
        default: 5
    },
    withHeader: {
        type: Boolean,
        default: true
    },
    withAvatar: {
        type: Boolean,
        default: false
    }
});
</script>