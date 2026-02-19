<script setup>
/**
 * Main authenticated layout mimicking the vuestic-admin design.
 * Integrates a top navbar, a responsive left sidebar, and a dark/light mode switcher.
 */
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useBreakpoint } from 'vuestic-ui';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
// CORREÇÃO AQUI: Importar do caminho correto onde o ficheiro se encontra
import ThemeButton from '@/Components/navbar/ThemeButton.vue';

const breakpoints = useBreakpoint();

const isSidebarMinimized = ref(false);

/**
 * Handles window resize to automatically collapse or expand the sidebar 
 * based on device width to ensure a responsive design.
 * @returns {void}
 */
const onResize = () => {
    isSidebarMinimized.value = breakpoints.mdDown;
};

onMounted(() => {
    window.addEventListener('resize', onResize);
    onResize(); // Initial check on load
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
});

/**
 * Toggles the sidebar visibility state.
 * @returns {void}
 */
const toggleSidebar = () => {
    isSidebarMinimized.value = !isSidebarMinimized.value;
};

/**
 * Helper function to navigate using Inertia router inside Vuestic click events.
 * @param {string} url - The target route URL to visit.
 * @returns {void}
 */
const navigateTo = (url) => {
    router.visit(url);
};
</script>

<template>
    <VaLayout
        :top="{ fixed: true, order: 2 }"
        :left="{ fixed: true, absolute: breakpoints.mdDown, order: 1, overlay: breakpoints.mdDown && !isSidebarMinimized }"
        @leftOverlayClick="isSidebarMinimized = true"
    >
        <template #top>
            <VaNavbar color="background-secondary" class="shadow-sm border-b border-gray-200 dark:border-gray-800 transition-colors duration-300">
                <template #left>
                    <VaButton
                        preset="secondary"
                        icon="menu"
                        @click="toggleSidebar"
                        class="mr-4 text-gray-800 dark:text-gray-200"
                    />
                    <Link :href="route('dashboard')" class="flex items-center">
                        <ApplicationLogo class="block h-9 w-auto fill-current text-primary" />
                    </Link>
                </template>

                <template #right>
                    <div class="flex items-center gap-2 sm:gap-4">
                        
                        <ThemeButton />

                        <VaDropdown placement="bottom-end">
                            <template #anchor>
                                <VaButton preset="secondary" class="font-medium text-gray-800 dark:text-gray-200">
                                    {{ $page.props.auth.user.name }}
                                    <VaIcon name="expand_more" class="ml-2" />
                                </VaButton>
                            </template>

                            <VaDropdownContent class="py-2 w-48 bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-lg rounded-md mt-1">
                                <Link
                                    :href="route('profile.edit')"
                                    class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                >
                                    Profile
                                </Link>
                                <Link
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                >
                                    Log Out
                                </Link>
                            </VaDropdownContent>
                        </VaDropdown>
                    </div>
                </template>
            </VaNavbar>
        </template>

        <template #left>
            <VaSidebar :minimized="isSidebarMinimized" width="16rem" color="background-secondary" class="transition-colors duration-300">
                <VaSidebarItem
                    :active="route().current('dashboard')"
                    @click="navigateTo(route('dashboard'))"
                >
                    <VaSidebarItemContent>
                        <VaIcon name="dashboard" />
                        <VaSidebarItemTitle>Dashboard</VaSidebarItemTitle>
                    </VaSidebarItemContent>
                </VaSidebarItem>

                <VaSidebarItem
                    :active="route().current('tickets.*')"
                    @click="navigateTo(route('tickets.index'))"
                >
                    <VaSidebarItemContent>
                        <VaIcon name="confirmation_number" />
                        <VaSidebarItemTitle>Tickets</VaSidebarItemTitle>
                    </VaSidebarItemContent>
                </VaSidebarItem>
            </VaSidebar>
        </template>

        <template #content>
            <main class="min-h-screen p-4 sm:p-6 lg:p-8 bg-background-primary transition-colors duration-300">
                <header v-if="$slots.header" class="mb-6">
                    <slot name="header" />
                </header>
                <slot />
            </main>
        </template>
    </VaLayout>
</template>