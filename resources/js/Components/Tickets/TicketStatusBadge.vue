<template>
  <va-badge
    :text="formattedStatus"
    :color="statusColor"
    class="text-xs uppercase font-bold"
  />
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: {
    type: String,
    required: true,
  },
});

// Maps backend string states to human-readable strings and frontend color codes
const statusColor = computed(() => {
  const map = {
    open: 'warning',
    in_progress: 'info',
    resolved: 'success',
    closed: 'secondary',
  };
  return map[props.status] || 'primary';
});

const formattedStatus = computed(() => {
  return props.status.replace('_', ' ');
});
</script>