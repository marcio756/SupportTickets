<script setup>
/**
 * TimerWidget Component
 * Provides global access to clock-in/out and pause/resume actions.
 * Integrates a custom modal to prevent accidental shift termination.
 */
import { usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ActionConfirmModal from '@/Components/Common/ActionConfirmModal.vue';

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
</script>

<template>
    <div v-if="user && user.role === 'supporter'" class="flex items-center space-x-2 bg-white dark:bg-gray-900 p-1.5 px-3 rounded-full border border-gray-200 dark:border-gray-700 shadow-sm">
        
        <template v-if="!session">
            <div class="flex items-center">
                <div class="h-2 w-2 rounded-full bg-gray-400 mr-2"></div>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 mr-3 hidden md:inline">Offline</span>
            </div>
            <button @click="startSession" class="px-4 py-1.5 bg-indigo-600 text-white rounded-full text-xs font-bold hover:bg-indigo-500 transition-all active:scale-95 shadow-sm">
                ▶ Clock In
            </button>
        </template>
        
        <template v-else-if="session.status === 'active'">
            <div class="flex items-center mr-2">
                <span class="flex h-2 w-2 relative mr-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                </span>
                <span class="text-xs font-bold text-green-600 dark:text-green-400 hidden lg:inline">Active</span>
            </div>

            <div class="flex items-center gap-1">
                <button @click="pauseSession" class="p-1.5 bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400 rounded-full hover:bg-yellow-200 transition" title="Pause Shift">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </button>
                <button @click="confirmEndSession" class="px-3 py-1.5 bg-red-600 text-white rounded-full text-xs font-bold hover:bg-red-500 transition-all active:scale-95 shadow-sm" title="End Shift">
                    ⏹ End Shift
                </button>
            </div>
        </template>
        
        <template v-else-if="session.status === 'paused'">
            <div class="flex items-center mr-2">
                <span class="h-2 w-2 rounded-full bg-yellow-500 mr-2"></span>
                <span class="text-xs font-bold text-yellow-600 dark:text-yellow-400 hidden lg:inline">Paused</span>
            </div>

            <div class="flex items-center gap-1">
                <button @click="resumeSession" class="p-1.5 bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded-full hover:bg-green-200 transition" title="Resume Shift">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </button>
                <button @click="confirmEndSession" class="px-3 py-1.5 bg-red-600 text-white rounded-full text-xs font-bold hover:bg-red-500 transition" title="End Shift">
                    ⏹ End Shift
                </button>
            </div>
        </template>

        <ActionConfirmModal 
            :show="showEndModal"
            title="End Today's Shift?"
            message="Are you sure you want to clock out for today?&#10;&#10;Due to system policies, you cannot restart your shift once it is ended. If you are taking a short break, please use the 'Pause' feature instead."
            confirm-text="Yes, End Shift"
            cancel-text="Keep Working"
            intent="warning"
            @confirm="executeEndSession"
            @close="showEndModal = false"
        />
    </div>
</template>