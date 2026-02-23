<template>
    <va-card>
        <va-card-content>
            <va-data-table
                :items="resourceData.data"
                :columns="columns"
                :loading="loading"
                striped
                hoverable
                :clickable="clickable"
                @row:click="$emit('row:click', $event)"
            >
                <template v-for="(_, slotName) in $slots" #[slotName]="slotProps">
                    <slot :name="slotName" v-bind="slotProps" />
                </template>
            </va-data-table>

            <div v-if="resourceData.data.length === 0" class="text-center py-8" style="color: var(--va-secondary)">
                {{ emptyMessage }}
            </div>

            <div v-if="resourceData.last_page > 1" class="flex justify-center mt-6">
                <va-pagination
                    :model-value="resourceData.current_page"
                    :pages="resourceData.last_page"
                    :visible-pages="5"
                    color="primary"
                    @update:modelValue="$emit('page-change', $event)"
                />
            </div>
        </va-card-content>
    </va-card>
</template>

<script setup>
/**
 * Generic Table Component for resources
 * Wraps va-data-table to automatically handle Laravel pagination data structure and empty states.
 */
defineProps({
    resourceData: {
        type: Object,
        required: true,
        validator: (value) => 'data' in value && 'current_page' in value,
    },
    columns: {
        type: Array,
        required: true,
    },
    emptyMessage: {
        type: String,
        default: 'No records found.',
    },
    clickable: {
        type: Boolean,
        default: false,
    },
    loading: {
        type: Boolean,
        default: false,
    }
});

defineEmits(['page-change', 'row:click']);
</script>