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
        <va-button preset="plain" icon="notifications" class="notification-btn" />
        
        <va-dropdown placement="bottom-end">
          <template #anchor>
            <va-avatar size="small" color="warning" class="user-avatar">
              {{ userInitials }}
            </va-avatar>
          </template>
          <va-dropdown-content class="user-dropdown">
            <div class="dropdown-item theme-item" @click="toggleTheme">
              <va-icon 
                :name="isDark ? 'light_mode' : 'dark_mode'" 
                size="small" 
                class="mr-2" 
              />
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

defineEmits(['toggle-sidebar']);

const { currentPresetName, applyPreset } = useColors();

/**
 * Reactively tracks the current theme status.
 */
const isDark = computed(() => currentPresetName.value === 'dark');

/**
 * Switches the global application theme.
 */
const toggleTheme = () => {
    applyPreset(isDark.value ? 'light' : 'dark');
};

/**
 * Computes initials for the user avatar based on authenticated data.
 * @returns {string} User initials or 'U' fallback.
 */
const userInitials = computed(() => {
  const user = usePage().props.auth.user;
  if (!user || !user.name) return 'U';
  return user.name.substring(0, 2).toUpperCase();
});
</script>

<style scoped>
.app-navbar {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}
.left-section {
  display: flex;
  align-items: center;
  gap: 1rem;
}
.menu-toggle {
  cursor: pointer;
  font-size: 1.5rem;
}
.logo-link {
  text-decoration: none;
  color: inherit;
}
.logo-text {
  font-weight: bold;
  font-size: 1.25rem;
  letter-spacing: 0.5px;
}
.right-section {
  display: flex;
  align-items: center;
  gap: 1rem;
}
.user-avatar {
  cursor: pointer;
}
.user-dropdown {
  display: flex;
  flex-direction: column;
  min-width: 170px;
  padding: 0;
}
.dropdown-item {
  padding: 0.75rem 1rem;
  text-decoration: none;
  color: var(--va-dark);
  transition: background-color 0.2s;
  background: transparent;
  border: none;
  text-align: left;
  cursor: pointer;
  display: flex;
  align-items: center;
  font-size: 0.9rem;
}
.dropdown-item:hover {
  background-color: var(--va-background-element);
}
.theme-item {
  color: var(--va-primary);
  font-weight: 500;
}
.text-danger {
  color: var(--va-danger);
}
.mr-2 {
  margin-right: 0.75rem;
}
.m-0 {
  margin: 0;
}
</style>