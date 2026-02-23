<template>
  <va-navbar class="app-navbar" color="primary" shape>
    <template #left>
      <div class="left-section">
        <va-icon
          name="menu"
          class="menu-toggle"
          @click="$emit('toggle-sidebar')"
        />
        <Link href="/dashboard" class="logo-link">
          <span class="logo-text">SupportTickets</span>
        </Link>
      </div>
    </template>

    <template #right>
      <div class="right-section">
        
        <NotificationDropdown />
        
        <va-dropdown placement="bottom-end">
          <template #anchor>
            <va-avatar size="small" color="warning" class="user-avatar">
              {{ userInitials }}
            </va-avatar>
          </template>
          <va-dropdown-content class="user-dropdown">
            <div class="dropdown-item theme-item" @click="toggleTheme">
              <va-icon :name="isDark ? 'light_mode' : 'dark_mode'" size="small" class="mr-2" />
              <span>{{ isDark ? 'Modo Claro' : 'Modo Escuro' }}</span>
            </div>
            <va-divider class="m-0" />
            <Link href="/profile" class="dropdown-item">Profile</Link>
            <Link href="/logout" method="post" as="button" class="dropdown-item text-danger">Logout</Link>
          </va-dropdown-content>
        </va-dropdown>
      </div>
    </template>
  </va-navbar>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useColors } from 'vuestic-ui';
import NotificationDropdown from './NotificationDropdown.vue';

defineEmits(['toggle-sidebar']);

const { currentPresetName, applyPreset } = useColors();

const isDark = computed(() => currentPresetName.value === 'dark');

/**
 * Toggle the application theme between light and dark modes.
 */
const toggleTheme = () => applyPreset(isDark.value ? 'light' : 'dark');

/**
 * Calculate user initials to display in the generic avatar placeholder.
 * * @returns {String}
 */
const userInitials = computed(() => {
  const user = usePage().props.auth.user;
  return user?.name ? user.name.substring(0, 2).toUpperCase() : 'U';
});
</script>

<style scoped>
.app-navbar { 
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); 
}
.left-section, .right-section { 
    display: flex; 
    align-items: center; 
    gap: 1rem; 
}
.dropdown-item { 
    padding: 0.75rem 1rem; 
    cursor: pointer; 
    display: flex; 
    align-items: center; 
}
.mr-2 { 
    margin-right: 0.5rem; 
}
.text-danger { 
    color: var(--va-danger); 
}
.menu-toggle {
    cursor: pointer;
}
.logo-link {
    text-decoration: none;
    color: inherit;
    font-weight: bold;
}
</style>