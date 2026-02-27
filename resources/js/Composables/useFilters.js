/**
 * resources/js/Composables/useFilters.js
 * Provides encapsulated logic for server-side filtering with localStorage persistence.
 */
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * @param {Object} initialFilters Initial filter values from the server.
 * @param {string} routeName The Ziggy route name to call.
 * @param {number} currentPage The current pagination page.
 * @returns {Object} Reactive filter variables and methods.
 */
export function useFilters(initialFilters = {}, routeName, currentPage = 1) {
    const storageKey = `filters-${routeName}`;
    
    const savedFilters = JSON.parse(localStorage.getItem(storageKey) || '{}');

    const query = ref(initialFilters.search || initialFilters.query || savedFilters.search || savedFilters.query || '');
    const selectedStatus = ref(initialFilters.status || savedFilters.status || '');
    const selectedCustomers = ref(initialFilters.customers || savedFilters.customers || []);
    const selectedAssignees = ref(initialFilters.assignees || savedFilters.assignees || []);
    const selectedRole = ref(initialFilters.role || savedFilters.role || '');
    // New reactive reference for handling tag selections
    const selectedTags = ref(initialFilters.tags || savedFilters.tags || []);
    
    const page = ref(currentPage);
    let debounceTimeout = null;

    /**
     * Executes the server-side request and updates local storage.
     * @param {boolean} replace Determines if the navigation history should be replaced.
     */
    const fetchResults = (replace = true) => {
        if (debounceTimeout) clearTimeout(debounceTimeout);
        
        debounceTimeout = setTimeout(() => {
            const params = {
                search: query.value,
                query: query.value, 
                page: page.value
            };
            
            if (selectedStatus.value) params.status = selectedStatus.value;
            if (selectedCustomers.value?.length) params.customers = selectedCustomers.value;
            if (selectedAssignees.value?.length) params.assignees = selectedAssignees.value;
            if (selectedRole.value) params.role = selectedRole.value;
            // Append selected tags to the request payload
            if (selectedTags.value?.length) params.tags = selectedTags.value;

            localStorage.setItem(storageKey, JSON.stringify(params));

            router.get(route(routeName), params, {
                preserveState: true,
                replace: replace,
                preserveScroll: true
            });
        }, 300);
    };

    // Reset page to 1 whenever any filter changes to avoid empty result sets
    watch([query, selectedStatus, selectedCustomers, selectedAssignees, selectedRole, selectedTags], () => {
        page.value = 1; 
        fetchResults(true);
    }, { deep: true });

    const changePage = (newPage) => {
        page.value = newPage;
        fetchResults(false);
    };

    return {
        query,
        selectedStatus,
        selectedCustomers,
        selectedAssignees,
        selectedRole,
        selectedTags,
        page,
        changePage
    };
}