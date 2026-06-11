<script setup>
/**
 * Social Accounts Management Form.
 * Allows authenticated users to link or unlink external OAuth providers.
 */
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const form = useForm({});

// Estado local para Optimistic UI e Progress Illusions
const optimisticUnlinked = ref({ google: false, facebook: false });
const isConnecting = ref({ google: false, facebook: false });

// Propriedades computadas reativas em relação ao objeto page.props (corrige o problema do F5)
// e integradas com o estado Optimistic UI para uma resposta visual instantânea.
const hasGoogleLinked = computed(() => !!page.props.auth.user.google_id && !optimisticUnlinked.value.google);
const hasFacebookLinked = computed(() => !!page.props.auth.user.facebook_id && !optimisticUnlinked.value.facebook);

/**
 * Sends a DELETE request to unlink the specified provider.
 * Uses Optimistic UI principles for perceived performance.
 */
const unlinkProvider = (provider) => {
    // Optimistic UI: assumimos sucesso imediato para não fazer o utilizador esperar
    optimisticUnlinked.value[provider] = true; 
    
    form.delete(route('profile.social.destroy', provider), {
        preserveScroll: true,
        onError: () => {
            // Em caso de falha silenciosa, revertemos o estado visual
            optimisticUnlinked.value[provider] = false;
        },
    });
};

/**
 * Progress Illusion para a ação de conectar.
 */
const connectProvider = (provider) => {
    isConnecting.value[provider] = true;
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Contas Sociais
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Associa a tua conta Google ou Facebook para poderes fazer login rapidamente sem precisares da tua password.
            </p>
        </header>

        <InputError :message="$page.props.errors.social" class="mt-2" />
        
        <div class="mt-6 space-y-4">
            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6" viewBox="0 0 24 24">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Google</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400" v-if="hasGoogleLinked">Conectado</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400" v-else>Não conectado</p>
                    </div>
                </div>

                <DangerButton v-if="hasGoogleLinked" @click="unlinkProvider('google')" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Desconectar
                </DangerButton>
                <a v-else :href="route('profile.social.redirect', 'google')" @click="connectProvider('google')" :class="{ 'opacity-50 pointer-events-none': isConnecting.google }" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    <svg v-if="isConnecting.google" class="animate-spin -ml-1 mr-2 h-4 w-4 text-gray-500 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ isConnecting.google ? 'A conectar...' : 'Conectar' }}</span>
                </a>
            </div>

            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6" viewBox="0 0 24 24">
                        <path fill-rule="evenodd" fill="#1877F2" d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" clip-rule="evenodd" />
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Facebook</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400" v-if="hasFacebookLinked">Conectado</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400" v-else>Não conectado</p>
                    </div>
                </div>

                <DangerButton v-if="hasFacebookLinked" @click="unlinkProvider('facebook')" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Desconectar
                </DangerButton>
                <a v-else :href="route('profile.social.redirect', 'facebook')" @click="connectProvider('facebook')" :class="{ 'opacity-50 pointer-events-none': isConnecting.facebook }" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-500 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    <svg v-if="isConnecting.facebook" class="animate-spin -ml-1 mr-2 h-4 w-4 text-gray-500 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ isConnecting.facebook ? 'A conectar...' : 'Conectar' }}</span>
                </a>
            </div>
        </div>
    </section>
</template>