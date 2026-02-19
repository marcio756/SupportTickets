<template>
    <div class="app-layout">
        <AppNavbar @toggle-sidebar="toggleSidebar" />

        <div class="app-layout__content">
            <AppSidebar :minimized="isSidebarMinimized" />

            <main class="app-layout__main">
                <div class="app-layout__page">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import AppNavbar from '@/Components/Layout/AppNavbar.vue';
import AppSidebar from '@/Components/Layout/AppSidebar.vue';

/**
 * Controls the collapsed/expanded state of the sidebar
 */
const isSidebarMinimized = ref(false);

/**
 * Toggles the sidebar state when the navbar button is clicked
 */
const toggleSidebar = () => {
    isSidebarMinimized.value = !isSidebarMinimized.value;
};
</script>

<style scoped>
.app-layout {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    background-color: #f4f6f8;
}

.app-layout__content {
    display: flex;
    flex: 1;
    overflow: hidden;
}

.app-layout__main {
    flex: 1;
    overflow-y: auto;
    padding: 2rem;
}

.app-layout__page {
    max-width: 1200px;
    margin: 0 auto;
}
</style>