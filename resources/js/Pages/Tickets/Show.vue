<template>
    <AppLayout>
        <Head :title="`Ticket #${ticket.id} - ${ticket.title}`" />

        <div 
            v-if="showClaimOverlay" 
            class="fixed inset-0 z-50 flex items-center justify-center px-4"
            style="background-color: rgba(0, 0, 0, 0.4); backdrop-filter: blur(4px);"
        >
            <VaCard class="w-full max-w-md p-6 text-center shadow-xl border-t-4 border-primary">
                <VaCardContent>
                    <VaIcon name="lock_person" size="5rem" color="secondary" class="mb-6 opacity-75" />
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-3">View-Only Mode</h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-8 text-lg leading-relaxed">
                        You must claim ownership of this ticket before you can interact with the customer or track time.
                    </p>

                    <div class="flex flex-col gap-3">
                        <VaButton size="large" icon="person_add" @click="assignToMe" :loading="isAssigning" class="w-full font-bold py-3">
                            Claim Ticket Now
                        </VaButton>
                        <Link :href="route('tickets.index')">
                            <VaButton preset="secondary" color="secondary" size="small">Cancel and go back</VaButton>
                        </Link>
                    </div>
                </VaCardContent>
            </VaCard>
        </div>

        <div :class="{ 'blur-md pointer-events-none select-none transition-all duration-300': showClaimOverlay }">
            <div class="mb-4">
                <Link :href="route('tickets.index')" class="text-blue-600 dark:text-blue-400 hover:underline text-sm flex items-center gap-1 mb-2">
                    &larr; Back to Tickets
                </Link>
                
                <div class="flex flex-col md:flex-row justify-between md:items-start gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 flex items-center gap-3">
                            #{{ ticket.id }} {{ ticket.title }}
                        </h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Opened by <span class="font-medium text-gray-700 dark:text-gray-300">{{ ticket.customer.name }}</span>
                        </p>
                        
                        <div class="flex flex-wrap items-center gap-2 mt-3">
                            <TagBadge v-for="tag in ticket.tags" :key="tag.id" :tag="tag" />
                            <va-button 
                                v-if="isSupporter" 
                                preset="secondary" 
                                size="small" 
                                icon="edit" 
                                @click="isTagModalOpen = true"
                                class="!px-2 !py-0 h-6"
                            >
                                Manage Tags
                            </va-button>
                        </div>
                    </div>
                    
                    <div class="flex flex-wrap items-center gap-3 mt-2 md:mt-0">
                        <TicketStatusBadge :status="ticket.status" />
                        <span v-if="ticket.assignee" class="text-sm bg-gray-100 dark:bg-gray-800 px-3 py-1 rounded-full text-gray-600 dark:text-gray-400 flex items-center gap-1">
                            <VaIcon name="support_agent" size="small" color="secondary" />
                            Assigned to: {{ ticket.assignee.name }}
                        </span>
                        <SupportTimeDisplay :seconds="currentRemainingSeconds" />

                        <va-dropdown placement="bottom-end" v-if="!isSupporter || isAssignedSupporter">
                            <template #anchor>
                                <va-button preset="secondary" icon="settings" size="small">Actions</va-button>
                            </template>
                            <va-dropdown-content class="p-0">
                                <va-list>
                                    <va-list-item v-if="!isSupporter && ticket.status !== 'resolved' && ticket.status !== 'closed'" @click="updateStatus('resolved')" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <va-list-item-section icon><va-icon name="check_circle" color="success" /></va-list-item-section>
                                        <va-list-item-section>Mark as Resolved</va-list-item-section>
                                    </va-list-item>

                                    <template v-if="isAssignedSupporter">
                                        <va-list-item @click="updateStatus('open')" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"><va-list-item-section>Re-Open Ticket</va-list-item-section></va-list-item>
                                        <va-list-item @click="updateStatus('in_progress')" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"><va-list-item-section>Set In Progress</va-list-item-section></va-list-item>
                                        <va-list-item @click="updateStatus('resolved')" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"><va-list-item-section class="text-blue-600 dark:text-blue-400">Mark Resolved</va-list-item-section></va-list-item>
                                        <va-list-item @click="updateStatus('closed')" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"><va-list-item-section class="text-red-600 dark:text-red-400">Close Ticket</va-list-item-section></va-list-item>
                                        <va-list-item @click="confirmingDeletion = true" class="cursor-pointer hover:bg-red-50 dark:hover:bg-red-900/20 border-t border-gray-100 dark:border-gray-700">
                                            <va-list-item-section icon><va-icon name="delete" color="danger" /></va-list-item-section>
                                            <va-list-item-section class="text-red-600 dark:text-red-400">Delete Ticket</va-list-item-section>
                                        </va-list-item>
                                    </template>
                                </va-list>
                            </va-dropdown-content>
                        </va-dropdown>
                    </div>
                </div>
            </div>

            <div class="chat-container flex flex-col bg-gray-100 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden h-[600px] mt-4">
                <va-alert v-if="isTimeUp" color="danger" class="m-4" dense icon="block">
                    Daily support time has expired (30 minutes reached). The chat is locked until tomorrow.
                </va-alert>

                <va-alert v-if="ticket.status === 'open' && !showClaimOverlay" color="info" class="m-4" dense outline icon="info">
                    The ticket is currently "Open". Please set it to "In Progress" in the actions menu to start replying and tracking time.
                </va-alert>
                
                <div class="flex-1 overflow-y-auto p-6" ref="messagesContainer">
                    <div v-if="localMessages.length === 0" class="text-center text-gray-500 dark:text-gray-400 py-10">No messages yet.</div>
                    <TicketMessageBubble v-for="msg in localMessages" :key="msg.id" :message="msg" />
                </div>

                <div class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 p-4">
                    <form @submit.prevent="submitReply" class="flex flex-col gap-3">
                        <div class="flex gap-3 items-end">
                            <div class="flex-1">
                                <va-textarea
                                    v-model="replyForm.message"
                                    :placeholder="isInputDisabled ? 'Chat is locked. Ticket must be In Progress.' : 'Type your reply here...'"
                                    class="w-full chat-input"
                                    :min-rows="2"
                                    autosize
                                    :error="!!replyForm.errors.message"
                                    :disabled="isInputDisabled"
                                />
                            </div>
                            <va-button type="submit" color="primary" icon="send" :loading="replyForm.processing" :disabled="isSubmitDisabled">Send</va-button>
                        </div>
                        <div class="flex items-center gap-2">
                            <va-file-upload v-model="replyForm.attachment" type="single" file-types=".pdf,.jpg,.jpeg,.png,.zip" :disabled="isInputDisabled" class="w-full max-w-sm" uploadButtonText="Attach File" dropzoneText="Drop a file here" />
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <va-modal
            v-model="isTagModalOpen"
            title="Manage Ticket Tags"
            ok-text="Save Changes"
            cancel-text="Cancel"
            @ok="saveTags"
        >
            <div class="py-4">
                <va-select
                    v-model="tagForm.tags"
                    :options="tagOptions"
                    value-by="value"
                    text-by="text"
                    label="Select Categories"
                    multiple
                    clearable
                    class="w-full"
                >
                    <template #content="{ valueArray }">
                        <div class="flex gap-1 flex-wrap">
                            <span v-if="valueArray.length === 0" class="text-gray-500">No tags selected</span>
                            <TagBadge v-for="tagId in valueArray" :key="tagId" :tag="getDetailedTag(tagId)" />
                        </div>
                    </template>
                </va-select>
            </div>
        </va-modal>

        <va-modal v-model="confirmingDeletion" title="Delete Ticket" message="Are you sure you want to delete this ticket? This action is permanent." ok-text="Confirm Deletion" cancel-text="Cancel" state="danger" @ok="deleteTicket">
            <div class="mt-4">
                <va-input v-model="deleteForm.password" type="password" label="Confirmation Password" placeholder="Enter your current password" :error="!!deleteForm.errors.password" :error-messages="deleteForm.errors.password" class="w-full" @keyup.enter="deleteTicket" />
            </div>
        </va-modal>
    </AppLayout>
</template>

<script setup>
import { ref, toRef, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TicketStatusBadge from '@/Components/Tickets/TicketStatusBadge.vue';
import TicketMessageBubble from '@/Components/Tickets/TicketMessageBubble.vue';
import SupportTimeDisplay from '@/Components/Tickets/SupportTimeDisplay.vue';
import TagBadge from '@/Components/Common/TagBadge.vue';
import { 
    VaTextarea, VaButton, VaAlert, VaDropdown, VaDropdownContent, 
    VaList, VaListItem, VaListItemSection, VaCard, VaCardContent, 
    VaIcon, VaFileUpload, VaModal, VaInput, VaSelect 
} from 'vuestic-ui';
import { useTicketSupport } from '@/Composables/useTicketSupport';

const props = defineProps({
    ticket: { type: Object, required: true },
    availableTags: { type: Array, default: () => [] }
});

const ticketRef = toRef(props, 'ticket');

const {
    isSupporter, currentRemainingSeconds, localMessages, messagesContainer,
    isAssigning, replyForm, deleteForm, confirmingDeletion, isTimeUp,
    isAssignedSupporter, showClaimOverlay, isInputDisabled, isSubmitDisabled,
    submitReply, assignToMe, updateStatus, deleteTicket
} = useTicketSupport(ticketRef);

// Tag Management Logic
const isTagModalOpen = ref(false);

const tagForm = useForm({
    tags: props.ticket.tags.map(tag => String(tag.id))
});

const tagOptions = computed(() => {
    return props.availableTags.map(tag => ({
        text: tag.name,
        value: String(tag.id)
    }));
});

const getDetailedTag = (id) => {
    return props.availableTags.find(t => String(t.id) === String(id)) || { name: 'Unknown', color: '#ccc' };
};

/**
 * Syncs the selected tags to the server pivot table.
 */
const saveTags = () => {
    tagForm.put(route('tickets.tags.sync', props.ticket.id), {
        preserveScroll: true,
        onSuccess: () => {
            isTagModalOpen.value = false;
        }
    });
};
</script>

<style scoped>
.chat-container { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
.blur-md { backdrop-filter: blur(12px); }
</style>