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
        <span class="title">{{ $t('notifications.title') }}</span>
        <va-button 
          v-if="notifications.length > 0" 
          preset="plain" 
          size="small" 
          color="danger" 
          @click="clearAll"
        >
          {{ $t('notifications.clear_all') }}
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
                <va-icon 
                  :name="item.data.type === 'status_change' ? 'info' : 'chat'" 
                  size="small" 
                  class="mr-1 text-gray-400" 
                />
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
          {{ $t('notifications.empty') }}
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

/**
 * @type {import('vue').ComputedRef<number>}
 */
const unreadCount = computed(() => notifications.value.length);

/**
 * Groups notifications by ticket_id AND by notification type.
 * This ensures that standard messages do not merge with status change alerts,
 * providing a cleaner and more organized UI for the user.
 *
 * @returns {Array<Object>} Array of grouped notification objects.
 */
const groupedNotifications = computed(() => {
    const groups = [];
    const messageGroups = {};

    notifications.value.forEach(n => {
        const tId = n.data?.ticket_id;
        const type = n.data?.type || 'general';
        
        if (tId) {
            // Composite key (e.g., "1_new_message" or "1_status_change")
            const groupKey = `${tId}_${type}`;

            if (!messageGroups[groupKey]) {
                messageGroups[groupKey] = { ...n, count: 1, ids: [n.id] };
            } else {
                messageGroups[groupKey].count++;
                messageGroups[groupKey].ids.push(n.id);
            }
        } else {
            groups.push({ ...n, count: 1, ids: [n.id] });
        }
    });

    return [...groups, ...Object.values(messageGroups)];
});

/**
 * Fetches initial unread notifications from the native API endpoint.
 * @returns {Promise<void>}
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
 * Handles clicks on a grouped or single notification item.
 * Marks all related notifications as read in the database before redirecting.
 *
 * @param {Object} item The notification item or group with respective IDs.
 * @returns {Promise<void>}
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
 * Visually removes the notification and deletes it from the DB without view redirection.
 *
 * @param {Object} item The notification item or group.
 * @returns {Promise<void>}
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
 * Hard reset: clears all notifications from the panel and database for the user.
 * @returns {Promise<void>}
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
    
    const userId = usePage().props.auth?.user?.id;
    
    // Listens for real-time notifications via WebSockets (Laravel Echo + Reverb)
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