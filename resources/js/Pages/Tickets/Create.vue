<template>
    <AppLayout>
        <Head title="Open New Ticket" />

        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Open a New Ticket</h1>
            <p class="text-gray-500 text-sm">Please describe your issue in detail.</p>
        </div>

        <va-card class="max-w-2xl">
            <va-card-content>
                <form @submit.prevent="submit">
                    <div class="mb-4">
                        <va-input
                            v-model="form.title"
                            label="Ticket Subject"
                            placeholder="Briefly summarize the issue"
                            class="w-full"
                            :error="!!form.errors.title"
                            :error-messages="form.errors.title"
                        />
                    </div>

                    <div class="mb-6">
                        <va-textarea
                            v-model="form.message"
                            label="Initial Message"
                            placeholder="Provide as much detail as possible..."
                            class="w-full"
                            :min-rows="5"
                            :error="!!form.errors.message"
                            :error-messages="form.errors.message"
                        />
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('tickets.index')">
                            <va-button preset="secondary" color="gray">Cancel</va-button>
                        </Link>
                        <va-button 
                            type="submit" 
                            color="primary" 
                            :loading="form.processing"
                        >
                            Submit Ticket
                        </va-button>
                    </div>
                </form>
            </va-card-content>
        </va-card>
    </AppLayout>
</template>

<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { VaCard, VaCardContent, VaInput, VaTextarea, VaButton } from 'vuestic-ui';

/**
 * Inertia form handler ensures reactive validation and state management.
 */
const form = useForm({
    title: '',
    message: '',
});

/**
 * Submits the payload to the server.
 */
const submit = () => {
    form.post(route('tickets.store'));
};
</script>