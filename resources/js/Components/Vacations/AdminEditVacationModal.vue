<template>
    <va-modal :modelValue="show" @update:modelValue="handleModalUpdate" hide-default-actions size="small">
        <h3 class="va-h5 mb-6" style="color: var(--va-text-primary)">
            Edit Vacation Request
        </h3>

        <div v-if="hasErrors" class="mb-4 p-3 rounded text-sm" style="background-color: rgba(228, 34, 34, 0.1); color: var(--va-danger);">
             <ul class="list-disc pl-5">
                <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
             </ul>
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-4">
            <va-input v-model="form.start_date" type="date" label="Start Date" :error="!!form.errors.start_date" required />
            <va-input v-model="form.end_date" type="date" label="End Date" :error="!!form.errors.end_date" required />
            
            <va-select 
                v-model="form.status" 
                :options="['pending', 'approved', 'rejected']" 
                label="Request Status" 
                required 
            />

            <div class="flex justify-end gap-3 mt-4">
                <va-button preset="secondary" @click="closeModal">Cancel</va-button>
                <va-button type="submit" :loading="form.processing">Save Changes</va-button>
            </div>
        </form>
    </va-modal>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({ show: Boolean, vacation: Object });
const emit = defineEmits(['close']);

const form = useForm({
    start_date: '',
    end_date: '',
    status: 'pending'
});

const hasErrors = computed(() => Object.keys(form.errors).length > 0);

watch(() => props.show, (val) => {
    if (val && props.vacation) {
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