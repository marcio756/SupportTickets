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
      placeholder="Filter by Status"
      clearable
      preset="bordered"
      class="status-select"
      @update:modelValue="emitFilters"
    />

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

    <va-checkbox
      v-if="isSupporter"
      v-model="internalUnassigned"
      label="Unassigned Only"
      class="unassigned-checkbox"
      @update:modelValue="emitFilters"
    />
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { VaCheckbox } from 'vuestic-ui';

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
  unassigned: {
    type: Boolean,
    default: false,
  },
  statusOptions: {
    type: Array,
    default: () => [],
  },
  customerOptions: {
    type: Array,
    default: () => [],
  },
  isSupporter: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:query', 'update:status', 'update:customers', 'update:unassigned']);

const internalQuery = ref(props.query);
const internalStatus = ref(props.status);
const internalCustomers = ref(props.customers);
const internalUnassigned = ref(props.unassigned);

// Synchronizes the local component state with the parent state whenever external changes occur
watch(() => props.query, (newVal) => { internalQuery.value = newVal; });
watch(() => props.status, (newVal) => { internalStatus.value = newVal; });
watch(() => props.customers, (newVal) => { internalCustomers.value = newVal; });
watch(() => props.unassigned, (newVal) => { internalUnassigned.value = newVal; });

// Emits the updated values back to the parent component where the composable resides
const emitFilters = () => {
  const statusValue = typeof internalStatus.value === 'object' && internalStatus.value !== null 
      ? internalStatus.value.value 
      : internalStatus.value;

  emit('update:query', internalQuery.value);
  emit('update:status', statusValue || '');
  emit('update:customers', internalCustomers.value || []);
  emit('update:unassigned', internalUnassigned.value);
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
.unassigned-checkbox {
  margin-left: auto;
}
</style>