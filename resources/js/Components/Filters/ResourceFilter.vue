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
      placeholder="Filter by Status"
      clearable
      preset="bordered"
      class="status-select"
      @update:modelValue="emitFilters"
    />
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  query: {
    type: String,
    default: '',
  },
  status: {
    type: String,
    default: '',
  },
  statusOptions: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['update:query', 'update:status']);

const internalQuery = ref(props.query);
const internalStatus = ref(props.status);

// Synchronizes the local component state with the parent state whenever external changes occur
watch(() => props.query, (newVal) => { internalQuery.value = newVal; });
watch(() => props.status, (newVal) => { internalStatus.value = newVal; });

// Emits the updated values back to the parent component where the composable resides
const emitFilters = () => {
  emit('update:query', internalQuery.value);
  emit('update:status', internalStatus.value);
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
</style>