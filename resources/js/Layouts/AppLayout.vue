<script setup>
/**
 * AppLayout.vue
 * * Main layout component acting as the shell for the authenticated application.
 * Integrates Vuestic UI structure (Sidebar, Navbar) with Inertia.js navigation.
 * * @vue-prop {String} title - The page title for the document head.
 */
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

// Props
defineProps({
    title: String,
});

// State for Sidebar
const isSidebarMinimized = ref(false);
const showSidebar = ref(true);

// Get User from Inertia Shared Props
const user = computed(() => usePage().props.auth.user);

/**
 * Toggles the sidebar visibility or minimization state.
 */
const toggleSidebar = () => {
    isSidebarMinimized.value = !isSidebarMinimized.value;
};
</script>

<template>
    <div class="app-layout va-bg-background">
        <Head :title="title" />

        <VaLayout
            :left="{ absolute: false, overlay: false }"
            :top="{ fixed: true, order: 1 }"
            class="h-screen"
        >
            <template #top>
                <VaNavbar color="primary" class="py-2 app-navbar">
                    <template #left>
                        <VaButton
                            preset="secondary"
                            :icon="isSidebarMinimized ? 'menu_open' : 'menu'"
                            @click="toggleSidebar"
                            color="textPrimary" 
                            class="mr-3"
                        />
                        <span class="text-xl font-bold text-white uppercase tracking-wider">
                            Support<span class="text-yellow-400">Tickets</span>
                        </span>
                    </template>

                    <template #right>
                        <div class="flex items-center gap-4">
                             <VaDropdown placement="bottom-end">
                                <template #anchor>
                                    <div class="flex items-center cursor-pointer gap-2 text-white">
                                        <VaAvatar size="small" color="warning">
                                            {{ user.name.charAt(0).toUpperCase() }}
                                        </VaAvatar>
                                        <span class="hidden sm:block font-medium">{{ user.name }}</span>
                                        <VaIcon name="expand_more" />
                                    </div>
                                </template>

                                <VaDropdownContent class="p-2 min-w-[200px]">
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
                    class="border-r border-gray-200"
                >
                    <VaSidebarItem
                        :active="route().current('dashboard')"
                        :to="route('dashboard')"
                        is="a" 
                    >
                        <Link :href="route('dashboard')" class="w-full h-full flex items-center p-3 text-inherit decoration-0">
                            <VaSidebarItemContent>
                                <VaIcon name="dashboard" />
                                <VaSidebarItemTitle v-if="!isSidebarMinimized">
                                    Dashboard
                                </VaSidebarItemTitle>
                            </VaSidebarItemContent>
                        </Link>
                    </VaSidebarItem>

                    <VaSidebarItem
                        :active="route().current('tickets.*')"
                    >
                        <Link :href="route('tickets.index')" class="w-full h-full flex items-center p-3 text-inherit decoration-0">
                            <VaSidebarItemContent>
                                <VaIcon name="confirmation_number" />
                                <VaSidebarItemTitle v-if="!isSidebarMinimized">
                                    Meus Tickets
                                </VaSidebarItemTitle>
                            </VaSidebarItemContent>
                        </Link>
                    </VaSidebarItem>
                    
                    <VaSidebarItem
                        :active="route().current('users.*')"
                    >
                        <Link :href="route('users.index')" class="w-full h-full flex items-center p-3 text-inherit decoration-0">
                            <VaSidebarItemContent>
                                <VaIcon name="group" />
                                <VaSidebarItemTitle v-if="!isSidebarMinimized">
                                    Utilizadores
                                </VaSidebarItemTitle>
                            </VaSidebarItemContent>
                        </Link>
                    </VaSidebarItem>

                </VaSidebar>
            </template>

            <template #content>
                <main class="p-6 bg-gray-50 min-h-full">
                    <header v-if="$slots.header" class="mb-6">
                        <h1 class="text-2xl font-bold text-gray-800">
                            <slot name="header" />
                        </h1>
                    </header>

                    <slot />
                </main>
            </template>
        </VaLayout>
    </div>
</template>

<style scoped>
/* Pequenos ajustes para garantir que o layout ocupa a altura total */
.app-layout {
    height: 100vh;
    display: flex;
    flex-direction: column;
}
</style>