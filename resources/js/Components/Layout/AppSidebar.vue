<template>
    <va-sidebar 
        :minimized="minimized" 
        width="16rem" 
        minimized-width="4rem"
        class="app-sidebar"
    >
        <Link :href="route('dashboard')" class="sidebar-link">
            <va-sidebar-item :active="route().current('dashboard')">
                <va-sidebar-item-content>
                    <va-icon name="dashboard" />
                    <va-sidebar-item-title v-if="!minimized">
                        Dashboard
                    </va-sidebar-item-title>
                </va-sidebar-item-content>
            </va-sidebar-item>
        </Link>

        <Link href="#" class="sidebar-link">
            <va-sidebar-item :active="false">
                <va-sidebar-item-content>
                    <va-icon name="confirmation_number" />
                    <va-sidebar-item-title v-if="!minimized">
                        Tickets
                    </va-sidebar-item-title>
                </va-sidebar-item-content>
            </va-sidebar-item>
        </Link>
        
        <Link 
            v-if="isSupporter" 
            href="#" 
            class="sidebar-link"
        >
            <va-sidebar-item :active="false">
                <va-sidebar-item-content>
                    <va-icon name="group" />
                    <va-sidebar-item-title v-if="!minimized">
                        Users
                    </va-sidebar-item-title>
                </va-sidebar-item-content>
            </va-sidebar-item>
        </Link>
    </va-sidebar>
</template>

<script setup>
import { computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import { VaSidebar, VaSidebarItem, VaSidebarItemContent, VaSidebarItemTitle, VaIcon } from 'vuestic-ui';

/**
 * Defines the props for the sidebar state
 */
defineProps({
    minimized: {
        type: Boolean,
        required: true,
    }
});

/**
 * Extracts the user role from Inertia shared props to handle permissions
 */
const page = usePage();
const isSupporter = computed(() => {
    const role = page.props.auth.user?.role;
    return role === 'supporter' || role === 'admin';
});
</script>

<style scoped>
.sidebar-link {
    text-decoration: none;
    color: inherit;
    display: block;
}
</style>