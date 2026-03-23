<template>
    <AppLayout>
        <Head :title="$t('tags.title')" />

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <h1 class="text-2xl font-bold" style="color: var(--va-text-primary)">{{ $t('tags.page_header') }}</h1>
            <va-button 
                v-if="workSessionStatus === 'active'" 
                color="primary" 
                icon="add" 
                @click="openCreateModal"
            >
                {{ $t('tags.add_new') }}
            </va-button>
        </div>

        <template v-if="workSessionStatus !== 'active'">
            <WorkSessionBlocker :session-status="workSessionStatus" />
        </template>

        <template v-else>
            <ResourceFilter v-model:query="query" />

            <ResourceTable
                :resource-data="tags"
                :columns="tableColumns"
                :empty-message="$t('tags.no_tags')"
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
        </template>

        <va-modal
            v-model="isModalOpen"
            :title="editingTag ? $t('tags.form.edit_title') : $t('tags.form.create_title')"
            :ok-text="editingTag ? $t('tags.form.save') : $t('tags.form.create')"
            :cancel-text="$t('common.actions.cancel')"
            @ok="submitForm"
        >
            <div class="flex flex-col gap-4 py-4">
                <va-input
                    v-model="form.name"
                    :label="$t('tags.form.name_label')"
                    :placeholder="$t('tags.form.name_placeholder')"
                    :error="!!form.errors.name"
                    :error-messages="form.errors.name"
                />
                
                <va-color-input
                    v-model="form.color"
                    :label="$t('tags.form.color_label')"
                    :error="!!form.errors.color"
                    :error-messages="form.errors.color"
                />
            </div>
        </va-modal>

        <va-modal
            v-model="isDeleteModalOpen"
            :title="$t('tags.delete.title')"
            :message="$t('tags.delete.message')"
            :ok-text="$t('tags.delete.confirm_btn')"
            :cancel-text="$t('common.actions.cancel')"
            state="danger"
            @ok="executeDelete"
        />
    </AppLayout>
</template>

<script setup>
/**
 * Tags Management Index Component.
 * Interfaces with the tag resource to allow listing, creating, editing and deleting
 * classification tags applied globally across tickets.
 */
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import TagBadge from '@/Components/Common/TagBadge.vue';
import ResourceTable from '@/Components/Common/ResourceTable.vue';
import ResourceFilter from '@/Components/Filters/ResourceFilter.vue';
import WorkSessionBlocker from '@/Components/WorkSession/WorkSessionBlocker.vue';
import { useFilters } from '@/Composables/useFilters.js';

const props = defineProps({
    tags: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    workSessionStatus: { type: String, default: 'active' }
});

const { t } = useI18n();

const isModalOpen = ref(false);
const isDeleteModalOpen = ref(false);
const editingTag = ref(null);
const tagToDelete = ref(null);

// Inject Centralized Filtering Logic
const { query, changePage } = useFilters(props.filters, 'tags.index', props.tags.current_page || 1);

/**
 * Dynamically binds table headers to the i18n instance.
 * @returns {Array<Object>} The table column definition object.
 */
const tableColumns = computed(() => [
    { key: 'preview', label: t('tags.columns.preview') },
    { key: 'name', label: t('tags.columns.name'), sortable: true },
    { key: 'color', label: t('tags.columns.color') },
    { key: 'actions', label: t('tags.columns.actions'), align: 'right' }
]);

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
 * Submits the create or update request to the server based on current modal state.
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

/**
 * Prompts the destructive deletion overlay for the selected tag.
 * @param {Object} tag - The tag reference to flag for deletion.
 */
const confirmDelete = (tag) => {
    tagToDelete.value = tag;
    isDeleteModalOpen.value = true;
};

/**
 * Confirms and executes the actual API request to delete the tag from the database.
 */
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