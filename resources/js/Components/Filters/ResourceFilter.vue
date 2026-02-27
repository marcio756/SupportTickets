<template>
  <va-card class="mb-6">
    <va-card-content>
      <div class="flex flex-col xl:flex-row items-center gap-4 w-full flex-wrap">
        
        <slot name="prepend" />

        <va-input
          v-if="!hideSearch"
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

        <slot name="append" />

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

        <va-select
          v-if="isSupporter && availableTags && availableTags.length > 0"
          v-model="internalTags"
          :options="formattedTagOptions"
          value-by="value"
          text-by="text"
          placeholder="Filter by Tags"
          multiple
          clearable
          searchable
          preset="bordered"
          class="w-full xl:w-64 flex-none"
          @update:modelValue="emitFilters"
        >
          <template #content="{ valueArray }">
            <div class="flex gap-1 flex-wrap items-center">
              <span v-if="valueArray.length === 0" class="text-gray-400">Filter by Tags</span>
              <template v-else>
                <span 
                    v-for="(val, index) in valueArray.slice(0, 3)" 
                    :key="index"
                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    {{ resolveTagName(val) }}{{ index < Math.min(valueArray.length, 3) - 1 ? ', ' : '' }}
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
  hideSearch: { type: Boolean, default: false },
  additionalFilterCount: { type: Number, default: 0 },
  status: { type: [String, Object], default: '' },
  role: { type: [String, Object], default: '' },
  customers: { type: Array, default: () => [] },
  assignees: { type: Array, default: () => [] },
  tags: { type: Array, default: () => [] },
  statusOptions: { type: Array, default: () => [] },
  roleOptions: { type: Array, default: () => [] },
  customerOptions: { type: Array, default: () => [] },
  assigneeOptions: { type: Array, default: () => [] },
  availableTags: { type: Array, default: () => [] },
  isSupporter: { type: Boolean, default: false },
});

const emit = defineEmits([
    'update:query', 'update:status', 'update:role', 
    'update:customers', 'update:assignees', 'update:tags', 'clear-all'
]);

const internalQuery = ref(props.query);
const internalStatus = ref(props.status);
const internalRole = ref(props.role);
const internalCustomers = ref(props.customers);
const internalAssignees = ref(props.assignees);
const internalTags = ref(props.tags);

watch(() => props.query, (newVal) => { internalQuery.value = newVal; });
watch(() => props.status, (newVal) => { internalStatus.value = newVal; });
watch(() => props.role, (newVal) => { internalRole.value = newVal; });
watch(() => props.customers, (newVal) => { internalCustomers.value = newVal; });
watch(() => props.assignees, (newVal) => { internalAssignees.value = newVal; });
watch(() => props.tags, (newVal) => { internalTags.value = newVal; });

const activeFiltersCount = computed(() => {
    let count = props.additionalFilterCount;
    if (!props.hideSearch && internalQuery.value && internalQuery.value.trim() !== '') count++;
    if (internalStatus.value && internalStatus.value !== '') count++;
    if (internalRole.value && internalRole.value !== '') count++;
    if (internalCustomers.value && internalCustomers.value.length > 0) count++;
    if (internalAssignees.value && internalAssignees.value.length > 0) count++;
    if (internalTags.value && internalTags.value.length > 0) count++;
    return count;
});

const formattedTagOptions = computed(() => {
  return props.availableTags.map(tag => ({
    text: tag.name,
    value: String(tag.id)
  }));
});

const getDetailedTag = (id) => {
  return props.availableTags.find(t => String(t.id) === String(id)) || { name: 'Unknown', color: '#ccc' };
};

const resolveTagName = (val) => {
    if (typeof val === 'object' && val !== null) {
        return val.text || val.name || getDetailedTag(val.value || val.id).name;
    }
    return getDetailedTag(val).name;
};

const clearAllFilters = () => {
    internalQuery.value = '';
    internalStatus.value = '';
    internalRole.value = '';
    internalCustomers.value = [];
    internalAssignees.value = [];
    internalTags.value = [];
    emitFilters();
    emit('clear-all');
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
  emit('update:tags', internalTags.value || []);
};
</script>