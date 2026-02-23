<template>
  <AppLayout>
    <Head title="Create Ticket" />

    <div class="max-w-3xl mx-auto">
      <div class="mb-6">
        <Link :href="route('tickets.index')" class="text-[var(--va-primary)] hover:underline text-sm flex items-center gap-1">
          <va-icon name="arrow_back" size="small" /> Back to Tickets
        </Link>
        <h1 class="text-2xl font-bold mt-4 text-[var(--va-dark)]">Open a New Support Ticket</h1>
        <p class="text-gray-500 text-sm mt-1">Please describe your issue in detail. Our support team will assist you shortly.</p>
      </div>

      <va-card>
        <va-card-content>
          <form @submit.prevent="submit">
            
            <div v-if="isSupporter" class="mb-4">
              <va-select
                v-model="form.customer_id"
                :options="customerOptions"
                label="Select Customer"
                placeholder="Search by customer name..."
                searchable
                text-by="text"
                value-by="value"
                :error="!!form.errors.customer_id"
                :error-messages="form.errors.customer_id"
                class="w-full"
                required
              >
                <template #prependInner>
                  <va-icon name="person" color="secondary" />
                </template>
              </va-select>
            </div>

            <div class="mb-4">
              <va-input
                v-model="form.title"
                label="Subject / Short Description"
                placeholder="e.g. Cannot connect to the database"
                :error="!!form.errors.title"
                :error-messages="form.errors.title"
                class="w-full"
                required
              />
            </div>

            <div class="mb-6">
              <div class="va-title mb-2 text-sm font-bold" style="color: var(--va-dark);">Detailed Message</div>
              <va-textarea
                v-model="form.message"
                placeholder="Explain the steps to reproduce the issue, any error codes, etc."
                :error="!!form.errors.message"
                :error-messages="form.errors.message"
                class="w-full"
                :min-rows="6"
                autosize
                required
              />
            </div>

            <div class="flex justify-end gap-3 mt-6">
              <va-button preset="secondary" @click="cancel">Cancel</va-button>
              <va-button type="submit" color="primary" :loading="form.processing">
                Submit Ticket
              </va-button>
            </div>
          </form>
        </va-card-content>
      </va-card>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  customers: {
    type: Array,
    default: () => [],
  },
});

const page = usePage();
const isSupporter = page.props.auth.user.role !== 'customer';

const form = useForm({
  title: '',
  message: '',
  customer_id: '',
});

/**
 * Adapts raw backend data for Vuestic select
 */
const customerOptions = computed(() => {
  return props.customers.map(customer => ({
    value: customer.id,
    text: customer.name
  }));
});

/**
 * Submits the ticket without attachment logic
 */
const submit = () => {
  form.transform((data) => {
    return {
      ...data,
      customer_id: data.customer_id?.value || data.customer_id,
    };
  }).post(route('tickets.store'), {
    preserveScroll: true,
  });
};

const cancel = () => {
  router.get(route('tickets.index'));
};
</script>