/**
 * resources/js/Composables/useFilters.js
 * Provides encapsulated logic for server-side filtering with localStorage persistence.
 * Includes auto-apply functionality for returning visitors.
 */
import { ref, watch, reactive, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Hook to manage filtering state, URL syncing, and LocalStorage persistence.
 * * @param {Object} initialFilters Initial filter values from the server (URL query string).
 * @param {string} routeName The Ziggy route name to call.
 * @param {number} currentPage The current pagination page.
 * @param {Array<string>} customKeys Array of extra generic filter keys to track.
 * @returns {Object} Reactive filter variables and methods.
 */
export function useFilters(initialFilters = {}, routeName, currentPage = 1, customKeys = []) {
    const storageKey = `filters-${routeName}`;
    
    // Load stored filters from previous sessions
    const savedFilters = JSON.parse(localStorage.getItem(storageKey) || '{}');

    // Check if the current visit includes URL parameters
    const hasUrlParams = window.location.search.length > 0;
    
    // If the user arrived via a shared link with parameters, prioritize the URL.
    // Otherwise, prioritize the saved filters from LocalStorage.
    const sourceFilters = hasUrlParams ? initialFilters : savedFilters;

    const query = ref(initialFilters.search ?? initialFilters.query ?? sourceFilters.search ?? sourceFilters.query ?? '');
    const selectedStatus = ref(initialFilters.status ?? sourceFilters.status ?? '');
    const selectedCustomers = ref(initialFilters.customers ?? sourceFilters.customers ?? []);
    const selectedAssignees = ref(initialFilters.assignees ?? sourceFilters.assignees ?? []);
    const selectedRole = ref(initialFilters.role ?? sourceFilters.role ?? '');
    const selectedTags = ref(initialFilters.tags ?? sourceFilters.tags ?? []);
    
    // Dynamically track custom filters passed from specific views
    const customFilters = reactive({});
    customKeys.forEach(key => {
        customFilters[key] = initialFilters[key] !== undefined && initialFilters[key] !== null
            ? initialFilters[key] 
            : (sourceFilters[key] !== undefined ? sourceFilters[key] : null);
    });
    
    const page = ref(currentPage);
    let debounceTimeout = null;

    /**
     * Executes the server-side request and updates local storage.
     * * @param {boolean} replace Determines if the navigation history should be replaced.
     */
    const fetchResults = (replace = true) => {
        if (debounceTimeout) clearTimeout(debounceTimeout);
        
        debounceTimeout = setTimeout(() => {
            const params = {};
            
            if (page.value > 1) params.page = page.value;
            
            if (query.value) {
                params.search = query.value;
                params.query = query.value;
            }
            if (selectedStatus.value) params.status = selectedStatus.value;
            if (selectedCustomers.value?.length) params.customers = selectedCustomers.value;
            if (selectedAssignees.value?.length) params.assignees = selectedAssignees.value;
            if (selectedRole.value) params.role = selectedRole.value;
            if (selectedTags.value?.length) params.tags = selectedTags.value;

            // Append custom populated filters to the request payload
            customKeys.forEach(key => {
                const val = customFilters[key];
                if (val !== null && val !== undefined && val !== '') {
                    if (Array.isArray(val)) {
                        if (val.length > 0) params[key] = val;
                    } else {
                        params[key] = val;
                    }
                }
            });

            // Persist current active filters for future visits
            localStorage.setItem(storageKey, JSON.stringify(params));

            router.get(route(routeName), params, {
                preserveState: true,
                replace: replace,
                preserveScroll: true
            });
        }, 300);
    };

    // Reset page to 1 whenever any filter changes to avoid empty result sets on unmapped pages
    watch([query, selectedStatus, selectedCustomers, selectedAssignees, selectedRole, selectedTags, customFilters], () => {
        page.value = 1; 
        fetchResults(true);
    }, { deep: true });

    /**
     * Navigates to a specific pagination page while maintaining current filters.
     * * @param {number} newPage The target page number.
     */
    const changePage = (newPage) => {
        page.value = newPage;
        fetchResults(false);
    };

    onMounted(() => {
        // If the user navigated to the base URL without parameters, but we restored filters from storage,
        // we must dispatch an initial fetch so the table data matches the UI filter state.
        if (!hasUrlParams && Object.keys(savedFilters).length > 0) {
            const hasActiveFilters = 
                query.value || 
                selectedStatus.value || 
                selectedRole.value || 
                selectedCustomers.value?.length > 0 || 
                selectedAssignees.value?.length > 0 || 
                selectedTags.value?.length > 0 || 
                Object.values(customFilters).some(v => v !== null && v !== '' && (!Array.isArray(v) || v.length > 0));

            if (hasActiveFilters) {
                // Trigger fetch with state replacement to avoid double back-button issues
                fetchResults(true); 
            }
        }
    });

    return {
        query,
        selectedStatus,
        selectedCustomers,
        selectedAssignees,
        selectedRole,
        selectedTags,
        customFilters,
        page,
        changePage
    };
}