<script setup>
/**
 * Delete User Form Component.
 * Handles the destructive action of permanently removing a user account.
 * Requires password confirmation within a modal to prevent accidental deletions.
 */
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

/**
 * Initiates the deletion process by opening the confirmation modal
 * and focusing the password input for quick user action.
 */
const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

/**
 * Submits the deletion request to the server.
 * On validation error, refocuses the password input. On success, closes the modal and resets state.
 */
const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

/**
 * Cancels the deletion flow, closing the modal and clearing any sensitive
 * input data or validation errors from the form object.
 */
const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ $t('profile.delete_account.title') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ $t('profile.delete_account.description') }}
            </p>
        </header>

        <DangerButton @click="confirmUserDeletion">{{ $t('profile.delete_account.title') }}</DangerButton>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                    {{ $t('profile.delete_account.modal_title') }}
                </h2>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ $t('profile.delete_account.modal_description') }}
                </p>

                <div class="mt-6">
                    <InputLabel
                        for="password"
                        :value="$t('profile.delete_account.password')"
                        class="sr-only"
                    />

                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-3/4"
                        :placeholder="$t('profile.delete_account.password')"
                        @keyup.enter="deleteUser"
                    />

                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeModal">
                        {{ $t('profile.delete_account.cancel') }}
                    </SecondaryButton>

                    <DangerButton
                        class="ms-3"
                        :class="{ 'opacity-25': form.processing }"
                        :disabled="form.processing"
                        @click="deleteUser"
                    >
                        {{ $t('profile.delete_account.confirm') }}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </section>
</template>