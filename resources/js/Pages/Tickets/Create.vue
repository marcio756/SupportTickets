<template>
  <AppLayout>
    <Head :title="$t('tickets.create.page_title')" />

    <div class="max-w-3xl mx-auto">
      <div class="mb-6">
        <Link :href="route('tickets.index')" class="text-[var(--va-primary)] hover:underline text-sm flex items-center gap-1">
          <va-icon name="arrow_back" size="small" /> {{ $t('tickets.create.back') }}
        </Link>
        <h1 class="text-2xl font-bold mt-4 text-[var(--va-dark)]">{{ $t('tickets.create.title') }}</h1>
        <p class="text-gray-500 text-sm mt-1">{{ $t('tickets.create.subtitle') }}</p>
      </div>

      <va-card>
        <va-card-content>
          <form @submit.prevent="submit">
            
            <div v-if="isSupporter" class="mb-4">
              <va-select
                v-model="form.customer_id"
                :options="customerOptions"
                :label="$t('tickets.create.customer_label')"
                :placeholder="$t('tickets.create.customer_placeholder')"
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

            <div v-if="isSupporter && availableTags.length > 0" class="mb-4">
              <va-select
                v-model="form.tags"
                :options="tagOptions"
                :label="$t('tickets.create.tags_label')"
                :placeholder="$t('tickets.create.tags_placeholder')"
                searchable
                multiple
                clearable
                text-by="text"
                value-by="value"
                :error="!!form.errors.tags"
                :error-messages="form.errors.tags"
                class="w-full"
              >
                <template #content="{ valueArray }">
                  <div class="flex gap-1 flex-wrap">
                    <span v-if="valueArray.length === 0" class="text-gray-500">{{ $t('tickets.create.no_tags') }}</span>
                    <TagBadge 
                      v-for="tagId in valueArray" 
                      :key="tagId" 
                      :tag="getDetailedTag(tagId)" 
                    />
                  </div>
                </template>
              </va-select>
            </div>

            <div class="mb-4">
              <va-input
                v-model="form.title"
                :label="$t('tickets.create.subject_label')"
                :placeholder="$t('tickets.create.subject_placeholder')"
                :error="!!form.errors.title"
                :error-messages="form.errors.title"
                class="w-full"
                required
              />
            </div>

            <div class="mb-6">
              <div class="va-title mb-2 text-sm font-bold" style="color: var(--va-dark);">{{ $t('tickets.create.message_label') }}</div>
              <va-textarea
                v-model="form.message"
                :placeholder="$t('tickets.create.message_placeholder')"
                :error="!!form.errors.message"
                :error-messages="form.errors.message"
                class="w-full"
                :min-rows="6"
                autosize
                required
              />
            </div>

            <div class="flex justify-end gap-3 mt-6">
              <va-button preset="secondary" @click="cancel">{{ $t('common.cancel') }}</va-button>
              <va-button type="submit" color="primary" :loading="form.processing">
                {{ $t('tickets.create.submit') }}
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
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import TagBadge from '@/Components/Common/TagBadge.vue';

/**
 * Ticket Creation Component.
 * Handles the logic for users and supporters to open new support tickets.
 * Integrates customer selection and tag assignment conditionally based on user roles.
 */
const props = defineProps({
  customers: { type: Array, default: () => [] },
  availableTags: { type: Array, default: () => [] }
});

const page = usePage();
const { t } = useI18n();
const isSupporter = page.props.auth.user.role !== 'customer';

const form = useForm({
  title: '',
  message: '',
  customer_id: '',
  tags: []
});

const customerOptions = computed(() => {
  return props.customers.map(customer => ({
    value: customer.id,
    text: customer.name
  }));
});

const tagOptions = computed(() => {
  return props.availableTags.map(tag => ({
    text: tag.name,
    value: String(tag.id)
  }));
});

/**
 * Retrieves full tag details for rendering dynamic badges in the UI.
 * @param {number|string} id - The Tag ID to find.
 * @returns {Object} The tag object or a localized fallback unknown object.
 */
const getDetailedTag = (id) => {
  return props.availableTags.find(t => String(t.id) === String(id)) || { name: t('common.unknown'), color: '#ccc' };
};

/**
 * Transforms the form payload before submission.
 * Ensures data relationships like customer IDs and Tags are formatted exactly as the backend expects.
 */
const submit = () => {
  form.transform((data) => {
    return {
      ...data,
      customer_id: data.customer_id?.value || data.customer_id,
      // Map back to string array to send properly formatted IDs
      tags: data.tags?.map(tag => typeof tag === 'object' ? tag.value : tag) || [],
    };
  }).post(route('tickets.store'), {
    preserveScroll: true,
  });
};

/**
 * Cancels ticket creation and returns to the list view.
 */
const cancel = () => {
  router.get(route('tickets.index'));
};
</script>