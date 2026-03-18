<template>
    <div :class="['message-wrapper mb-4 flex items-start gap-3', isMine ? 'flex-row-reverse' : '']">
        
        <template v-if="message.sender">
            <UserAvatar :user="message.sender" size="48px" />
        </template>
        <template v-else>
            <div class="flex items-center justify-center shrink-0 min-w-[48px] w-[48px] min-h-[48px] h-[48px] rounded-full bg-gray-200 text-gray-500 overflow-hidden shadow-sm" title="Mensagem via Email">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                </svg>
            </div>
        </template>

        <div 
            :class="[
                'message-bubble p-4 rounded-lg max-w-[70%]',
                isMine ? 'bg-blue-600 text-white rounded-br-none' : 'bg-white border border-gray-200 text-gray-800 rounded-bl-none shadow-sm'
            ]"
        >
            <div class="flex justify-between items-baseline mb-1">
                <span class="font-bold text-sm" :class="isMine ? 'text-blue-100' : 'text-gray-900'">
                    {{ message.sender ? message.sender.name : message.sender_email }}
                </span>
                <span class="text-xs ml-4" :class="isMine ? 'text-blue-200' : 'text-gray-400'">
                    {{ formattedDate }}
                </span>
            </div>
            
            <p v-if="message.message" class="text-sm whitespace-pre-wrap leading-relaxed" v-html="formattedMessageContent"></p>
            
            <div v-if="message.attachment_url" class="mt-2 pt-2 flex items-center gap-2" :class="[message.message ? 'border-t border-opacity-20' : '']">
                <va-icon name="attach_file" size="small" />
                <a :href="message.attachment_url" target="_blank" class="text-sm underline hover:opacity-80">
                    View Attachment
                </a>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { VaIcon } from 'vuestic-ui';
import UserAvatar from '@/Components/Common/UserAvatar.vue';

/**
 * Defines the strict props for the bubble.
 */
const props = defineProps({
    message: {
        type: Object,
        required: true,
    }
});

const page = usePage();

/**
 * Determines if the message was sent by the currently authenticated user
 * to align the bubble to the right side. Safely checks for null user_id.
 */
const isMine = computed(() => {
    return props.message.user_id && props.message.user_id === page.props.auth.user.id;
});

/**
 * Formats the timestamp nicely.
 */
const formattedDate = computed(() => {
    const date = new Date(props.message.created_at);
    return date.toLocaleString([], { 
        month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' 
    });
});

/**
 * Processes the message text to highlight mentions.
 * Identifies patterns starting with @ followed by alphanumeric characters or complete email formats.
 * Also performs sanitization replacing HTML entities to prevent XSS vulnerabilities.
 */
const formattedMessageContent = computed(() => {
    if (!props.message.message) return '';
    
    // Replace html tags to avoid XSS injections since we are using v-html
    const escapedMessage = props.message.message
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");

    // Match @Name or @Email formats to recreate the Discord pings aesthetic
    return escapedMessage.replace(/@([a-zA-Z0-9_À-ÿ.-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}|[a-zA-Z0-9_À-ÿ]+)/g, '<span class="text-blue-600 dark:text-blue-400 font-bold bg-blue-50 dark:bg-blue-900/30 px-1 py-0.5 rounded cursor-pointer hover:underline">@$1</span>');
});
</script>

<style scoped>
.message-bubble {
    transition: all 0.2s ease;
}
</style>