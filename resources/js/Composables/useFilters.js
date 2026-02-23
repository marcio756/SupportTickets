/**
 * resources/js/Composables/useFilters.js
 * * Provides an encapsulated logic block for handling server-side filtering via Inertia.js.
 */
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

export function useFilters(initialFilters = {}, routeName, currentPage = 1) {
    const query = ref(initialFilters.search || '');
    const selectedStatus = ref(initialFilters.status || '');
    const selectedCustomers = ref(initialFilters.customers || []);
    
    // Arrays para suportar opções múltiplas
    const selectedAssignees = ref(initialFilters.assignees || []);
    
    const page = ref(currentPage);

    let debounceTimeout = null;

    const fetchResults = (replace = true) => {
        if (debounceTimeout) clearTimeout(debounceTimeout);
        
        debounceTimeout = setTimeout(() => {
            router.get(route(routeName), {
                search: query.value,
                status: selectedStatus.value,
                customers: selectedCustomers.value,
                assignees: selectedAssignees.value,
                page: page.value
            }, {
                preserveState: true,
                replace: replace,
                preserveScroll: true
            });
        }, 300);
    };

    // Observar todas as propriedades de filtros para emitir a chamada
    watch([query, selectedStatus, selectedCustomers, selectedAssignees], () => {
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
        page,
        changePage
    };
}