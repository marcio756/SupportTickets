<template>
    <va-modal :modelValue="show" @update:modelValue="handleModalUpdate" hide-default-actions size="small">
        <h3 class="va-h5 mb-6" style="color: var(--va-text-primary)">{{ $t('vacations.modals.book_title') }}</h3>

        <div v-if="hasErrors" class="mb-4 p-3 rounded text-sm" style="background-color: rgba(228, 34, 34, 0.1); color: var(--va-danger);">
             <ul class="list-disc pl-5">
                <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
             </ul>
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-4">
            <va-input v-model="form.start_date" type="date" :label="$t('vacations.modals.start_date')" :error="!!form.errors.start_date" required />
            <va-input v-model="form.end_date" type="date" :label="$t('vacations.modals.end_date')" :error="!!form.errors.end_date" required />

            <div class="flex justify-end gap-3 mt-4">
                <va-button preset="secondary" @click="closeModal">{{ $t('vacations.modals.cancel') }}</va-button>
                <va-button type="submit" :loading="form.processing">{{ $t('vacations.modals.submit') }}</va-button>
            </div>
        </form>
    </va-modal>
</template>

<script setup>
/**
 * Book Vacation Modal Component.
 * Responsible for submitting personal time-off requests.
 */
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({ show: Boolean });
const emit = defineEmits(['close']);

const form = useForm({
    start_date: '',
    end_date: '',
});

/**
 * Detects presence of active form errors.
 * @returns {boolean}
 */
const hasErrors = computed(() => Object.keys(form.errors).length > 0);

watch(() => props.show, (val) => {
    if (val) {
        form.reset();
        form.clearErrors();
    }
});

const submit = () => {
    form.post(route('vacations.store'), {
        onSuccess: () => closeModal(),
    });
};

const handleModalUpdate = (val) => { if (!val) closeModal(); };
const closeModal = () => emit('close');
</script>