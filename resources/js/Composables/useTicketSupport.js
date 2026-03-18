import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';

/**
 * Encapsulates the reactive state, form handling, and WebSocket interactions
 * needed for ticket chat and support time tracking.
 *
 * @param {import('vue').Ref<Object>} ticket - The reactive reference to the ticket object.
 * @returns {Object} Reactive variables and control functions for the UI.
 */
export function useTicketSupport(ticket) {
    const page = usePage();
    const currentUser = page.props.auth.user;
    const isSupporter = currentUser.role === 'supporter' || currentUser.role === 'admin';

    const currentRemainingSeconds = ref(ticket.value.customer ? ticket.value.customer.daily_support_seconds : 0);
    const localMessages = ref([...ticket.value.messages]);
    const messagesContainer = ref(null);
    const isAssigning = ref(false);
    const confirmingDeletion = ref(false);
    
    const replyForm = useForm({ 
        message: '',
        attachment: [],
        mentions: []
    });

    const deleteForm = useForm({
        password: '',
    });

    let heartbeatInterval = null;

    /**
     * @type {import('vue').ComputedRef<boolean>}
     */
    const isTimeUp = computed(() => {
        // Core fix: Email users (without account) have no time restriction
        if (!ticket.value.customer) return false;
        return currentRemainingSeconds.value <= 0;
    });

    /**
     * Computes whether the active user has explicit write permission.
     * It checks if they are the primary assignee OR a registered participant (mentioned).
     * @type {import('vue').ComputedRef<boolean>}
     */
    const hasWritePermission = computed(() => {
        if (!isSupporter) return false;
        if (ticket.value.assignee && ticket.value.assignee.id === currentUser.id) return true;
        if (ticket.value.participants && ticket.value.participants.some(p => p.id === currentUser.id)) return true;
        return false;
    });

    /**
     * @type {import('vue').ComputedRef<boolean>}
     */
    const showClaimOverlay = computed(() => {
        if (ticket.value.status === 'closed' || ticket.value.status === 'resolved') {
            return false;
        }
        // If they already have permission (assignee or mentioned), no need to claim
        return isSupporter && !hasWritePermission.value;
    });

    /**
     * Blocks input if status is NOT 'in_progress', time is up, or the user lacks write access.
     * @type {import('vue').ComputedRef<boolean>}
     */
    const isInputDisabled = computed(() => {
        if (isTimeUp.value || ticket.value.status === 'closed' || ticket.value.status === 'resolved') {
            return true;
        }
        if (ticket.value.status !== 'in_progress') {
            return true;
        }
        if (isSupporter && !hasWritePermission.value) {
            return true;
        }
        return false;
    });

    /**
     * @type {import('vue').ComputedRef<boolean>}
     */
    const isSubmitDisabled = computed(() => {
        if (isInputDisabled.value) return true;
        const hasMessage = replyForm.message && replyForm.message.trim().length > 0;
        const hasFile = (Array.isArray(replyForm.attachment) && replyForm.attachment.length > 0) || (replyForm.attachment instanceof File);
        return !hasMessage && !hasFile;
    });

    /**
     * Determines if the automatic time deduction ping should run.
     * @type {import('vue').ComputedRef<boolean>}
     */
    const shouldRunHeartbeat = computed(() => {
        if (!ticket.value.customer) return false;
        // Allows any participant writing in the chat to deduct time
        return hasWritePermission.value && ticket.value.status === 'in_progress' && !isTimeUp.value;
    });

    /**
     * Scrolls the message container wrapper to the bottom.
     * @returns {Promise<void>}
     */
    const scrollToBottom = async () => {
        await nextTick();
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    };

    /**
     * Submits a new reply message.
     * @returns {void}
     */
    const submitReply = () => {
        if (isSubmitDisabled.value) return;
        replyForm.clearErrors('attachment');
        let fileToUpload = Array.isArray(replyForm.attachment) ? replyForm.attachment[0] : replyForm.attachment;
        if (fileToUpload && fileToUpload.file instanceof File) fileToUpload = fileToUpload.file;

        replyForm.transform((data) => ({
            message: data.message || '',
            attachment: fileToUpload,
            mentions: data.mentions
        })).post(route('tickets.messages.store', ticket.value.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                replyForm.reset('message', 'attachment');
                replyForm.attachment = [];
                replyForm.mentions = [];
            }
        });
    };

    /**
     * Claims the ticket for the authenticated supporter.
     * @returns {void}
     */
    const assignToMe = () => {
        isAssigning.value = true;
        router.patch(route('tickets.assign', ticket.value.id), {}, {
            preserveScroll: true,
            onFinish: () => { isAssigning.value = false; }
        });
    };

    /**
     * Updates the ticket status via local API.
     * @param {string} newStatus The new ticket status.
     * @returns {void}
     */
    const updateStatus = (newStatus) => {
        router.patch(route('tickets.update-status', ticket.value.id), { status: newStatus }, { preserveScroll: true });
    };

    /**
     * Executes soft-delete or hard-delete of the ticket.
     * @returns {void}
     */
    const deleteTicket = () => {
        deleteForm.delete(route('tickets.destroy', ticket.value.id), {
            preserveScroll: true,
            onSuccess: () => { confirmingDeletion.value = false; },
            onFinish: () => deleteForm.reset(),
        });
    };

    /**
     * Starts the heartbeat interval to deduct support time.
     * @returns {void}
     */
    const startHeartbeat = () => {
        if (heartbeatInterval) return;
        heartbeatInterval = setInterval(() => {
            if (document.visibilityState === 'visible') {
                axios.post(route('tickets.tick-time', ticket.value.id))
                    .then(response => {
                        if (response.data.remaining_seconds !== undefined) {
                            currentRemainingSeconds.value = response.data.remaining_seconds;
                        }
                    }).catch(() => stopHeartbeat());
            }
        }, 5000);
    };

    /**
     * Stops the actively running heartbeat interval.
     * @returns {void}
     */
    const stopHeartbeat = () => {
        if (heartbeatInterval) {
            clearInterval(heartbeatInterval);
            heartbeatInterval = null;
        }
    };

    watch(() => ticket.value.messages, (newMessages) => {
        localMessages.value = [...newMessages];
        scrollToBottom();
    }, { deep: true });

    watch(shouldRunHeartbeat, (run) => {
        if (run) startHeartbeat(); else stopHeartbeat();
    });

    onMounted(() => {
        scrollToBottom();
        if (window.Echo) {
            window.Echo.private(`ticket.${ticket.value.id}`)
                .listen('SupportTimeUpdated', (e) => { currentRemainingSeconds.value = e.remainingSeconds; })
                .listen('TicketMessageCreated', (e) => {
                    if (!localMessages.value.find(m => m.id === e.message.id)) {
                        localMessages.value.push(e.message);
                        scrollToBottom();
                    }
                });
        }
        if (shouldRunHeartbeat.value) startHeartbeat();
    });

    onUnmounted(() => {
        stopHeartbeat();
        if (window.Echo) window.Echo.leave(`ticket.${ticket.value.id}`);
    });

    return {
        isSupporter,
        currentRemainingSeconds,
        localMessages,
        messagesContainer,
        isAssigning,
        replyForm,
        deleteForm,
        confirmingDeletion,
        isTimeUp,
        hasWritePermission, // Exporting the new unified permission flag
        showClaimOverlay,
        isInputDisabled,
        isSubmitDisabled,
        submitReply,
        assignToMe,
        updateStatus,
        deleteTicket
    };
}