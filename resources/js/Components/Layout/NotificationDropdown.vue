<script setup>
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Dropdown from '@/Components/Dropdown.vue';

const notifications = ref([]);

/**
 * Group notifications by ticket_id for messages.
 * Status changes remain separate.
 */
const groupedNotifications = computed(() => {
    const groups = [];
    const messageGroups = {};

    notifications.value.forEach(notification => {
        const data = notification.data;
        
        if (data.type === 'new_message') {
            if (!messageGroups[data.ticket_id]) {
                messageGroups[data.ticket_id] = {
                    ...notification,
                    count: 1
                };
            } else {
                messageGroups[data.ticket_id].count++;
                // Keep the most recent ID for the click action
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

const fetchNotifications = async () => {
    const response = await axios.get(route('notifications.index'));
    notifications.value = response.data;
};

const handleNotificationClick = async (notification) => {
    const response = await axios.post(route('notifications.read', notification.id));
    notifications.value = notifications.value.filter(n => 
        notification.data.type === 'new_message' 
            ? n.data.ticket_id !== notification.data.ticket_id 
            : n.id !== notification.id
    );
    router.get(route('tickets.show', response.data.ticket_id));
};

const deleteNotification = async (id, e) => {
    e.stopPropagation();
    await axios.delete(route('notifications.destroy', id));
    notifications.value = notifications.value.filter(n => n.id !== id);
};

const clearAll = async () => {
    await axios.post(route('notifications.clear'));
    notifications.value = [];
};

onMounted(() => {
    fetchNotifications();
    // Real-time listener using Laravel Echo
    window.Echo.private(`App.Models.User.${window.Laravel.user.id}`)
        .notification((notification) => {
            notifications.value.unshift(notification);
        });
});
</script>

<template>
    <div class="relative">
        <Dropdown align="right" width="80">
            <template #trigger>
                <button class="relative p-2 text-gray-500 hover:text-gray-700 focus:outline-none transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <span v-if="notifications.length > 0" 
                          class="absolute top-0 right-0 flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-red-500 rounded-full transform translate-x-1/2 -translate-y-1/2">
                        {{ notifications.length }}
                    </span>
                </button>
            </template>

            <template #content>
                <div class="w-80 bg-white dark:bg-gray-800 rounded-md shadow-lg overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-2 border-b dark:border-gray-700">
                        <span class="text-sm font-semibold">Notificações</span>
                        <button v-if="notifications.length > 0" @click="clearAll" class="text-xs text-red-500 hover:underline">
                            Apagar todas
                        </button>
                    </div>

                    <div class="max-height-[400px] overflow-y-auto">
                        <div v-if="groupedNotifications.length === 0" class="p-4 text-center text-gray-500 text-sm">
                            Sem novas notificações
                        </div>
                        
                        <div v-for="notification in groupedNotifications" 
                             :key="notification.id"
                             @click="handleNotificationClick(notification)"
                             class="flex items-start p-4 border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition">
                            
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ notification.data.title }}
                                    <span v-if="notification.count > 1" class="ml-1 text-blue-500 font-bold">
                                        x{{ notification.count }}
                                    </span>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">{{ notification.data.message }}</p>
                            </div>

                            <button @click="(e) => deleteNotification(notification.id, e)" 
                                    class="text-gray-400 hover:text-red-500 ml-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </Dropdown>
    </div>
</template>