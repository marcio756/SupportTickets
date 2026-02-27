<template>
    <AppLayout>
        <Head title="Manage Tags" />

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <h1 class="text-2xl font-bold" style="color: var(--va-text-primary)">Category Tags</h1>
            <va-button color="primary" icon="add" @click="openCreateModal">
                Create New Tag
            </va-button>
        </div>

        <ResourceFilter v-model:query="query" />

        <ResourceTable
            :resource-data="tags"
            :columns="tableColumns"
            empty-message="No tags found matching your criteria."
            @page-change="changePage"
        >
            <template #cell(preview)="{ rowData }">
                <TagBadge :tag="rowData" />
            </template>

            <template #cell(color)="{ rowData }">
                <span class="font-mono text-sm text-gray-500">{{ rowData.color }}</span>
            </template>

            <template #cell(actions)="{ rowData }">
                <div class="flex justify-end gap-2">
                    <va-button preset="plain" icon="edit" color="secondary" @click="openEditModal(rowData)" />
                    <va-button preset="plain" icon="delete" color="danger" @click="confirmDelete(rowData)" />
                </div>
            </template>
        </ResourceTable>

        <va-modal
            v-model="isModalOpen"
            :title="editingTag ? 'Edit Tag' : 'Create Tag'"
            :ok-text="editingTag ? 'Save Changes' : 'Create'"
            cancel-text="Cancel"
            @ok="submitForm"
        >
            <div class="flex flex-col gap-4 py-4">
                <va-input
                    v-model="form.name"
                    label="Tag Name"
                    placeholder="e.g., Bug, Urgent, Billing"
                    :error="!!form.errors.name"
                    :error-messages="form.errors.name"
                />
                
                <va-color-input
                    v-model="form.color"
                    label="Tag Color"
                    :error="!!form.errors.color"
                    :error-messages="form.errors.color"
                />
            </div>
        </va-modal>

        <va-modal
            v-model="isDeleteModalOpen"
            title="Delete Tag"
            message="Are you sure you want to delete this tag? This action will remove it from all associated tickets."
            ok-text="Delete"
            cancel-text="Cancel"
            state="danger"
            @ok="executeDelete"
        />
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TagBadge from '@/Components/Common/TagBadge.vue';
import ResourceTable from '@/Components/Common/ResourceTable.vue';
import ResourceFilter from '@/Components/Filters/ResourceFilter.vue';
import { useFilters } from '@/Composables/useFilters.js';
import { VaButton, VaModal, VaInput, VaColorInput } from 'vuestic-ui';

const props = defineProps({
    tags: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) }
});

const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingTag = ref(null);
const tagToDelete = ref(null);

// Inject Centralized Filtering Logic
const { query, changePage } = useFilters(props.filters, 'tags.index', props.tags.current_page);

const tableColumns = [
    { key: 'preview', label: 'Preview' },
    { key: 'name', label: 'Name', sortable: true },
    { key: 'color', label: 'Color Code' },
    { key: 'actions', label: 'Actions', align: 'right' }
];

const form = useForm({
    name: '',
    color: '#3B82F6' 
});

/**
 * Initializes and opens the modal for creating a new tag.
 */
const openCreateModal = () => {
    editingTag.value = null;
    form.reset();
    form.clearErrors();
    isModalOpen.value = true;
};

/**
 * Initializes and opens the modal for editing an existing tag.
 * @param {Object} tag - The tag to be edited.
 */
const openEditModal = (tag) => {
    editingTag.value = tag;
    form.name = tag.name;
    form.color = tag.color;
    form.clearErrors();
    isModalOpen.value = true;
};

/**
 * Submits the create or update request to the server.
 */
const submitForm = () => {
    if (editingTag.value) {
        form.put(route('tags.update', editingTag.value.id), {
            onSuccess: () => isModalOpen.value = false
        });
    } else {
        form.post(route('tags.store'), {
            onSuccess: () => isModalOpen.value = false
        });
    }
};

const confirmDelete = (tag) => {
    tagToDelete.value = tag;
    isDeleteModalOpen.value = true;
};

const executeDelete = () => {
    if (tagToDelete.value) {
        form.delete(route('tags.destroy', tagToDelete.value.id), {
            onSuccess: () => {
                isDeleteModalOpen.value = false;
                tagToDelete.value = null;
            }
        });
    }
};
</script>