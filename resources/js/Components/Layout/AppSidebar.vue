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

const displayRole = computed(() => {
    const user = page.props.auth?.user;
    if (!user) return 'Guest';
    return typeof user.role === 'object' ? user.role.value : user.role;
});

const navigationItems = computed(() => {
  const user = page.props.auth?.user;
  if (!user) return [];
  
  const role = (typeof user.role === 'object' ? user.role.value : user.role).toLowerCase();

  const items = [
    { title: 'Dashboard', icon: 'dashboard', route: 'dashboard' },
  ];

  // Admin does NOT see operational Tickets menu
  if (role !== 'admin') {
    items.push({ title: 'Tickets', icon: 'confirmation_number', route: 'tickets.index' });
  }

  // Staff (Supporter/Admin) can manage Users and Tags
  if (role === 'supporter' || role === 'admin') {
    items.push({ title: 'Users', icon: 'group', route: 'users.index' });
    items.push({ title: 'Manage Tags', icon: 'local_offer', route: 'tags.index' });
  }

  if (role === 'admin') {
    items.push({ title: 'Activity Logs', icon: 'history', route: 'activity-logs.index' });
  }

  return items;
});

const getRouteUrl = (routeName) => {
    try { return route(routeName); } catch { return '#'; }
};

const isCurrentRoute = (routeName) => {
  try {
    return route().current(routeName) || route().current(routeName.replace('.index', '.*'));
  } catch { return false; }
};
</script>