/**
 * resources/js/Composables/useFilters.js
 * Provides encapsulated logic for server-side filtering with localStorage persistence.
 */
import { ref, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

export function useFilters(initialFilters = {}, routeName, currentPage = 1) {
    const storageKey = `filters-${routeName}`;
    
    // Initialize from URL/Props or fallback to localStorage
    const savedFilters = JSON.parse(localStorage.getItem(storageKey) || '{}');

    const query = ref(initialFilters.search || savedFilters.search || '');
    const selectedStatus = ref(initialFilters.status || savedFilters.status || '');
    const selectedCustomers = ref(initialFilters.customers || savedFilters.customers || []);
    const selectedAssignees = ref(initialFilters.assignees || savedFilters.assignees || []);
    const selectedRole = ref(initialFilters.role || savedFilters.role || '');
    
    const page = ref(currentPage);
    let debounceTimeout = null;

    /**
     * Executes the server-side request and updates local storage.
     */
    const fetchResults = (replace = true) => {
        if (debounceTimeout) clearTimeout(debounceTimeout);
        
        debounceTimeout = setTimeout(() => {
            const params = {
                search: query.value,
                page: page.value
            };
            
            if (selectedStatus.value) params.status = selectedStatus.value;
            if (selectedCustomers.value?.length) params.customers = selectedCustomers.value;
            if (selectedAssignees.value?.length) params.assignees = selectedAssignees.value;
            if (selectedRole.value) params.role = selectedRole.value;

            // Persist the current filter state
            localStorage.setItem(storageKey, JSON.stringify(params));

            router.get(route(routeName), params, {
                preserveState: true,
                replace: replace,
                preserveScroll: true
            });
        }, 300);
    };

    // Reset page to 1 whenever a filter changes to avoid empty results on high page numbers
    watch([query, selectedStatus, selectedCustomers, selectedAssignees, selectedRole], () => {
        page.value = 1; 
        fetchResults(true);
    }, { deep: true });

    /**
     * Handles pagination changes.
     */
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
        page,
        changePage
    };
}