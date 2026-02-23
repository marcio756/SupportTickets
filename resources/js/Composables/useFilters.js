/**
 * resources/js/Composables/useFilters.js
 * * Provides an encapsulated logic block for handling server-side filtering via Inertia.js.
 * * @param {Object} initialFilters - The current filters passed from the backend.
 * @param {String} routeName - The named route to fetch the filtered data from.
 * @param {Number} currentPage - The current pagination page.
 * @returns {Object} Reactive filter states and actions.
 */
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

export function useFilters(initialFilters = {}, routeName, currentPage = 1) {
    const query = ref(initialFilters.search || '');
    const selectedStatus = ref(initialFilters.status || '');
    const selectedCustomers = ref(initialFilters.customers || []);
    
    // Parse boolean value from URL safely
    const showUnassigned = ref(
        initialFilters.unassigned === 'true' || initialFilters.unassigned === true
    );
    
    const page = ref(currentPage);

    let debounceTimeout = null;

    /**
     * Dispatches the Inertia request to fetch new filtered or paginated data.
     * @param {Boolean} replace - Determines if the browser history should be replaced.
     */
    const fetchResults = (replace = true) => {
        if (debounceTimeout) clearTimeout(debounceTimeout);
        
        debounceTimeout = setTimeout(() => {
            router.get(route(routeName), {
                search: query.value,
                status: selectedStatus.value,
                customers: selectedCustomers.value,
                unassigned: showUnassigned.value,
                page: page.value
            }, {
                preserveState: true,
                replace: replace,
                preserveScroll: true
            });
        }, 300);
    };

    // Watch for text, status dropdown, customer selection, or unassigned toggle changes
    watch([query, selectedStatus, selectedCustomers, showUnassigned], () => {
        page.value = 1; // Always return to first page on new search
        fetchResults(true);
    }, { deep: true });

    // Manually trigger a page change without losing current filters
    const changePage = (newPage) => {
        page.value = newPage;
        fetchResults(false);
    };

    return {
        query,
        selectedStatus,
        selectedCustomers,
        showUnassigned,
        page,
        changePage
    };
}