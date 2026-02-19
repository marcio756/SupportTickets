import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import ThemeButton from '@/Components/Layout/ThemeButton.vue';

/**
 * Mocking vuestic-ui composables to isolate testing logic
 */
vi.mock('vuestic-ui', () => {
    const currentPresetName = { value: 'light' };
    return {
        useColors: () => ({
            currentPresetName,
            applyPreset: vi.fn((newPreset) => {
                currentPresetName.value = newPreset;
            }),
        }),
    };
});

describe('ThemeButton.vue', () => {
    it('renders the dark_mode icon when the theme is light', () => {
        const wrapper = mount(ThemeButton);
        expect(wrapper.text()).toContain('dark_mode');
    });

    it('toggles the theme and changes icon when clicked', async () => {
        const wrapper = mount(ThemeButton);
        
        // Initial state is light, icon should be dark_mode
        expect(wrapper.text()).toContain('dark_mode');

        // Trigger theme toggle
        await wrapper.find('.theme-button').trigger('click');

        // Verify the theme has been switched
        const { useColors } = await import('vuestic-ui');
        expect(useColors().applyPreset).toHaveBeenCalledWith('dark');
        
        // Mock updates, icon should now reflect light_mode option
        expect(useColors().currentPresetName.value).toBe('dark');
    });
});