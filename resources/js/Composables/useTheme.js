/**
 * Composable to manage the application theme synchronization 
 * between Vuestic UI, Tailwind CSS, and localStorage.
 */
import { watch, onMounted } from 'vue';
import { useColors } from 'vuestic-ui';

export function useTheme() {
    const { applyPreset, currentPresetName } = useColors();

    /**
     * Updates the DOM root element with the dark class for Tailwind
     * @param {string} theme - The theme name ('dark' or 'light')
     */
    const syncTailwindWithTheme = (theme) => {
        if (theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    };

    /**
     * Changes the application theme and persists it.
     * @param {string} themeName - 'dark' | 'light'
     */
    const setTheme = (themeName) => {
        applyPreset(themeName);
        localStorage.setItem('app-theme', themeName);
        syncTailwindWithTheme(themeName);
    };

    /**
     * Initializes theme on component mount.
     */
    const initTheme = () => {
        const savedTheme = localStorage.getItem('app-theme') || 'light';
        setTheme(savedTheme);
    };

    // Watch for changes in Vuestic preset to sync Tailwind automatically
    watch(currentPresetName, (newTheme) => {
        syncTailwindWithTheme(newTheme);
    });

    return {
        currentPresetName,
        setTheme,
        initTheme,
    };
}