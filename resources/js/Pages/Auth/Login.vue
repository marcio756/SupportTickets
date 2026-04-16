<script setup>
/**
 * Authentication Login Component.
 * Handles the user login UI and payload submission to the authentication endpoint.
 * Fully internationalized using the global vue-i18n instance.
 */
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

/**
 * Submits the login form payload to the server.
 * Ensures the password field is cleared from state upon request completion for security.
 */
const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('auth.login.title')" />

        <div v-if="status" class="mb-4 font-medium text-sm text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div>
                <InputLabel for="email" :value="$t('auth.login.email')" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full transition-opacity duration-300"
                    :class="{ 'opacity-60': form.processing }"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    :disabled="form.processing"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" :value="$t('auth.login.password')" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full transition-opacity duration-300"
                    :class="{ 'opacity-60': form.processing }"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    :disabled="form.processing"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="block mt-4">
                <label class="flex items-center w-fit cursor-pointer" :class="{ 'opacity-60 pointer-events-none': form.processing }">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-3 text-sm text-gray-600 dark:text-gray-400 select-none">{{ $t('auth.login.remember_me') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-between mt-6">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800 transition-colors"
                >
                    {{ $t('auth.login.forgot_password') }}
                </Link>

                <PrimaryButton class="ms-4 py-2.5 px-6 relative overflow-hidden transition-all duration-300" :class="{ 'opacity-80 pointer-events-none scale-95': form.processing }" :disabled="form.processing">
                    <span :class="{ 'opacity-0': form.processing }" class="transition-opacity duration-200">{{ $t('auth.login.submit') }}</span>
                    <div v-if="form.processing" class="absolute inset-0 flex items-center justify-center">
                        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>