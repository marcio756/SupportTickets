<script setup>
import { ref, computed, onMounted } from 'vue'; // Added onMounted
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useColors } from 'vuestic-ui'; // Import useColors to apply theme on load
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import ThemeButton from '@/Components/navbar/ThemeButton.vue';

defineProps({ title: String });

const { applyPreset, currentPresetName } = useColors();
const isSidebarMinimized = ref(false);
const showSidebar = ref(true);
const user = computed(() => usePage().props.auth.user);

const toggleSidebar = () => {
    isSidebarMinimized.value = !isSidebarMinimized.value;
};

/**
 * Ensures the user's theme preference is applied as soon as the application loads.
 */
onMounted(() => {
    const savedTheme = localStorage.getItem('app-theme');
    if (savedTheme && savedTheme !== currentPresetName.value) {
        applyPreset(savedTheme);
    }
});
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
                <VaNavbar color="primary" class="py-2 app-navbar shadow-md">
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
                                    <div class="flex items-center cursor-pointer gap-2 text-white px-2 py-1 rounded hover:bg-white/10 transition-colors">
                                        <UserAvatar :user="user" size="32px" />
                                        <span class="hidden sm:block font-medium">{{ user.name }}</span>
                                        <VaIcon name="expand_more" />
                                    </div>
                                </template>

                                <VaDropdownContent class="p-2 min-w-[200px]">
                                    <div class="mb-1 border-b border-gray-100 dark:border-gray-700 pb-1">
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
                    class="border-r border-gray-200 dark:border-gray-800"
                >
                    <VaSidebarItem :active="route().current('dashboard')">
                        <Link :href="route('dashboard')" class="w-full h-full flex items-center p-3 text-inherit decoration-0">
                            <VaSidebarItemContent>
                                <VaIcon name="dashboard" />
                                <VaSidebarItemTitle v-if="!isSidebarMinimized">
                                    Dashboard
                                </VaSidebarItemTitle>
                            </VaSidebarItemContent>
                        </Link>
                    </VaSidebarItem>

                    <VaSidebarItem :active="route().current('tickets.*')">
                        <Link :href="route('tickets.index')" class="w-full h-full flex items-center p-3 text-inherit decoration-0">
                            <VaSidebarItemContent>
                                <VaIcon name="confirmation_number" />
                                <VaSidebarItemTitle v-if="!isSidebarMinimized">
                                    Tickets
                                </VaSidebarItemTitle>
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
                                <VaSidebarItemTitle v-if="!isSidebarMinimized">
                                    Utilizadores
                                </VaSidebarItemTitle>
                            </VaSidebarItemContent>
                        </Link>
                    </VaSidebarItem>
                </VaSidebar>
            </template>

            <template #content>
                <main class="p-6 bg-gray-50 dark:bg-gray-900 min-h-full transition-colors duration-300">
                    <header v-if="$slots.header" class="mb-6">
                        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">
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
.app-layout {
    height: 100vh;
    display: flex;
    flex-direction: column;
}
</style>