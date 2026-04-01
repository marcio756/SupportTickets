<template>
    <AppLayout>
        <Head :title="$t('tickets.show.page_title', { id: ticket.id, title: ticket.title })" />

        <Transition name="fade-overlay">
            <ClaimTicketOverlay 
                v-if="showClaimOverlay"
                :is-assigning="isAssigning"
                @claim="assignToMe"
            />
        </Transition>

        <Transition name="drill-transition" appear>
            <div :class="{ 'blur-md pointer-events-none select-none transition-all duration-300': showClaimOverlay }" class="content-wrapper">
                <div class="mb-4">
                    <Link :href="route('tickets.index')" class="text-blue-600 dark:text-blue-400 hover:underline text-sm flex items-center gap-1 mb-2 transition-colors">
                        &larr; {{ $t('tickets.show.back_to_tickets') }}
                    </Link>
                    
                    <div class="flex flex-col md:flex-row justify-between md:items-start gap-4">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100 flex items-center gap-3">
                                #{{ ticket.id }} {{ ticket.title }}
                                <span v-if="ticket.source === 'email'"
                                      class="inline-flex items-center justify-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 shadow-sm"
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
                                <TransitionGroup name="tag-list">
                                    <TagBadge v-for="tag in optimisticTags" :key="tag.id" :tag="tag" />
                                </TransitionGroup>
                                <va-button 
                                    v-if="isSupporter" 
                                    preset="secondary" 
                                    size="small" 
                                    icon="edit" 
                                    @click="isTagModalOpen = true"
                                    class="!px-2 !py-0 h-6 transition-transform hover:scale-105"
                                >
                                    {{ $t('tickets.show.manage_tags') }}
                                </va-button>
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap items-center gap-3 mt-2 md:mt-0">
                            <TicketStatusBadge :status="optimisticStatus" class="transition-all duration-300" />
                            
                            <span v-if="ticket.assignee" class="text-sm bg-gray-100 dark:bg-gray-800 px-3 py-1 rounded-full text-gray-600 dark:text-gray-400 flex items-center gap-1 shadow-sm">
                                <VaIcon name="support_agent" size="small" color="secondary" />
                                {{ $t('tickets.show.assigned_to') }}: {{ ticket.assignee.name }}
                            </span>
                            
                            <SupportTimeDisplay v-if="ticket.customer" :seconds="currentRemainingSeconds" />

                            <va-dropdown placement="bottom-end" v-if="!isSupporter || hasWritePermission">
                                <template #anchor>
                                    <va-button preset="secondary" icon="settings" size="small" class="transition-colors">{{ $t('common.actions.actions') }}</va-button>
                                </template>
                                <va-dropdown-content class="p-0 shadow-lg rounded-lg border border-gray-100 dark:border-gray-700">
                                    <va-list>
                                        <va-list-item v-if="!isSupporter && optimisticStatus !== 'resolved' && optimisticStatus !== 'closed'" @click="handleOptimisticStatusUpdate('resolved')" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                            <va-list-item-section icon><va-icon name="check_circle" color="success" /></va-list-item-section>
                                            <va-list-item-section>{{ $t('tickets.actions.mark_resolved') }}</va-list-item-section>
                                        </va-list-item>

                                        <template v-if="hasWritePermission">
                                            <va-list-item @click="handleOptimisticStatusUpdate('open')" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"><va-list-item-section>{{ $t('tickets.actions.reopen') }}</va-list-item-section></va-list-item>
                                            <va-list-item @click="handleOptimisticStatusUpdate('in_progress')" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"><va-list-item-section>{{ $t('tickets.actions.set_in_progress') }}</va-list-item-section></va-list-item>
                                            <va-list-item @click="handleOptimisticStatusUpdate('resolved')" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"><va-list-item-section class="text-blue-600 dark:text-blue-400 font-medium">{{ $t('tickets.actions.mark_resolved') }}</va-list-item-section></va-list-item>
                                            <va-list-item @click="handleOptimisticStatusUpdate('closed')" class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"><va-list-item-section class="text-red-600 dark:text-red-400 font-medium">{{ $t('tickets.actions.close_ticket') }}</va-list-item-section></va-list-item>
                                            <va-list-item @click="confirmingDeletion = true" class="cursor-pointer hover:bg-red-50 dark:hover:bg-red-900/20 border-t border-gray-100 dark:border-gray-700 transition-colors">
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

                <TicketChatArea 
                    :ticket="ticket" 
                    :isTimeUp="isTimeUp"
                    :showClaimOverlay="showClaimOverlay"
                    :isInputDisabled="isInputDisabled"
                    :isSubmitDisabled="isSubmitDisabled"
                    class="shadow-sm rounded-lg"
                />
            </div>
        </Transition>

        <va-modal
            v-model="isTagModalOpen"
            :title="$t('tickets.tags.manage_title')"
            :ok-text="$t('common.actions.save_changes')"
            :cancel-text="$t('common.actions.cancel')"
            @ok="handleOptimisticTagsSave"
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
                    virtual-scroller
                    class="w-full"
                >
                    <template #content="{ valueArray }">
                        <div class="flex gap-1 flex-wrap items-center">
                            <span v-if="valueArray.length === 0" class="text-gray-400">{{ $t('tickets.tags.select_placeholder') }}</span>
                            <template v-else>
                                <span 
                                    v-for="(val, index) in valueArray.slice(0, 3)" 
                                    :key="index"
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded"
                                >
                                    {{ resolveTagName(val) }}
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
import { ref, toRef, computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import TicketStatusBadge from '@/Components/Tickets/TicketStatusBadge.vue';
import SupportTimeDisplay from '@/Components/Tickets/SupportTimeDisplay.vue';
import TagBadge from '@/Components/Common/TagBadge.vue';
import TicketChatArea from '@/Pages/Tickets/TicketChatArea.vue';
import ClaimTicketOverlay from '@/Features/Tickets/ClaimTicketOverlay.vue';
import { 
    VaButton, VaDropdown, VaDropdownContent, 
    VaList, VaListItem, VaListItemSection, 
    VaIcon, VaModal, VaInput, VaSelect 
} from 'vuestic-ui';
import { useTicketSupport } from '@/Composables/useTicketSupport';

const props = defineProps({
    ticket: { type: Object, required: true },
    availableTags: { type: Array, default: () => [] },
});

const ticketRef = toRef(props, 'ticket');

const {
    isSupporter, currentRemainingSeconds,
    isAssigning, deleteForm, confirmingDeletion, isTimeUp,
    hasWritePermission, showClaimOverlay, isInputDisabled, isSubmitDisabled,
    assignToMe, updateStatus, deleteTicket
} = useTicketSupport(ticketRef);

const { t } = useI18n();

// --- OPTIMISTIC UI: Estado Local ---
const optimisticStatus = ref(props.ticket.status);
const optimisticTags = ref([...props.ticket.tags]);

// Sincronizar caso os props mudem (ex: reload via Inertia noutro contexto)
watch(() => props.ticket.status, (newVal) => optimisticStatus.value = newVal);
watch(() => props.ticket.tags, (newVal) => optimisticTags.value = [...newVal], { deep: true });

const handleOptimisticStatusUpdate = (newStatus) => {
    // 1. Atualizar UI localmente de imediato
    optimisticStatus.value = newStatus;
    // 2. Chamar o endpoint real via composable
    updateStatus(newStatus);
};

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
    return props.availableTags.find(t => String(t.id) === String(id)) || { id, name: t('tickets.tags.unknown'), color: '#ccc' };
};

const resolveTagName = (val) => {
    if (typeof val === 'object' && val !== null) {
        return val.text || val.name || getDetailedTag(val.value || val.id).name;
    }
    return getDetailedTag(val).name;
};

const handleOptimisticTagsSave = () => {
    // 1. Atualizar visualmente de imediato fechando a modal
    isTagModalOpen.value = false;
    
    // 2. Atualizar UI local
    optimisticTags.value = tagForm.tags.map(tagId => getDetailedTag(tagId));

    // 3. Submeter pedido real
    tagForm.put(route('tickets.tags.sync', props.ticket.id), {
        preserveScroll: true,
        onError: () => {
            // Fazer rollback se o pedido falhar
            optimisticTags.value = [...props.ticket.tags];
        }
    });
};
</script>

<style scoped>
/* Efeito de Blur Aprimorado */
.blur-md { 
    filter: blur(8px);
    transition: filter 0.4s ease-out;
}

/* Transições Optimistic UI para lista de Tags */
.tag-list-enter-active, .tag-list-leave-active {
  transition: all 0.3s ease;
}
.tag-list-enter-from {
  opacity: 0;
  transform: scale(0.8) translateY(10px);
}
.tag-list-leave-to {
  opacity: 0;
  transform: scale(0.8);
}

/* Drill Transitions para imersão profunda na página do Ticket */
.drill-transition-enter-active {
    transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}
.drill-transition-enter-from {
    opacity: 0;
    transform: scale(0.97) translateY(20px);
}
.drill-transition-leave-active {
    transition: all 0.3s ease-in;
}
.drill-transition-leave-to {
    opacity: 0;
    transform: scale(1.02);
}

.fade-overlay-enter-active, .fade-overlay-leave-active {
    transition: opacity 0.4s ease;
}
.fade-overlay-enter-from, .fade-overlay-leave-to {
    opacity: 0;
}
</style>