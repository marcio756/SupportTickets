import { ref } from 'vue';
import axios from 'axios';

/**
 * Composable for managing Vacation API interactions.
 */
export function useVacations() {
    const vacations = ref([]);
    const summary = ref({
        total_allowed: 22,
        used_days: 0,
        remaining_days: 22,
        year: new Date().getFullYear(),
    });
    const isLoading = ref(false);
    const errors = ref(null);

    const fetchAllVacations = async () => {
        isLoading.value = true;
        try {
            const response = await axios.get('/api/vacations');
            vacations.value = response.data.data;
        } catch (error) {
            errors.value = error.response?.data?.message || 'Failed to fetch vacations.';
        } finally {
            isLoading.value = false;
        }
    };

    const fetchSupporterVacations = async (supporterId) => {
        isLoading.value = true;
        try {
            const response = await axios.get(`/api/vacations/supporter/${supporterId}`);
            vacations.value = response.data.data;
            summary.value = response.data.summary;
        } catch (error) {
            errors.value = error.response?.data?.message || 'Failed to fetch supporter vacations.';
        } finally {
            isLoading.value = false;
        }
    };

    const bookVacation = async (startDate, endDate) => {
        isLoading.value = true;
        errors.value = null;
        try {
            await axios.post('/api/vacations', {
                start_date: startDate,
                end_date: endDate,
            });
            return true;
        } catch (error) {
            errors.value = error.response?.data?.errors || { general: ['Failed to book vacation.'] };
            return false;
        } finally {
            isLoading.value = false;
        }
    };

    return {
        vacations,
        summary,
        isLoading,
        errors,
        fetchAllVacations,
        fetchSupporterVacations,
        bookVacation,
    };
}