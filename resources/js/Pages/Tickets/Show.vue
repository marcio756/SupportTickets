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
                
                <div class="flex items-center gap-3">
                    <TicketStatusBadge :status="ticket.status" />
                    <span v-if="ticket.assignee" class="text-sm bg-gray-100 px-3 py-1 rounded-full text-gray-600">
                        Assigned to: {{ ticket.assignee.name }}
                    </span>
                </div>
            </div>
        </div>

        <div class="chat-container flex flex-col bg-[#f4f6f8] rounded-xl border border-gray-200 overflow-hidden h-[600px]">
            
            <div class="flex-1 overflow-y-auto p-6">
                <div v-if="ticket.messages.length === 0" class="text-center text-gray-500 py-10">
                    No messages yet.
                </div>
                
                <TicketMessageBubble 
                    v-for="msg in ticket.messages" 
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
                        />
                    </div>
                    
                    <va-button preset="plain" icon="attach_file" color="gray" title="Attach file (coming soon)" />
                    
                    <va-button 
                        type="submit" 
                        color="primary" 
                        icon="send"
                        :loading="replyForm.processing"
                        :disabled="!replyForm.message.trim()"
                    >
                        Send
                    </va-button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TicketStatusBadge from '@/Components/Tickets/TicketStatusBadge.vue';
import TicketMessageBubble from '@/Components/Tickets/TicketMessageBubble.vue';
import { VaTextarea, VaButton } from 'vuestic-ui';

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    }
});

/**
 * Handle the new message submission
 */
const replyForm = useForm({
    message: '',
});

const submitReply = () => {
    replyForm.post(route('tickets.messages.store', props.ticket.id), {
        preserveScroll: true, // Keeps the page from jumping to top
        onSuccess: () => {
            replyForm.reset('message'); // Clear the input on success
        }
    });
};
</script>

<style scoped>
.chat-container {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}
</style>