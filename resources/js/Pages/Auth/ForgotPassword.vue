<script setup>
/**
 * ForgotPassword Page Component.
 * Allows users to request a password reset link via email.
 * Fully internationalized using the global vue-i18n instance.
 */
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import BrandTitle from '@/Components/Common/BrandTitle.vue';
import { Head, useForm } from '@inertiajs/vue3';

/**
 * Defines component props.
 * @typedef {Object} Props
 * @property {string} [status] - Success message from the session.
 */
defineProps({
    status: {
        type: String,
    },
});

/**
 * Initializes the form state management using Inertia's utility.
 */
const form = useForm({
    email: '',
});

/**
 * Handles form submission.
 * Sends the request to Laravel's password reset endpoint.
 */
const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('auth.forgot_password.title')" />

        <div class="mb-6 text-center">
            <BrandTitle />
        </div>

        <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ $t('auth.forgot_password.description') }}
        </div>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600 dark:text-green-400">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" :value="$t('auth.forgot_password.email')" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    :placeholder="$t('auth.forgot_password.email_placeholder')"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4 flex items-center justify-end">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    {{ $t('auth.forgot_password.submit') }}
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>