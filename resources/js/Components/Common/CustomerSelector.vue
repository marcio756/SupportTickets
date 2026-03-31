<template>
  <div class="flex flex-col gap-5">
    <va-input 
        v-model="search" 
        :placeholder="t('announcements.search_placeholder')" 
        class="w-full transition-all duration-300"
        color="primary"
        bordered
        clearable
        @update:modelValue="debouncedSearch"
    >
        <template #prependInner>
            <va-icon name="search" :color="isLoading ? 'primary' : '#9ca3af'" size="small" :class="{ 'animate-pulse': isLoading }" />
        </template>
    </va-input>
    
    <div 
        v-if="asyncCustomers.length > 0 && !isLoading"
        class="flex items-center gap-3 pb-3 border-b border-gray-100 dark:border-gray-800 px-2 cursor-pointer group transition-opacity"
        @click="toggleAll"
    >
      <va-checkbox 
        v-model="isAllSelected" 
        color="primary"
        class="flex-shrink-0"
        @click.stop
      />
      <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer select-none group-hover:text-primary transition-colors">
        {{ t('announcements.select_all') }}
      </label>
    </div>

    <div 
        class="relative border rounded-xl min-h-[150px] max-h-[450px] overflow-y-auto custom-scrollbar transition-all duration-300 bg-gray-50/50 dark:bg-gray-950/30"
        :class="{
            'border-red-300 dark:border-red-800 ring-1 ring-red-100 dark:ring-red-900/30': error,
            'border-gray-100 dark:border-gray-800': !error
        }"
    >
      
      <transition name="fade" mode="out-in">
        <div v-if="isLoading" class="flex flex-col p-1.5 gap-2">
            <div v-for="i in 4" :key="`skeleton-${i}`" class="flex items-center gap-4 p-3 rounded-lg animate-pulse bg-gray-100/50 dark:bg-gray-800/50">
                <div class="w-5 h-5 bg-gray-300 dark:bg-gray-700 rounded"></div>
                <div class="flex-grow space-y-2">
                    <div class="h-4 bg-gray-300 dark:bg-gray-700 rounded w-1/3"></div>
                    <div class="h-3 bg-gray-200 dark:bg-gray-800 rounded w-1/2"></div>
                </div>
            </div>
        </div>

        <div v-else-if="asyncCustomers.length === 0 && search.length > 0" class="absolute inset-0 flex flex-col items-center justify-center p-6 text-sm text-gray-500 gap-2">
          <va-icon name="person_search" size="large" color="#9ca3af" />
          <span>{{ t('announcements.no_customers_found') }}</span>
        </div>

        <div v-else-if="asyncCustomers.length === 0 && search.length === 0" class="absolute inset-0 flex flex-col items-center justify-center p-6 text-sm text-gray-500 gap-2">
          <va-icon name="search" size="large" color="#9ca3af" />
          <span>Escreva para pesquisar clientes...</span>
        </div>
        
        <transition-group v-else name="list-complete" tag="div" class="flex flex-col p-1.5">
          <div 
            v-for="customer in asyncCustomers" 
            :key="customer.id" 
            class="list-complete-item flex items-center gap-4 p-3 hover:bg-white dark:hover:bg-gray-800 rounded-lg transition-all duration-200 cursor-pointer group"
            :class="{ 'bg-blue-50/50 dark:bg-blue-900/20': internalSelection.includes(customer.id) }"
            @click="toggleSingleSelection(customer.id)"
          >
            <va-checkbox 
                :model-value="internalSelection.includes(customer.id)"
                color="primary"
                class="flex-shrink-0"
                @click.stop="toggleSingleSelection(customer.id)"
            />
            
            <div class="flex-grow select-none">
              <div class="font-semibold text-gray-950 dark:text-gray-50 text-sm flex items-center gap-2">
                  {{ customer.name }}
                  <transition name="drill">
                    <span v-if="internalSelection.includes(customer.id)" class="text-xs text-blue-500 font-medium">✓</span>
                  </transition>
              </div>
              <div class="text-xs text-gray-600 dark:text-gray-400 font-mono">{{ customer.email }}</div>
            </div>
          </div>
        </transition-group>
      </transition>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const props = defineProps({
  customers: { type: Array, default: () => [] },
  modelValue: { type: Array, default: () => [] },
  error: { type: Boolean, default: false }
});

const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();

const search = ref('');
const isLoading = ref(false);
const asyncCustomers = ref([...props.customers]);
const internalSelection = ref([...props.modelValue]);
let searchTimeout = null;

const isAllSelected = computed({
  get() {
      if (asyncCustomers.value.length === 0) return false;
      return asyncCustomers.value.every(c => internalSelection.value.includes(c.id));
  },
  set() {}
});

const debouncedSearch = (query) => {
    clearTimeout(searchTimeout);
    
    if (!query || query.length < 2) {
        asyncCustomers.value = props.customers; 
        isLoading.value = false;
        return;
    }

    isLoading.value = true;
    
    searchTimeout = setTimeout(async () => {
        try {
            const response = await axios.get('/api/users', { 
                params: { search: query, role: 'customer', limit: 50 } 
            });
            asyncCustomers.value = response.data.data || response.data;
        } catch (error) {
            console.error('Search failed:', error);
        } finally {
            isLoading.value = false;
        }
    }, 400); 
};

const toggleAll = () => {
  const currentVisibleIds = asyncCustomers.value.map(c => c.id);
  const alreadySelected = currentVisibleIds.filter(id => internalSelection.value.includes(id));
  
  if (alreadySelected.length === currentVisibleIds.length) {
    internalSelection.value = internalSelection.value.filter(id => !currentVisibleIds.includes(id));
  } else {
    const newSelection = new Set([...internalSelection.value, ...currentVisibleIds]);
    internalSelection.value = Array.from(newSelection);
  }
  emitSelection();
};

const toggleSingleSelection = (id) => {
    const index = internalSelection.value.indexOf(id);
    if (index === -1) {
        internalSelection.value.push(id);
    } else {
        internalSelection.value.splice(index, 1);
    }
    emitSelection();
};

const emitSelection = () => {
  emit('update:modelValue', internalSelection.value);
};

watch(() => props.modelValue, (newVal) => {
  internalSelection.value = [...newVal];
}, { deep: true });

onUnmounted(() => {
    clearTimeout(searchTimeout);
});
</script>

<style scoped>
/* Scrollbar optimization for UI continuity */
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: #e2e8f0; border-radius: 10px; }
.dark .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #1f2937; }

/* Transitions */
.fade-enter-active, .fade-leave-active { transition: opacity 0.25s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.drill-enter-active, .drill-leave-active { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
.drill-enter-from, .drill-leave-to { opacity: 0; transform: scale(0.8); }

.list-complete-item {
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}
.list-complete-enter-from,
.list-complete-leave-to {
  opacity: 0;
  transform: translateX(-10px);
}
.list-complete-leave-active {
  position: absolute;
  width: calc(100% - 12px);
}
</style>