<script setup>
/**
 * Component responsible for toggling between light and dark themes.
 * Updated to use the useTheme composable for system-wide sync.
 */
import { computed } from 'vue';
import { useTheme } from '@/Composables/useTheme';

const { currentPresetName, setTheme } = useTheme();

/**
 * Proxy for the theme state to allow clean binding with VaButtonToggle.
 */
const themeProxy = computed({
    get() {
        return currentPresetName.value;
    },
    set(value) {
        setTheme(value);
    }
});

const options = [
    { label: 'Dark', value: 'dark' },
    { label: 'Light', value: 'light' }
];
</script>

<template>
    <VaButtonToggle
        v-model="themeProxy"
        color="background-element"
        border-color="background-element"
        :options="options"
        preset="secondary"
        size="small"
    />
</template>