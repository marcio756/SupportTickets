<template>
    <AppLayout>
        <Head :title="`Ticket #${ticket.id} - ${ticket.title}`" />

        <div class="mb-4">
            <Link :href="route('tickets.index')" class="text-blue-600 hover:underline text-sm flex items-center gap-1 mb-2">
                &larr; Back to Tickets
            </Link>
            
            <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        #{{ ticket.id }} {{ ticket.title }}
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Opened by <span class="font-medium text-gray-700">{{ ticket.customer.name }}</span>
                    </p>
                </div>
                
                <div class="flex flex-wrap items-center gap-3">
                    <TicketStatusBadge :status="ticket.status" />
                    
                    <span v-if="ticket.assignee" class="text-sm bg-gray-100 px-3 py-1 rounded-full text-gray-600">
                        Assigned to: {{ ticket.assignee.name }}
                    </span>
                    
                    <va-button 
                        v-else-if="isSupporter"
                        size="small" 
                        color="success" 
                        icon="person_add"
                        @click="assignToMe"
                        :loading="isAssigning"
                    >
                        Claim Ticket
                    </va-button>

                    <SupportTimeDisplay :seconds="currentRemainingSeconds" />
                </div>
            </div>
        </div>

        <div class="chat-container flex flex-col bg-[#f4f6f8] rounded-xl border border-gray-200 overflow-hidden h-[600px]">
            
            <va-alert v-if="isTimeUp" color="danger" class="m-4" dense icon="block">
                Daily support time has expired (30 minutes reached). The chat is locked until tomorrow.
            </va-alert>

            <div class="flex-1 overflow-y-auto p-6" ref="messagesContainer">
                <div v-if="localMessages.length === 0" class="text-center text-gray-500 py-10">
                    No messages yet.
                </div>
                
                <TicketMessageBubble 
                    v-for="msg in localMessages" 
                    :key="msg.id" 
                    :message="msg" 
                />
            </div>

            <div class="bg-white border-t border-gray-200 p-4">
                <form @submit.prevent="submitReply" class="flex gap-3 items-end">
                    <div class="flex-1">
                        <va-textarea
                            v-model="replyForm.message"
                            placeholder="Type your reply here..."
                            class="w-full chat-input"
                            :min-rows="2"
                            autosize
                            :error="!!replyForm.errors.message"
                            :disabled="isInputDisabled"
                        />
                    </div>
                    
                    <va-button 
                        type="submit" 
                        color="primary" 
                        icon="send"
                        :loading="replyForm.processing"
                        :disabled="isInputDisabled || !replyForm.message.trim()"
                    >
                        Send
                    </va-button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TicketStatusBadge from '@/Components/Tickets/TicketStatusBadge.vue';
import TicketMessageBubble from '@/Components/Tickets/TicketMessageBubble.vue';
import SupportTimeDisplay from '@/Components/Tickets/SupportTimeDisplay.vue';
import { VaTextarea, VaButton, VaAlert } from 'vuestic-ui';

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    }
});

const page = usePage();
const currentUser = page.props.auth.user;
const isSupporter = currentUser.role === 'supporter' || currentUser.role === 'admin';

// Variáveis reativas base
const currentRemainingSeconds = ref(props.ticket.customer.daily_support_seconds);
const localMessages = ref([...props.ticket.messages]);
let heartbeatInterval = null;

const isTimeUp = computed(() => currentRemainingSeconds.value <= 0);
const isInputDisabled = computed(() => {
    return isTimeUp.value || props.ticket.status === 'closed' || props.ticket.status === 'resolved';
});

const replyForm = useForm({ message: '' });
const messagesContainer = ref(null);
const isAssigning = ref(false);

const scrollToBottom = async () => {
    await nextTick();
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
};

/**
 * Sync external Inertia prop updates with local state
 */
watch(() => props.ticket.messages, (newMessages) => {
    localMessages.value = [...newMessages];
    scrollToBottom();
}, { deep: true });

const submitReply = () => {
    if (isInputDisabled.value) return;

    replyForm.post(route('tickets.messages.store', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => {
            replyForm.reset('message');
        }
    });
};

const assignToMe = () => {
    isAssigning.value = true;
    router.patch(route('tickets.assign', props.ticket.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            isAssigning.value = false;
        }
    });
};

onMounted(() => {
    scrollToBottom();

    if (window.Echo) {
        window.Echo.private(`ticket.${props.ticket.id}`)
            .listen('SupportTimeUpdated', (e) => {
                currentRemainingSeconds.value = e.remainingSeconds;
            })
            .listen('TicketMessageCreated', (e) => {
                // Impede que a mensagem seja duplicada se quem a enviou for o próprio utilizador atual
                const exists = localMessages.value.find(m => m.id === e.message.id);
                if (!exists) {
                    localMessages.value.push(e.message);
                    scrollToBottom();
                }
            });
    }

    if (isSupporter && props.ticket.status === 'open' && !isTimeUp.value) {
        heartbeatInterval = setInterval(() => {
            if (document.visibilityState === 'visible') {
                axios.post(route('tickets.tick-time', props.ticket.id))
                    .then(response => {
                        if (response.data.remaining_seconds !== undefined) {
                            currentRemainingSeconds.value = response.data.remaining_seconds;
                        }
                        if (currentRemainingSeconds.value <= 0) {
                            clearInterval(heartbeatInterval);
                        }
                    }).catch(error => console.error("Heartbeat failed", error));
            }
        }, 5000);
    }
});

onUnmounted(() => {
    if (heartbeatInterval) clearInterval(heartbeatInterval);
    if (window.Echo) window.Echo.leave(`ticket.${props.ticket.id}`);
});
</script>

<style scoped>
.chat-container {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}
</style>