<script setup>
/**
 * Professional Landing Page for SupportTickets.
 * Replaces the default Laravel welcome page.
 */
import { Head, Link } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import FeatureCard from '@/Components/Welcome/FeatureCard.vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});
</script>

<template>
    <Head title="Welcome to SupportTickets" />

    <GuestLayout>
        <div class="text-center py-4">
            <div class="mb-10">
                <h1 class="text-4xl font-black text-gray-900 dark:text-white tracking-tight uppercase">
                    Support<span class="text-yellow-500">Tickets</span>
                </h1>
                <p class="mt-4 text-gray-600 dark:text-gray-400 text-lg leading-relaxed">
                    The centralized platform for all your technical support needs. 
                    Manage tickets, track progress, and communicate with ease.
                </p>
            </div>

            <div class="space-y-4 mb-10 text-left px-2">
                <FeatureCard 
                    icon="confirmation_number"
                    iconColor="primary"
                    iconBgClass="bg-indigo-100 dark:bg-indigo-900/50"
                    title="Smart Ticketing"
                    description="Organize and prioritize requests efficiently."
                />
                
                <FeatureCard 
                    icon="chat"
                    iconColor="success"
                    iconBgClass="bg-green-100 dark:bg-green-900/50"
                    title="Real-time Updates"
                    description="Get notified the moment a supporter responds."
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <template v-if="$page.props.auth?.user">
                    <Link :href="route('dashboard')" class="sm:col-span-2">
                        <PrimaryButton class="w-full justify-center py-3 text-base">
                            Access Dashboard
                        </PrimaryButton>
                    </Link>
                </template>

                <template v-else>
                    <Link :href="route('login')">
                        <PrimaryButton class="w-full justify-center py-3 text-base">
                            Login
                        </PrimaryButton>
                    </Link>
                </template>
            </div>

            <p class="mt-12 text-xs font-mono text-gray-400 dark:text-gray-600 uppercase tracking-widest">
                SupportTickets System v1.0.0
            </p>
        </div>
    </GuestLayout>
</template>