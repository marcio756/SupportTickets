<script setup>
/**
 * Two Factor Challenge Page.
 * Displayed after credentials verification but before full authentication 
 * for users who have 2FA enabled.
 */
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const codeInput = ref(null);

const form = useForm({
    code: '',
});

/**
 * Submits the 2FA code to the backend verification endpoint.
 */
const submit = () => {
    form.post(route('two-factor.challenge'), {
        onFinish: () => {
            form.reset('code');
            codeInput.value?.focus();
        },
    });
};

// Auto-focus input on page load for faster user experience
onMounted(() => {
    codeInput.value?.focus();
});
</script>

<template>
    <GuestLayout>
        <Head title="Verificação em 2 Passos" />

        <div class="mb-6 text-sm text-gray-600 dark:text-gray-400 text-center">
            <svg class="w-12 h-12 mx-auto mb-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            </svg>
            Abra a sua aplicação de Autenticação (ex: Google Authenticator) e insira o código de 6 dígitos para continuar.
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="code" value="Código de Autenticação" />

                <TextInput
                    id="code"
                    ref="codeInput"
                    v-model="form.code"
                    type="text"
                    inputmode="numeric"
                    class="mt-1 block w-full text-center tracking-widest text-xl font-mono"
                    placeholder="000000"
                    required
                    autofocus
                    autocomplete="one-time-code"
                />

                <InputError class="mt-2 text-center" :message="form.errors.code" />
            </div>

            <div class="mt-6 flex items-center justify-end">
                <PrimaryButton 
                    class="w-full justify-center transition-all duration-200" 
                    :class="{ 'opacity-50 cursor-wait': form.processing }" 
                    :disabled="form.processing"
                >
                    <span v-if="form.processing" class="flex items-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        A verificar...
                    </span>
                    <span v-else>Verificar Código</span>
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>