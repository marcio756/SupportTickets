<template>
    <div :class="['message-wrapper mb-4 flex items-start gap-3', isMine ? 'flex-row-reverse' : '']">
        
        <UserAvatar :user="message.sender" size="48px" />

        <div 
            :class="[
                'message-bubble p-4 rounded-lg max-w-[70%]',
                isMine ? 'bg-blue-600 text-white rounded-br-none' : 'bg-white border border-gray-200 text-gray-800 rounded-bl-none shadow-sm'
            ]"
        >
            <div class="flex justify-between items-baseline mb-1">
                <span class="font-bold text-sm" :class="isMine ? 'text-blue-100' : 'text-gray-900'">
                    {{ message.sender.name }}
                </span>
                <span class="text-xs ml-4" :class="isMine ? 'text-blue-200' : 'text-gray-400'">
                    {{ formattedDate }}
                </span>
            </div>
            
            <p v-if="message.message" class="text-sm whitespace-pre-wrap leading-relaxed">{{ message.message }}</p>
            
            <div v-if="message.attachment_path" class="mt-2 pt-2 flex items-center gap-2" :class="[message.message ? 'border-t border-opacity-20' : '']">
                <va-icon name="attach_file" size="small" />
                <a :href="attachmentUrl" target="_blank" class="text-sm underline hover:opacity-80">
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

/**
 * Determines if the message was sent by the currently authenticated user
 * to align the bubble to the right side.
 */
const page = usePage();
const isMine = computed(() => {
    return props.message.user_id === page.props.auth.user.id;
});

/**
 * Computes the correct URL for the attachment.
 */
const attachmentUrl = computed(() => {
    if (!props.message.attachment_path) return null;
    return props.message.attachment_path.startsWith('http') 
        ? props.message.attachment_path 
        : `/storage/${props.message.attachment_path}`;
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
</script>

<style scoped>
.message-bubble {
    transition: all 0.2s ease;
}
</style>