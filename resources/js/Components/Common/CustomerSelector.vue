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
        @clear="clearSearch"
    >
        <template #prependInner>
            <va-icon name="search" :color="isLoading ? 'primary' : '#9ca3af'" size="small" :class="{ 'animate-pulse': isLoading }" />
        </template>
    </va-input>
    
    <div 
        v-if="asyncCustomers.length > 0 && !isLoading && !apiError"
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
        class="relative border rounded-xl min-h-[250px] max-h-[450px] overflow-y-auto custom-scrollbar transition-all duration-300 bg-gray-50/50 dark:bg-gray-950/30"
        :class="{
            'border-red-300 dark:border-red-800 ring-1 ring-red-100 dark:ring-red-900/30': error || apiError,
            'border-gray-100 dark:border-gray-800': !error && !apiError
        }"
        @scroll="handleScroll"
    >
      <transition name="fade" mode="out-in">
        <div v-if="apiError" class="absolute inset-0 flex flex-col items-center justify-center p-6 text-sm text-red-500 gap-3 text-center">
            <va-icon name="error_outline" size="large" color="danger" />
            <span class="font-medium">{{ apiError }}</span>
            <va-button preset="secondary" size="small" color="danger" @click="fetchCustomers(1)">
                Tentar Novamente
            </va-button>
        </div>

        <div v-else-if="isLoading && currentPage === 1 && asyncCustomers.length === 0" class="flex flex-col p-1.5 gap-2">
            <div v-for="i in 5" :key="`skeleton-${i}`" class="flex items-center gap-4 p-3 rounded-lg animate-pulse bg-gray-100/50 dark:bg-gray-800/50">
                <div class="w-5 h-5 bg-gray-300 dark:bg-gray-700 rounded"></div>
                <div class="flex-grow space-y-2">
                    <div class="h-4 bg-gray-300 dark:bg-gray-700 rounded w-1/3"></div>
                    <div class="h-3 bg-gray-200 dark:bg-gray-800 rounded w-1/2"></div>
                </div>
            </div>
        </div>

        <div v-else-if="asyncCustomers.length === 0" class="absolute inset-0 flex flex-col items-center justify-center p-6 text-sm text-gray-500 gap-2">
          <va-icon :name="search.length > 0 ? 'person_search' : 'group'" size="large" color="#9ca3af" />
          <span>{{ search.length > 0 ? t('announcements.no_customers_found') : 'Nenhum utilizador encontrado.' }}</span>
        </div>
        
        <div v-else>
            <transition-group name="list-complete" tag="div" class="flex flex-col p-1.5">
              <div 
                v-for="customer in asyncCustomers" 
                :key="customer.id" 
                class="list-complete-item flex items-center gap-4 p-3 hover:bg-white dark:hover:bg-gray-800 rounded-lg transition-all duration-200 cursor-pointer group"
                :class="{ 'bg-indigo-50/50 dark:bg-indigo-900/20': internalSelection.includes(customer.id) }"
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
                        <span v-if="internalSelection.includes(customer.id)" class="text-xs text-indigo-500 font-medium">✓</span>
                      </transition>
                  </div>
                  <div class="text-xs text-gray-600 dark:text-gray-400 font-mono">{{ customer.email }}</div>
                </div>
              </div>
            </transition-group>

            <div v-if="isLoadingMore" class="p-4 flex justify-center items-center gap-2 text-sm text-gray-500 border-t border-gray-50 dark:border-gray-900">
                <va-icon name="loop" class="animate-spin" size="small" color="primary" />
                <span>{{ t('common.loading') }}...</span>
            </div>
        </div>
      </transition>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onUnmounted, onMounted } from 'vue';
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
const isLoadingMore = ref(false);
const apiError = ref(null);
const internalSelection = ref([...props.modelValue]);
let searchTimeout = null;

// Initial state from props to avoid empty screen
const asyncCustomers = ref([...props.customers]);
const currentPage = ref(1);
const lastPage = ref(1);

const isAllSelected = computed({
  get() {
      if (asyncCustomers.value.length === 0) return false;
      const currentVisibleIds = asyncCustomers.value.map(c => c.id);
      return currentVisibleIds.every(id => internalSelection.value.includes(id));
  },
  set() {}
});

/**
 * Core fetch function with 20 items per page limit as requested.
 * Contains fail-safes for unauthorized API calls commonly found in SPA setups.
 */
const fetchCustomers = async (page = 1, append = false) => {
    if (page === 1) isLoading.value = true;
    else isLoadingMore.value = true;
    
    apiError.value = null;

    try {
        const response = await axios.get('/api/v1/users', { 
            params: { 
                search: search.value, 
                role: 'customer', 
                limit: 20,
                page: page
            }
        });
        
        const data = response.data;
        const newItems = data.data || [];
        
        if (append) {
            const existingIds = new Set(asyncCustomers.value.map(c => c.id));
            const filtered = newItems.filter(item => !existingIds.has(item.id));
            asyncCustomers.value = [...asyncCustomers.value, ...filtered];
        } else {
            asyncCustomers.value = newItems;
        }
        
        currentPage.value = data.current_page || 1;
        lastPage.value = data.last_page || 1;
        
    } catch (err) {
        console.error('Customer fetch failed:', err);
        // Architectural UX enhancement: Graceful UI feedback for HTTP errors
        if (err.response && err.response.status === 401) {
            apiError.value = 'Acesso não autorizado (401). Verifica o SANCTUM_STATEFUL_DOMAINS no teu ficheiro .env para permitir pedidos API.';
        } else {
            apiError.value = 'Falha na comunicação com o servidor ao procurar clientes.';
        }
    } finally {
        isLoading.value = false;
        isLoadingMore.value = false;
    }
};

const debouncedSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => fetchCustomers(1, false), 400); 
};

const clearSearch = () => {
    search.value = '';
    fetchCustomers(1, false);
};

const handleScroll = (e) => {
    if (isLoadingMore.value || currentPage.value >= lastPage.value || apiError.value) return;
    
    const { scrollTop, scrollHeight, clientHeight } = e.target;
    if (scrollTop + clientHeight >= scrollHeight - 50) {
        fetchCustomers(currentPage.value + 1, true);
    }
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
    if (index === -1) internalSelection.value.push(id);
    else internalSelection.value.splice(index, 1);
    emitSelection();
};

const emitSelection = () => {
  emit('update:modelValue', internalSelection.value);
};

watch(() => props.modelValue, (newVal) => {
  internalSelection.value = [...newVal];
}, { deep: true });

onMounted(() => {
    fetchCustomers(1, false);
});

onUnmounted(() => clearTimeout(searchTimeout));
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: #e2e8f0; border-radius: 10px; }
.dark .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #1f2937; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.25s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.drill-enter-active, .drill-leave-active { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
.drill-enter-from, .drill-leave-to { opacity: 0; transform: scale(0.8); }

.list-complete-item { transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1); }
.list-complete-enter-from, .list-complete-leave-to { opacity: 0; transform: translateX(-10px); }
.list-complete-leave-active { position: absolute; width: calc(100% - 12px); }
</style>