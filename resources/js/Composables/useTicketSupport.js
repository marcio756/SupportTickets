/**
 * resources/js/Composables/useTicketSupport.js
 * * Encapsulates all business logic, WebSocket connections, and state management 
 * for the ticket support chat. Keeps the UI components clean and focused on design.
 */
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';

export function useTicketSupport(ticket) {
    const page = usePage();
    const currentUser = page.props.auth.user;
    
    // Determine user role and privileges
    const isSupporter = currentUser.role === 'supporter' || currentUser.role === 'admin';

    // Reactive State
    // As injeções reativas baseiam-se em ticket.value para acompanhar o estado do Inertia
    const currentRemainingSeconds = ref(ticket.value.customer.daily_support_seconds);
    const localMessages = ref([...ticket.value.messages]);
    const messagesContainer = ref(null);
    const isAssigning = ref(false);
    
    // Forms
    const replyForm = useForm({ 
        message: '',
        attachment: []
    });

    // Internal Timers
    let heartbeatInterval = null;

    // Computed Properties
    const isTimeUp = computed(() => currentRemainingSeconds.value <= 0);

    /**
     * Validates if the current logged-in user is exactly the supporter assigned to this ticket.
     * @returns {Boolean}
     */
    const isAssignedSupporter = computed(() => {
        return isSupporter && ticket.value.assignee && ticket.value.assignee.id === currentUser.id;
    });

    /**
     * Determines if the "Claim Ticket" blocking overlay should be visible.
     * @returns {Boolean}
     */
    const showClaimOverlay = computed(() => {
        if (ticket.value.status === 'closed' || ticket.value.status === 'resolved') {
            return false;
        }
        return isSupporter && !isAssignedSupporter.value;
    });

    /**
     * Computes if the chat input should be locked.
     * @returns {Boolean}
     */
    const isInputDisabled = computed(() => {
        if (isTimeUp.value || ticket.value.status === 'closed' || ticket.value.status === 'resolved') {
            return true;
        }
        if (isSupporter && !isAssignedSupporter.value) {
            return true;
        }
        return false;
    });

    /**
     * Computes whether the submit button should be disabled based on input and file presence.
     * @returns {Boolean}
     */
    const isSubmitDisabled = computed(() => {
        if (isInputDisabled.value) return true;
        
        const hasMessage = replyForm.message && replyForm.message.trim().length > 0;
        
        let hasFile = false;
        if (Array.isArray(replyForm.attachment) && replyForm.attachment.length > 0) {
            hasFile = true;
        } else if (replyForm.attachment instanceof File) {
            hasFile = true;
        } else if (replyForm.attachment && replyForm.attachment.file) {
            hasFile = true;
        }

        return !hasMessage && !hasFile;
    });

    /**
     * Computes whether the conditions are met for the heartbeat (time deduction) to be running.
     * @returns {Boolean}
     */
    const shouldRunHeartbeat = computed(() => {
        return isAssignedSupporter.value && ticket.value.status === 'open' && !isTimeUp.value;
    });

    // Methods
    const scrollToBottom = async () => {
        await nextTick();
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    };

    const submitReply = () => {
        if (isSubmitDisabled.value) return;

        replyForm.clearErrors('attachment');

        let fileToUpload = null;

        // Extrair em segurança o ficheiro do Vuestic
        if (Array.isArray(replyForm.attachment) && replyForm.attachment.length > 0) {
            fileToUpload = replyForm.attachment[0];
        } else if (replyForm.attachment && !Array.isArray(replyForm.attachment)) {
            fileToUpload = replyForm.attachment;
        }

        // Se o Vuestic o embrulhou num objeto personalizado, extraímos o File nativo
        if (fileToUpload && fileToUpload.file instanceof File) {
            fileToUpload = fileToUpload.file;
        }

        // Validar limite de 10MB do lado do cliente
        if (fileToUpload) {
            const maxSizeInBytes = 10 * 1024 * 1024;
            if (fileToUpload.size > maxSizeInBytes) {
                replyForm.setError('attachment', 'The selected file is too large. Maximum allowed size is 10MB.');
                return;
            }
        }

        replyForm.transform((data) => {
            return {
                message: data.message || '',
                attachment: fileToUpload,
            };
        }).post(route('tickets.messages.store', ticket.value.id), {
            forceFormData: true, // Obriga o envio em multipart/form-data para o Laravel reconhecer o ficheiro
            preserveScroll: true,
            onSuccess: () => {
                replyForm.reset('message', 'attachment');
                replyForm.attachment = []; // Limpar visualmente o va-file-upload
            }
        });
    };

    const assignToMe = () => {
        isAssigning.value = true;
        router.patch(route('tickets.assign', ticket.value.id), {}, {
            preserveScroll: true,
            onFinish: () => {
                isAssigning.value = false;
            }
        });
    };

    const updateStatus = (newStatus) => {
        router.patch(route('tickets.update-status', ticket.value.id), {
            status: newStatus
        }, {
            preserveScroll: true
        });
    };

    /**
     * Starts the heartbeat interval to deduct support time.
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
                    }).catch(error => console.error("Heartbeat failed", error));
            }
        }, 5000);
    };

    /**
     * Safely stops and clears the heartbeat interval.
     */
    const stopHeartbeat = () => {
        if (heartbeatInterval) {
            clearInterval(heartbeatInterval);
            heartbeatInterval = null;
        }
    };

    // Watchers
    watch(() => ticket.value.messages, (newMessages) => {
        localMessages.value = [...newMessages];
        scrollToBottom();
    }, { deep: true });

    watch(shouldRunHeartbeat, (run) => {
        if (run) {
            startHeartbeat();
        } else {
            stopHeartbeat();
        }
    });

    // Lifecycle Hooks
    onMounted(() => {
        scrollToBottom();

        if (window.Echo) {
            window.Echo.private(`ticket.${ticket.value.id}`)
                .listen('SupportTimeUpdated', (e) => {
                    currentRemainingSeconds.value = e.remainingSeconds;
                })
                .listen('TicketMessageCreated', (e) => {
                    const exists = localMessages.value.find(m => m.id === e.message.id);
                    if (!exists) {
                        localMessages.value.push(e.message);
                        scrollToBottom();
                    }
                });
        }

        if (shouldRunHeartbeat.value) {
            startHeartbeat();
        }
    });

    onUnmounted(() => {
        stopHeartbeat();
        if (window.Echo) window.Echo.leave(`ticket.${ticket.value.id}`);
    });

    // Expose data and methods to the component
    return {
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
    };
}