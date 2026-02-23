<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

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
    }
});

const emit = defineEmits(['close']);

const currentPasswordInput = ref(null);

const form = useForm({
    name: '',
    email: '',
    role: '',
    password: '',
    password_confirmation: '',
    current_password: '',
});

watch(() => props.show, (isShowing) => {
    if (isShowing) {
        form.name = props.user ? props.user.name : '';
        form.email = props.user ? props.user.email : '';
        form.role = props.user ? props.user.role : (props.roles[0] || '');
        form.password = '';
        form.password_confirmation = '';
        form.current_password = '';
        form.clearErrors();
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
            {{ user ? 'Edit User' : 'Create New User' }}
        </h3>

        <form @submit.prevent="submit" class="flex flex-col gap-4">
            <va-input
                v-model="form.name"
                label="Name"
                :error="!!form.errors.name"
                :error-messages="form.errors.name"
                required
            />

            <va-input
                v-model="form.email"
                type="email"
                label="Email"
                :error="!!form.errors.email"
                :error-messages="form.errors.email"
                required
            />

            <va-select
                v-model="form.role"
                :options="roles"
                label="Role"
                :error="!!form.errors.role"
                :error-messages="form.errors.role"
                required
            />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <va-input
                    v-model="form.password"
                    type="password"
                    :label="user ? 'New Password (leave blank to keep current)' : 'Password'"
                    :error="!!form.errors.password"
                    :error-messages="form.errors.password"
                    :required="!user"
                />

                <va-input
                    v-model="form.password_confirmation"
                    type="password"
                    label="Confirm New Password"
                    :error="!!form.errors.password_confirmation"
                    :error-messages="form.errors.password_confirmation"
                    :required="!!form.password || !user"
                />
            </div>

            <div class="mt-4 p-4 rounded-lg border border-solid" style="background-color: var(--va-background-element); border-color: var(--va-background-border);">
                <p class="mb-3 text-sm font-semibold" style="color: var(--va-text-primary)">
                    Authorization Required
                </p>
                <p class="mb-4 text-sm" style="color: var(--va-secondary)">
                    Please enter your current password to confirm this action.
                </p>
                <va-input
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    label="Your Current Password"
                    :error="!!form.errors.current_password"
                    :error-messages="form.errors.current_password"
                    required
                />
            </div>

            <div class="flex justify-end gap-3 mt-4">
                <va-button preset="secondary" @click="closeModal"> Cancel </va-button>
                <va-button type="submit" :loading="form.processing" :disabled="form.processing">
                    {{ user ? 'Save Changes' : 'Create User' }}
                </va-button>
            </div>
        </form>
    </va-modal>
</template>