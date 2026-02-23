<script setup>
/**
 * Custom Welcome/Landing page for the SupportTickets application.
 * Replaces the default Laravel welcome page.
 */
import { Head, Link } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});
</script>

<template>
    <Head title="Welcome to SupportTickets" />

    <GuestLayout>
        <div class="text-center">
            <div class="mb-8">
                <h1 class="text-4xl font-extrabold text-gray-900 dark:text-white sm:text-5xl">
                    Smart Support <span class="text-indigo-600 dark:text-indigo-400">Simplified</span>
                </h1>
                <p class="mt-4 text-lg text-gray-600 dark:text-gray-400">
                    Manage your customer requests efficiently with our professional ticketing system. 
                    Fast, reliable, and always synchronized.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 mb-8 text-left">
                <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-100 dark:border-gray-700">
                    <h3 class="font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <span class="text-indigo-500">✓</span> Real-time Communication
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Chat with supporters instantly regarding your issues.</p>
                </div>
                <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-100 dark:border-gray-700">
                    <h3 class="font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <span class="text-indigo-500">✓</span> Track Everything
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Monitor the status of your tickets from open to resolved.</p>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row justify-center gap-4">
                <template v-if="$page.props.auth.user">
                    <Link :href="route('dashboard')" class="w-full sm:w-auto">
                        <PrimaryButton class="w-full justify-center py-3 px-8 text-base">
                            Go to Dashboard
                        </PrimaryButton>
                    </Link>
                </template>

                <template v-else>
                    <Link :href="route('login')" class="w-full sm:w-auto">
                        <PrimaryButton class="w-full justify-center py-3 px-8 text-base">
                            Log in
                        </PrimaryButton>
                    </Link>
                </template>
            </div>

            <p class="mt-8 text-xs text-gray-500 dark:text-gray-500 italic">
                SupportTickets v1.0.0 &copy; 2026
            </p>
        </div>
    </GuestLayout>
</template>