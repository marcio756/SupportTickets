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
        <va-dropdown placement="bottom-end" :offset="[10, 10]" :close-on-content-click="false">
          <template #anchor>
            <va-button preset="plain" class="notification-btn">
              <va-badge :text="unreadCount" :visible="unreadCount > 0" color="danger" overlap>
                <va-icon name="notifications" color="white" />
              </va-badge>
            </va-button>
          </template>

          <va-dropdown-content class="notification-dropdown">
            <div class="dropdown-header">
              <span class="title">Notifications</span>
              <va-button 
                v-if="notifications.length > 0" 
                preset="plain" 
                size="small" 
                color="danger" 
                @click="clearAll"
              >
                Clear All
              </va-button>
            </div>

            <va-divider class="m-0" />

            <div class="notification-list">
              <template v-if="groupedNotifications.length > 0">
                <div 
                  v-for="item in groupedNotifications" 
                  :key="item.id" 
                  class="notification-item"
                  @click="handleNotificationClick(item)"
                >
                  <div class="content">
                    <p class="message">
                      {{ item.data.message }}
                      <va-badge 
                        v-if="item.count > 1" 
                        :text="`x${item.count}`" 
                        color="info" 
                        class="ml-1" 
                      />
                    </p>
                    <span class="time">{{ formatTime(item.created_at) }}</span>
                  </div>
                  <va-button 
                    preset="plain" 
                    icon="close" 
                    size="small" 
                    color="secondary" 
                    @click.stop="deleteIndividual(item.id)" 
                  />
                </div>
              </template>
              <div v-else class="empty-state">
                No new notifications
              </div>
            </div>
          </va-dropdown-content>
        </va-dropdown>
        
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
import { ref, computed, onMounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { useColors } from 'vuestic-ui';
import axios from 'axios';

defineEmits(['toggle-sidebar']);

const { currentPresetName, applyPreset } = useColors();
const notifications = ref([]);

/**
 * Reactively tracks the current theme status.
 */
const isDark = computed(() => currentPresetName.value === 'dark');

/**
 * Groups notifications by ticket_id for messages to show "xN" format.
 * Status changes are kept separate.
 */
const groupedNotifications = computed(() => {
    const groups = [];
    const messageGroups = {};

    notifications.value.forEach(notification => {
        const data = notification.data;
        if (data.type === 'new_message') {
            if (!messageGroups[data.ticket_id]) {
                messageGroups[data.ticket_id] = { ...notification, count: 1 };
            } else {
                messageGroups[data.ticket_id].count++;
                // Update with the most recent ID for deletion/interaction
                messageGroups[data.ticket_id].id = notification.id;
            }
        } else {
            groups.push({ ...notification, count: 1 });
        }
    });

    return [...groups, ...Object.values(messageGroups)].sort((a, b) => 
        new Date(b.created_at) - new Date(a.created_at)
    );
});

const unreadCount = computed(() => notifications.value.length);

/**
 * Fetches initial notifications from the database.
 */
const fetchNotifications = async () => {
    const response = await axios.get(route('notifications.index'));
    notifications.value = response.data;
};

/**
 * Handles notification click: marks as read, removes from list and redirects.
 */
const handleNotificationClick = async (item) => {
    const response = await axios.post(route('notifications.read', item.id));
    // Remove all notifications of the same ticket if it's a message group
    if (item.data.type === 'new_message') {
        notifications.value = notifications.value.filter(n => n.data.ticket_id !== item.data.ticket_id);
    } else {
        notifications.value = notifications.value.filter(n => n.id !== item.id);
    }
    router.get(route('tickets.show', response.data.ticket_id));
};

const deleteIndividual = async (id) => {
    await axios.delete(route('notifications.destroy', id));
    notifications.value = notifications.value.filter(n => n.id !== id);
};

const clearAll = async () => {
    await axios.post(route('notifications.clear'));
    notifications.value = [];
};

const formatTime = (date) => new Date(date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

const toggleTheme = () => applyPreset(isDark.value ? 'light' : 'dark');

const userInitials = computed(() => {
  const user = usePage().props.auth.user;
  return user?.name ? user.name.substring(0, 2).toUpperCase() : 'U';
});

onMounted(() => {
    fetchNotifications();
    // Real-time listener for private notification channel
    const userId = usePage().props.auth.user.id;
    window.Echo.private(`App.Models.User.${userId}`)
        .notification((notification) => {
            notifications.value.unshift(notification);
        });
});
</script>

<style scoped>
.app-navbar { box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
.left-section { display: flex; align-items: center; gap: 1rem; }
.right-section { display: flex; align-items: center; gap: 1rem; }
.logo-text { font-weight: bold; font-size: 1.25rem; }
.notification-dropdown { width: 320px; padding: 0; }
.dropdown-header { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; }
.dropdown-header .title { font-weight: 600; font-size: 0.9rem; }
.notification-list { max-height: 350px; overflow-y: auto; }
.notification-item { 
    display: flex; justify-content: space-between; align-items: center; 
    padding: 0.75rem 1rem; border-bottom: 1px solid var(--va-background-border);
    cursor: pointer; transition: background 0.2s;
}
.notification-item:hover { background-color: var(--va-background-element); }
.content { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; }
.message { font-size: 0.85rem; margin: 0; line-height: 1.2; }
.time { font-size: 0.75rem; color: var(--va-secondary); }
.empty-state { padding: 2rem; text-align: center; color: var(--va-secondary); font-size: 0.85rem; }
.user-dropdown { display: flex; flex-direction: column; min-width: 170px; padding: 0; }
.dropdown-item { 
    padding: 0.75rem 1rem; text-decoration: none; color: var(--va-dark);
    background: transparent; border: none; text-align: left; cursor: pointer;
    display: flex; align-items: center; font-size: 0.9rem;
}
.dropdown-item:hover { background-color: var(--va-background-element); }
.text-danger { color: var(--va-danger); }
</style>