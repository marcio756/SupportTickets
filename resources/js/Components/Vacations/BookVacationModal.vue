<template>
    <Modal :show="show" @close="closeModal" max-width="md">
        <div class="p-6 bg-white dark:bg-background-secondary border dark:border-gray-800 rounded-lg">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                Book Vacation
            </h2>

            <form @submit.prevent="submit">
                <div v-if="formErrors" class="mb-4 p-3 bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded text-sm">
                    <ul class="list-disc pl-5">
                        <li v-for="(error, key) in formErrors" :key="key">
                            {{ Array.isArray(error) ? error[0] : error }}
                        </li>
                    </ul>
                </div>

                <div class="mb-4">
                    <InputLabel for="start_date" value="Start Date" />
                    <TextInput
                        id="start_date"
                        type="date"
                        class="mt-1 block w-full dark:bg-gray-900 dark:border-gray-700"
                        v-model="form.start_date"
                        required
                    />
                </div>

                <div class="mb-6">
                    <InputLabel for="end_date" value="End Date" />
                    <TextInput
                        id="end_date"
                        type="date"
                        class="mt-1 block w-full dark:bg-gray-900 dark:border-gray-700"
                        v-model="form.end_date"
                        required
                    />
                </div>

                <div class="flex justify-end space-x-3">
                    <SecondaryButton @click="closeModal" :disabled="isLoading">
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton type="submit" :disabled="isLoading">
                        <span v-if="isLoading">Booking...</span>
                        <span v-else>Submit Request</span>
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>

<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useVacations } from '@/Composables/useVacations.js';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'success']);

const { bookVacation, isLoading, errors } = useVacations();

const form = ref({
    start_date: '',
    end_date: '',
});

const formErrors = ref(null);

watch(() => props.show, (newVal) => {
    if (newVal) {
        form.value.start_date = '';
        form.value.end_date = '';
        formErrors.value = null;
    }
});

const submit = async () => {
    formErrors.value = null;
    const success = await bookVacation(form.value.start_date, form.value.end_date);
    
    if (success) {
        emit('success');
        closeModal();
    } else {
        formErrors.value = errors.value;
    }
};

const closeModal = () => {
    emit('close');
};
</script>