<template>
  <va-card class="mb-6">
    <va-card-content>
      <div class="flex flex-col xl:flex-row items-center gap-4 w-full">
        
        <va-input
          v-model="internalQuery"
          placeholder="Search..."
          class="flex-1 w-full min-w-[200px]"
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
          class="w-full xl:w-48 flex-none"
          @update:modelValue="emitFilters"
        >
            <template #content="{ valueArray }">
                <span v-if="valueArray.length === 1">{{ valueArray[0].text || valueArray[0] }}</span>
                <span v-else class="text-gray-400">All Statuses</span>
            </template>
        </va-select>

        <va-select
          v-if="roleOptions && roleOptions.length > 0"
          v-model="internalRole"
          :options="roleOptions"
          value-by="value"
          text-by="text"
          placeholder="All Roles"
          clearable
          preset="bordered"
          class="w-full xl:w-48 flex-none"
          @update:modelValue="emitFilters"
        >
            <template #content="{ valueArray }">
                <span v-if="valueArray.length === 1">{{ valueArray[0].text || valueArray[0] }}</span>
                <span v-else class="text-gray-400">All Roles</span>
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
          class="w-full xl:w-56 flex-none"
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
          class="w-full xl:w-56 flex-none"
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
          class="w-full xl:w-auto flex-none mt-2 xl:mt-0"
        >
          Clear All
        </va-button>
      </div>
    </va-card-content>
  </va-card>
</template>

<script setup>
import { ref, watch, computed } from 'vue';

const props = defineProps({
  query: { type: String, default: '' },
  status: { type: [String, Object], default: '' },
  role: { type: [String, Object], default: '' },
  customers: { type: Array, default: () => [] },
  assignees: { type: Array, default: () => [] },
  statusOptions: { type: Array, default: () => [] },
  roleOptions: { type: Array, default: () => [] },
  customerOptions: { type: Array, default: () => [] },
  assigneeOptions: { type: Array, default: () => [] },
  isSupporter: { type: Boolean, default: false },
});

const emit = defineEmits(['update:query', 'update:status', 'update:role', 'update:customers', 'update:assignees']);

const internalQuery = ref(props.query);
const internalStatus = ref(props.status);
const internalRole = ref(props.role);
const internalCustomers = ref(props.customers);
const internalAssignees = ref(props.assignees);

watch(() => props.query, (newVal) => { internalQuery.value = newVal; });
watch(() => props.status, (newVal) => { internalStatus.value = newVal; });
watch(() => props.role, (newVal) => { internalRole.value = newVal; });
watch(() => props.customers, (newVal) => { internalCustomers.value = newVal; });
watch(() => props.assignees, (newVal) => { internalAssignees.value = newVal; });

const activeFiltersCount = computed(() => {
    let count = 0;
    if (internalQuery.value && internalQuery.value.trim() !== '') count++;
    if (internalStatus.value && internalStatus.value !== '') count++;
    if (internalRole.value && internalRole.value !== '') count++;
    if (internalCustomers.value && internalCustomers.value.length > 0) count++;
    if (internalAssignees.value && internalAssignees.value.length > 0) count++;
    return count;
});

const clearAllFilters = () => {
    internalQuery.value = '';
    internalStatus.value = '';
    internalRole.value = '';
    internalCustomers.value = [];
    internalAssignees.value = [];
    emitFilters();
};

const emitFilters = () => {
  const statusValue = typeof internalStatus.value === 'object' && internalStatus.value !== null 
      ? internalStatus.value.value : internalStatus.value;
  const roleValue = typeof internalRole.value === 'object' && internalRole.value !== null 
      ? internalRole.value.value : internalRole.value;

  emit('update:query', internalQuery.value);
  emit('update:status', statusValue || '');
  emit('update:role', roleValue || '');
  emit('update:customers', internalCustomers.value || []);
  emit('update:assignees', internalAssignees.value || []);
};
</script>