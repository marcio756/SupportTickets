<template>
  <va-navbar class="app-navbar" color="primary" shape>
    <template #left>
      <div class="left-section">
        <va-icon name="menu" class="menu-toggle" @click="$emit('toggle-sidebar')" />
        <Link href="/dashboard" class="logo-link">
          <span class="logo-text">SupportTickets</span>
        </Link>
      </div>
    </template>

    <template #right>
      <div class="right-section flex items-center gap-4">
        
        <SessionStatusBadge 
            v-if="activeSession" 
            :session="activeSession" 
        />

        <NotificationDropdown />
        
        <va-dropdown placement="bottom-end">
          <template #anchor>
            <va-avatar size="small" color="warning" class="user-avatar cursor-pointer">
              {{ userInitials }}
            </va-avatar>
          </template>
          
          <va-dropdown-content class="user-dropdown">
            <div class="dropdown-item theme-item cursor-pointer" @click="toggleTheme">
              <va-icon :name="isDark ? 'light_mode' : 'dark_mode'" size="small" class="mr-2" />
              <span>{{ isDark ? $t('navbar.light_mode') : $t('navbar.dark_mode') }}</span>
            </div>
            
            <va-divider class="m-0" />

            <div class="dropdown-item flex items-center justify-between cursor-default" @click.stop>
              <div class="flex items-center text-inherit whitespace-nowrap">
                <va-icon name="language" size="small" class="mr-2" />
                <span>{{ $t('navbar.language') }}</span>
              </div>
              
              <div class="ml-4 flex items-center">
                  <LanguageSwitcher class="scale-90 origin-right" />
              </div>
            </div>

            <va-divider class="m-0" />
            
            <Link href="/profile" class="dropdown-item">{{ $t('navbar.profile') }}</Link>
            <div @click="handleLogout" class="dropdown-item text-danger cursor-pointer">{{ $t('navbar.logout') }}</div>
          </va-dropdown-content>
        </va-dropdown>
      </div>
    </template>
  </va-navbar>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { useColors, useToast } from 'vuestic-ui';
import { useI18n } from 'vue-i18n';
import NotificationDropdown from './NotificationDropdown.vue';
import SessionStatusBadge from '@/Components/WorkSession/SessionStatusBadge.vue';
import LanguageSwitcher from '@/Components/navbar/LanguageSwitcher.vue';

defineEmits(['toggle-sidebar']);

const { currentPresetName, applyPreset } = useColors();
const { init } = useToast();
const page = usePage();
const { t } = useI18n();

/**
 * Determines the current application visual theme.
 * Evaluates the active preset to dynamically render theme-switching UI elements and text.
 * * @returns {boolean} True if the dark theme preset is currently active.
 */
const isDark = computed(() => currentPresetName.value === 'dark');

/**
 * Toggles the global application theme between light and dark modes via Vuestic's color engine.
 */
const toggleTheme = () => applyPreset(isDark.value ? 'light' : 'dark');

/**
 * Extracts and capitalizes the first two letters of the authenticated user's name
 * to serve as a reliable visual avatar placeholder if a profile image is missing.
 * * @returns {string} The formatted user initials or a fallback character.
 */
const userInitials = computed(() => {
  const user = page.props.auth?.user;
  return user?.name ? user.name.substring(0, 2).toUpperCase() : 'U';
});

/**
 * Resolves the current active work session state from the shared Inertia page properties.
 * This ensures the layout can globally display the user's shift status without dispatching additional API calls.
 * * @returns {Object|null} The active or paused session object, otherwise null.
 */
const activeSession = computed(() => {
    const props = page.props;
    return props.auth?.active_session 
        || props.auth?.work_session 
        || props.active_session 
        || props.work_session 
        || null;
});

/**
 * Intercepts the logout action to enforce critical business logic related to operational metrics.
 * Prevents staff members from abandoning an ongoing shift unexpectedly, which guarantees accurate time tracking.
 */
const handleLogout = () => {
    const session = activeSession.value;
    
    if (session && (session.status === 'active' || session.status === 'paused')) {
        init({
            message: t('navbar.prevent_logout_msg'),
            color: 'danger',
            position: 'top-right',
            duration: 5000
        });
        return;
    }

    router.post(route('logout'));
};
</script>

<style scoped>
/* * Establishes a predictable structural alignment for the navbar's trailing components.
 * Prevents UI layout collapse when dynamic elements like badges or notification popups are conditionally rendered.
 */
.right-section {
    display: flex;
    align-items: center;
    gap: 1rem;
}
</style>