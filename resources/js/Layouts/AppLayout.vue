<template>
  <div class="app-layout">
    <AppNavbar @toggle-sidebar="toggleSidebar" />
    
    <div class="app-layout__content-wrapper">
      <AppSidebar :minimized="isSidebarMinimized" />
      
      <main class="app-layout__main">
        <div class="content-container">
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

// Manages the global state of the sidebar to provide a responsive and flexible layout
const isSidebarMinimized = ref(false);

const toggleSidebar = () => {
  isSidebarMinimized.value = !isSidebarMinimized.value;
};
</script>

<style scoped>
.app-layout {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  background-color: var(--va-background-primary);
}
.app-layout__content-wrapper {
  display: flex;
  flex: 1;
  overflow: hidden;
}
.app-layout__main {
  flex: 1;
  overflow-y: auto;
  padding: 1.5rem;
}
.content-container {
  max-width: 1440px;
  margin: 0 auto;
}
</style>