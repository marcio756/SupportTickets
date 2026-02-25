/**
 * resources/js/Composables/useFilters.js
 * Fornece lógica encapsulada para filtragem server-side com persistência em localStorage.
 */
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * @param {Object} initialFilters Valores iniciais do filtro.
 * @param {string} routeName O nome da rota Ziggy a chamar (ex: 'users.index' ou 'tickets.index').
 * @param {number} currentPage A página atual da paginação.
 * @returns {Object} Variáveis e métodos de filtragem reativos.
 */
export function useFilters(initialFilters = {}, routeName, currentPage = 1) {
    const storageKey = `filters-${routeName}`;
    
    // Inicializa do URL/Props ou faz fallback para localStorage
    const savedFilters = JSON.parse(localStorage.getItem(storageKey) || '{}');

    // A variável query acomoda tanto 'search' (Tickets) como 'query' (Users)
    const query = ref(initialFilters.search || initialFilters.query || savedFilters.search || savedFilters.query || '');
    const selectedStatus = ref(initialFilters.status || savedFilters.status || '');
    const selectedCustomers = ref(initialFilters.customers || savedFilters.customers || []);
    const selectedAssignees = ref(initialFilters.assignees || savedFilters.assignees || []);
    const selectedRole = ref(initialFilters.role || savedFilters.role || '');
    
    const page = ref(currentPage);
    let debounceTimeout = null;

    /**
     * Executa o pedido server-side e atualiza a local storage.
     * @param {boolean} replace Define se substitui o histórico de navegação.
     */
    const fetchResults = (replace = true) => {
        if (debounceTimeout) clearTimeout(debounceTimeout);
        
        debounceTimeout = setTimeout(() => {
            const params = {
                // Envia as duas keys para garantir compatibilidade com ambos os Controllers
                search: query.value,
                query: query.value, 
                page: page.value
            };
            
            if (selectedStatus.value) params.status = selectedStatus.value;
            if (selectedCustomers.value?.length) params.customers = selectedCustomers.value;
            if (selectedAssignees.value?.length) params.assignees = selectedAssignees.value;
            if (selectedRole.value) params.role = selectedRole.value;

            // Persiste o estado atual do filtro
            localStorage.setItem(storageKey, JSON.stringify(params));

            router.get(route(routeName), params, {
                preserveState: true,
                replace: replace,
                preserveScroll: true
            });
        }, 300);
    };

    // Fazer reset à página para 1 sempre que um filtro muda para evitar resultados vazios
    watch([query, selectedStatus, selectedCustomers, selectedAssignees, selectedRole], () => {
        page.value = 1; 
        fetchResults(true);
    }, { deep: true });

    /**
     * Lida com as mudanças de paginação.
     * @param {number} newPage
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