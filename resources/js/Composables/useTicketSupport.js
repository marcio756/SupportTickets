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
    const currentRemainingSeconds = ref(ticket.customer.daily_support_seconds);
    const localMessages = ref([...ticket.messages]);
    const messagesContainer = ref(null);
    const isAssigning = ref(false);
    
    // Forms
    const replyForm = useForm({ message: '' });

    // Internal Timers
    let heartbeatInterval = null;

    // Computed Properties
    const isTimeUp = computed(() => currentRemainingSeconds.value <= 0);

    /**
     * Validates if the current logged-in user is exactly the supporter assigned to this ticket.
     * @returns {Boolean}
     */
    const isAssignedSupporter = computed(() => {
        return isSupporter && ticket.assignee && ticket.assignee.id === currentUser.id;
    });

    /**
     * Determines if the "Claim Ticket" blocking overlay should be visible.
     * @returns {Boolean}
     */
    const showClaimOverlay = computed(() => {
        if (ticket.status === 'closed' || ticket.status === 'resolved') {
            return false;
        }
        return isSupporter && !isAssignedSupporter.value;
    });

    /**
     * Computes if the chat input should be locked.
     * @returns {Boolean}
     */
    const isInputDisabled = computed(() => {
        if (isTimeUp.value || ticket.status === 'closed' || ticket.status === 'resolved') {
            return true;
        }
        if (isSupporter && !isAssignedSupporter.value) {
            return true;
        }
        return false;
    });

    /**
     * Computes whether the conditions are met for the heartbeat (time deduction) to be running.
     * @returns {Boolean}
     */
    const shouldRunHeartbeat = computed(() => {
        return isAssignedSupporter.value && ticket.status === 'open' && !isTimeUp.value;
    });

    // Methods
    const scrollToBottom = async () => {
        await nextTick();
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    };

    const submitReply = () => {
        if (isInputDisabled.value) return;

        replyForm.post(route('tickets.messages.store', ticket.id), {
            preserveScroll: true,
            onSuccess: () => {
                replyForm.reset('message');
            }
        });
    };

    const assignToMe = () => {
        isAssigning.value = true;
        router.patch(route('tickets.assign', ticket.id), {}, {
            preserveScroll: true,
            onFinish: () => {
                isAssigning.value = false;
            }
        });
    };

    const updateStatus = (newStatus) => {
        router.patch(route('tickets.update-status', ticket.id), {
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
                axios.post(route('tickets.tick-time', ticket.id))
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
    watch(() => ticket.messages, (newMessages) => {
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
            window.Echo.private(`ticket.${ticket.id}`)
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
        if (window.Echo) window.Echo.leave(`ticket.${ticket.id}`);
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
        submitReply,
        assignToMe,
        updateStatus
    };
}