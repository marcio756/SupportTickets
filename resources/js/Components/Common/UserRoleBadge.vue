<template>
  <va-badge 
    :color="badgeColor" 
    :text="roleName.toUpperCase()" 
    class="font-semibold uppercase"
  />
</template>

<script setup>
import { computed } from 'vue';

/**
 * Centrally manages the visual representation of user roles across the app.
 * Adheres to the DRY principle to ensure consistent branding (e.g., Purple Admin).
 */
const props = defineProps({
  role: {
    type: [String, Object],
    required: true
  }
});

/**
 * Normalizes the role input which might be a plain string or a Laravel Enum object.
 * @returns {string}
 */
const roleName = computed(() => {
  if (typeof props.role === 'object' && props.role !== null) {
    return props.role.value || props.role.name || '';
  }
  return props.role || '';
});

/**
 * Maps roles to their specific brand colors.
 * Admin: Purple (#8b5cf6)
 * Supporter: Primary Blue
 * Customer: Info Light Blue
 * @returns {string}
 */
const badgeColor = computed(() => {
  const normalized = roleName.value.toLowerCase();
  
  switch (normalized) {
    case 'admin':
      return '#8b5cf6';
    case 'supporter':
    case 'support':
      return 'primary';
    case 'customer':
      return 'info';
    default:
      return 'secondary';
  }
});
</script>