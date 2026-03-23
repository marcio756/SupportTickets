<template>
    <va-modal :modelValue="show" @update:modelValue="handleModalUpdate" hide-default-actions size="small">
        <h3 class="va-h5 mb-6" style="color: var(--va-text-primary)">
            {{ $t('vacations.modals.edit_title') }}
        </h3>

        <div v-if="hasErrors" class="mb-4 p-3 rounded text-sm" style="background-color: rgba(228, 34, 34, 0.1); color: var(--va-danger);">
             <ul class="list-disc pl-5">
                <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
             </ul>
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-4">
            <va-input 
                v-model="form.start_date" 
                type="date" 
                :label="$t('vacations.modals.start_date')" 
                :error="!!form.errors.start_date" 
                required 
            />
            
            <va-input 
                v-model="form.end_date" 
                type="date" 
                :label="$t('vacations.modals.end_date')" 
                :error="!!form.errors.end_date" 
                required 
            />
            
            <va-select 
                v-model="form.status" 
                :options="statusOptions" 
                value-by="value"
                text-by="text"
                :label="$t('vacations.modals.status')" 
                required 
            />

            <div class="flex justify-end gap-3 mt-4">
                <va-button preset="secondary" @click="closeModal">{{ $t('vacations.modals.cancel') }}</va-button>
                <va-button type="submit" :loading="form.processing">{{ $t('vacations.modals.save') }}</va-button>
            </div>
        </form>
    </va-modal>
</template>

<script setup>
/**
 * Administrative Edit Vacation Modal Component.
 * Grants superusers the ability to manually override dates or toggle approval states.
 */
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({ 
    show: Boolean, 
    vacation: Object 
});

const emit = defineEmits(['close']);
const { t } = useI18n();

const form = useForm({
    start_date: '',
    end_date: '',
    status: 'pending'
});

/**
 * Transformed array structure to render localized names in dropdown
 * while enforcing strict system values for submissions.
 * @returns {Array<{text: string, value: string}>}
 */
const statusOptions = computed(() => [
    { text: t('vacations.filters.pending'), value: 'pending' },
    { text: t('vacations.filters.approved'), value: 'approved' },
    { text: t('vacations.filters.rejected'), value: 'rejected' },
    { text: t('vacations.filters.completed'), value: 'completed' }
]);

/**
 * Detects presence of active form errors.
 * @returns {boolean}
 */
const hasErrors = computed(() => Object.keys(form.errors).length > 0);

// Pre-populates the internal form payload when the modal opens
watch(() => props.show, (val) => {
    if (val && props.vacation) {
        // Enforces strict YYYY-MM-DD parsing for native HTML5 input constraints
        form.start_date = props.vacation.start_date.substring(0, 10);
        form.end_date = props.vacation.end_date.substring(0, 10);
        form.status = props.vacation.status;
        form.clearErrors();
    }
});

const submit = () => {
    form.put(route('vacations.update', props.vacation.id), {
        onSuccess: () => closeModal(),
    });
};

const handleModalUpdate = (val) => { if (!val) closeModal(); };
const closeModal = () => emit('close');
</script>