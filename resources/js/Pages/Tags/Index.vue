<template>
    <AppLayout>
        <Head title="Manage Tags" />

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <h1 class="text-2xl font-bold" style="color: var(--va-text-primary)">Category Tags</h1>
            <va-button color="primary" icon="add" @click="openCreateModal">
                Create New Tag
            </va-button>
        </div>

        <va-card>
            <va-card-content>
                <div v-if="tags.length === 0" class="text-center py-8 text-gray-500">
                    No tags have been created yet.
                </div>
                
                <div class="overflow-x-auto" v-else>
                    <table class="va-table w-full">
                        <thead>
                            <tr>
                                <th>Preview</th>
                                <th>Name</th>
                                <th>Color Code</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="tag in tags" :key="tag.id">
                                <td>
                                    <TagBadge :tag="tag" />
                                </td>
                                <td>{{ tag.name }}</td>
                                <td class="font-mono text-sm text-gray-500">{{ tag.color }}</td>
                                <td class="text-right">
                                    <va-button preset="plain" icon="edit" color="secondary" @click="openEditModal(tag)" class="mr-2" />
                                    <va-button preset="plain" icon="delete" color="danger" @click="confirmDelete(tag)" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </va-card-content>
        </va-card>

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
import { VaCard, VaCardContent, VaButton, VaModal, VaInput, VaColorInput } from 'vuestic-ui';

defineProps({
    tags: {
        type: Array,
        required: true
    }
});

const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingTag = ref(null);
const tagToDelete = ref(null);

const form = useForm({
    name: '',
    color: '#3B82F6' // Default to a blueish tone
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