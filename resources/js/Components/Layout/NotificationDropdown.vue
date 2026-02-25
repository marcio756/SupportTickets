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

/**
 * @type {import('vue').ComputedRef<number>}
 */
const unreadCount = computed(() => notifications.value.length);

/**
 * Agrupa as notificações pelo ticket_id E pelo tipo de notificação.
 * Isto garante que mensagens não se misturam com mudanças de estado.
 *
 * @returns {Array<Object>} Array de objetos de notificação agrupados.
 */
const groupedNotifications = computed(() => {
    const groups = [];
    const messageGroups = {};

    notifications.value.forEach(n => {
        const tId = n.data?.ticket_id;
        const type = n.data?.type || 'general';
        
        if (tId) {
            // Chave composta (Ex: "1_new_message" ou "1_status_change")
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
 * Busca notificações iniciais não lidas da API nativa.
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
 * Lida com o clique numa notificação agrupada ou item único.
 * Elimina todas as notificações atreladas a ela na DB.
 *
 * @param {Object} item O item ou grupo com os respetivos IDs
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
 * Apaga a notificação visualmente e da DB sem redirecionar a view.
 *
 * @param {Object} item O item ou grupo de notificação.
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
 * Hard reset que elimina todas as notificações do painel.
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
    
    // Ouve notificações via WebSockets (Laravel Echo + Reverb)
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