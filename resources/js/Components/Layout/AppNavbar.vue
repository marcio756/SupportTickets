<template>
    <va-navbar color="primary" class="app-navbar">
        <template #left>
            <div class="left-section">
                <va-icon-menu 
                    class="sidebar-toggle" 
                    @click="$emit('toggle-sidebar')" 
                />
                <span class="logo-text font-bold text-lg ml-3">Support Tickets</span>
            </div>
        </template>

        <template #right>
            <div class="right-section flex items-center gap-3">
                <span class="text-white">{{ $page.props.auth.user.name }}</span>
                <Link 
                    :href="route('logout')" 
                    method="post" 
                    as="button"
                    class="logout-button text-sm text-gray-200 hover:text-white"
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

defineEmits(['toggle-sidebar']);
</script>

<style scoped>
.app-navbar {
    z-index: 1000;
    position: relative;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}
.sidebar-toggle {
    cursor: pointer;
    color: white;
}
</style>