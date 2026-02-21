import { ref, computed } from 'vue';

/**
 * Provides an encapsulated logic block for filtering collections of objects.
 * Designed to be generic and reusable across different resource listings like Tickets or Users.
 * * @param {Array} initialData - The raw array of objects to filter.
 * @param {Array} searchFields - Keys in the objects to match the search query against.
 * @returns {Object} State and computed properties for the filtering context.
 */
export function useFilters(initialData = [], searchFields = ['title', 'name']) {
    const query = ref('');
    const selectedStatus = ref('');
    const sourceData = ref(initialData);

    const setSourceData = (data) => {
        sourceData.value = data;
    };

    // Computes the intersection of the search query and the status dropdown to yield the final dataset
    const filteredResults = computed(() => {
        let results = sourceData.value;

        if (selectedStatus.value) {
            results = results.filter(item => item.status === selectedStatus.value);
        }

        if (query.value) {
            const lowerQuery = query.value.toLowerCase();
            results = results.filter(item => {
                return searchFields.some(field => {
                    return item[field] && String(item[field]).toLowerCase().includes(lowerQuery);
                });
            });
        }

        return results;
    });

    return {
        query,
        selectedStatus,
        filteredResults,
        setSourceData
    };
}