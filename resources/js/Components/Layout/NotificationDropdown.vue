<template>
  <va-dropdown placement="bottom-end" :offset="[10, 10]" :close-on-content-click="false">
    <template #anchor>
      <va-button preset="plain" class="notification-btn">
        <span class="mr-1" v-if="unreadCount > 0">({{ unreadCount }})</span>
        <va-badge :text="unreadCount" :visible="unreadCount > 0" color="danger" overlap>
          <va-icon name="notifications" color="#ffffff" />
        </va-badge>
      </va-button>
    </template>

    <va-dropdown-content class="notification-dropdown">
      <div class="dropdown-header">
        <span class="title">Notificações</span>
        <va-button 
          v-if="notifications.length > 0" 
          preset="plain" 
          size="small" 
          color="danger" 
          @click="clearAll"
        >
          Apagar todas
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
            </div>
            <va-button 
              preset="plain" 
              icon="close" 
              size="small" 
              color="secondary" 
              @click.stop="deleteNotification(item)" 
            />
          </div>
        </template>
        <div v-else class="empty-state">
          Sem notificações novas
        </div>
      </div>
    </va-dropdown-content>
  </va-dropdown>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import axios from 'axios';

const notifications = ref([]);

const unreadCount = computed(() => notifications.value.length);

/**
 * Group notifications by ticket_id for new messages.
 * This ensures multiple messages for the same ticket appear as a single notification indicator.
 *
 * @returns {Array} Array of grouped notification objects.
 */
const groupedNotifications = computed(() => {
    const groups = [];
    const messageGroups = {};

    notifications.value.forEach(n => {
        if (n.data.type === 'new_message') {
            if (!messageGroups[n.data.ticket_id]) {
                messageGroups[n.data.ticket_id] = { ...n, count: 1, ids: [n.id] };
            } else {
                messageGroups[n.data.ticket_id].count++;
                messageGroups[n.data.ticket_id].ids.push(n.id);
            }
        } else {
            groups.push({ ...n, count: 1, ids: [n.id] });
        }
    });

    return [...groups, ...Object.values(messageGroups)];
});

/**
 * Fetch initial unread notifications from the server endpoint.
 */
const fetchNotifications = async () => {
    try {
        const response = await axios.get(route('notifications.index'));
        notifications.value = response.data;
    } catch (error) {
        console.error("Failed to load notifications.", error);
    }
};

/**
 * Handle clicking on a notification group or single item.
 * Marks all associated IDs as read and redirects the user to the specific ticket view.
 *
 * @param {Object} item The notification item/group.
 */
const handleNotificationClick = async (item) => {
    try {
        const response = await axios.post(route('notifications.read-bulk'), { ids: item.ids });
        notifications.value = notifications.value.filter(n => !item.ids.includes(n.id));
        router.get(route('tickets.show', response.data.ticket_id));
    } catch (error) {
        console.error("Failed to process notification redirect.", error);
    }
};

/**
 * Delete a specific notification or group of notifications without redirecting.
 *
 * @param {Object} item The notification item/group to delete.
 */
const deleteNotification = async (item) => {
    try {
        await axios.post(route('notifications.read-bulk'), { ids: item.ids });
        notifications.value = notifications.value.filter(n => !item.ids.includes(n.id));
    } catch (error) {
        console.error("Failed to delete notification.", error);
    }
};

/**
 * Clear all notifications for the currently authenticated user.
 */
const clearAll = async () => {
    try {
        await axios.post(route('notifications.clear'));
        notifications.value = [];
    } catch (error) {
        console.error("Failed to clear all notifications.", error);
    }
};

onMounted(() => {
    fetchNotifications();
    
    // Safety check: Safely retrieve user ID to avoid unhandled errors on non-auth views
    const userId = usePage().props.auth?.user?.id;
    
    // Listen for real-time broadcasted notifications via Laravel Echo only if user is logged in
    if (window.Echo && userId) {
        window.Echo.private(`App.Models.User.${userId}`)
            .notification((notification) => {
                notifications.value.unshift(notification);
            });
    }
});
</script>

<style scoped>
.notification-dropdown { 
    width: 320px; 
    padding: 0; 
}
.dropdown-header { 
    display: flex; 
    justify-content: space-between; 
    padding: 0.75rem 1rem; 
    align-items: center; 
}
.title {
    font-weight: bold;
}
.notification-item { 
    display: flex; 
    justify-content: space-between; 
    padding: 0.75rem 1rem; 
    border-bottom: 1px solid var(--va-background-border); 
    cursor: pointer; 
    transition: background-color 0.2s ease-in-out;
}
.notification-item:hover { 
    background: var(--va-background-element); 
}
.content {
    display: flex;
    align-items: center;
    flex-grow: 1;
}
.message { 
    font-size: 0.85rem; 
    margin: 0; 
}
.empty-state { 
    padding: 1.5rem; 
    text-align: center; 
    color: var(--va-secondary); 
}
</style>