<script setup>
/**
 * Global Application Layout.
 * Manages independent scrolling, Sidebar persistence, and main navigation.
 * Delegates notification orchestration to useAppNotifications composable for SRP compliance.
 * Includes a custom card modal to prevent logouts during active shifts.
 */
import { ref, computed, onMounted } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { useTheme } from '@/Composables/useTheme';
import { useAppNotifications } from '@/Composables/useAppNotifications';
import { useI18n } from 'vue-i18n';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import ThemeButton from '@/Components/navbar/ThemeButton.vue';
import NotificationDropdown from '@/Components/Layout/NotificationDropdown.vue';
import AppSidebar from '@/Components/Layout/AppSidebar.vue';
import TimerWidget from '@/Components/WorkSession/TimerWidget.vue';

// Correct path to the global Language Switcher component
import LanguageSwitcher from '@/Components/navbar/LanguageSwitcher.vue';

defineProps({
    title: String,
});

const { initTheme } = useTheme();
useAppNotifications();

// Initialize i18n for layout-level translations (e.g., modals, dropdowns)
const { t } = useI18n();

const isSidebarMinimized = ref(false);
const showLogoutBlocker = ref(false);
const logoutErrorMessage = ref('');

/**
 * Safely computes the authenticated user from Inertia page props.
 * @returns {Object|undefined} The user object.
 */
const user = computed(() => usePage().props.auth?.user);

/**
 * Handles the logout process safely without triggering native browser alerts.
 * Utilizes vue-i18n for translated error messages.
 */
const handleLogout = () => {
    // Proactive frontend check
    const session = usePage().props.auth?.work_session;
    if (session && (session.status === 'active' || session.status === 'paused')) {
        logoutErrorMessage.value = t('layout.prevent_logout_msg');
        showLogoutBlocker.value = true;
        return;
    }

    // Backend fallback check
    router.post(route('logout'), {}, {
        onError: (errors) => {
            if (errors.logout) {
                logoutErrorMessage.value = errors.logout;
                showLogoutBlocker.value = true;
            }
        }
    });
};

/**
 * Toggles the sidebar state and persists it to localStorage.
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
                            
                            <TimerWidget />

                            <LanguageSwitcher />
                            
                            <NotificationDropdown />

                             <VaDropdown placement="bottom-end">
                                <template #anchor>
                                    <div class="flex items-center cursor-pointer gap-2 text-white px-2 py-1 rounded hover:bg-white/10 transition-colors">
                                        <UserAvatar :user="user" size="32px" />
                                        <span class="hidden sm:block font-medium text-white">{{ user?.name }}</span>
                                        <VaIcon name="expand_more" color="#ffffff" />
                                    </div>
                                </template>

                                <VaDropdownContent class="p-2 min-w-[200px] dark:bg-gray-800 border dark:border-gray-700 shadow-xl">
                                    <div class="mb-1 border-b border-gray-100 dark:border-gray-700 pb-1 flex justify-center">
                                        <ThemeButton />
                                    </div>

                                    <Link :href="route('profile.edit')" class="block w-full">
                                        <VaButton preset="plain" color="textPrimary" class="w-full justify-start mb-1" icon="person">
                                            {{ $t('layout.profile') }}
                                        </VaButton>
                                    </Link>
                                    
                                    <div class="block w-full">
                                        <VaButton 
                                            preset="plain" 
                                            color="danger" 
                                            class="w-full justify-start" 
                                            icon="logout"
                                            @click="handleLogout"
                                        >
                                            {{ $t('layout.logout') }}
                                        </VaButton>
                                    </div>
                                </VaDropdownContent>
                            </VaDropdown>
                        </div>
                    </template>
                </VaNavbar>
            </template>

            <template #left>
                <AppSidebar :minimized="isSidebarMinimized" />
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

        <VaModal
            v-model="showLogoutBlocker"
            :title="$t('layout.modal.title')"
            hide-default-actions
            size="small"
        >
            <div class="p-6 flex flex-col items-center text-center">
                <div class="h-16 w-16 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mb-4">
                    <VaIcon name="block" color="danger" size="2.5rem" />
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">{{ $t('layout.modal.subtitle') }}</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ logoutErrorMessage }}
                </p>
            </div>
            <template #footer>
                <div class="w-full flex justify-center pb-2">
                    <VaButton @click="showLogoutBlocker = false" color="danger" class="w-full max-w-[200px]">
                        {{ $t('layout.modal.understood') }}
                    </VaButton>
                </div>
            </template>
        </VaModal>
    </div>
</template>

<style scoped>
.app-layout {
    height: 100vh;
}
:deep(.va-layout__area--content) {
    height: 100%;
}
</style>