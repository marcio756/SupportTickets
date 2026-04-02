<script setup>
import { ref, watch, computed, onUnmounted } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    customers: {
        type: Array,
        default: () => [],
    },
    availableTags: {
        type: Array,
        default: () => [],
    }
});

const emit = defineEmits(['close']);

const page = usePage();
const { t } = useI18n();

const isSupporter = page.props.auth.user.role !== 'customer';

const form = useForm({
    title: '',
    message: '',
    customer_id: '',
    tags: [],
    attachment: [], // Mantemos a correção do array vazio para o file-upload
});

// Dynamic State for Customers Infinite Scroll & Search
const asyncCustomerOptions = ref([]);
const isLoadingCustomers = ref(false);
const currentCustomerCursor = ref(null);
const hasMoreCustomers = ref(true);
const currentCustomerSearch = ref('');
let searchTimeout = null;

/**
 * Monitors the modal's visibility state to enforce a clean slate.
 * Resets form data and clears any previous validation errors whenever the modal is opened.
 * Architect Note: Triggers the initial fetch of customers if the modal is opened.
 */
watch(() => props.show, (isShowing) => {
    if (isShowing) {
        form.reset();
        form.clearErrors();
        
        // Fetch inicial se a lista estiver vazia quando o supporter abre o modal
        if (isSupporter && asyncCustomerOptions.value.length === 0) {
            fetchCustomersOnType('', false);
        }
    }
});

/**
 * Lida com o input do utilizador com debounce para evitar spam na API.
 */
const handleCustomerSearch = (query = '') => {
    currentCustomerSearch.value = query;
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        fetchCustomersOnType(query, false);
    }, 350);
};

/**
 * Função desencadeada quando a caixa de utilizadores atinge o limite do scroll.
 */
const loadMoreCustomers = () => {
    if (hasMoreCustomers.value && !isLoadingCustomers.value) {
        fetchCustomersOnType(currentCustomerSearch.value, true);
    }
};

/**
 * Abstrai a lógica de chamadas assíncronas à API e lida com a paginação de cursores.
 */
const fetchCustomersOnType = async (query = '', isLoadMore = false) => {
    if (!isLoadMore && query.length > 0 && query.length < 2) return;

    if (!isLoadMore) {
        isLoadingCustomers.value = true;
        currentCustomerCursor.value = null; 
    } else {
        isLoadingCustomers.value = true;
    }
    
    try {
        const response = await axios.get('/api/v1/customers', { 
            params: { 
                search: query,
                cursor: currentCustomerCursor.value
            } 
        });
        
        const data = response.data.data || response.data || [];
        
        // Formata os dados para o formato que a dropdown precisa (text / value)
        const formattedData = data.map(customer => ({
            value: customer.id,
            text: customer.name
        }));
        
        if (isLoadMore) {
            const existingIds = new Set(asyncCustomerOptions.value.map(c => c.value));
            const filtered = formattedData.filter(item => !existingIds.has(item.value));
            asyncCustomerOptions.value = [...asyncCustomerOptions.value, ...filtered];
        } else {
            asyncCustomerOptions.value = formattedData;
        }

        currentCustomerCursor.value = response.data.next_cursor || null;
        hasMoreCustomers.value = response.data.next_cursor !== null;

    } catch (error) {
        console.error("Error fetching customers dynamically", error);
    } finally {
        isLoadingCustomers.value = false;
    }
};

onUnmounted(() => {
    clearTimeout(searchTimeout);
});

/**
 * Transforms the raw tags array into the structure expected by Vuestic Select.
 */
const tagOptions = computed(() => {
    return props.availableTags.map(tag => ({
        value: tag.id,
        text: tag.name
    }));
});

/**
 * Intercepts the form submission to normalize payload structures before dispatching to the backend.
 * Ensures the 'customer_id' is extracted correctly whether the Vuestic component emits an object or a primitive.
 * Maps tags to only send the IDs array.
 */
const submit = () => {
    form.transform((data) => {
        return {
            ...data,
            customer_id: data.customer_id?.value || data.customer_id,
            tags: data.tags.map(tag => tag.value || tag),
            // When dealing with files, passing the raw File object is required for Inertia to auto-configure multipart/form-data
            attachment: data.attachment && data.attachment.length > 0 ? data.attachment[0] : null,
        };
    }).post(route('tickets.store'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};

/**
 * Handles external modal updates (e.g., clicking outside the modal or pressing Esc).
 * @param {boolean} value 
 */
const handleModalUpdate = (value) => {
    if (!value) closeModal();
};

/**
 * Encapsulates the teardown logic for closing the modal safely.
 */
const closeModal = () => {
    emit('close');
    form.reset();
    form.clearErrors();
};
</script>

<template>
    <va-modal
        :modelValue="show"
        @update:modelValue="handleModalUpdate"
        hide-default-actions
        size="large"
    >
        <h3 class="va-h5 mb-6" style="color: var(--va-text-primary)">
            {{ $t('tickets.form.open_new_title') }}
        </h3>

        <p class="text-sm mb-6" style="color: var(--va-secondary)">
            {{ $t('tickets.form.description') }}
        </p>

        <form @submit.prevent="submit" class="flex flex-col gap-5">
            
            <va-select
                v-if="isSupporter"
                v-model="form.customer_id"
                :options="asyncCustomerOptions"
                :loading="isLoadingCustomers"
                :label="$t('tickets.form.select_customer')"
                :placeholder="$t('tickets.form.search_customer')"
                searchable
                text-by="text"
                value-by="value"
                :error="!!form.errors.customer_id"
                :error-messages="form.errors.customer_id"
                @update:search="handleCustomerSearch"
                @scroll-bottom="loadMoreCustomers"
                required
            >
                <template #prependInner>
                    <va-icon name="person" color="secondary" />
                </template>
            </va-select>

            <va-input
                v-model="form.title"
                :label="$t('tickets.form.subject_label')"
                :placeholder="$t('tickets.form.subject_placeholder')"
                :error="!!form.errors.title"
                :error-messages="form.errors.title"
                required
            />
            
            <va-select
                v-if="isSupporter && tagOptions.length > 0"
                v-model="form.tags"
                :options="tagOptions"
                :label="$t('tickets.form.tags_label')"
                :placeholder="$t('tickets.form.tags_placeholder')"
                multiple
                searchable
                text-by="text"
                value-by="value"
                :error="!!form.errors.tags"
                :error-messages="form.errors.tags"
            />

            <div>
                <div class="mb-2 text-sm font-bold" style="color: var(--va-text-primary);">
                    {{ $t('tickets.form.detailed_message_label') }}
                </div>
                <va-textarea
                    v-model="form.message"
                    :placeholder="$t('tickets.form.detailed_message_placeholder')"
                    :error="!!form.errors.message"
                    :error-messages="form.errors.message"
                    :min-rows="6"
                    autosize
                    required
                    class="w-full"
                />
            </div>

            <div>
                <va-file-upload
                    v-model="form.attachment"
                    type="single"
                    :upload-button-text="$t('tickets.form.upload_attachment')"
                    :error="!!form.errors.attachment"
                    :error-messages="form.errors.attachment"
                    class="w-full"
                />
                <div class="text-xs mt-1" style="color: var(--va-secondary)">
                    {{ $t('tickets.form.attachment_limit') }}
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-2">
                <va-button preset="secondary" @click="closeModal">{{ $t('common.actions.cancel') }}</va-button>
                <va-button type="submit" color="primary" :loading="form.processing">
                    {{ $t('tickets.form.submit_button') }}
                </va-button>
            </div>
        </form>
    </va-modal>
</template>