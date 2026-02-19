<script setup>
/**
 * Component responsible for toggling the application's global color theme 
 * between 'light' and 'dark' using Vuestic UI composables.
 */
import { computed } from 'vue';
import { useColors } from 'vuestic-ui';

const { currentPresetName, applyPreset } = useColors();

/**
 * Evaluates the currently active theme preset.
 * @returns {boolean} Returns true if the current theme is 'dark', false otherwise.
 */
const isDark = computed(() => currentPresetName.value === 'dark');

/**
 * Toggles the UI theme preset based on the current state.
 * @returns {void}
 */
const toggleTheme = () => {
    applyPreset(isDark.value ? 'light' : 'dark');
};
</script>

<template>
    <VaButton
        preset="secondary"
        color="textPrimary"
        class="theme-button flex-shrink-0"
        @click="toggleTheme"
        aria-label="Toggle Theme"
    >
        <VaIcon size="large" :name="isDark ? 'light_mode' : 'dark_mode'" />
    </VaButton>
</template>

<style scoped>
/* Component-specific styles for smooth hover transitions */
.theme-button {
    cursor: pointer;
    transition: opacity 0.2s ease-in-out;
}
.theme-button:hover {
    opacity: 0.8;
}
</style>