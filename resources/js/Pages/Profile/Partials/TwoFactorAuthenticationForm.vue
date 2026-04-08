<script setup>
/**
 * Two Factor Authentication Form Component.
 * Handles the enabling and disabling of 2FA for the authenticated user.
 * Displays the QR code and recovery/setup secret upon initial activation.
 */
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);

// Check if the user already has 2FA enabled based on the user object,
// or if the backend just flashed the success status.
const twoFactorEnabled = computed(
    () => user.value.two_factor_secret !== null || page.props.flash?.status === 'two-factor-authentication-enabled'
);

const qrCode = computed(() => page.props.flash?.qr_code);
const setupKey = computed(() => page.props.flash?.secret);

const enableForm = useForm({});
const disableForm = useForm({});

/**
 * Sends request to enable 2FA and generate keys.
 */
const enableTwoFactorAuthentication = () => {
    enableForm.post(route('two-factor.enable'), {
        preserveScroll: true,
    });
};

/**
 * Sends request to remove 2FA from the user account.
 */
const disableTwoFactorAuthentication = () => {
    disableForm.delete(route('two-factor.disable'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Autenticação de Dois Fatores (2FA)
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Adicione segurança extra à sua conta utilizando a autenticação de dois fatores.
            </p>
        </header>

        <div class="mt-6">
            <h3 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-2">
                <span v-if="twoFactorEnabled" class="text-green-600 dark:text-green-400 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    A Autenticação de Dois Fatores está ativada.
                </span>
                <span v-else class="text-gray-600 dark:text-gray-400">
                    A Autenticação de Dois Fatores não está ativada.
                </span>
            </h3>

            <transition 
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0 transform -translate-y-4"
                enter-to-class="opacity-100 transform translate-y-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 transform translate-y-0"
                leave-to-class="opacity-0 transform -translate-y-4"
            >
                <div v-if="twoFactorEnabled && qrCode" class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                    <p class="text-sm text-gray-600 dark:text-gray-400 max-w-xl">
                        A autenticação de dois fatores está agora ativada. Leia o código QR abaixo utilizando a aplicação Google Authenticator no seu telemóvel.
                    </p>

                    <div class="mt-4 bg-white p-2 inline-block rounded-lg shadow-sm" v-html="qrCode"></div>

                    <div class="mt-4 max-w-xl text-sm text-gray-600 dark:text-gray-400">
                        <p class="font-semibold mb-1">Chave de Configuração Manual:</p>
                        <code class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded font-mono text-gray-800 dark:text-gray-200">{{ setupKey }}</code>
                    </div>
                </div>
            </transition>

            <div class="mt-6 flex items-center gap-4">
                <PrimaryButton 
                    v-if="!twoFactorEnabled" 
                    @click="enableTwoFactorAuthentication" 
                    :disabled="enableForm.processing"
                    :class="{ 'opacity-50': enableForm.processing }"
                >
                    <span v-if="enableForm.processing" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        A Ativar...
                    </span>
                    <span v-else>Ativar 2FA</span>
                </PrimaryButton>

                <DangerButton 
                    v-else 
                    @click="disableTwoFactorAuthentication" 
                    :disabled="disableForm.processing"
                    :class="{ 'opacity-50': disableForm.processing }"
                >
                    <span v-if="disableForm.processing" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        A Desativar...
                    </span>
                    <span v-else>Desativar 2FA</span>
                </DangerButton>
            </div>
        </div>
    </section>
</template>