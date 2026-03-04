<script setup>
import { usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const session = computed(() => page.props.auth.work_session);

const startSession = () => router.post(route('work-sessions.start'), {}, { preserveScroll: true });
const pauseSession = () => router.post(route('work-sessions.pause'), {}, { preserveScroll: true });
const resumeSession = () => router.post(route('work-sessions.resume'), {}, { preserveScroll: true });
const endSession = () => router.post(route('work-sessions.end'), {}, { preserveScroll: true });
</script>

<template>
    <div v-if="user && user.role === 'supporter'" class="flex items-center space-x-2 bg-gray-100 dark:bg-gray-800 p-2 rounded-lg border border-gray-200 dark:border-gray-700">
        
        <template v-if="!session">
            <span class="text-sm text-gray-500 dark:text-gray-400 mr-2 hidden sm:inline">Offline</span>
            <button @click="startSession" class="px-4 py-1.5 bg-indigo-600 text-white rounded text-sm font-bold shadow hover:bg-indigo-500 transition">
                ▶ Clock In
            </button>
        </template>
        
        <template v-else-if="session.status === 'active'">
            <span class="flex h-3 w-3 relative ml-1">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
            </span>
            <span class="text-sm font-bold text-green-600 dark:text-green-400 mr-2 hidden sm:inline">On Duty</span>

            <button @click="pauseSession" class="px-3 py-1.5 bg-yellow-500 text-white rounded text-xs font-bold shadow hover:bg-yellow-400 transition" title="Pause Shift">
                ⏸ Pause
            </button>
            <button @click="endSession" class="px-3 py-1.5 bg-red-600 text-white rounded text-xs font-bold shadow hover:bg-red-500 transition" title="End Shift">
                ⏹ Clock Out
            </button>
        </template>
        
        <template v-else-if="session.status === 'paused'">
            <span class="flex h-3 w-3 relative ml-1">
                <span class="relative inline-flex rounded-full h-3 w-3 bg-yellow-500"></span>
            </span>
            <span class="text-sm font-bold text-yellow-600 dark:text-yellow-400 mr-2 hidden sm:inline">Paused</span>

            <button @click="resumeSession" class="px-3 py-1.5 bg-blue-600 text-white rounded text-xs font-bold shadow hover:bg-blue-500 transition" title="Resume Shift">
                ▶ Resume
            </button>
            <button @click="endSession" class="px-3 py-1.5 bg-red-600 text-white rounded text-xs font-bold shadow hover:bg-red-500 transition" title="End Shift">
                ⏹ Clock Out
            </button>
        </template>

    </div>
</template>