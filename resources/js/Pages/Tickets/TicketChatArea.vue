<template>
    <div class="chat-container flex flex-col bg-gray-100 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden h-[600px] mt-4">
        <va-alert v-if="ticket.customer && isTimeUp" color="danger" class="m-4" dense icon="block">
            {{ $t('tickets.chat.time_expired_alert') }}
        </va-alert>

        <va-alert v-if="ticket.status === 'open' && !showClaimOverlay" color="info" class="m-4" dense outline icon="info">
            {{ $t('tickets.chat.open_status_alert') }}
        </va-alert>
        
        <div class="flex-1 overflow-y-auto p-6" ref="messagesContainer" @scroll="handleScroll">
            <div v-if="isLoadingMessages" class="text-center py-2">
                <va-progress-circle indeterminate size="small" color="primary" />
            </div>

            <div v-if="asyncMessages.length === 0 && !isLoadingMessages" class="text-center text-gray-500 dark:text-gray-400 py-10">
                {{ $t('tickets.chat.no_messages') }}
            </div>

            <TicketMessageBubble v-for="msg in asyncMessages" :key="msg.id" :message="msg" />
        </div>

        <div class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 p-4">
            <form @submit.prevent="submitReply" class="flex flex-col gap-3">
                <div class="flex gap-3 items-end">
                    <div class="relative flex-1">
                        <div v-if="showMentionMenu" class="absolute bottom-full left-0 mb-2 w-64 max-h-48 overflow-y-auto bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl z-50">
                            <div class="px-3 py-2 text-xs font-semibold text-gray-500 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 flex justify-between">
                                <span>{{ $t('tickets.mentions.available_members') }}</span>
                                <va-progress-circle v-if="isLoadingMentions" indeterminate size="small" class="w-4 h-4" />
                            </div>
                            <div 
                                v-for="user in mentionableEntities" 
                                :key="user.id" 
                                @click="insertMention(user)"
                                class="px-3 py-2 text-sm cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/30 text-gray-700 dark:text-gray-200 flex items-center gap-2"
                            >
                                <va-icon name="alternate_email" size="small" color="secondary" />
                                {{ user.name }}
                                <span v-if="user.role === 'supporter' || user.role === 'admin'" class="text-xs text-blue-500 font-bold ml-auto">{{ $t('tickets.mentions.role_supporter') }}</span>
                                <span v-if="user.role === 'customer'" class="text-xs text-green-500 font-bold ml-auto">{{ $t('tickets.mentions.role_customer') }}</span>
                            </div>
                            <div v-if="mentionableEntities.length === 0 && !isLoadingMentions" class="px-3 py-4 text-center text-sm text-gray-500">
                                {{ $t('tickets.mentions.no_members') }}
                            </div>
                        </div>

                        <va-textarea
                            v-model="replyForm.message"
                            @keyup="handleKeyUp"
                            @click="updateCursor"
                            :placeholder="isInputDisabled ? $t('tickets.chat.input_disabled_placeholder') : $t('tickets.chat.input_placeholder')"
                            class="w-full chat-input"
                            :min-rows="2"
                            autosize
                            :error="!!replyForm.errors.message"
                            :disabled="isInputDisabled"
                        />
                    </div>
                    <va-button type="submit" color="primary" icon="send" :loading="replyForm.processing" :disabled="isSubmitDisabled">
                        {{ $t('common.actions.send') }}
                    </va-button>
                </div>
                <div class="flex items-center gap-2">
                    <va-file-upload 
                        v-model="replyForm.attachment" 
                        type="single" 
                        file-types=".pdf,.jpg,.jpeg,.png,.zip" 
                        :disabled="isInputDisabled" 
                        class="w-full max-w-sm" 
                        :uploadButtonText="$t('common.actions.attach_file')" 
                        :dropzoneText="$t('common.actions.drop_file')" 
                    />
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, toRef } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import TicketMessageBubble from '@/Components/Tickets/TicketMessageBubble.vue';
import { VaTextarea, VaButton, VaAlert, VaIcon, VaFileUpload, VaProgressCircle } from 'vuestic-ui';
import { useTicketSupport } from '@/Composables/useTicketSupport';

const props = defineProps({
    ticket: { type: Object, required: true },
    isTimeUp: Boolean,
    showClaimOverlay: Boolean,
    isInputDisabled: Boolean,
    isSubmitDisabled: Boolean
});

// Sync with legacy composable if needed for form submission
const ticketRef = toRef(props, 'ticket');
const { replyForm, submitReply } = useTicketSupport(ticketRef);

/**
 * Message Infinite Scroll Logic
 * Replaces the monolithic array load, querying pages asynchronously as the user scrolls up.
 */
const messagesContainer = ref(null);
const asyncMessages = ref([]);
const isLoadingMessages = ref(false);
const nextCursor = ref(null);

const fetchMessages = async (cursor = null) => {
    if (isLoadingMessages.value) return;
    isLoadingMessages.value = true;

    try {
        const url = route('api.tickets.messages', props.ticket.id);
        const response = await axios.get(url, { params: { cursor } });
        
        // Reverse because APIs usually return latest first, and chat displays oldest at top
        const newMessages = response.data.data.reverse();
        asyncMessages.value = [...newMessages, ...asyncMessages.value];
        nextCursor.value = response.data.next_cursor;

        // Auto-scroll to bottom on initial load
        if (!cursor) {
            setTimeout(() => {
                if (messagesContainer.value) {
                    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
                }
            }, 100);
        }
    } catch (error) {
        console.error("Error fetching messages", error);
    } finally {
        isLoadingMessages.value = false;
    }
};

const handleScroll = () => {
    if (messagesContainer.value.scrollTop === 0 && nextCursor.value) {
        fetchMessages(nextCursor.value);
    }
};

onMounted(() => {
    fetchMessages();
});

/**
 * Lazy Loading Mentions Logic
 * Replaces the O(N) frontend array filter. Uses an API endpoint via debounce proxy.
 */
const mentionableEntities = ref([]);
const showMentionMenu = ref(false);
const mentionSearch = ref('');
const cursorPosition = ref(0);
const isLoadingMentions = ref(false);

if (typeof replyForm.mentions === 'undefined') {
    replyForm.mentions = [];
}

let mentionTimeout = null;

const fetchMentions = async (query) => {
    if (!query || query.length < 2) return;
    
    isLoadingMentions.value = true;
    
    try {
        // Consults the global async search API
        const response = await axios.get(route('api.users.search'), { 
            params: { q: query, roles: ['supporter', 'admin'] } 
        });
        
        const users = response.data.data || response.data;
        
        // Always include the customer entity dynamically if not matched
        if (props.ticket.customer && !users.find(u => String(u.id) === String(props.ticket.customer.id))) {
            users.push({ id: String(props.ticket.customer.id), name: props.ticket.customer.name, role: 'customer' });
        } else if (props.ticket.sender_email) {
            users.push({ id: props.ticket.sender_email, name: props.ticket.sender_email, role: 'customer' });
        }
        
        mentionableEntities.value = users;
    } catch (error) {
        console.error("Failed to load mentions", error);
    } finally {
        isLoadingMentions.value = false;
    }
};

const updateCursor = (e) => {
    cursorPosition.value = e.target.selectionStart;
};

const handleKeyUp = (e) => {
    updateCursor(e);
    
    const textBeforeCursor = replyForm.message.substring(0, cursorPosition.value);
    const match = textBeforeCursor.match(/@([a-zA-Z0-9_À-ÿ.\-@]*)$/);
    
    if (match) {
        showMentionMenu.value = true;
        mentionSearch.value = match[1];
        
        // Debounce to prevent flooding the server
        clearTimeout(mentionTimeout);
        mentionTimeout = setTimeout(() => {
            fetchMentions(mentionSearch.value);
        }, 300);
        
    } else {
        showMentionMenu.value = false;
    }
};

const insertMention = (user) => {
    const textBefore = replyForm.message.substring(0, cursorPosition.value);
    const textAfter = replyForm.message.substring(cursorPosition.value);
    
    const textBeforeMention = textBefore.replace(/@[a-zA-Z0-9_À-ÿ.\-@]*$/, '');
    const mentionText = `@${user.name.replace(/\s+/g, '')} `;
    
    replyForm.message = textBeforeMention + mentionText + textAfter;
    
    if (!replyForm.mentions.includes(user.id)) {
        replyForm.mentions.push(user.id);
    }
    
    showMentionMenu.value = false;
};
</script>

<style scoped>
.chat-container { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
</style>