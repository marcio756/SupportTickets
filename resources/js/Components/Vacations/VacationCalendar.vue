<template>
    <div class="overflow-hidden">
        <div class="flex justify-between items-center mb-4 px-2">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 font-mono">
                {{ monthName }} {{ currentYear }}
            </h3>
            <div class="flex space-x-2">
                <SecondaryButton @click="prevMonth" class="!px-3 py-1">&larr; Prev</SecondaryButton>
                <SecondaryButton @click="nextMonth" class="!px-3 py-1">Next &rarr;</SecondaryButton>
            </div>
        </div>

        <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800/80">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400 tracking-wider sticky left-0 bg-gray-50 dark:bg-gray-800 z-10 w-48">Supporter</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400 tracking-wider">Team</th>
                        <th class="px-3 py-3 text-center font-medium text-gray-500 dark:text-gray-400 tracking-wider">Shift</th>
                        <th class="px-3 py-3 text-center font-medium text-gray-500 dark:text-gray-400 tracking-wider">Used</th>
                        <th class="px-3 py-3 text-center font-medium text-gray-500 dark:text-gray-400 tracking-wider">Left</th>
                        <th v-for="day in daysInMonth" :key="day" class="px-1 py-3 text-center font-medium text-gray-400 dark:text-gray-500 min-w-[32px]">
                            {{ day }}
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-background-secondary divide-y divide-gray-200 dark:divide-gray-800">
                    <tr v-for="supporter in mappedSupporters" :key="supporter.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors">
                        <td class="px-4 py-2 font-medium text-gray-900 dark:text-gray-200 sticky left-0 bg-white dark:bg-background-secondary z-10 truncate border-r dark:border-gray-800">
                            {{ supporter.name }}
                        </td>
                        <td class="px-4 py-2 text-gray-500 dark:text-gray-400 truncate max-w-[120px]">
                            {{ supporter.team?.name || 'Unassigned' }}
                        </td>
                        <td class="px-3 py-2 text-center text-gray-500 dark:text-gray-400 capitalize">
                            <span v-if="supporter.team?.shift" class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-gray-700">
                                {{ supporter.team.shift }}
                            </span>
                            <span v-else>-</span>
                        </td>
                        <td class="px-3 py-2 text-center text-red-600 dark:text-red-400 font-bold">{{ supporter.usedDays }}</td>
                        <td class="px-3 py-2 text-center text-green-600 dark:text-green-400 font-bold border-r dark:border-gray-800">{{ supporter.remainingDays }}</td>
                        
                        <td v-for="day in daysInMonth" :key="day" class="p-1 border-r border-gray-100 dark:border-gray-800/50 relative">
                            <div 
                                v-if="isVacationDay(supporter.id, day)" 
                                class="absolute inset-1 bg-blue-500/80 dark:bg-blue-600 rounded-sm shadow-sm"
                                title="On Vacation"
                            ></div>
                            <div v-else-if="isWeekend(day)" class="absolute inset-0 bg-gray-50 dark:bg-gray-900/50"></div>
                        </td>
                    </tr>
                    <tr v-if="mappedSupporters.length === 0">
                        <td :colspan="5 + daysInMonth" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                            No supporters found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    supporters: { type: Array, required: true },
    vacations: { type: Array, required: true },
});

// State for Month/Year Selection
const currentDate = new Date();
const currentMonth = ref(currentDate.getMonth());
const currentYear = ref(currentDate.getFullYear());

// Computed Properties for Calendar Layout
const monthName = computed(() => {
    return new Date(currentYear.value, currentMonth.value).toLocaleString('en-US', { month: 'long' });
});

const daysInMonth = computed(() => {
    return new Date(currentYear.value, currentMonth.value + 1, 0).getDate();
});

// Maps Supporters to include their calculated vacation limits
const mappedSupporters = computed(() => {
    return props.supporters.map(supporter => {
        // Calculate total used days in the current viewing year
        const usedDays = props.vacations
            .filter(v => v.supporter_id === supporter.id && v.year === currentYear.value)
            .reduce((sum, v) => sum + v.total_days, 0);

        return {
            ...supporter,
            usedDays,
            remainingDays: Math.max(0, 22 - usedDays)
        };
    });
});

// Calendar Helpers
const isWeekend = (day) => {
    const date = new Date(currentYear.value, currentMonth.value, day);
    const dayOfWeek = date.getDay();
    return dayOfWeek === 0 || dayOfWeek === 6; // 0 = Sunday, 6 = Saturday
};

const isVacationDay = (supporterId, day) => {
    // Format date to YYYY-MM-DD for comparison
    const targetDateStr = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    const targetDate = new Date(targetDateStr).getTime();

    // Check if this date falls within any of this supporter's vacation ranges
    return props.vacations.some(v => {
        if (v.supporter_id !== supporterId) return false;
        const start = new Date(v.start_date).getTime();
        const end = new Date(v.end_date).getTime();
        return targetDate >= start && targetDate <= end && !isWeekend(day);
    });
};

// Navigation
const prevMonth = () => {
    if (currentMonth.value === 0) {
        currentMonth.value = 11;
        currentYear.value--;
    } else {
        currentMonth.value--;
    }
};

const nextMonth = () => {
    if (currentMonth.value === 11) {
        currentMonth.value = 0;
        currentYear.value++;
    } else {
        currentMonth.value++;
    }
};
</script>