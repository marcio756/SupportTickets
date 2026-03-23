<script setup>
/**
 * User Delete Modal Component.
 * Responsible for confirming the safe deactivation (soft delete) of users.
 * Requires the administrator's password to prevent accidental security breaches.
 */
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    user: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close']);

const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
});

/**
 * Submits the form to deactivate the selected user.
 */
const deleteUser = () => {
    if (!props.user) return;

    form.delete(route('users.destroy', props.user.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => {
            currentPasswordInput.value?.focus();
        },
    });
};

/**
 * Handle VaModal modelValue updates (e.g., clicking outside to close).
 * @param {Boolean} value 
 */
const handleModalUpdate = (value) => {
    if (!value) closeModal();
};

/**
 * Close the modal and reset form state.
 */
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
        size="small"
    >
        <h3 class="va-h5 mb-3" style="color: var(--va-text-primary)">
            {{ $t('users.delete.title') }}
        </h3>

        <p class="mb-4 leading-relaxed" style="color: var(--va-secondary)">
            {{ $t('users.delete.confirm_msg') }} <strong>{{ user?.name }}</strong>?
            <br>{{ $t('users.delete.warning_msg') }}
        </p>

        <p class="text-sm mb-4" style="color: var(--va-secondary)">
            {{ $t('users.delete.password_prompt') }}
        </p>

        <form @submit.prevent="deleteUser" class="flex flex-col gap-4">
            <va-input
                ref="currentPasswordInput"
                v-model="form.current_password"
                type="password"
                :label="$t('users.delete.password_label')"
                :error="!!form.errors.current_password"
                :error-messages="form.errors.current_password"
                required
            />

            <div class="flex justify-end gap-3 mt-2">
                <va-button preset="secondary" @click="closeModal">{{ $t('common.actions.cancel') }}</va-button>
                <va-button color="danger" type="submit" :loading="form.processing" :disabled="form.processing">
                    {{ $t('users.delete.submit_btn') }}
                </va-button>
            </div>
        </form>
    </va-modal>
</template>