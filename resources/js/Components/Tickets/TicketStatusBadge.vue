<template>
  <va-badge
    :text="formattedStatus"
    :color="statusColor"
    class="text-xs uppercase font-bold"
  />
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
  status: {
    type: String,
    required: true,
  },
});

const { t } = useI18n();

/**
 * Maps backend string states to frontend color codes for visual consistency.
 * * @returns {string} The Vuestic UI color corresponding to the ticket status.
 */
const statusColor = computed(() => {
  const map = {
    open: 'warning',
    in_progress: 'info',
    resolved: 'success',
    closed: 'secondary',
  };
  return map[props.status] || 'primary';
});

/**
 * Resolves the human-readable translated string for the current ticket status.
 * Enforces the use of the i18n dictionary instead of raw string manipulation,
 * guaranteeing correct terms across all supported languages.
 * * @returns {string} The localized status name.
 */
const formattedStatus = computed(() => {
  return t(`tickets.status.${props.status}`);
});
</script>