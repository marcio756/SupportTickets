<template>
  <div class="flex flex-col items-center justify-center py-16 px-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
    <va-icon name="schedule" size="80px" color="warning" class="mb-6 opacity-80" />
    <h2 class="text-2xl font-bold mb-4 text-center" style="color: var(--va-text-primary)">
      {{ isPaused ? 'Work Session Paused' : 'Inactive Work Session' }}
    </h2>
    <p class="text-md text-center max-w-lg mb-8" style="color: var(--va-text-secondary)">
      To protect data privacy and accurately log your time, you must {{ isPaused ? 'resume' : 'start' }} your shift before accessing this section.
    </p>
    <va-button color="primary" size="large" :icon="isPaused ? 'play_circle' : 'play_arrow'" @click="handleAction" :loading="isLoading">
      {{ isPaused ? 'Resume Shift Now' : 'Start Shift Now' }}
    </va-button>
  </div>
</template>

<script setup>
/**
 * Component responsible for blocking access to content when a supporter 
 * does not have an active work session. Provides a clear call-to-action to clock in or resume.
 */
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  sessionStatus: {
    type: String,
    default: null,
  }
});

const isLoading = ref(false);
const isPaused = computed(() => props.sessionStatus === 'paused');

const handleAction = () => {
  isLoading.value = true;
  const routeName = isPaused.value ? 'work-sessions.resume' : 'work-sessions.start';
  
  router.post(route(routeName), {}, {
    onFinish: () => isLoading.value = false,
    preserveScroll: true,
  });
};
</script>