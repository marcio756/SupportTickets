<script setup>
/**
 * Professional Landing Page for SupportTickets.
 * Replaces the default Laravel welcome page.
 * Implements full localization to target a wider user base.
 */
import { Head, Link } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import FeatureCard from '@/Components/Welcome/FeatureCard.vue';
import BrandTitle from '@/Components/Common/BrandTitle.vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});
</script>

<template>
    <Head :title="$t('welcome.page_title')" />

    <GuestLayout>
        <div class="text-center py-4">
            <div class="mb-10">
                <BrandTitle />
                <p class="mt-4 text-gray-600 dark:text-gray-400 text-lg leading-relaxed">
                    {{ $t('welcome.subtitle') }}
                </p>
            </div>

            <div class="space-y-4 mb-10 text-left px-2">
                <FeatureCard 
                    icon="confirmation_number"
                    iconColor="primary"
                    iconBgClass="bg-indigo-100 dark:bg-indigo-900/50"
                    :title="$t('welcome.features.ticketing.title')"
                    :description="$t('welcome.features.ticketing.description')"
                />
                
                <FeatureCard 
                    icon="chat"
                    iconColor="success"
                    iconBgClass="bg-green-100 dark:bg-green-900/50"
                    :title="$t('welcome.features.updates.title')"
                    :description="$t('welcome.features.updates.description')"
                />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <template v-if="$page.props.auth?.user">
                    <Link :href="route('dashboard')" class="sm:col-span-2">
                        <PrimaryButton class="w-full justify-center py-3 text-base">
                            {{ $t('welcome.access_dashboard') }}
                        </PrimaryButton>
                    </Link>
                </template>

                <template v-else>
                    <Link :href="route('login')">
                        <PrimaryButton class="w-full justify-center py-3 text-base">
                            {{ $t('welcome.login') }}
                        </PrimaryButton>
                    </Link>
                </template>
            </div>

            <p class="mt-12 text-xs font-mono text-gray-400 dark:text-gray-600 uppercase tracking-widest">
                {{ $t('welcome.footer_version') }}
            </p>
        </div>
    </GuestLayout>
</template>