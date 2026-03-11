<template>
    <AppLayout title="Teams Management">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Teams Management
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white dark:bg-background-secondary overflow-hidden shadow-sm sm:rounded-lg p-6 border dark:border-gray-800">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">List of Teams</h3>
                        <PrimaryButton @click="openCreateModal">
                            Add New Team
                        </PrimaryButton>
                    </div>

                    <div v-if="isLoading" class="text-center py-4 text-gray-500 dark:text-gray-400">
                        Loading teams...
                    </div>

                    <div v-else-if="errors" class="text-red-500 mb-4">
                        {{ errors }}
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800/50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Shift</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Supporters</th>
                                    <th scope="col" class="relative px-6 py-3">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-background-secondary divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="team in teams" :key="team.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">#{{ team.id }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ team.name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 capitalize">{{ team.shift }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ team.supporters ? team.supporters.length : 0 }} Members
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button @click="deleteTeam(team.id)" class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300 transition-colors">Delete</button>
                                    </td>
                                </tr>
                                <tr v-if="teams.length === 0">
                                    <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No teams found. Create one above!
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const teams = ref([]);
const isLoading = ref(true);
const errors = ref(null);

/**
 * Fetch all teams from our API endpoint
 */
const fetchTeams = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/api/teams');
        teams.value = response.data.data;
    } catch (error) {
        errors.value = 'Failed to load teams data.';
        console.error(error);
    } finally {
        isLoading.value = false;
    }
};

const deleteTeam = async (id) => {
    if (!confirm('Are you sure you want to delete this team?')) return;
    try {
        await axios.delete(`/api/teams/${id}`);
        await fetchTeams();
    } catch (error) {
        alert('Could not delete team.');
    }
};

const openCreateModal = () => {
    alert("Modal creation logic will be implemented in the next step.");
};

onMounted(() => {
    fetchTeams();
});
</script>