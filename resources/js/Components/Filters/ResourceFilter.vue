<template>
  <div class="resource-filter">
    <va-input
      v-model="internalQuery"
      placeholder="Search..."
      class="search-input"
      clearable
      preset="bordered"
      @update:modelValue="emitFilters"
    >
      <template #prependInner>
        <va-icon name="search" color="secondary" />
      </template>
    </va-input>

    <va-select
      v-if="statusOptions && statusOptions.length > 0"
      v-model="internalStatus"
      :options="statusOptions"
      value-by="value"
      placeholder="All Statuses"
      clearable
      preset="bordered"
      class="status-select"
      @update:modelValue="emitFilters"
    >
        <template #content="{ valueArray }">
            <span v-if="valueArray.length === 1">{{ valueArray[0].text || valueArray[0] }}</span>
            <span v-else class="text-gray-400">All Statuses</span>
        </template>
    </va-select>

    <va-select
      v-if="isSupporter && customerOptions && customerOptions.length > 0"
      v-model="internalCustomers"
      :options="customerOptions"
      multiple
      searchable
      text-by="name"
      value-by="id"
      placeholder="Filter by Customers"
      clearable
      preset="bordered"
      class="customer-select"
      @update:modelValue="emitFilters"
    >
        <template #content="{ valueArray }">
            <span v-if="valueArray.length === 1">{{ valueArray[0].name || valueArray[0] }}</span>
            <span v-else-if="valueArray.length > 1" class="font-bold text-sm">
                {{ valueArray.length }} selected
            </span>
            <span v-else class="text-gray-400">Filter by Customers</span>
        </template>
    </va-select>

    <va-select
      v-if="isSupporter && assigneeOptions && assigneeOptions.length > 0"
      v-model="internalAssignees"
      :options="assigneeOptions"
      multiple
      value-by="value"
      placeholder="Filter by Assignment"
      clearable
      preset="bordered"
      class="assignment-select"
      @update:modelValue="emitFilters"
    >
        <template #content="{ valueArray }">
            <span v-if="valueArray.length === 1">{{ valueArray[0].text || valueArray[0] }}</span>
            <span v-else-if="valueArray.length > 1" class="font-bold text-sm">
                {{ valueArray.length }} selected
            </span>
            <span v-else class="text-gray-400">Filter by Assignment</span>
        </template>
    </va-select>

    <va-button
      v-if="activeFiltersCount > 1"
      preset="plain"
      color="danger"
      icon="delete_sweep"
      @click="clearAllFilters"
      class="clear-all-btn ml-auto"
    >
      Clear All
    </va-button>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';

const props = defineProps({
  query: {
    type: String,
    default: '',
  },
  status: {
    type: [String, Object], 
    default: '',
  },
  customers: {
    type: Array,
    default: () => [],
  },
  assignees: {
    type: Array,
    default: () => [],
  },
  statusOptions: {
    type: Array,
    default: () => [],
  },
  customerOptions: {
    type: Array,
    default: () => [],
  },
  assigneeOptions: {
    type: Array,
    default: () => [],
  },
  isSupporter: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:query', 'update:status', 'update:customers', 'update:assignees']);

const internalQuery = ref(props.query);
const internalStatus = ref(props.status);
const internalCustomers = ref(props.customers);
const internalAssignees = ref(props.assignees);

// Synchronizes the local component state with the parent state whenever external changes occur
watch(() => props.query, (newVal) => { internalQuery.value = newVal; });
watch(() => props.status, (newVal) => { internalStatus.value = newVal; });
watch(() => props.customers, (newVal) => { internalCustomers.value = newVal; });
watch(() => props.assignees, (newVal) => { internalAssignees.value = newVal; });

/**
 * Computes how many individual filter categories are currently active.
 * @returns {Number} The count of active filters.
 */
const activeFiltersCount = computed(() => {
    let count = 0;
    if (internalQuery.value && internalQuery.value.trim() !== '') count++;
    if (internalStatus.value && internalStatus.value !== '') count++;
    if (internalCustomers.value && internalCustomers.value.length > 0) count++;
    if (internalAssignees.value && internalAssignees.value.length > 0) count++;
    return count;
});

/**
 * Resets all internal filter states to their default empty values and emits the update.
 */
const clearAllFilters = () => {
    internalQuery.value = '';
    internalStatus.value = '';
    internalCustomers.value = [];
    internalAssignees.value = [];
    emitFilters();
};

const emitFilters = () => {
  const statusValue = typeof internalStatus.value === 'object' && internalStatus.value !== null 
      ? internalStatus.value.value 
      : internalStatus.value;

  emit('update:query', internalQuery.value);
  emit('update:status', statusValue || '');
  emit('update:customers', internalCustomers.value || []);
  emit('update:assignees', internalAssignees.value || []);
};
</script>

<style scoped>
.resource-filter {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
  align-items: center;
  flex-wrap: wrap;
}
.search-input {
  flex: 1;
  min-width: 250px;
}
.status-select {
  width: 200px;
}
.customer-select {
  width: 250px;
}
.assignment-select {
  width: 220px;
}
.clear-all-btn {
  /* Ensure the button stays aligned nicely on larger screens */
  margin-left: auto;
}
</style>