import { describe, it, expect } from 'vitest';
import { useFilters } from '../../../resources/js/Composables/useFilters';

describe('useFilters Composable', () => {
    const mockData = [
        { id: 1, title: 'Server Down', status: 'open', customer: 'Alice' },
        { id: 2, title: 'Billing Issue', status: 'closed', customer: 'Bob' },
        { id: 3, title: 'Login Error', status: 'open', customer: 'Charlie' }
    ];

    it('should return all items when no filters are applied', () => {
        const { filteredResults } = useFilters(mockData, ['title']);
        expect(filteredResults.value.length).toBe(3);
    });

    it('should filter by exact status match', () => {
        const { selectedStatus, filteredResults } = useFilters(mockData, ['title']);
        
        selectedStatus.value = 'open';
        expect(filteredResults.value.length).toBe(2);
        expect(filteredResults.value[0].id).toBe(1);
        expect(filteredResults.value[1].id).toBe(3);
    });

    it('should filter by search query ignoring casing', () => {
        const { query, filteredResults } = useFilters(mockData, ['title', 'customer']);
        
        query.value = 'error';
        expect(filteredResults.value.length).toBe(1);
        expect(filteredResults.value[0].id).toBe(3);

        query.value = 'BOB';
        expect(filteredResults.value.length).toBe(1);
        expect(filteredResults.value[0].customer).toBe('Bob');
    });

    it('should combine text query and status filter restrictively', () => {
        const { query, selectedStatus, filteredResults } = useFilters(mockData, ['title']);
        
        selectedStatus.value = 'open';
        query.value = 'login';
        
        expect(filteredResults.value.length).toBe(1);
        expect(filteredResults.value[0].title).toBe('Login Error');
    });
});