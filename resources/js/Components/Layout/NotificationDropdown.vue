<script setup>
/**
 * Componente de Gestão de Notificações.
 * Implementa agrupamento lógico (multiplier xN), integração real-time (Echo)
 * e gestão de estado (delete-on-click) conforme requisitos.
 * * @author Arquiteto de Software Sénior
 */
import { ref, computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';

const notifications = ref([]);

/**
 * Contagem total de notificações não lidas para o Badge.
 * @returns {ComputedRef<number>}
 */
const unreadCount = computed(() => notifications.value.length);

/**
 * Agrupa notificações de mensagens pelo ticket_id para exibir multiplicador (ex: x5).
 * Notificações de mudança de estado permanecem isoladas para clareza.
 * @returns {ComputedRef<Array>}
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
                // Atualiza o ID para o mais recente para garantir que a ação de leitura funcione
                messageGroups[data.ticket_id].id = notification.id;
            }
        } else {
            groups.push({ ...notification, count: 1 });
        }
    });

    // Ordenação cronológica (mais recentes primeiro)
    return [...groups, ...Object.values(messageGroups)].sort((a, b) => 
        new Date(b.created_at) - new Date(a.created_at)
    );
});

/**
 * Carrega as notificações iniciais da base de dados.
 */
const fetchNotifications = async () => {
    try {
        const response = await axios.get(route('notifications.index'));
        notifications.value = response.data;
    } catch (error) {
        console.error("Falha ao carregar notificações:", error);
    }
};

/**
 * Trata o clique na notificação: marca como lida (elimina), 
 * remove do estado local e redireciona.
 * @param {Object} item
 */
const handleNotificationClick = async (item) => {
    try {
        const response = await axios.post(route('notifications.read', item.id));
        
        // Se for mensagem, removemos todas as do mesmo ticket da vista local
        if (item.data.type === 'new_message') {
            notifications.value = notifications.value.filter(n => n.data.ticket_id !== item.data.ticket_id);
        } else {
            notifications.value = notifications.value.filter(n => n.id !== item.id);
        }
        
        router.get(route('tickets.show', response.data.ticket_id));
    } catch (error) {
        console.error("Erro ao processar clique na notificação:", error);
    }
};

/**
 * Elimina uma notificação individualmente.
 * @param {string|number} id
 */
const deleteIndividual = async (id) => {
    try {
        await axios.delete(route('notifications.destroy', id));
        notifications.value = notifications.value.filter(n => n.id !== id);
    } catch (error) {
        console.error("Erro ao eliminar notificação:", error);
    }
};

/**
 * Elimina todas as notificações do utilizador.
 */
const clearAll = async () => {
    try {
        await axios.post(route('notifications.clear'));
        notifications.value = [];
    } catch (error) {
        console.error("Erro ao limpar todas as notificações:", error);
    }
};

onMounted(() => {
    fetchNotifications();
    
    // Registo do listener privado para notificações em tempo real
    if (window.Echo) {
        const userId = usePage().props.auth.user.id;
        window.Echo.private(`App.Models.User.${userId}`)
            .notification((notification) => {
                notifications.value.unshift(notification);
            });
    }
});
</script>

<template>
    <va-dropdown placement="bottom-end" :offset="[10, 10]" :close-on-content-click="false">
        <template #anchor>
            <div class="cursor-pointer flex items-center justify-center p-2 rounded hover:bg-white/10 transition-colors text-white mr-1 sm:mr-2">
                <va-badge :text="unreadCount" :visible="unreadCount > 0" color="danger" overlap>
                    <va-icon name="notifications" color="#ffffff" />
                </va-badge>
            </div>
        </template>

        <va-dropdown-content class="p-0 min-w-[320px] dark:bg-gray-800 border dark:border-gray-700 shadow-xl rounded-md overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                <span class="text-sm font-semibold text-gray-800 dark:text-gray-100">Notificações</span>
                <button 
                    v-if="notifications.length > 0" 
                    @click="clearAll" 
                    class="text-xs text-red-500 hover:text-red-700 font-medium transition-colors"
                >
                    Apagar todas
                </button>
            </div>

            <div class="max-h-[350px] overflow-y-auto">
                <div v-if="groupedNotifications.length === 0" class="p-6 text-center text-gray-500 text-sm">
                    Sem novas notificações
                </div>
                
                <div 
                    v-for="item in groupedNotifications" 
                    :key="item.id"
                    @click="handleNotificationClick(item)"
                    class="flex items-start p-4 border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors group"
                >
                    <div class="flex-1 pr-2">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 leading-tight">
                            {{ item.data.title }}
                            <span v-if="item.count > 1" class="ml-1 text-xs px-1.5 py-0.5 bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 rounded-full font-bold">
                                x{{ item.count }}
                            </span>
                        </p>
                        <p class="text-xs text-gray-500 mt-1 line-clamp-2">{{ item.data.message }}</p>
                    </div>

                    <button 
                        @click.stop="deleteIndividual(item.id)" 
                        class="text-gray-400 opacity-0 group-hover:opacity-100 hover:text-red-500 transition-opacity p-1"
                        title="Remover"
                    >
                        <va-icon name="close" size="small" />
                    </button>
                </div>
            </div>
        </va-dropdown-content>
    </va-dropdown>
</template>

<style scoped>
/**
 * Implementação do truncamento de texto com compatibilidade standard CSS.
 */
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2; 
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>