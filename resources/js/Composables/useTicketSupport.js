import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';

export function useTicketSupport(ticket) {
    const page = usePage();
    const currentUser = page.props.auth.user;
    const isSupporter = currentUser.role === 'supporter' || currentUser.role === 'admin';

    const currentRemainingSeconds = ref(ticket.value.customer.daily_support_seconds);
    const localMessages = ref([...ticket.value.messages]);
    const messagesContainer = ref(null);
    const isAssigning = ref(false);
    const confirmingDeletion = ref(false);
    
    const replyForm = useForm({ 
        message: '',
        attachment: []
    });

    const deleteForm = useForm({
        password: '',
    });

    let heartbeatInterval = null;

    const isTimeUp = computed(() => currentRemainingSeconds.value <= 0);

    const isAssignedSupporter = computed(() => {
        return isSupporter && ticket.value.assignee && ticket.value.assignee.id === currentUser.id;
    });

    const showClaimOverlay = computed(() => {
        if (ticket.value.status === 'closed' || ticket.value.status === 'resolved') {
            return false;
        }
        return isSupporter && !isAssignedSupporter.value;
    });

    /**
     * Updated: Lock input if status is NOT 'in_progress'
     */
    const isInputDisabled = computed(() => {
        if (isTimeUp.value || ticket.value.status === 'closed' || ticket.value.status === 'resolved') {
            return true;
        }
        if (ticket.value.status !== 'in_progress') {
            return true;
        }
        if (isSupporter && !isAssignedSupporter.value) {
            return true;
        }
        return false;
    });

    const isSubmitDisabled = computed(() => {
        if (isInputDisabled.value) return true;
        const hasMessage = replyForm.message && replyForm.message.trim().length > 0;
        const hasFile = (Array.isArray(replyForm.attachment) && replyForm.attachment.length > 0) || (replyForm.attachment instanceof File);
        return !hasMessage && !hasFile;
    });

    /**
     * Updated: Only run heartbeat if status is 'in_progress'
     */
    const shouldRunHeartbeat = computed(() => {
        return isAssignedSupporter.value && ticket.value.status === 'in_progress' && !isTimeUp.value;
    });

    const scrollToBottom = async () => {
        await nextTick();
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    };

    const submitReply = () => {
        if (isSubmitDisabled.value) return;
        replyForm.clearErrors('attachment');
        let fileToUpload = Array.isArray(replyForm.attachment) ? replyForm.attachment[0] : replyForm.attachment;
        if (fileToUpload && fileToUpload.file instanceof File) fileToUpload = fileToUpload.file;

        replyForm.transform((data) => ({
            message: data.message || '',
            attachment: fileToUpload,
        })).post(route('tickets.messages.store', ticket.value.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                replyForm.reset('message', 'attachment');
                replyForm.attachment = [];
            }
        });
    };

    const assignToMe = () => {
        isAssigning.value = true;
        router.patch(route('tickets.assign', ticket.value.id), {}, {
            preserveScroll: true,
            onFinish: () => { isAssigning.value = false; }
        });
    };

    const updateStatus = (newStatus) => {
        router.patch(route('tickets.update-status', ticket.value.id), { status: newStatus }, { preserveScroll: true });
    };

    const deleteTicket = () => {
        deleteForm.delete(route('tickets.destroy', ticket.value.id), {
            preserveScroll: true,
            onSuccess: () => { confirmingDeletion.value = false; },
            onFinish: () => deleteForm.reset(),
        });
    };

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
        isAssignedSupporter,
        showClaimOverlay,
        isInputDisabled,
        isSubmitDisabled,
        submitReply,
        assignToMe,
        updateStatus,
        deleteTicket
    };
}