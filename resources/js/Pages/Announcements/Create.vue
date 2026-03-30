<template>
  <AppLayout :title="t('announcements.title')">
    <template #header>
      <h2 class="font-bold text-2xl text-gray-800 dark:text-gray-100 tracking-tight">
        {{ t('announcements.title') }}
      </h2>
    </template>

    <div class="py-10">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
        
        <form @submit.prevent="submit" class="bg-white dark:bg-gray-800 shadow-xl shadow-gray-200/50 dark:shadow-gray-900/50 sm:rounded-2xl overflow-hidden border border-gray-100 dark:border-gray-700">
          <div class="p-8 text-gray-900 dark:text-gray-100 space-y-10">
            
            <section class="space-y-4">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 flex items-center justify-center font-bold text-sm">1</div>
                <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-100">{{ t('announcements.step_1') }}</h3>
              </div>
              <div class="pl-10">
                <CustomerSelector 
                  :customers="customers" 
                  v-model="form.customer_ids" 
                />
                <InputError :message="form.errors.customer_ids" class="mt-2" />
              </div>
            </section>

            <div class="h-px bg-gray-100 dark:bg-gray-700 ml-10"></div>

            <section class="space-y-4">
               <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 flex items-center justify-center font-bold text-sm">2</div>
                <h3 class="text-xl font-semibold text-gray-800 dark:text-gray-100">{{ t('announcements.step_2') }}</h3>
              </div>
              
              <div class="pl-10 space-y-6">
                <div>
                  <InputLabel for="subject" :value="t('announcements.subject_label')" class="mb-1.5" />
                  <TextInput
                    id="subject"
                    type="text"
                    class="block w-full text-lg py-2.5 transition-shadow focus:ring-2 focus:ring-indigo-200"
                    v-model="form.subject"
                    required
                  />
                  <InputError :message="form.errors.subject" class="mt-2" />
                </div>

                <div>
                  <InputLabel :value="t('announcements.body_label')" class="mb-1.5" />
                  <RichTextEditor v-model="form.content" />
                  <InputError :message="form.errors.content" class="mt-2" />
                </div>
              </div>
            </section>
          </div>

          <div class="bg-gray-50 dark:bg-gray-800/80 px-8 py-5 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
              <transition name="fade">
                <span v-if="form.customer_ids.length > 0">
                  {{ t('announcements.customers_selected', { count: form.customer_ids.length }) }}
                </span>
              </transition>
            </div>
            
            <PrimaryButton 
              class="px-6 py-2.5 text-sm transition-all duration-300 transform"
              :class="{ 'opacity-75 cursor-wait scale-95': form.processing, 'hover:-translate-y-0.5 shadow-md': !form.processing }" 
              :disabled="form.processing || form.customer_ids.length === 0"
            >
              <va-icon v-if="form.processing" name="sync" class="mr-2 animate-spin" size="small" />
              <va-icon v-else name="send" class="mr-2" size="small" />
              {{ form.processing ? t('announcements.btn_sending') : t('announcements.btn_send') }}
            </PrimaryButton>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
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
      // Obtém a mensagem do flash ou usa a chave padrão
      const flashMessage = page.props.flash?.success;
      
      // Lógica de Tradução Inteligente:
      // Se a mensagem for a chave técnica ou não existir, traduzimos no frontend.
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

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>