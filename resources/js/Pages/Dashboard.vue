<template>
    <AppLayout>
        <Head title="Dashboard" />

        <div class="dashboard-header mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Welcome, {{ $page.props.auth.user.name }}!</h1>
            <p class="text-gray-500">Here is your daily overview.</p>
        </div>

        <va-card>
            <va-card-content>
                You are logged in as a <strong>{{ $page.props.auth.user.role }}</strong>.
                <br /><br />
                We will build the metrics and charts here in Step 6.
            </va-card-content>
        </va-card>
    </AppLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { VaCard, VaCardContent } from 'vuestic-ui';
</script>