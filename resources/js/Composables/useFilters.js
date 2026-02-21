import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Composable to abstract dynamic data table filtering logic.
 * Manages reactive filter states and synchronizes them with the URL query string.
 *
 * @param {Object} initialFilters - Key-value pairs of default filter states.
 * @param {string} routeName - The target Inertia route name to reload data.
 */
export function useFilters(initialFilters = {}, routeName) {
    const filters = ref({ ...initialFilters });

    let timeout = null;

    /**
     * Watches for deep changes in the filters object.
     * Debounces the Inertia visit to prevent excessive network requests while the user is actively typing.
     */
    watch(filters, (newFilters) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            const queryParams = Object.fromEntries(
                Object.entries(newFilters).filter(([_, value]) => value !== '' && value !== null)
            );

            router.get(route(routeName), queryParams, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);
    }, { deep: true });

    /**
     * Resets all filters back to their initial default state.
     */
    const resetFilters = () => {
        filters.value = { ...initialFilters };
    };

    return {
        filters,
        resetFilters,
    };
}