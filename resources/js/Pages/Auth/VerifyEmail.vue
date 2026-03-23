<script setup>
/**
 * Verify Email Component.
 * Prompts the user to verify their email address before accessing the application.
 * Fully internationalized using the global vue-i18n instance.
 */
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

/**
 * Defines component properties to receive flash messages from the server.
 */
const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

/**
 * Requests a new verification email link from the server.
 */
const submit = () => {
    form.post(route('verification.send'));
};

/**
 * Evaluates if a new verification link was successfully dispatched.
 * @returns {boolean}
 */
const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head :title="$t('auth.verify_email.title')" />

        <div class="mb-4 text-sm text-gray-600">
            {{ $t('auth.verify_email.description') }}
        </div>

        <div
            class="mb-4 text-sm font-medium text-green-600"
            v-if="verificationLinkSent"
        >
            {{ $t('auth.verify_email.link_sent') }}
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex items-center justify-between">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ $t('auth.verify_email.resend') }}
                </PrimaryButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    {{ $t('auth.verify_email.logout') }}
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>