<script setup>
/**
 * Reset Password Component.
 * Provides the interface for users to define a new password using a valid reset token.
 * Fully internationalized using the global vue-i18n instance.
 */
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

/**
 * Defines component properties required for the reset password process.
 */
const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

/**
 * Initializes the form state, pre-filling the token and email.
 */
const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

/**
 * Submits the new password payload to the server.
 * Ensures password fields are cleared from the state upon completion.
 */
const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('auth.reset_password.title')" />

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" :value="$t('auth.reset_password.email')" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" :value="$t('auth.reset_password.password')" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel
                    for="password_confirmation"
                    :value="$t('auth.reset_password.password_confirmation')"
                />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError
                    class="mt-2"
                    :message="form.errors.password_confirmation"
                />
            </div>

            <div class="mt-4 flex items-center justify-end">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ $t('auth.reset_password.submit') }}
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>