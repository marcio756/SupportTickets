<template>
    <AppLayout>
        <Head title="Users Management" />

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Users Management</h1>
            <p class="text-gray-500 text-sm">Create, edit and manage platform users.</p>
        </div>

        <va-card>
            <va-card-content>
                <div class="text-center py-10 text-gray-500">
                    <va-icon name="engineering" size="large" class="mb-4 text-gray-400" />
                    <p>Users list will be implemented here soon.</p>
                </div>
            </va-card-content>
        </va-card>
    </AppLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { VaCard, VaCardContent, VaIcon } from 'vuestic-ui';

defineProps({
    users: {
        type: Object,
        required: true,
    }
});
</script>