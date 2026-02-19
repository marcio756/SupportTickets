<script setup>
/**
 * Component responsible for toggling between light and dark themes 
 * using the vuestic-ui useColors composable.
 */
import { computed } from 'vue';
import { useColors } from 'vuestic-ui';

const { applyPreset, currentPresetName } = useColors();

/**
 * Computed property to get and set the current active theme.
 * @type {import('vue').WritableComputedRef<string>}
 */
const theme = computed({
    get() {
        return currentPresetName.value;
    },
    set(value) {
        applyPreset(value);
    }
});

const options = [
    { label: 'Dark', value: 'dark' },
    { label: 'Light', value: 'light' }
];
</script>

<template>
    <VaButtonToggle
        v-model="theme"
        color="background-element"
        border-color="background-element"
        :options="options"
        preset="secondary"
        size="small"
    />
</template>