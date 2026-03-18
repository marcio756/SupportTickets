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
          {{ $t('sidebar.role') }}: {{ displayRole }}
       </span>
    </div>
  </va-sidebar>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
  minimized: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();

/**
 * Computes the display string for the user's role.
 */
const displayRole = computed(() => {
    const user = page.props.auth?.user;
    if (!user) return t('sidebar.roles.guest');
    
    const roleValue = typeof user.role === 'object' ? user.role.value : user.role;
    // Tries to find a localized role string, falling back to the raw value if undefined
    return t(`sidebar.roles.${roleValue.toLowerCase()}`) || roleValue;
});

/**
 * Computes the available navigation items dynamically based on the authenticated user's role.
 * Wraps translations within the computed property to ensure sidebar reactivity on locale changes.
 */
const navigationItems = computed(() => {
  const user = page.props.auth?.user;
  if (!user) return [];
  
  const role = (typeof user.role === 'object' ? user.role.value : user.role).toLowerCase();

  const items = [
    { title: t('sidebar.dashboard'), icon: 'dashboard', route: 'dashboard' },
  ];

  // Time Tracking is available for internal Staff only (Supporters and Admins)
  if (role !== 'customer') {
    items.push({ 
        title: t('sidebar.time_tracking'), 
        icon: 'schedule', 
        route: 'work-sessions.index' 
    });
  }

  // Tickets menu is now accessible to all roles (Customers, Supporters, and Admins)
  items.push({ title: t('sidebar.tickets'), icon: 'confirmation_number', route: 'tickets.index' });

  // Staff (Supporters and Admins) can manage operational entities
  if (role === 'supporter' || role === 'admin') {
    items.push({ title: t('sidebar.users'), icon: 'group', route: 'users.index' });
    items.push({ title: t('sidebar.manage_tags'), icon: 'local_offer', route: 'tags.index' });
    items.push({ title: t('sidebar.vacations'), icon: 'event', route: 'vacations.index' });
  }

  // Admin specific routes
  if (role === 'admin') {
    items.push({ title: t('sidebar.teams'), icon: 'groups', route: 'teams.index' });
    items.push({ title: t('sidebar.activity_logs'), icon: 'history', route: 'activity-logs.index' });
  }

  return items;
});

/**
 * Safely resolves a route URL, providing a fallback to prevent app crashes if a route name goes missing.
 *
 * @param {string} routeName 
 * @returns {string} The resolved URL or '#' as fallback.
 */
const getRouteUrl = (routeName) => {
    try { return route(routeName); } catch { return '#'; }
};

/**
 * Checks if the given route is currently active to highlight the sidebar item.
 * Evaluates both exact matches and wildcard child routes (e.g., matching tickets.show when on tickets.index).
 *
 * @param {string} routeName 
 * @returns {boolean} True if the route is active.
 */
const isCurrentRoute = (routeName) => {
  try {
    return route().current(routeName) || route().current(routeName.replace('.index', '.*'));
  } catch { return false; }
};
</script>