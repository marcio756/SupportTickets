<script setup>
import { watch, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    customers: {
        type: Array,
        default: () => [],
    }
});

const emit = defineEmits(['close']);

const page = usePage();
const isSupporter = page.props.auth.user.role !== 'customer';

const form = useForm({
    title: '',
    message: '',
    customer_id: '',
    attachment: [],
});

/**
 * Reset form when modal opens
 */
watch(() => props.show, (isShowing) => {
    if (isShowing) {
        form.reset();
        form.clearErrors();
    }
});

/**
 * Format customers array for Vuestic Select
 */
const customerOptions = computed(() => {
    return props.customers.map(customer => ({
        value: customer.id,
        text: customer.name
    }));
});

/**
 * Submits the form handling client-side file limits and payload transformation
 */
const submit = () => {
    form.clearErrors('attachment');

    let fileToUpload = null;

    // Safely extract the file from Vuestic wrapper
    if (form.attachment && form.attachment.length > 0) {
        fileToUpload = form.attachment[0];
        if (fileToUpload && fileToUpload.file instanceof File) {
            fileToUpload = fileToUpload.file;
        }
    }

    // Validate 10MB limit on the client side
    if (fileToUpload) {
        const maxSizeInBytes = 10 * 1024 * 1024;

        if (fileToUpload.size > maxSizeInBytes) {
            form.setError('attachment', 'The selected file is too large. Maximum allowed size is 10MB.');
            return;
        }
    }

    form.transform((data) => {
        return {
            ...data,
            customer_id: data.customer_id?.value || data.customer_id,
            attachment: fileToUpload,
        };
    }).post(route('tickets.store'), {
        forceFormData: true, // Forces multipart/form-data for file uploads
        preserveScroll: true,
        onSuccess: () => closeModal(), // Se falhar, o modal mantém-se aberto com os erros
    });
};

const handleModalUpdate = (value) => {
    if (!value) closeModal();
};

const closeModal = () => {
    emit('close');
    form.reset();
    form.clearErrors();
};
</script>

<template>
    <va-modal
        :modelValue="show"
        @update:modelValue="handleModalUpdate"
        hide-default-actions
        size="large"
    >
        <h3 class="va-h5 mb-6" style="color: var(--va-text-primary)">
            Open a New Support Ticket
        </h3>

        <p class="text-sm mb-6" style="color: var(--va-secondary)">
            Please describe your issue in detail. Our support team will assist you shortly.
        </p>

        <form @submit.prevent="submit" class="flex flex-col gap-5">
            
            <va-select
                v-if="isSupporter"
                v-model="form.customer_id"
                :options="customerOptions"
                label="Select Customer"
                placeholder="Search by customer name..."
                searchable
                text-by="text"
                value-by="value"
                :error="!!form.errors.customer_id"
                :error-messages="form.errors.customer_id"
                required
            >
                <template #prependInner>
                    <va-icon name="person" color="secondary" />
                </template>
            </va-select>

            <va-input
                v-model="form.title"
                label="Subject / Short Description"
                placeholder="e.g. Cannot connect to the database"
                :error="!!form.errors.title"
                :error-messages="form.errors.title"
                required
            />

            <div>
                <div class="mb-2 text-sm font-bold" style="color: var(--va-text-primary);">Detailed Message</div>
                <va-textarea
                    v-model="form.message"
                    placeholder="Explain the steps to reproduce the issue, any error codes, etc."
                    :error="!!form.errors.message"
                    :error-messages="form.errors.message"
                    :min-rows="6"
                    autosize
                    required
                    class="w-full"
                />
            </div>

            <div>
                <div class="mb-2 text-sm font-bold" style="color: var(--va-text-primary);">Attachment (Optional)</div>
                <va-file-upload
                    v-model="form.attachment"
                    dropzone
                    type="single"
                    file-types=".pdf,.jpg,.jpeg,.png,.zip"
                    :error="!!form.errors.attachment"
                    :error-messages="form.errors.attachment"
                    uploadButtonText="Select File"
                    dropzoneText="Drag and drop a file here, or click to browse"
                    class="w-full"
                />
                <p class="text-xs mt-2" style="color: var(--va-secondary)">
                    Maximum file size: 10MB. Allowed types: PDF, JPG, PNG, ZIP.
                </p>
            </div>

            <div class="flex justify-end gap-3 mt-2">
                <va-button preset="secondary" @click="closeModal">Cancel</va-button>
                <va-button type="submit" color="primary" :loading="form.processing">
                    Submit Ticket
                </va-button>
            </div>
        </form>
    </va-modal>
</template>