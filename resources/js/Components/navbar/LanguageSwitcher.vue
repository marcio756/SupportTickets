<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { locale } = useI18n();
const page = usePage();

/**
 * Derives the active system language globally from the Inertia page properties.
 */
const currentLocale = computed(() => page.props.locale || 'pt');

/**
 * Triggers a global language update by communicating with the backend session endpoint.
 * Synchronizes the local Vue i18n instance automatically upon a successful server response 
 * to ensure immediate UI reactivity without full page reloads.
 * * @param {string} selectedLocale 
 */
const switchLanguage = (selectedLocale) => {
    if (selectedLocale === currentLocale.value) return;

    router.post(route('language.switch'), { locale: selectedLocale }, {
        preserveScroll: true,
        onSuccess: () => {
            locale.value = selectedLocale;
        }
    });
};
</script>

<template>
    <div class="flex items-center space-x-3 bg-gray-100 dark:bg-gray-800 px-3 py-1 rounded-full">
        <button
            @click="switchLanguage('pt')"
            :class="{
                'font-bold text-primary': currentLocale === 'pt', 
                'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200': currentLocale !== 'pt'
            }"
            class="text-sm transition-colors duration-200 focus:outline-none"
            :title="$t('common.switch_to_pt')"
        >
            PT
        </button>
        <span class="text-gray-300 dark:text-gray-600">|</span>
        <button
            @click="switchLanguage('en')"
            :class="{
                'font-bold text-primary': currentLocale === 'en', 
                'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200': currentLocale !== 'en'
            }"
            class="text-sm transition-colors duration-200 focus:outline-none"
            :title="$t('common.switch_to_en')"
        >
            EN
        </button>
    </div>
</template>