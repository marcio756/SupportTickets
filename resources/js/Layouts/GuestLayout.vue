<script setup>
/**
 * GuestLayout Component
 * Provides the base structure for public-facing pages.
 * Now includes global theme management for unauthenticated users.
 */
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import ThemeButton from '@/Components/navbar/ThemeButton.vue';
import { Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';

// CORREÇÃO: Utilização de destructuring para importar o Named Export
import { useTheme } from '@/Composables/useTheme';

const { initTheme } = useTheme();

/**
 * Initialize theme on mount to prevent "flash" of unstyled content
 * and ensure the theme persists after F5/refresh.
 */
onMounted(() => {
    initTheme();
});
</script>

<template>
    <div class="relative min-h-screen flex flex-col sm:justify-center items-center pt-2 sm:pt-0 bg-gray-100 dark:bg-gray-900 transition-colors duration-300">
        
        <div class="absolute top-4 right-4">
            <ThemeButton />
        </div>

        <div class="w-full flex justify-center py-4">
            <Link href="/">
                <ApplicationLogo />
            </Link>
        </div>

        <div
            class="w-full sm:max-w-md mt-2 px-6 py-4 bg-white dark:bg-gray-800 shadow-md overflow-hidden sm:rounded-lg mb-4 border border-gray-200 dark:border-gray-700"
        >
            <slot />
        </div>
    </div>
</template>

<style scoped>
/* Ensure smooth transition when switching themes */
.transition-colors {
    transition-property: background-color, border-color, color, fill, stroke;
}
</style>