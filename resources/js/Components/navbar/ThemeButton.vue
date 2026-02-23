<script setup>
/**
 * Component responsible for toggling the application's global color theme.
 * Uses the icon prop for consistency with other menu buttons.
 */
import { computed } from 'vue';
import { useColors } from 'vuestic-ui';

const { currentPresetName, applyPreset } = useColors();

const isDark = computed(() => currentPresetName.value === 'dark');

/**
 * Toggles the theme and persists the choice.
 */
const toggleTheme = () => {
    const newTheme = isDark.value ? 'light' : 'dark';
    applyPreset(newTheme);
    localStorage.setItem('app-theme', newTheme);
};
</script>

<template>
    <VaButton
        preset="plain"
        color="textPrimary"
        class="w-full justify-start"
        :icon="isDark ? 'light_mode' : 'dark_mode'"
        @click="toggleTheme"
    >
        {{ isDark ? 'Modo Claro' : 'Modo Escuro' }}
    </VaButton>
</template>

<style scoped>
/* Standardizing font size with the rest of the dropdown items */
.va-button {
    font-weight: 400;
}
</style>