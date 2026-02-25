import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';

/**
 * Encapsula o estado reativo, manipulação de formulários e interações WebSocket
 * necessários para o chat de tickets e acompanhamento de tempo de suporte.
 *
 * @param {import('vue').Ref<Object>} ticket - A referência reativa ao objeto do ticket.
 * @returns {Object} Variáveis reativas e funções de controlo para a UI.
 */
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

    /**
     * @type {import('vue').ComputedRef<boolean>}
     */
    const isTimeUp = computed(() => currentRemainingSeconds.value <= 0);

    /**
     * @type {import('vue').ComputedRef<boolean>}
     */
    const isAssignedSupporter = computed(() => {
        return isSupporter && ticket.value.assignee && ticket.value.assignee.id === currentUser.id;
    });

    /**
     * @type {import('vue').ComputedRef<boolean>}
     */
    const showClaimOverlay = computed(() => {
        if (ticket.value.status === 'closed' || ticket.value.status === 'resolved') {
            return false;
        }
        return isSupporter && !isAssignedSupporter.value;
    });

    /**
     * Bloqueia o input se o estado NÃO for 'in_progress', se o tempo esgotou ou se o utilizador não estiver atribuído.
     * @type {import('vue').ComputedRef<boolean>}
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
     * Determina se o ping automático de dedução de tempo deve ser despachado.
     * @type {import('vue').ComputedRef<boolean>}
     */
    const shouldRunHeartbeat = computed(() => {
        return isAssignedSupporter.value && ticket.value.status === 'in_progress' && !isTimeUp.value;
    });

    /**
     * Rola o scroll do contentor de mensagens para o fundo.
     * @returns {Promise<void>}
     */
    const scrollToBottom = async () => {
        await nextTick();
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    };

    /**
     * Submete uma nova mensagem de resposta no ticket.
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
        })).post(route('tickets.messages.store', ticket.value.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                replyForm.reset('message', 'attachment');
                replyForm.attachment = [];
            }
        });
    };

    /**
     * Reivindica o ticket para o supporter autenticado.
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
     * Atualiza o estado do ticket via API local.
     * @param {string} newStatus O novo estado do ticket.
     * @returns {void}
     */
    const updateStatus = (newStatus) => {
        router.patch(route('tickets.update-status', ticket.value.id), { status: newStatus }, { preserveScroll: true });
    };

    /**
     * Executa o soft-delete ou hard-delete do ticket.
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
     * Inicia o intervalo de heartbeat para dedução de tempo de suporte.
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
     * Para o intervalo de heartbeat ativamente em execução.
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