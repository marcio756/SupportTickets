<script setup>
/**
 * User Form Modal Component.
 * Unified interface for creating and updating user accounts.
 * Includes role-based logic to handle team assignment dynamically.
 */
import { ref, watch, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    user: {
        type: Object,
        default: null,
    },
    roles: {
        type: Array,
        required: true,
    },
    teams: {
        type: Array,
        default: () => [],
    }
});

const emit = defineEmits(['close']);

const { t } = useI18n();
const currentPasswordInput = ref(null);

const form = useForm({
    name: '',
    email: '',
    role: '',
    team_id: null,
    password: '',
    password_confirmation: '',
    current_password: '',
});

/**
 * Maps the raw teams array into the expected format for Vuestic's Select component.
 */
const teamOptions = computed(() => {
    return props.teams.map(team => ({
        text: `${team.name} (${team.shift})`,
        value: team.id
    }));
});

watch(() => props.show, (isShowing) => {
    if (isShowing) {
        form.name = props.user ? props.user.name : '';
        form.email = props.user ? props.user.email : '';
        form.role = props.user ? (props.user.role?.value || props.user.role) : (props.roles[0] || '');
        form.team_id = props.user ? props.user.team_id : null;
        form.password = '';
        form.password_confirmation = '';
        form.current_password = '';
        form.clearErrors();
    }
});

/**
 * Automatically clears the team_id if the chosen role is not a 'supporter'.
 * Prevents invalid data states being submitted to the backend.
 */
watch(() => form.role, (newRole) => {
    if (newRole !== 'supporter') {
        form.team_id = null;
    }
});

const submit = () => {
    if (props.user) {
        form.put(route('users.update', props.user.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => {
                if (form.errors.current_password) {
                    form.reset('current_password');
                    currentPasswordInput.value?.focus();
                }
            },
        });
    } else {
        form.post(route('users.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => {
                if (form.errors.current_password) {
                    form.reset('current_password');
                    currentPasswordInput.value?.focus();
                }
            },
        });
    }
};

const handleModalUpdate = (value) => {
    if (!value) closeModal();
};

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
            {{ user ? $t('users.form.edit_title') : $t('users.form.create_title') }}
        </h3>

        <form @submit.prevent="submit" class="flex flex-col gap-4">
            <va-input
                v-model="form.name"
                :label="$t('users.form.name')"
                :error="!!form.errors.name"
                :error-messages="form.errors.name"
                required
            />

            <va-input
                v-model="form.email"
                type="email"
                :label="$t('users.form.email')"
                :error="!!form.errors.email"
                :error-messages="form.errors.email"
                required
            />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <va-select
                    v-if="roles.length > 1"
                    v-model="form.role"
                    :options="roles"
                    :label="$t('users.form.role')"
                    :error="!!form.errors.role"
                    :error-messages="form.errors.role"
                    required
                />

                <va-select
                    v-if="form.role === 'supporter'"
                    v-model="form.team_id"
                    :options="teamOptions"
                    :label="$t('users.form.team_assignment')"
                    value-by="value"
                    :error="!!form.errors.team_id"
                    :error-messages="form.errors.team_id"
                    clearable
                />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <va-input
                    v-model="form.password"
                    type="password"
                    :label="user ? $t('users.form.new_password') : $t('users.form.password')"
                    :error="!!form.errors.password"
                    :error-messages="form.errors.password"
                    :required="!user"
                />

                <va-input
                    v-model="form.password_confirmation"
                    type="password"
                    :label="$t('users.form.confirm_password')"
                    :error="!!form.errors.password_confirmation"
                    :error-messages="form.errors.password_confirmation"
                    :required="!!form.password || !user"
                />
            </div>

            <div class="mt-4 p-4 rounded-lg border border-solid" style="background-color: var(--va-background-element); border-color: var(--va-background-border);">
                <p class="mb-3 text-sm font-semibold" style="color: var(--va-text-primary)">
                    {{ $t('users.form.auth_required') }}
                </p>
                <p class="mb-4 text-sm" style="color: var(--va-secondary)">
                    {{ $t('users.form.auth_description') }}
                </p>
                <va-input
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    :label="$t('users.form.current_password')"
                    :error="!!form.errors.current_password"
                    :error-messages="form.errors.current_password"
                    required
                />
            </div>

            <div class="flex justify-end gap-3 mt-4">
                <va-button preset="secondary" @click="closeModal">{{ $t('common.actions.cancel') }}</va-button>
                <va-button type="submit" :loading="form.processing" :disabled="form.processing">
                    {{ user ? $t('common.actions.save_changes') : $t('users.form.create_btn') }}
                </va-button>
            </div>
        </form>
    </va-modal>
</template>