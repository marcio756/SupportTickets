<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center px-4 overlay-backdrop">
        <VaCard class="w-full max-w-md p-6 text-center shadow-2xl border-t-4 border-primary transition-transform duration-300 transform scale-100">
            <VaCardContent>
                <VaIcon name="lock_person" size="5rem" color="secondary" class="mb-6 opacity-75" />
                <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-100 mb-3">{{ $t('tickets.show.view_only_mode') }}</h2>
                <p class="text-gray-600 dark:text-gray-400 mb-8 text-lg leading-relaxed">
                    {{ $t('tickets.show.claim_description') }}
                </p>

                <div class="flex flex-col gap-3">
                    <VaButton size="large" icon="person_add" @click="$emit('claim')" :loading="isAssigning" class="w-full font-bold py-3">
                        {{ $t('tickets.show.claim_button') }}
                    </VaButton>
                    <Link :href="route('tickets.index')">
                        <VaButton preset="secondary" color="secondary" size="small" class="w-full">{{ $t('common.actions.cancel_and_back') }}</VaButton>
                    </Link>
                </div>
            </VaCardContent>
        </VaCard>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { VaCard, VaCardContent, VaIcon, VaButton } from 'vuestic-ui';

defineProps({
    isAssigning: {
        type: Boolean,
        default: false
    }
});

defineEmits(['claim']);
</script>

<style scoped>
.overlay-backdrop {
    background-color: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(8px);
    animation: fadeIn 0.4s ease-out forwards;
}

@keyframes fadeIn {
    from { opacity: 0; backdrop-filter: blur(0px); }
    to { opacity: 1; backdrop-filter: blur(8px); }
}
</style>