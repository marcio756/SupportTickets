<template>
    <AppLayout>
        <Head :title="`Ticket #${ticket.id} - ${ticket.title}`" />

        <div 
            v-if="showClaimOverlay" 
            class="fixed inset-0 z-50 flex items-center justify-center px-4"
            style="background-color: rgba(255, 255, 255, 0.6); backdrop-filter: blur(4px);"
        >
            <VaCard class="w-full max-w-md p-6 text-center shadow-xl border-t-4 border-primary">
                <VaCardContent>
                    <VaIcon name="lock_person" size="5rem" color="secondary" class="mb-6 opacity-75" />
                    
                    <h2 class="text-2xl font-bold text-gray-800 mb-3">
                        View-Only Mode
                    </h2>
                    
                    <p class="text-gray-600 mb-8 text-lg leading-relaxed">
                        You must claim ownership of this ticket before you can interact with the customer or track time.
                    </p>

                    <div class="flex flex-col gap-3">
                        <VaButton 
                            size="large" 
                            icon="person_add"
                            @click="assignToMe"
                            :loading="isAssigning"
                            class="w-full font-bold py-3"
                        >
                            Claim Ticket Now
                        </VaButton>

                        <Link :href="route('tickets.index')">
                            <VaButton preset="secondary" color="secondary" size="small">
                                Cancel and go back
                            </VaButton>
                        </Link>
                    </div>
                </VaCardContent>
            </VaCard>
        </div>

        <div :class="{ 'blur-md pointer-events-none select-none transition-all duration-300': showClaimOverlay }">
            
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
                        
                        <span v-if="ticket.assignee" class="text-sm bg-gray-100 px-3 py-1 rounded-full text-gray-600 flex items-center gap-1">
                            <VaIcon name="support_agent" size="small" color="secondary" />
                            Assigned to: {{ ticket.assignee.name }}
                        </span>

                        <SupportTimeDisplay :seconds="currentRemainingSeconds" />

                        <va-dropdown placement="bottom-end" v-if="!isSupporter || isAssignedSupporter">
                            <template #anchor>
                                <va-button preset="secondary" icon="settings" size="small">
                                    Actions
                                </va-button>
                            </template>

                            <va-dropdown-content class="p-0">
                                <va-list>
                                    <va-list-item 
                                        v-if="!isSupporter && ticket.status !== 'resolved' && ticket.status !== 'closed'"
                                        @click="updateStatus('resolved')"
                                        class="cursor-pointer hover:bg-gray-100"
                                    >
                                        <va-list-item-section icon>
                                            <va-icon name="check_circle" color="success" />
                                        </va-list-item-section>
                                        <va-list-item-section>Mark as Resolved</va-list-item-section>
                                    </va-list-item>

                                    <template v-if="isAssignedSupporter">
                                        <va-list-item @click="updateStatus('open')" class="cursor-pointer hover:bg-gray-100">
                                            <va-list-item-section>Re-Open Ticket</va-list-item-section>
                                        </va-list-item>
                                        <va-list-item @click="updateStatus('in_progress')" class="cursor-pointer hover:bg-gray-100">
                                            <va-list-item-section>Set In Progress</va-list-item-section>
                                        </va-list-item>
                                        <va-list-item @click="updateStatus('resolved')" class="cursor-pointer hover:bg-gray-100">
                                            <va-list-item-section class="text-blue-600">Mark Resolved</va-list-item-section>
                                        </va-list-item>
                                        <va-list-item @click="updateStatus('closed')" class="cursor-pointer hover:bg-gray-100">
                                            <va-list-item-section class="text-red-600">Close Ticket</va-list-item-section>
                                        </va-list-item>
                                    </template>
                                </va-list>
                            </va-dropdown-content>
                        </va-dropdown>
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
                    <form @submit.prevent="submitReply" class="flex flex-col gap-3">
                        <div class="flex gap-3 items-end">
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
                                :disabled="isSubmitDisabled"
                            >
                                Send
                            </va-button>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <va-file-upload
                                v-model="replyForm.attachment"
                                type="single"
                                file-types=".pdf,.jpg,.jpeg,.png,.zip"
                                :disabled="isInputDisabled"
                                class="w-full max-w-sm"
                                uploadButtonText="Attach File"
                                dropzoneText="Drop a file here"
                            />
                            <div class="text-red-500 text-xs mt-1" v-if="replyForm.errors.attachment">
                                {{ replyForm.errors.attachment }}
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
/**
 * Show.vue
 * * Presentation layer for the Ticket Chat. 
 * All business logic is abstracted into useTicketSupport.
 */
import { toRef } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TicketStatusBadge from '@/Components/Tickets/TicketStatusBadge.vue';
import TicketMessageBubble from '@/Components/Tickets/TicketMessageBubble.vue';
import SupportTimeDisplay from '@/Components/Tickets/SupportTimeDisplay.vue';
import { VaTextarea, VaButton, VaAlert, VaDropdown, VaDropdownContent, VaList, VaListItem, VaListItemSection, VaCard, VaCardContent, VaIcon, VaFileUpload } from 'vuestic-ui';

// Import the business logic composable
import { useTicketSupport } from '@/Composables/useTicketSupport';

const props = defineProps({
    ticket: {
        type: Object,
        required: true,
    }
});

// Passar a prop como uma referência reativa garante que o Inertia
// propaga corretamente as alterações sem necessitar de refresh manual.
const ticketRef = toRef(props, 'ticket');

// Destructure all required reactive state and methods from the composable
const {
    isSupporter,
    currentRemainingSeconds,
    localMessages,
    messagesContainer,
    isAssigning,
    replyForm,
    isTimeUp,
    isAssignedSupporter,
    showClaimOverlay,
    isInputDisabled,
    isSubmitDisabled,
    submitReply,
    assignToMe,
    updateStatus
} = useTicketSupport(ticketRef);
</script>

<style scoped>
.chat-container {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.blur-md {
    backdrop-filter: blur(12px); 
}
</style>