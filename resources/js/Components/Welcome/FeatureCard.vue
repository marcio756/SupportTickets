<script setup>
/**
 * UI Component for displaying feature highlights on the Welcome page.
 * Extracted to enforce the DRY principle and ensure visual consistency 
 * across promotional/informational sections of the application.
 */
defineProps({
    icon: {
        type: String,
        required: true
    },
    iconColor: {
        type: String,
        required: true
    },
    iconBgClass: {
        type: String,
        required: true
    },
    title: {
        type: String,
        required: true
    },
    description: {
        type: String,
        required: true
    }
});
</script>

<template>
    <div class="flex items-start gap-3 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-700">
        <div :class="['p-2 rounded-lg', iconBgClass]">
            <va-icon :name="icon" :color="iconColor" />
        </div>
        <div>
            <h4 class="font-bold text-gray-900 dark:text-gray-100">{{ title }}</h4>
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ description }}</p>
        </div>
    </div>
</template>