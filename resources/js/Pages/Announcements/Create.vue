<template>
  <AppLayout :title="t('announcements.title')">
    <template #header>
      <div class="flex items-center justify-between gap-4">
        <h2 class="font-extrabold text-3xl text-gray-950 dark:text-gray-50 tracking-tighter">
          {{ t('announcements.title') }}
        </h2>
        
        <va-button 
          preset="primary"
          icon="send"
          :loading="form.processing"
          :disabled="form.processing || form.customer_ids.length === 0"
          @click="submit"
          class="shadow-lg shadow-indigo-500/20"
        >
          {{ form.processing ? t('announcements.btn_sending') : t('announcements.btn_send') }}
        </va-button>
      </div>
    </template>

    <div class="py-8">
      <div class="max-w-[1600px] mx-auto sm:px-6 lg:px-8">
        
        <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-3 gap-8 items-start">
          
          <div class="md:col-span-2 space-y-8">
            <va-card outlined class="!border-gray-200 dark:!border-gray-800 !bg-white dark:!bg-gray-900 !rounded-2xl shadow-sm">
              <va-card-title class="flex items-center gap-3 !pt-6 !pb-2">
                <va-icon name="edit_note" color="indigo" size="large" />
                <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ t('announcements.step_2') }}</h3>
              </va-card-title>
              
              <va-card-content class="space-y-6 !pb-8">
                <div>
                  <va-input
                    v-model="form.subject"
                    :label="t('announcements.subject_label')"
                    pattern="text"
                    required
                    autofocus
                    class="w-full text-lg"
                    :error="!!form.errors.subject"
                    :error-messages="form.errors.subject"
                    color="indigo"
                    bordered
                  >
                    <template #prependInner>
                      <va-icon name="mail_outline" color="gray" size="small" />
                    </template>
                  </va-input>
                </div>

                <div>
                  <InputLabel :value="t('announcements.body_label')" class="mb-2.5 text-gray-700 dark:text-gray-300 font-semibold" />
                  <RichTextEditor v-model="form.content" :error="!!form.errors.content" />
                  <InputError :message="form.errors.content" class="mt-2" />
                </div>
              </va-card-content>
            </va-card>
          </div>

          <div class="md:col-span-1">
            <va-card outlined class="!border-gray-200 dark:!border-gray-800 !bg-white dark:!bg-gray-900 !rounded-2xl shadow-sm">
               <va-card-title class="flex items-center justify-between gap-3 !pt-6 !pb-2">
                 <div class="flex items-center gap-3">
                   <va-icon name="group_add" color="indigo" size="large" />
                   <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ t('announcements.step_1') }}</h3>
                 </div>
                 
                 <div class="px-3 py-1 rounded-full bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 font-mono text-sm font-bold shadow-inner">
                    {{ form.customer_ids.length }}
                 </div>
               </va-card-title>

               <va-card-content class="!pb-6">
                 <CustomerSelector 
                    :customers="customers" 
                    v-model="form.customer_ids" 
                    :error="!!form.errors.customer_ids"
                 />
                 <InputError :message="form.errors.customer_ids" class="mt-3" />
               </va-card-content>
            </va-card>
          </div>
          
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import RichTextEditor from '@/Components/Common/RichTextEditor.vue';
import CustomerSelector from '@/Components/Common/CustomerSelector.vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useToast } from 'vuestic-ui';

defineProps({
  customers: { type: Array, required: true }
});

const { t } = useI18n();
const { init: initToast } = useToast();

const form = useForm({
  subject: '',
  content: '',
  customer_ids: [],
});

/**
 * Triggers the form submission process using Inertia.
 * Automatically dispatches a system-wide Vuestic toast on success.
 */
const submit = () => {
  form.post(route('announcements.store'), {
    preserveScroll: true,
    onSuccess: (page) => {
      const flashMessage = page.props.flash?.success;
      
      const displayMessage = (flashMessage && flashMessage !== 'announcements.sent_successfully') 
        ? flashMessage 
        : t('announcements.sent_successfully');

      initToast({
        message: displayMessage,
        color: 'success',
        position: 'bottom-right',
        icon: 'check_circle',
        duration: 5000
      });
      
      form.reset('subject', 'content', 'customer_ids');
    },
  });
};
</script>

<style>
/* Substituição do @apply por CSS nativo para resolver os avisos do linter */
.va-input__label {
    font-weight: 600 !important;
    color: #374151 !important; /* equivalente ao text-gray-700 */
}

.dark .va-input__label {
    color: #d1d5db !important; /* equivalente ao text-gray-300 */
}

.va-input-wrapper--bordered .va-input-wrapper__field {
    border-radius: 0.75rem !important; /* equivalente ao rounded-xl */
    border-color: #e5e7eb !important; /* equivalente ao border-gray-200 */
}

.dark .va-input-wrapper--bordered .va-input-wrapper__field {
    border-color: #374151 !important; /* equivalente ao border-gray-700 */
}
</style>