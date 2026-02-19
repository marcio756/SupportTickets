<template>
    <va-navbar color="primary" class="app-navbar">
        <template #left>
            <div class="left-section flex items-center">
                <button 
                    class="sidebar-toggle mr-3 p-2 rounded hover:bg-white hover:bg-opacity-20 transition-colors border-0 bg-transparent text-white cursor-pointer flex items-center justify-center" 
                    @click="$emit('toggle-sidebar')"
                >
                    <VaIconMenu />
                </button>
                <span class="logo-text font-bold text-lg text-white">Support Tickets</span>
            </div>
        </template>

        <template #right>
            <div class="right-section flex items-center gap-3">
                <span class="text-white font-medium">{{ $page.props.auth.user.name }}</span>
                <Link 
                    :href="route('logout')" 
                    method="post" 
                    as="button"
                    class="logout-button ml-4 text-sm font-bold text-white bg-red-500 hover:bg-red-600 px-4 py-2 rounded shadow-sm transition-colors"
                >
                    Logout
                </Link>
            </div>
        </template>
    </va-navbar>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { VaNavbar } from 'vuestic-ui';
import VaIconMenu from '@/Components/Icons/VaIconMenu.vue';

/**
 * Declaração explícita do evento para garantir que a Navbar propaga a ação para o AppLayout
 */
defineEmits(['toggle-sidebar']);
</script>

<style scoped>
.app-navbar {
    z-index: 1000;
    position: relative;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}
</style>