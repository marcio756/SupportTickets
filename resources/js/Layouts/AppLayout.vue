<script setup>
/**
 * Global Application Layout.
 * Manages independent scrolling, Sidebar persistence, and main navigation.
 * Delegates notification orchestration to useAppNotifications composable for SRP compliance.
 */
import { ref, computed, onMounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useTheme } from '@/Composables/useTheme';
import { useAppNotifications } from '@/Composables/useAppNotifications';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import ThemeButton from '@/Components/navbar/ThemeButton.vue';
import NotificationDropdown from '@/Components/Layout/NotificationDropdown.vue';

defineProps({
    title: String,
});

const { initTheme } = useTheme();
useAppNotifications();

const isSidebarMinimized = ref(false);
const showSidebar = ref(true);

/**
 * Safely computes the authenticated user from Inertia page props.
 * * @returns {Object|undefined} The user object.
 */
const user = computed(() => usePage().props.auth?.user);

/**
 * Toggles the sidebar state and persists it to localStorage to maintain state across page refreshes.
 * * @returns {void}
 */
const toggleSidebar = () => {
    isSidebarMinimized.value = !isSidebarMinimized.value;
    localStorage.setItem('sidebar-is-minimized', isSidebarMinimized.value);
};

onMounted(() => {
    initTheme();
    
    const savedSidebar = localStorage.getItem('sidebar-is-minimized');
    if (savedSidebar !== null) {
        isSidebarMinimized.value = savedSidebar === 'true';
    }
});
</script>

<template>
    <div class="app-layout va-bg-background h-screen overflow-hidden">
        <Head :title="title" />

        <VaLayout
            :left="{ absolute: false, overlay: false }"
            :top="{ fixed: true, order: 1 }"
            class="h-full"
        >
            <template #top>
                <VaNavbar color="primary" class="py-2 app-navbar shadow-md">
                    <template #left>
                        <VaButton
                            preset="secondary"
                            :icon="isSidebarMinimized ? 'menu_open' : 'menu'"
                            @click="toggleSidebar"
                            color="textPrimary" 
                            class="mr-3"
                        />
                        <Link href="/" class="flex items-center hover:opacity-90 transition-opacity">
                            <span class="text-xl font-bold text-white uppercase tracking-wider select-none">
                                Support<span class="text-yellow-400">Tickets</span>
                            </span>
                        </Link>
                    </template>

                    <template #right>
                        <div class="flex items-center gap-2 sm:gap-4">
                            
                            <NotificationDropdown />

                             <VaDropdown placement="bottom-end">
                                <template #anchor>
                                    <div class="flex items-center cursor-pointer gap-2 text-white px-2 py-1 rounded hover:bg-white/10 transition-colors">
                                        <UserAvatar :user="user" size="32px" />
                                        <span class="hidden sm:block font-medium text-white">{{ user?.name }}</span>
                                        <VaIcon name="expand_more" color="white" />
                                    </div>
                                </template>

                                <VaDropdownContent class="p-2 min-w-[200px] dark:bg-gray-800 border dark:border-gray-700 shadow-xl">
                                    <div class="mb-1 border-b border-gray-100 dark:border-gray-700 pb-1 flex justify-center">
                                        <ThemeButton />
                                    </div>

                                    <Link :href="route('profile.edit')" class="block w-full">
                                        <VaButton preset="plain" color="textPrimary" class="w-full justify-start mb-1" icon="person">
                                            Perfil
                                        </VaButton>
                                    </Link>
                                    
                                    <Link :href="route('logout')" method="post" as="button" class="block w-full">
                                        <VaButton preset="plain" color="danger" class="w-full justify-start" icon="logout">
                                            Sair
                                        </VaButton>
                                    </Link>
                                </VaDropdownContent>
                            </VaDropdown>
                        </div>
                    </template>
                </VaNavbar>
            </template>

            <template #left>
                <VaSidebar
                    v-model="showSidebar"
                    :minimized="isSidebarMinimized"
                    width="16rem"
                    minimized-width="4rem"
                    color="background-secondary"
                    class="h-full border-r border-gray-200 dark:border-gray-800 flex flex-col"
                >
                    <div class="flex-grow overflow-y-auto overflow-x-hidden">
                        <VaSidebarItem :active="route().current('dashboard')">
                            <Link :href="route('dashboard')" class="w-full h-full flex items-center p-3 text-inherit decoration-0">
                                <VaSidebarItemContent>
                                    <VaIcon name="dashboard" />
                                    <VaSidebarItemTitle v-if="!isSidebarMinimized">Dashboard</VaSidebarItemTitle>
                                </VaSidebarItemContent>
                            </Link>
                        </VaSidebarItem>

                        <VaSidebarItem :active="route().current('tickets.*')">
                            <Link :href="route('tickets.index')" class="w-full h-full flex items-center p-3 text-inherit decoration-0">
                                <VaSidebarItemContent>
                                    <VaIcon name="confirmation_number" />
                                    <VaSidebarItemTitle v-if="!isSidebarMinimized">Tickets</VaSidebarItemTitle>
                                </VaSidebarItemContent>
                            </Link>
                        </VaSidebarItem>
                        
                        <VaSidebarItem
                            v-if="user?.role === 'supporter' || user?.role === 'admin'"
                            :active="route().current('users.*')"
                        >
                            <Link :href="route('users.index')" class="w-full h-full flex items-center p-3 text-inherit decoration-0">
                                <VaSidebarItemContent>
                                    <VaIcon name="group" />
                                    <VaSidebarItemTitle v-if="!isSidebarMinimized">Utilizadores</VaSidebarItemTitle>
                                </VaSidebarItemContent>
                            </Link>
                        </VaSidebarItem>
                    </div>
                </VaSidebar>
            </template>

            <template #content>
                <div class="content-wrapper h-full overflow-y-auto bg-gray-50 dark:bg-gray-900 transition-colors duration-300">
                    <main class="p-6 min-h-full">
                        <header v-if="$slots.header" class="mb-6">
                            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">
                                <slot name="header" />
                            </h1>
                        </header>
                        <slot />
                    </main>
                </div>
            </template>
        </VaLayout>
    </div>
</template>

<style scoped>
.app-layout {
    height: 100vh;
}
:deep(.va-layout__area--content) {
    height: 100%;
}
:deep(.va-sidebar__menu) {
    background-color: transparent !important;
}
</style>