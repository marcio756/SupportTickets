<template>
    <div class="resource-filters flex flex-col md:flex-row gap-4 items-end bg-white p-4 rounded-lg shadow-sm mb-6 border border-gray-100">
        <div class="w-full md:w-1/3">
            <va-input
                v-model="modelValue.search"
                placeholder="Search by keyword..."
                clearable
                class="w-full"
            >
                <template #prependInner>
                    <va-icon name="search" color="secondary" />
                </template>
            </va-input>
        </div>

        <div class="w-full md:w-1/4" v-if="statusOptions && statusOptions.length">
            <va-select
                v-model="modelValue.status"
                :options="statusOptions"
                placeholder="Filter by Status"
                clearable
                class="w-full"
            />
        </div>

        <div class="ml-auto flex gap-2">
            <va-button preset="secondary" icon="refresh" @click="emitReset">
                Clear Filters
            </va-button>
        </div>
    </div>
</template>

<script setup>
import { VaInput, VaSelect, VaButton, VaIcon } from 'vuestic-ui';

/**
 * Generic filter interface component for data tables.
 * Employs two-way binding with the parent component using v-model.
 *
 * @property {Object} modelValue - Reactive object holding the active filters.
 * @property {Array} statusOptions - Array of available statuses for the dropdown menu.
 */
const props = defineProps({
    modelValue: {
        type: Object,
        required: true,
    },
    statusOptions: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue', 'reset']);

/**
 * Emits the reset event to the parent container so it can trigger the composable reset function.
 */
const emitReset = () => {
    emit('reset');
};
</script>