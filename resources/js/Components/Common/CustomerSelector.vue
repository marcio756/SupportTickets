<template>
  <div class="flex flex-col gap-5">
    <va-input 
        v-model="search" 
        :placeholder="t('announcements.search_placeholder')" 
        class="w-full"
        color="indigo"
        bordered
        clearable
    >
        <template #prependInner>
            <va-icon name="search" color="gray" size="small" />
        </template>
    </va-input>
    
    <div 
        class="flex items-center gap-3 pb-3 border-b border-gray-100 dark:border-gray-800 px-2 cursor-pointer group"
        @click="toggleAll"
    >
      <va-checkbox 
        v-model="isAllSelected" 
        color="indigo"
        class="flex-shrink-0"
        @click.stop
      />
      <label class="text-sm font-semibold text-gray-700 dark:text-gray-300 cursor-pointer select-none group-hover:text-indigo-600 transition-colors">
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
        <div v-if="filteredCustomers.length === 0" class="absolute inset-0 flex flex-col items-center justify-center p-6 text-sm text-gray-500 gap-2">
          <va-icon name="person_search" size="large" color="gray" />
          <span>{{ t('announcements.no_customers_found') }}</span>
        </div>
        
        <transition-group v-else name="list-complete" tag="div" class="flex flex-col p-1.5">
          <div 
            v-for="customer in filteredCustomers" 
            :key="customer.id" 
            class="list-complete-item flex items-center gap-4 p-3 hover:bg-white dark:hover:bg-gray-800 rounded-lg transition-all duration-200 cursor-pointer group"
            :class="{ 'bg-indigo-50/50 dark:bg-indigo-900/20': internalSelection.includes(customer.id) }"
            @click="toggleSingleSelection(customer.id)"
          >
            <va-checkbox 
                :model-value="internalSelection.includes(customer.id)"
                color="indigo"
                class="flex-shrink-0"
                @click.stop="toggleSingleSelection(customer.id)"
            />
            
            <div class="flex-grow select-none">
              <div class="font-semibold text-gray-950 dark:text-gray-50 text-sm flex items-center gap-2">
                  {{ customer.name }}
                  <span v-if="internalSelection.includes(customer.id)" class="text-xs text-indigo-500 font-medium">✓</span>
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
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
  customers: { type: Array, required: true },
  modelValue: { type: Array, default: () => [] },
  error: { type: Boolean, default: false }
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
 * Validates if all currently visible (filtered) customers are selected.
 */
const isAllSelected = computed({
  get() {
      if (filteredCustomers.value.length === 0) return false;
      return filteredCustomers.value.every(c => internalSelection.value.includes(c.id));
  },
  set(value) {
      // This is handled by toggleAll, required for va-checkbox v-model binding compatibility
  }
});

/**
 * Toggles the selection state for all currently visible (filtered) customers.
 */
const toggleAll = () => {
  const currentFilteredIds = filteredCustomers.value.map(c => c.id);
  const alreadySelectedFiltered = currentFilteredIds.filter(id => internalSelection.value.includes(id));
  
  if (alreadySelectedFiltered.length === currentFilteredIds.length) {
    // All filtered are selected -> deselect them
    internalSelection.value = internalSelection.value.filter(id => !currentFilteredIds.includes(id));
  } else {
    // Some or none filtered are selected -> select all filtered
    const newSelection = new Set([...internalSelection.value, ...currentFilteredIds]);
    internalSelection.value = Array.from(newSelection);
  }
  emitSelection();
};

/**
 * Helper to toggle a single customer when clicking the row area.
 * @param {number|string} id The customer identifier.
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
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: #e2e8f0; border-radius: 10px; }
.dark .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #1f2937; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* List Animations Premium */
.list-complete-item {
  transition: all 0.4s ease;
}
.list-complete-enter-from,
.list-complete-leave-to {
  opacity: 0;
  transform: translateY(15px);
}
.list-complete-leave-active {
  position: absolute;
  width: 100%;
}
</style>