<template>
  <va-sidebar :minimized="minimized" class="app-sidebar" width="250px">
    <va-sidebar-item
      v-for="item in navigationItems"
      :key="item.title"
      :active="isCurrentRoute(item.route)"
    >
      <Link :href="route(item.route)" class="sidebar-link">
        <va-sidebar-item-content>
          <va-icon :name="item.icon" />
          <va-sidebar-item-title v-if="!minimized">{{ item.title }}</va-sidebar-item-title>
        </va-sidebar-item-content>
      </Link>
    </va-sidebar-item>
  </va-sidebar>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
  minimized: {
    type: Boolean,
    default: false,
  },
});

// Defines the sidebar structure dynamically based on the user's role to keep presentation and logic decoupled
const navigationItems = computed(() => {
  const role = usePage().props.auth.user?.role;
  const items = [
    { title: 'Dashboard', icon: 'dashboard', route: 'dashboard' },
    { title: 'Tickets', icon: 'confirmation_number', route: 'tickets.index' },
  ];

  if (role === 'supporter' || role === 'admin') {
    items.push({ title: 'Users', icon: 'group', route: 'users.index' });
  }

  return items;
});

// Evaluates if the current Inertia URL matches the navigation item to highlight the active state
const isCurrentRoute = (routeName) => {
  return route().current(routeName);
};
</script>

<style scoped>
.app-sidebar {
  height: calc(100vh - 64px); /* Subtracts the approximate height of the navbar */
}
.sidebar-link {
  text-decoration: none;
  color: inherit;
  display: block;
  width: 100%;
}
</style>