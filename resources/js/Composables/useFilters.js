/**
 * Composable to manage resource filtering with localStorage persistence.
 * Follows SRP by isolating filter logic from the components.
 */
import { ref, watch, onMounted } from 'vue';

/**
 * @param {Object} initialValues - The default filter values.
 * @param {string} storageKey - Unique key to persist filters in localStorage.
 */
export function useFilters(initialValues, storageKey) {
    const filters = ref({ ...initialValues });

    /**
     * Loads saved filters from localStorage if they exist.
     */
    const initFilters = () => {
        if (!storageKey) return;
        
        const saved = localStorage.getItem(`filters-${storageKey}`);
        if (saved) {
            try {
                filters.value = { ...initialValues, ...JSON.parse(saved) };
            } catch (e) {
                console.error('Error parsing persisted filters', e);
            }
        }
    };

    /**
     * Resets filters to their initial state and clears storage.
     */
    const resetFilters = () => {
        filters.value = { ...initialValues };
        if (storageKey) {
            localStorage.removeItem(`filters-${storageKey}`);
        }
    };

    // Initialize on call
    initFilters();

    // Watch for changes and persist
    watch(
        filters,
        (newFilters) => {
            if (storageKey) {
                localStorage.setItem(`filters-${storageKey}`, JSON.stringify(newFilters));
            }
        },
        { deep: true }
    );

    return {
        filters,
        resetFilters,
    };
}