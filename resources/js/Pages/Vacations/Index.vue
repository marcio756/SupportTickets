<template>
    <AppLayout title="Vacation Calendar">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Vacation Map
                </h2>
                <PrimaryButton @click="showBookModal = true" icon="add">
                    Book Vacation
                </PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <StatCard 
                        title="Annual Total" 
                        :value="summary.total_allowed" 
                        icon="event"
                        class="border-l-4 border-blue-500"
                    />
                    <StatCard 
                        title="Used Days" 
                        :value="summary.used_days" 
                        icon="event_busy"
                        class="border-l-4 border-red-500"
                    />
                    <StatCard 
                        title="Remaining Days" 
                        :value="summary.remaining_days" 
                        icon="event_available"
                        class="border-l-4 border-green-500"
                    />
                </div>

                <div class="bg-white dark:bg-background-secondary shadow-sm sm:rounded-lg p-6 border dark:border-gray-800">
                    <div v-if="isLoading" class="text-center py-8 text-gray-500 dark:text-gray-400">
                        Loading vacation data...
                    </div>
                    
                    <VacationCalendar 
                        v-else 
                        :supporters="supporters" 
                        :vacations="globalVacations" 
                    />
                </div>

            </div>
        </div>

        <BookVacationModal 
            :show="showBookModal" 
            @close="showBookModal = false"
            @success="handleBookingSuccess"
        />
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatCard from '@/Components/Dashboard/StatCard.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import VacationCalendar from '@/Components/Vacations/VacationCalendar.vue';
import BookVacationModal from '@/Components/Vacations/BookVacationModal.vue';
import { useVacations } from '@/Composables/useVacations.js';

const { props } = usePage();
const { summary, fetchSupporterVacations } = useVacations();

const showBookModal = ref(false);
const isLoading = ref(true);
const supporters = ref([]);
const globalVacations = ref([]);

const authUserId = props.auth?.user?.id;

const loadDashboardData = async () => {
    isLoading.value = true;
    try {
        // Fetch personal stats
        if (authUserId) {
            await fetchSupporterVacations(authUserId);
        }

        // Fetch grid data (Supporters + Global Vacations) concurrently
        const [supportersRes, vacationsRes] = await Promise.all([
            axios.get('/api/supporters'),
            axios.get('/api/vacations')
        ]);

        supporters.value = supportersRes.data.data;
        globalVacations.value = vacationsRes.data.data;
    } catch (error) {
        console.error("Failed to fetch calendar data", error);
    } finally {
        isLoading.value = false;
    }
};

const handleBookingSuccess = () => {
    alert("Vacation booked successfully!");
    // Reload all data to reflect the new vacation on the calendar and stats
    loadDashboardData();
};

onMounted(() => {
    loadDashboardData();
});
</script>