<template>
    <AppLayout>
        <Head :title="$t('tickets.show.page_title', { id: ticket.id, title: ticket.title })" />

        <div 
            v-if="showClaimOverlay" 
            class="fixed inset-0 z-50 flex items-center justify-center px-4"
            style="background-color: rgba(0, 0, 0, 0.4); backdrop-filter: blur(4px);"
        >
            <VaCard class="w-full max-w-md p-6 text-center shadow-xl border-t-4 border-primary">
                <VaCardContent>
                    <VaIcon name="lock_person" size="5rem" color="secondary" class="mb-6 opacity-75" />
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-3">{{ $t('tickets.show.view_only_mode') }}</h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-8 text-lg leading-relaxed">
                        {{ $t('tickets.show.claim_description') }}
                    </p>

                    <div class="flex flex-col gap-3">
                        <VaButton size="large" icon="person_add" @click="assignToMe" :loading="isAssigning" class="w-full font-bold py-3">
                            {{ $t('tickets.show.claim_button') }}
                        </VaButton>
                        <Link :href="route('tickets.index')">
                            <VaButton preset="secondary" color="secondary" size="small">{{ $t('common.actions.cancel_and_back') }}</VaButton>
                        </Link>
                    </div>
                </VaCardContent>
            </VaCard>
        </div>

        <div :class="{ 'blur-md pointer-events-none select-none transition-all duration-300': showClaimOverlay }">
            <div class="mb-4">
                <Link :href="route('tickets.index')" class="text-blue-600 dark:text-blue-400 hover:underline text-sm flex items-center gap-1 mb-2">
                    &larr; {{ $t('tickets.show.back_to_tickets') }}
                </Link>
                
                <div class="flex flex-col md:flex-row justify-between md:items-start gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 flex items-center gap-3">
                            #{{ ticket.id }} {{ ticket.title }}
                            <span v-if="ticket.source === 'email'"
                                  class="inline-flex items-center justify-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                                  :title="$t('tickets.received_via_email')">
                                📧 {{ $t('tickets.email') }}
                            </span>
                        </h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {{ $t('tickets.show.opened_by') }} 
                            <span v-if="ticket.customer" class="font-medium text-gray-700 dark:text-gray-300">{{ ticket.customer.name }}</span>
                            <span v-else class="font-medium text-gray-700 dark:text-gray-300">{{ ticket.sender_email }}</span>
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
                                {{ $t('tickets.show.manage_tags') }}
                            </va-button>
                        </div>
                    </div>
                    
                    <div class="flex flex-wrap items-center gap-3 mt-2 md:mt-0">
                        <TicketStatusBadge :status="ticket.status" />
                        <span v-if="ticket.assignee" class="text-sm bg-gray-100 dark:bg-gray-800 px-3 py-1 rounded-full text-gray-600 dark:text-gray-400 flex items-center gap-1">
                            <VaIcon name="support_agent" size="small" color="secondary" />
                            {{ $t('tickets.show.assigned_to') }}: {{ ticket.assignee.name }}
                        </span>
                        
                        <SupportTimeDisplay v-if="ticket.customer" :seconds="currentRemainingSeconds" />

                        <va-dropdown placement="bottom-end" v-if="!isSupporter || hasWritePermission">
                            <template #anchor>
                                <va-button preset="secondary" icon="settings" size="small">{{ $t('common.actions.actions') }}</va-button>
                            </template>
                            <va-dropdown-content class="p-0">
                                <va-list>
                                    <va-list-item v-if="!isSupporter && ticket.status !== 'resolved' && ticket.status !== 'closed'" @click="updateStatus('resolved')" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700">
                                        <va-list-item-section icon><va-icon name="check_circle" color="success" /></va-list-item-section>
                                        <va-list-item-section>{{ $t('tickets.actions.mark_resolved') }}</va-list-item-section>
                                    </va-list-item>

                                    <template v-if="hasWritePermission">
                                        <va-list-item @click="updateStatus('open')" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"><va-list-item-section>{{ $t('tickets.actions.reopen') }}</va-list-item-section></va-list-item>
                                        <va-list-item @click="updateStatus('in_progress')" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"><va-list-item-section>{{ $t('tickets.actions.set_in_progress') }}</va-list-item-section></va-list-item>
                                        <va-list-item @click="updateStatus('resolved')" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"><va-list-item-section class="text-blue-600 dark:text-blue-400">{{ $t('tickets.actions.mark_resolved') }}</va-list-item-section></va-list-item>
                                        <va-list-item @click="updateStatus('closed')" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"><va-list-item-section class="text-red-600 dark:text-red-400">{{ $t('tickets.actions.close_ticket') }}</va-list-item-section></va-list-item>
                                        <va-list-item @click="confirmingDeletion = true" class="cursor-pointer hover:bg-red-50 dark:hover:bg-red-900/20 border-t border-gray-100 dark:border-gray-700">
                                            <va-list-item-section icon><va-icon name="delete" color="danger" /></va-list-item-section>
                                            <va-list-item-section class="text-red-600 dark:text-red-400">{{ $t('tickets.actions.delete_ticket') }}</va-list-item-section>
                                        </va-list-item>
                                    </template>
                                </va-list>
                            </va-dropdown-content>
                        </va-dropdown>
                    </div>
                </div>
            </div>

            <div class="chat-container flex flex-col bg-gray-100 dark:bg-gray-800/50 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden h-[600px] mt-4">
                <va-alert v-if="ticket.customer && isTimeUp" color="danger" class="m-4" dense icon="block">
                    {{ $t('tickets.chat.time_expired_alert') }}
                </va-alert>

                <va-alert v-if="ticket.status === 'open' && !showClaimOverlay" color="info" class="m-4" dense outline icon="info">
                    {{ $t('tickets.chat.open_status_alert') }}
                </va-alert>
                
                <div class="flex-1 overflow-y-auto p-6" ref="messagesContainer">
                    <div v-if="localMessages.length === 0" class="text-center text-gray-500 dark:text-gray-400 py-10">{{ $t('tickets.chat.no_messages') }}</div>
                    <TicketMessageBubble v-for="msg in localMessages" :key="msg.id" :message="msg" />
                </div>

                <div class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 p-4">
                    <form @submit.prevent="submitReply" class="flex flex-col gap-3">
                        <div class="flex gap-3 items-end">
                            <div class="relative flex-1">
                                <div v-if="showMentionMenu" class="absolute bottom-full left-0 mb-2 w-64 max-h-48 overflow-y-auto bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl z-50">
                                    <div class="px-3 py-2 text-xs font-semibold text-gray-500 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                                        {{ $t('tickets.mentions.available_members') }}
                                    </div>
                                    <div 
                                        v-for="user in filteredMentions" 
                                        :key="user.id" 
                                        @click="insertMention(user)"
                                        class="px-3 py-2 text-sm cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-900/30 text-gray-700 dark:text-gray-200 flex items-center gap-2"
                                    >
                                        <va-icon name="alternate_email" size="small" color="secondary" />
                                        {{ user.name }}
                                        <span v-if="user.role === 'supporter' || user.role === 'admin'" class="text-xs text-blue-500 font-bold ml-auto">{{ $t('tickets.mentions.role_supporter') }}</span>
                                        <span v-if="user.role === 'customer'" class="text-xs text-green-500 font-bold ml-auto">{{ $t('tickets.mentions.role_customer') }}</span>
                                    </div>
                                    <div v-if="filteredMentions.length === 0" class="px-3 py-4 text-center text-sm text-gray-500">
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
        </div>

        <va-modal
            v-model="isTagModalOpen"
            :title="$t('tickets.tags.manage_title')"
            :ok-text="$t('common.actions.save_changes')"
            :cancel-text="$t('common.actions.cancel')"
            @ok="saveTags"
        >
            <div class="py-4">
                <va-select
                    v-model="tagForm.tags"
                    :options="tagOptions"
                    value-by="value"
                    text-by="text"
                    :label="$t('tickets.tags.select_categories')"
                    multiple
                    clearable
                    searchable
                    class="w-full"
                >
                    <template #content="{ valueArray }">
                        <div class="flex gap-1 flex-wrap items-center">
                            <span v-if="valueArray.length === 0" class="text-gray-400">{{ $t('tickets.tags.select_placeholder') }}</span>
                            <template v-else>
                                <span 
                                    v-for="(val, index) in valueArray.slice(0, 3)" 
                                    :key="index"
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    {{ resolveTagName(val) }}{{ index < Math.min(valueArray.length, 3) - 1 ? ', ' : '' }}
                                </span>
                                
                                <span 
                                    v-if="valueArray.length > 3" 
                                    class="ml-1 px-1.5 py-0.5 rounded text-xs font-bold bg-primary text-white"
                                >
                                    +{{ valueArray.length - 3 }}
                                </span>
                            </template>
                        </div>
                    </template>
                </va-select>
            </div>
        </va-modal>

        <va-modal 
            v-model="confirmingDeletion" 
            :title="$t('tickets.delete.title')" 
            :message="$t('tickets.delete.confirmation_message')" 
            :ok-text="$t('common.actions.confirm_deletion')" 
            :cancel-text="$t('common.actions.cancel')" 
            state="danger" 
            @ok="deleteTicket"
        >
            <div class="mt-4">
                <va-input 
                    v-model="deleteForm.password" 
                    type="password" 
                    :label="$t('tickets.delete.password_label')" 
                    :placeholder="$t('tickets.delete.password_placeholder')" 
                    :error="!!deleteForm.errors.password" 
                    :error-messages="deleteForm.errors.password" 
                    class="w-full" 
                    @keyup.enter="deleteTicket" 
                />
            </div>
        </va-modal>
    </AppLayout>
</template>

<script setup>
import { ref, toRef, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
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
    availableTags: { type: Array, default: () => [] },
    mentionableUsers: { type: Array, default: () => [] } // Fetched globally from Controller
});

const ticketRef = toRef(props, 'ticket');

const {
    isSupporter, currentRemainingSeconds, localMessages, messagesContainer,
    isAssigning, replyForm, deleteForm, confirmingDeletion, isTimeUp,
    hasWritePermission, showClaimOverlay, isInputDisabled, isSubmitDisabled,
    submitReply, assignToMe, updateStatus, deleteTicket
} = useTicketSupport(ticketRef);

const { t } = useI18n();

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

/**
 * Retrieves the full tag object from the available list to display properties like color and name.
 * Uses translations to return a safe fallback if the tag is no longer available.
 * @param {string|number} id - The ID of the requested tag.
 * @returns {Object} The complete tag object or a fallback generic object.
 */
const getDetailedTag = (id) => {
    return props.availableTags.find(t => String(t.id) === String(id)) || { name: t('tickets.tags.unknown'), color: '#ccc' };
};

/**
 * Safely resolves the tag name irrespective of the format emitted by the select component.
 * Prevents "Unknown" display bugs when UI frameworks modify the emitted payload format.
 * @param {Object|string|number} val - The tag payload.
 * @returns {string} The resolved tag name.
 */
const resolveTagName = (val) => {
    if (typeof val === 'object' && val !== null) {
        return val.text || val.name || getDetailedTag(val.value || val.id).name;
    }
    return getDetailedTag(val).name;
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

// --- MENTIONS LOGIC START ---
const showMentionMenu = ref(false);
const mentionSearch = ref('');
const cursorPosition = ref(0);

if (typeof replyForm.mentions === 'undefined') {
    replyForm.mentions = [];
}

/**
 * SRP Focus: We compute the strictly available mentionable participants locally.
 * It merges the globally fetched team members (mentionableUsers from Controller) 
 * with the external/internal ticket customer.
 */
const mentionableEntities = computed(() => {
    // Start with all possible supporters and admins
    const users = [...props.mentionableUsers];
    
    // Append customer or external email if they aren't already matched
    if (props.ticket.customer && !users.find(u => String(u.id) === String(props.ticket.customer.id))) {
        users.push({ id: String(props.ticket.customer.id), name: props.ticket.customer.name, role: 'customer' });
    } else if (props.ticket.sender_email) {
        users.push({ id: props.ticket.sender_email, name: props.ticket.sender_email, role: 'customer' });
    }
    
    return users;
});

/**
 * Computes the filtered list of mentionable users based on current search input.
 */
const filteredMentions = computed(() => {
    if (!mentionSearch.value) return mentionableEntities.value;
    const search = mentionSearch.value.toLowerCase();
    return mentionableEntities.value.filter(u => u.name.toLowerCase().includes(search));
});

/**
 * Updates the local state storing the cursor position inside the textarea.
 */
const updateCursor = (e) => {
    cursorPosition.value = e.target.selectionStart;
};

/**
 * Parses user input to handle the floating mention menu logic.
 * Matches standard names AND email patterns without breaking.
 */
const handleKeyUp = (e) => {
    updateCursor(e);
    
    const textBeforeCursor = replyForm.message.substring(0, cursorPosition.value);
    const match = textBeforeCursor.match(/@([a-zA-Z0-9_À-ÿ.\-@]*)$/);
    
    if (match) {
        showMentionMenu.value = true;
        mentionSearch.value = match[1];
    } else {
        showMentionMenu.value = false;
    }
};

/**
 * Inserts the chosen user's name or email directly into the textarea.
 * It also pushes the entity's ID to the form mentions array for backend parsing and permissions.
 */
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
// --- MENTIONS LOGIC END ---
</script>

<style scoped>
.chat-container { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
.blur-md { backdrop-filter: blur(12px); }
</style>