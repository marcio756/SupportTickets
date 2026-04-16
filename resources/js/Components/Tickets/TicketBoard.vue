<template>
  <div class="ticket-board flex gap-6 overflow-x-auto pb-4 items-start min-h-[60vh]">
    <div 
      v-for="col in boardColumns" 
      :key="col.value" 
      class="board-column flex-1 min-w-[320px] max-w-[400px] bg-gray-50 dark:bg-gray-800 rounded-xl p-4 flex flex-col border border-gray-100 dark:border-gray-700 shadow-sm"
    >
      <div class="flex justify-between items-center mb-4 px-1">
        <h3 class="font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2">
          {{ col.label }}
          <span class="bg-gray-200 dark:bg-gray-700 text-xs py-0.5 px-2 rounded-full font-medium">
            {{ columnsData[col.value].length }}
          </span>
        </h3>
      </div>

      <draggable 
        v-model="columnsData[col.value]"
        group="tickets"
        item-key="id"
        class="flex flex-col gap-3 min-h-[150px] h-full"
        ghost-class="ghost-ticket"
        drag-class="drag-ticket"
        :animation="200"
        @change="onChange($event, col.value)"
      >
        <template #item="{ element: ticket }">
          <div 
            class="board-card bg-white dark:bg-gray-900 p-4 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 cursor-grab hover:shadow-md transition-shadow"
            @click="$emit('ticket-click', ticket)"
          >
            <div class="flex justify-between items-start mb-2">
              <span class="text-xs font-bold text-primary">#{{ ticket.id }}</span>
              <span 
                v-if="ticket.source === 'email'"
                class="inline-flex items-center justify-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                title="Recebido via Email"
              >
                📧
              </span>
            </div>
            
            <h4 class="text-sm font-semibold mb-3 text-gray-800 dark:text-gray-100 line-clamp-2">
              {{ ticket.title }}
            </h4>

            <div class="flex flex-wrap gap-1 mb-4">
              <template v-if="ticket.tags && ticket.tags.length > 0">
                <span 
                  v-for="tag in ticket.tags" 
                  :key="tag.id"
                  class="text-[10px] px-2 py-0.5 rounded-full font-medium text-white"
                  :style="{ backgroundColor: tag.color }"
                >
                  {{ tag.name }}
                </span>
              </template>
              <span v-else class="text-[10px] text-gray-400 border border-dashed border-gray-300 dark:border-gray-600 rounded-full px-2 py-0.5">
                Sem tags
              </span>
            </div>

            <div class="flex justify-between items-center mt-auto pt-3 border-t border-gray-100 dark:border-gray-800">
              <div class="flex items-center gap-2">
                <template v-if="ticket.assignee">
                  <div class="w-6 h-6 rounded-full bg-primary flex items-center justify-center text-white text-xs font-bold" :title="ticket.assignee.name">
                    {{ ticket.assignee.name.charAt(0) }}
                  </div>
                  <span class="text-xs text-gray-500 truncate max-w-[100px]">{{ ticket.assignee.name }}</span>
                </template>
                <template v-else>
                  <div class="w-6 h-6 rounded-full border border-dashed border-red-300 flex items-center justify-center bg-red-50 text-red-400" title="Não atribuído">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                    </svg>
                  </div>
                </template>
              </div>
              
              <span class="text-[10px] text-gray-400">
                {{ new Date(ticket.created_at).toLocaleDateString() }}
              </span>
            </div>
          </div>
        </template>
      </draggable>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import draggable from 'vuedraggable';

const props = defineProps({
  tickets: {
    type: Array,
    required: true
  }
});

const emit = defineEmits(['update-status', 'ticket-click']);
const { t } = useI18n();

const boardColumns = computed(() => [
  { value: 'open', label: t('tickets.status.open') },
  { value: 'in_progress', label: t('tickets.status.in_progress') },
  { value: 'resolved', label: t('tickets.status.resolved') },
  { value: 'closed', label: t('tickets.status.closed') },
]);

// Mantém o estado reativo segmentado por colunas para o vuedraggable gerir internamente
const columnsData = ref({
  open: [],
  in_progress: [],
  resolved: [],
  closed: []
});

// Otimização Crítica: Shallow Clone para máxima performance e prevenção de bloqueio na pesquisa
watch(() => props.tickets, (newVal) => {
  const newColumns = { open: [], in_progress: [], resolved: [], closed: [] };
  
  if (newVal && newVal.length > 0) {
    newVal.forEach(ticket => {
      if (newColumns[ticket.status]) {
        // Clonagem leve (spread operator) para reatividade fluída sem sobrecarregar a memória
        newColumns[ticket.status].push({ ...ticket });
      }
    });
  }
  
  columnsData.value = newColumns;
}, { immediate: true });

/**
 * Disparado pelo vuedraggable quando a lista sofre uma alteração física
 */
const onChange = (evt, newStatus) => {
  // Apenas reagimos se o item for adicionado a uma nova coluna. 
  // (O vuedraggable já fez o update visual instantâneo - Optimistic UI)
  if (evt.added) {
    const ticketId = evt.added.element.id;
    
    emit('update-status', {
      ticketId: ticketId,
      newStatus: newStatus,
      revertCallback: () => {
        // Se a API falhar (ex: erro 500, validação), forçamos o Inertia a recarregar
        // o estado fidedigno do backend para reverter o Board de forma segura.
        import('@inertiajs/vue3').then(({ router }) => {
          router.reload({ only: ['tickets'] });
        });
      }
    });
  }
};
</script>

<style scoped>
/* Transparência exigida (ghosting) para simular on-drag placement */
.ghost-ticket {
  opacity: 0.4;
  background-color: #f3f4f6; /* cinza claro para ajudar ao destaque visual */
  border: 2px dashed var(--va-primary) !important;
}

/* Estado do cursor quando se arrasta o cartão */
.drag-ticket {
  cursor: grabbing !important;
}
</style>