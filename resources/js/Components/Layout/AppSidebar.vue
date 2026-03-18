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
       <span class="text-xs text-gray-500 font-bold font-mono uppercase">
          Role: {{ displayRole }}
       </span>
    </div>
  </va-sidebar>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
  minimized: { type: Boolean, default: false },
});

const page = usePage();

/**
 * Computes the display string for the user's role.
 */
const displayRole = computed(() => {
    const user = page.props.auth?.user;
    if (!user) return 'Guest';
    return typeof user.role === 'object' ? user.role.value : user.role;
});

/**
 * Computes the available navigation items dynamically based on the authenticated user's role.
 */
const navigationItems = computed(() => {
  const user = page.props.auth?.user;
  if (!user) return [];
  
  const role = (typeof user.role === 'object' ? user.role.value : user.role).toLowerCase();

  const items = [
    { title: 'Dashboard', icon: 'dashboard', route: 'dashboard' },
  ];

  // Time Tracking is available for internal Staff only (Supporters and Admins)
  if (role !== 'customer') {
    items.push({ 
        title: 'Time Tracking', 
        icon: 'schedule', 
        route: 'work-sessions.index' 
    });
  }

  // Tickets menu is now accessible to all roles (Customers, Supporters, and Admins)
  items.push({ title: 'Tickets', icon: 'confirmation_number', route: 'tickets.index' });

  // Staff (Supporters and Admins) can manage operational entities
  if (role === 'supporter' || role === 'admin') {
    items.push({ title: 'Users', icon: 'group', route: 'users.index' });
    items.push({ title: 'Manage Tags', icon: 'local_offer', route: 'tags.index' });
    items.push({ title: 'Vacations', icon: 'event', route: 'vacations.index' });
  }

  // Admin specific routes
  if (role === 'admin') {
    items.push({ title: 'Teams', icon: 'groups', route: 'teams.index' });
    items.push({ title: 'Activity Logs', icon: 'history', route: 'activity-logs.index' });
  }

  return items;
});

/**
 * Safely resolves a route URL, providing a fallback to prevent app crashes if a route name goes missing.
 * * @param {string} routeName 
 * @returns {string} The resolved URL or '#' as fallback.
 */
const getRouteUrl = (routeName) => {
    try { return route(routeName); } catch { return '#'; }
};

/**
 * Checks if the given route is currently active to highlight the sidebar item.
 * Evaluates both exact matches and wildcard child routes (e.g., matching tickets.show when on tickets.index).
 * * @param {string} routeName 
 * @returns {boolean} True if the route is active.
 */
const isCurrentRoute = (routeName) => {
  try {
    return route().current(routeName) || route().current(routeName.replace('.index', '.*'));
  } catch { return false; }
};
</script>