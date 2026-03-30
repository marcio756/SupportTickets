<template>
  <div class="flex flex-col gap-5">
    <div class="relative group">
      <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
        <va-icon name="search" class="text-gray-400 group-focus-within:text-indigo-500 transition-colors" size="small" />
      </div>
      <input 
        type="text" 
        v-model="search" 
        :placeholder="t('announcements.search_placeholder')" 
        class="w-full pl-10 pr-4 py-2 rounded-lg border-gray-300 shadow-sm transition-all duration-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200"
      >
    </div>
    
    <div class="flex items-center gap-3 pb-3 border-b border-gray-100 dark:border-gray-800">
      <div class="flex items-center justify-center w-5 h-5">
        <input 
          type="checkbox" 
          id="selectAll" 
          :checked="isAllSelected" 
          @change="toggleAll"
          class="w-4 h-4 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 cursor-pointer transition-all duration-200"
        >
      </div>
      <label for="selectAll" class="text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer select-none">
        {{ t('announcements.select_all') }}
      </label>
    </div>

    <div class="relative bg-gray-50/50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-lg p-2 min-h-[100px] max-h-72 overflow-y-auto custom-scrollbar">
      
      <transition name="fade" mode="out-in">
        <div v-if="filteredCustomers.length === 0" class="absolute inset-0 flex items-center justify-center p-6 text-sm text-gray-500">
          {{ t('announcements.no_customers_found') }}
        </div>
        
        <transition-group v-else name="list" tag="div" class="flex flex-col gap-1">
          <div 
            v-for="customer in filteredCustomers" 
            :key="customer.id" 
            class="flex items-center gap-3 p-2.5 hover:bg-white dark:hover:bg-gray-700 rounded-md transition-all duration-200 cursor-pointer"
            @click="toggleSingleSelection(customer.id)"
          >
            <div class="flex items-center justify-center w-5 h-5" @click.stop>
              <input 
                type="checkbox" 
                :id="`customer-${customer.id}`" 
                :value="customer.id" 
                v-model="internalSelection"
                @change="emitSelection"
                class="w-4 h-4 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 cursor-pointer"
              >
            </div>
            <label :for="`customer-${customer.id}`" class="text-sm text-gray-800 dark:text-gray-200 cursor-pointer w-full flex items-baseline gap-2 select-none" @click.stop>
              <span class="font-semibold">{{ customer.name }}</span> 
              <span class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ customer.email }}</span>
            </label>
          </div>
        </transition-group>
      </transition>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
  customers: { type: Array, required: true },
  modelValue: { type: Array, default: () => [] }
});

const emit = defineEmits(['update:modelValue']);
const { t } = useI18n();

const search = ref('');
const internalSelection = ref([...props.modelValue]);

/**
 * Computes the customer list based on the active search query.
 */
const filteredCustomers = computed(() => {
  const query = search.value.toLowerCase();
  if (!query) return props.customers;
  return props.customers.filter(c => 
    c.name.toLowerCase().includes(query) || 
    c.email.toLowerCase().includes(query)
  );
});

/**
 * Evaluates if all currently visible (filtered) customers are selected.
 */
const isAllSelected = computed(() => {
  if (filteredCustomers.value.length === 0) return false;
  return filteredCustomers.value.every(c => internalSelection.value.includes(c.id));
});

/**
 * Toggles the selection state for all currently visible (filtered) customers.
 * * @param {Event} event The native DOM change event.
 */
const toggleAll = (event) => {
  const isChecked = event.target.checked;
  const filteredIds = filteredCustomers.value.map(c => c.id);
  
  if (isChecked) {
    const newSelection = new Set([...internalSelection.value, ...filteredIds]);
    internalSelection.value = Array.from(newSelection);
  } else {
    internalSelection.value = internalSelection.value.filter(id => !filteredIds.includes(id));
  }
  emitSelection();
};

/**
 * Helper to toggle a single customer when clicking the row area.
 * * @param {number|string} id The customer identifier.
 */
const toggleSingleSelection = (id) => {
    const index = internalSelection.value.indexOf(id);
    if (index === -1) {
        internalSelection.value.push(id);
    } else {
        internalSelection.value.splice(index, 1);
    }
    emitSelection();
};

/**
 * Syncs internal selection state upward to the parent boundary.
 */
const emitSelection = () => {
  emit('update:modelValue', internalSelection.value);
};

// Sync external resets
watch(() => props.modelValue, (newVal) => {
  internalSelection.value = [...newVal];
}, { deep: true });
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
.dark .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #475569; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.list-enter-active, .list-leave-active { transition: all 0.3s ease; }
.list-enter-from, .list-leave-to { opacity: 0; transform: translateX(-10px); }
</style>