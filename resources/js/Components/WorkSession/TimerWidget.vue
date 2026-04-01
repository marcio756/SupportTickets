<script setup>
/**
 * TimerWidget Component
 * Provides global access to clock-in/out and pause/resume actions.
 * Integrates real-time work duration tracking, positioning the timer strictly on the left.
 */
import { usePage, router } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted } from 'vue';
import ActionConfirmModal from 'ActionConfirmModal.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const session = computed(() => page.props.auth.work_session);

const showEndModal = ref(false);

const startSession = () => router.post(route('work-sessions.start'), {}, { preserveScroll: true });
const pauseSession = () => router.post(route('work-sessions.pause'), {}, { preserveScroll: true });
const resumeSession = () => router.post(route('work-sessions.resume'), {}, { preserveScroll: true });

/**
 * Triggers the modal UI instead of a native browser confirm.
 */
const confirmEndSession = () => {
    showEndModal.value = true;
};

/**
 * Executes the actual end session request after user confirmation.
 */
const executeEndSession = () => {
    router.post(route('work-sessions.end'), {}, { 
        preserveScroll: true,
        onSuccess: () => showEndModal.value = false
    });
};

// ==========================================
// REAL-TIME CLOCK LOGIC
// ==========================================
const now = ref(new Date());
let timerInterval = null;

onMounted(() => {
    // Updates the internal clock every second to maintain reactive time
    timerInterval = setInterval(() => {
        now.value = new Date();
    }, 1000);
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
});

/**
 * Calculates effective work duration by ignoring past pauses.
 * @returns {string} Formatted duration.
 */
const workingTimeFormatted = computed(() => {
    if (!session.value) return '00:00:00';
    
    // Fallback normalization to ensure strict parsing compatibility (Safari/iOS safe)
    const startStr = session.value.started_at_iso || session.value.started_at;
    const validStartStr = startStr ? startStr.replace(' ', 'T') : null; 
    
    const startObj = new Date(validStartStr);
    if (isNaN(startObj.getTime())) return '00:00:00';

    const startMs = startObj.getTime();
    const endMs = session.value.ended_at_iso || session.value.ended_at 
                    ? new Date((session.value.ended_at_iso || session.value.ended_at).replace(' ', 'T')).getTime() 
                    : now.value.getTime();
    
    let totalWorkedMs = endMs - startMs;
    
    // Subtract paused durations
    if (session.value.pauses && session.value.pauses.length > 0) {
        session.value.pauses.forEach(pause => {
            const pStart = new Date((pause.started_at_iso || pause.started_at).replace(' ', 'T')).getTime();
            const pEnd = pause.ended_at_iso || pause.ended_at 
                            ? new Date((pause.ended_at_iso || pause.ended_at).replace(' ', 'T')).getTime() 
                            : endMs; 
            
            totalWorkedMs -= (pEnd - pStart);
        });
    }

    if (totalWorkedMs < 0) totalWorkedMs = 0;

    const hours = Math.floor(totalWorkedMs / 3600000);
    const minutes = Math.floor((totalWorkedMs % 3600000) / 60000);
    const seconds = Math.floor((totalWorkedMs % 60000) / 1000);

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

/**
 * Calculates current ongoing pause duration (if any).
 * @returns {string|null} Formatted duration.
 */
const currentPauseTimeFormatted = computed(() => {
    if (session.value?.status !== 'paused') return null;
    
    const currentPause = session.value.pauses?.find(p => !p.ended_at_iso && !p.ended_at);
    if (!currentPause) return '00:00';

    const pStart = new Date((currentPause.started_at_iso || currentPause.started_at).replace(' ', 'T')).getTime();
    const pauseElapsedMs = now.value.getTime() - pStart;

    const minutes = Math.floor(pauseElapsedMs / 60000);
    const seconds = Math.floor((pauseElapsedMs % 60000) / 1000);
    const hours = Math.floor(pauseElapsedMs / 3600000);

    if (hours > 0) {
        return `${hours}h ${String(minutes).padStart(2, '0')}m`;
    }
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});
</script>

<template>
    <div v-if="user && user.role === 'supporter'" class="flex items-center space-x-2 bg-white dark:bg-gray-900 p-1.5 px-3 rounded-full border border-gray-200 dark:border-gray-700 shadow-sm transition-all duration-300">
        
        <template v-if="!session">
            <div class="flex items-center">
                <div class="h-2 w-2 rounded-full bg-gray-400 mr-2"></div>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 mr-3 hidden md:inline">{{ $t('work_sessions.widget.offline') }}</span>
            </div>
            <button @click="startSession" class="px-4 py-1.5 bg-indigo-600 text-white rounded-full text-xs font-bold hover:bg-indigo-500 transition-all active:scale-95 shadow-sm">
                {{ $t('work_sessions.widget.clock_in') }}
            </button>
        </template>
        
        <template v-else-if="session.status === 'active'">
            <div class="flex items-center">
                
                <span class="font-mono tabular-nums text-xs font-semibold text-gray-700 dark:text-gray-200 mr-3 border-r border-gray-300 dark:border-gray-600 pr-3">
                    {{ workingTimeFormatted }}
                </span>

                <span class="flex h-2 w-2 relative mr-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                </span>
                <span class="text-xs font-bold text-green-600 dark:text-green-400 hidden lg:inline mr-3">{{ $t('work_sessions.widget.active') }}</span>
            </div>

            <div class="flex items-center gap-1">
                <button @click="pauseSession" class="p-1.5 bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400 rounded-full hover:bg-yellow-200 transition" :title="$t('work_sessions.widget.pause_shift')">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </button>
                <button @click="confirmEndSession" class="px-3 py-1.5 bg-red-600 text-white rounded-full text-xs font-bold hover:bg-red-500 transition-all active:scale-95 shadow-sm" :title="$t('work_sessions.widget.end_shift')">
                    {{ $t('work_sessions.widget.end_shift') }}
                </button>
            </div>
        </template>
        
        <template v-else-if="session.status === 'paused'">
            <div class="flex items-center">
                
                <span class="font-mono tabular-nums text-xs font-semibold text-gray-400 dark:text-gray-500 mr-2" :title="$t('work_sessions.widget.total_worked_time')">
                    {{ workingTimeFormatted }}
                </span>

                <span class="text-[10px] font-mono tabular-nums bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300 px-1.5 py-0.5 rounded shadow-inner flex items-center gap-1 mr-3 border-r border-gray-300 dark:border-gray-600 pr-3" :title="$t('work_sessions.widget.break_duration')">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ currentPauseTimeFormatted }}
                </span>

                <span class="h-2 w-2 rounded-full bg-yellow-500 mr-2"></span>
                <span class="text-xs font-bold text-yellow-600 dark:text-yellow-400 hidden lg:inline mr-3">{{ $t('work_sessions.widget.paused') }}</span>
            </div>

            <div class="flex items-center gap-1">
                <button @click="resumeSession" class="p-1.5 bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded-full hover:bg-green-200 transition" :title="$t('work_sessions.widget.resume_shift')">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </button>
                <button @click="confirmEndSession" class="px-3 py-1.5 bg-red-600 text-white rounded-full text-xs font-bold hover:bg-red-500 transition" :title="$t('work_sessions.widget.end_shift')">
                    {{ $t('work_sessions.widget.end_shift') }}
                </button>
            </div>
        </template>

        <ActionConfirmModal 
            :show="showEndModal"
            :title="$t('work_sessions.widget.modal.title')"
            :message="$t('work_sessions.widget.modal.message')"
            :confirm-text="$t('work_sessions.widget.modal.confirm')"
            :cancel-text="$t('work_sessions.widget.modal.cancel')"
            intent="warning"
            @confirm="executeEndSession"
            @close="showEndModal = false"
        />
    </div>
</template>