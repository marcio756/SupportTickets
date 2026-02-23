<script setup>
/**
 * Component responsible for toggling the application's global color theme.
 * Synchronizes Vuestic UI presets with Tailwind CSS dark mode and persists preference.
 */
import { computed } from 'vue';
import { useColors } from 'vuestic-ui';

const { currentPresetName, applyPreset } = useColors();

const isDark = computed(() => currentPresetName.value === 'dark');

/**
 * Toggles the theme, saves to localStorage, and updates the HTML class for Tailwind.
 */
const toggleTheme = () => {
    const newTheme = isDark.value ? 'light' : 'dark';
    applyPreset(newTheme);
    localStorage.setItem('app-theme', newTheme);
    
    // Sync with Tailwind CSS dark mode
    if (newTheme === 'dark') {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
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
.va-button {
    font-weight: 400;
}
</style>