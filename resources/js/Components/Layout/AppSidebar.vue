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

/**
 * Dynamically determines the sidebar menu items based on the user's role.
 * Uses computed to maintain reactivity during route changes or state updates.
 * @returns {Array} List of navigation objects.
 */
const navigationItems = computed(() => {
  // Accessing role via computed ensures it updates if the auth object changes
  const user = usePage().props.auth.user;
  const role = user?.role;

  const items = [
    { title: 'Dashboard', icon: 'dashboard', route: 'dashboard' },
    { title: 'Tickets', icon: 'confirmation_number', route: 'tickets.index' },
  ];

  // Supporters and Admins have access to the User management
  if (role === 'supporter') {
    items.push({ title: 'Users', icon: 'group', route: 'users.index' });
  }

  return items;
});

/**
 * Checks if the navigation item route is the current active route.
 * @param {string} routeName - The name of the route to check.
 * @returns {boolean}
 */
const isCurrentRoute = (routeName) => {
  return route().current(routeName);
};
</script>

<style scoped>
.app-sidebar {
  height: calc(100vh - 64px);
}
.sidebar-link {
  text-decoration: none;
  color: inherit;
  display: block;
  width: 100%;
}
</style>