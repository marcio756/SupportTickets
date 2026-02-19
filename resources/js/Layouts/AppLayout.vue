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
 * Controla o estado (aberto/fechado) da barra lateral
 */
const isSidebarMinimized = ref(false);

/**
 * Inverte o estado da variável reativa
 */
const toggleSidebar = () => {
    isSidebarMinimized.value = !isSidebarMinimized.value;
};
</script>

<style scoped>
.app-layout {
    display: flex;
    flex-direction: column;
    height: 100vh;
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
    transition: all 0.3s ease;
}

.app-layout__page {
    max-width: 1400px;
    margin: 0 auto;
    width: 100%;
}
</style>