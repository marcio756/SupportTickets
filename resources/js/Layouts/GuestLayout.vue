<script setup>
/**
 * Layout for non-authenticated pages (Login, Register).
 * Includes global theme persistence.
 */
import { onMounted } from 'vue';
import { useTheme } from '@/Composables/useTheme';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
import ThemeButton from '@/Components/navbar/ThemeButton.vue';

const { initTheme } = useTheme();

onMounted(() => {
    initTheme();
});
</script>

<template>
    <div class="flex min-h-screen flex-col items-center bg-gray-100 dark:bg-gray-900 pt-6 sm:justify-center sm:pt-0 transition-colors duration-300 relative">
        <div class="absolute top-4 right-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm p-1 border border-gray-200 dark:border-gray-700">
            <ThemeButton />
        </div>

        <div>
            <Link href="/">
                <ApplicationLogo class="h-20 w-20 fill-current text-gray-500" />
            </Link>
        </div>

        <div class="mt-6 w-full overflow-hidden bg-white dark:bg-gray-800 px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg transition-colors border border-gray-200 dark:border-gray-700">
            <slot />
        </div>
    </div>
</template>