/**
 * resources/js/Composables/useFilters.js
 * Provides an encapsulated logic block for handling server-side filtering via Inertia.js.
 */
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

export function useFilters(initialFilters = {}, routeName, currentPage = 1) {
    const query = ref(initialFilters.search || '');
    const selectedStatus = ref(initialFilters.status || '');
    const selectedCustomers = ref(initialFilters.customers || []);
    const selectedAssignees = ref(initialFilters.assignees || []);
    const selectedRole = ref(initialFilters.role || ''); // Adicionado para suportar Users
    
    const page = ref(currentPage);
    let debounceTimeout = null;

    const fetchResults = (replace = true) => {
        if (debounceTimeout) clearTimeout(debounceTimeout);
        
        debounceTimeout = setTimeout(() => {
            // Filter empty arrays and null values to keep URL clean
            const params = {
                search: query.value,
                page: page.value
            };
            if (selectedStatus.value) params.status = selectedStatus.value;
            if (selectedCustomers.value?.length) params.customers = selectedCustomers.value;
            if (selectedAssignees.value?.length) params.assignees = selectedAssignees.value;
            if (selectedRole.value) params.role = selectedRole.value;

            router.get(route(routeName), params, {
                preserveState: true,
                replace: replace,
                preserveScroll: true
            });
        }, 300);
    };

    watch([query, selectedStatus, selectedCustomers, selectedAssignees, selectedRole], () => {
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
        page,
        changePage
    };
}