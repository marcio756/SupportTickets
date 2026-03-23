<template>
  <div class="overflow-hidden">
    <div class="flex justify-between items-center mb-4 px-2">
      <h3 class="text-lg font-medium font-mono capitalize" style="color: var(--va-text-primary)">
          {{ monthName }} {{ currentYear }}
      </h3>
      <div class="flex space-x-2">
          <va-button preset="secondary" size="small" @click="prevMonth">&larr; {{ $t('vacations.calendar.prev') }}</va-button>
          <va-button preset="secondary" size="small" @click="nextMonth">{{ $t('vacations.calendar.next') }} &rarr;</va-button>
      </div>
    </div>

    <div class="overflow-x-auto border rounded-lg border-solid" style="border-color: var(--va-background-border);">
      <table class="min-w-full text-sm border-collapse">
        <thead style="background-color: var(--va-background-element); border-bottom: 1px solid var(--va-background-border);">
            <tr>
                <th class="px-4 py-3 text-left font-medium sticky left-0 z-10 w-48" style="color: var(--va-secondary); background-color: var(--va-background-element);">{{ $t('vacations.calendar.supporter') }}</th>
                <th class="px-4 py-3 text-left font-medium" style="color: var(--va-secondary);">{{ $t('vacations.calendar.team') }}</th>
                <th class="px-3 py-3 text-center font-medium" style="color: var(--va-secondary);">{{ $t('vacations.calendar.shift') }}</th>
                <th class="px-3 py-3 text-center font-medium" style="color: var(--va-secondary);">{{ $t('vacations.calendar.used') }}</th>
                <th class="px-3 py-3 text-center font-medium" style="color: var(--va-secondary);">{{ $t('vacations.calendar.left') }}</th>
                <th v-for="day in daysInMonth" :key="day" class="px-1 py-3 text-center font-medium min-w-[32px]" style="color: var(--va-secondary);">
                    {{ day }}
                </th>
            </tr>
        </thead>
        <tbody style="background-color: var(--va-background-secondary);">
            <tr v-for="supporter in mappedSupporters" :key="supporter.id" style="border-bottom: 1px solid var(--va-background-border);">
                <td class="px-4 py-2 font-medium sticky left-0 z-10 truncate border-r border-solid" style="color: var(--va-text-primary); background-color: var(--va-background-secondary); border-color: var(--va-background-border);">
                    {{ supporter.name }}
                </td>
                <td class="px-4 py-2 truncate max-w-[120px]" style="color: var(--va-secondary);">
                    {{ supporter.team?.name || $t('vacations.calendar.unassigned') }}
                </td>
                <td class="px-3 py-2 text-center capitalize" style="color: var(--va-secondary);">
                    <va-badge v-if="supporter.team?.shift" :text="$t(`teams.shifts.${supporter.team.shift}`)" color="info" size="small" />
                    <span v-else>-</span>
                </td>
                <td class="px-3 py-2 text-center font-bold" style="color: var(--va-danger);">{{ supporter.usedDays }}</td>
                <td class="px-3 py-2 text-center font-bold border-r border-solid" style="color: var(--va-success); border-color: var(--va-background-border);">{{ supporter.remainingDays }}</td>
                
                <td v-for="day in daysInMonth" :key="day" class="p-1 border-r border-solid relative" style="border-color: var(--va-background-border);">
                    
                    <div v-if="isWeekend(day)" class="absolute inset-0" style="background-color: rgba(0,0,0,0.05); pointer-events: none;"></div>

                    <template v-if="getVacationDay(supporter.id, day)">
                        
                        <div v-if="isWeekend(day)" 
                             class="absolute left-0 right-0 flex items-center justify-center shadow-sm" 
                             style="top: 38%; bottom: 38%; opacity: 0.6;"
                             :style="{ 
                                backgroundColor: getVacationDay(supporter.id, day).status === 'pending' ? 'var(--va-warning)' : 
                                                 getVacationDay(supporter.id, day).status === 'completed' ? 'var(--va-secondary)' : 'var(--va-primary)'
                             }"
                             :title="$t('vacations.calendar.weekend')">
                        </div>

                        <div v-else-if="getVacationDay(supporter.id, day).status === 'pending'" 
                             class="absolute inset-1 rounded-sm shadow-sm border border-dashed" 
                             style="border-color: var(--va-warning); background-color: rgba(225, 190, 50, 0.2);" 
                             :title="$t('vacations.calendar.pending')">
                        </div>

                        <div v-else-if="getVacationDay(supporter.id, day).status === 'completed'" 
                             class="absolute inset-1 rounded-sm shadow-sm" 
                             style="background-color: var(--va-secondary); opacity: 0.6;" 
                             :title="$t('vacations.calendar.completed')">
                        </div>

                        <div v-else 
                             class="absolute inset-1 rounded-sm shadow-sm" 
                             style="background-color: var(--va-primary);" 
                             :title="$t('vacations.calendar.approved')">
                        </div>

                    </template>
                </td>
            </tr>
            <tr v-if="mappedSupporters.length === 0">
                <td :colspan="5 + daysInMonth" class="px-6 py-8 text-center" style="color: var(--va-secondary);">
                    {{ $t('vacations.calendar.no_supporters') }}
                </td>
            </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
/**
 * Vacation Calendar Component.
 * Renders an interactive matrix tracking employee absence states dynamically by month.
 * Localizes time strings using native Intl APIS driven by vue-i18n.
 */
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    supporters: { type: Array, required: true },
    vacations: { type: Array, required: true },
});

const { locale } = useI18n();

const currentDate = new Date();
const currentMonth = ref(currentDate.getMonth());
const currentYear = ref(currentDate.getFullYear());

/**
 * Derives the localized full name of the targeted month.
 * @returns {string} The localized month string.
 */
const monthName = computed(() => {
    return new Date(currentYear.value, currentMonth.value).toLocaleString(locale.value, { month: 'long' });
});

/**
 * Calculates total available days in the current localized matrix.
 * @returns {number}
 */
const daysInMonth = computed(() => {
    return new Date(currentYear.value, currentMonth.value + 1, 0).getDate();
});

/**
 * Aggregates supporters array with computed allowance metrics.
 * Limits computations strictly to ongoing cycles excluding rejections.
 * @returns {Array<Object>}
 */
const mappedSupporters = computed(() => {
    return props.supporters.map(supporter => {
        // Ignores rejections for quota math. Pending and completed subtract availability.
        const usedDays = props.vacations
            .filter(v => v.supporter_id === supporter.id && v.year === currentYear.value && v.status !== 'rejected')
            .reduce((sum, v) => sum + v.total_days, 0);

        return { ...supporter, usedDays, remainingDays: Math.max(0, 22 - usedDays) };
    });
});

/**
 * Boolean check for standard weekend offset rendering (Sat/Sun).
 * @param {number} day 
 * @returns {boolean}
 */
const isWeekend = (day) => {
    const date = new Date(currentYear.value, currentMonth.value, day);
    const dayOfWeek = date.getDay();
    return dayOfWeek === 0 || dayOfWeek === 6; 
};

/**
 * Locates an active vacation node crossing the target matrix cell.
 * @param {number|string} supporterId 
 * @param {number} day 
 * @returns {Object|undefined} The matching vacation record if exists.
 */
const getVacationDay = (supporterId, day) => {
    const targetStr = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

    return props.vacations.find(v => {
        if (v.supporter_id !== supporterId) return false;
        if (v.status === 'rejected') return false; 
        
        // Remove timestamps to strictly compare YYYY-MM-DD
        const sDate = v.start_date.substring(0, 10);
        const eDate = v.end_date.substring(0, 10);
        
        // Weekend filtering omitted to render continuous HTML bridges
        return targetStr >= sDate && targetStr <= eDate;
    });
};

const prevMonth = () => { if (currentMonth.value === 0) { currentMonth.value = 11; currentYear.value--; } else { currentMonth.value--; } };
const nextMonth = () => { if (currentMonth.value === 11) { currentMonth.value = 0; currentYear.value++; } else { currentMonth.value++; } };
</script>