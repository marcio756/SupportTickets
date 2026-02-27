<template>
  <va-sidebar
    :minimized="minimized"
    width="16rem"
    minimized-width="4rem"
    color="background-secondary"
    class="h-full border-r border-gray-200 dark:border-gray-800 flex flex-col"
  >
    <div class="flex-grow overflow-y-auto overflow-x-hidden">
        <va-sidebar-item
          v-for="item in navigationItems"
          :key="item.title"
          :active="isCurrentRoute(item.route)"
        >
          <Link :href="getRouteUrl(item.route)" class="w-full h-full flex items-center p-3 text-inherit decoration-0">
            <va-sidebar-item-content>
              <va-icon :name="item.icon" />
              <va-sidebar-item-title v-if="!minimized">{{ item.title }}</va-sidebar-item-title>
            </va-sidebar-item-content>
          </Link>
        </va-sidebar-item>
    </div>

    <div v-if="!minimized" class="p-4 mt-auto border-t border-gray-200 dark:border-gray-800">
       <span class="text-xs text-gray-500 font-mono font-bold uppercase">
         Perfil: {{ displayRole }}
       </span>
    </div>
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

const page = usePage();

/**
 * Extracts the reactive user role from the authenticated session.
 * Handles both string representations and Enum objects to prevent UI breakage.
 * @returns {string} The normalized role designation for display.
 */
const displayRole = computed(() => {
    const user = page.props.auth?.user;
    if (!user) return 'Guest';
    
    return typeof user.role === 'object' ? user.role.value : user.role;
});

/**
 * Dynamically builds the navigation menu based on user permissions.
 * Ensures administrative panels are strictly accessible by authorized roles.
 * @returns {Array<Object>} List of accessible navigation structures.
 */
const navigationItems = computed(() => {
  const user = page.props.auth?.user;
  
  let role = null;
  if (user && user.role) {
    role = typeof user.role === 'object' ? user.role.value : user.role;
    if (typeof role === 'string') {
        role = role.toLowerCase(); 
    }
  }

  const items = [
    { title: 'Dashboard', icon: 'dashboard', route: 'dashboard' },
    { title: 'Tickets', icon: 'confirmation_number', route: 'tickets.index' },
  ];

  if (role === 'supporter' || role === 'admin') {
    items.push({ title: 'Users', icon: 'group', route: 'users.index' });
    items.push({ title: 'Manage Tags', icon: 'local_offer', route: 'tags.index' });
    items.push({ title: 'Activity Logs', icon: 'history', route: 'activity-logs.index' });
  }

  return items;
});

/**
 * Safely resolves the Ziggy route URL to prevent frontend crashes.
 * Falls back to a safe anchor if the backend routes are out of sync with the frontend cache.
 * @param {string} routeName - The internal identifier for the route.
 * @returns {string} The formatted URL or a fallback hash.
 */
const getRouteUrl = (routeName) => {
    try {
        return route(routeName);
    } catch (error) {
        console.error(`Ziggy Error: Route '${routeName}' is missing. Please clear route cache.`);
        return '#'; 
    }
};

/**
 * Determines if the sidebar item represents the currently active view.
 * @param {string} routeName - The internal identifier for the route.
 * @returns {boolean} True if the route is active, false otherwise.
 */
const isCurrentRoute = (routeName) => {
  try {
    return route().current(routeName) || route().current(routeName.replace('.index', '.*'));
  } catch (error) {
    return false;
  }
};
</script>

<style scoped>
:deep(.va-sidebar__menu) {
    background-color: transparent !important;
}
</style>