<template>
  <va-badge
    :text="tag.name"
    :color="mappedColor"
    class="text-xs uppercase font-bold"
  />
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  tag: {
    type: Object,
    required: true
  }
});

/**
 * Intercepts common string color names stored in the database
 * and converts them to precise Hex values to prevent Vuestic UI warnings.
 *
 * @returns {string} The precise Hex color code or the default Vuestic theme color.
 */
const mappedColor = computed(() => {
  const dbColor = props.tag?.color?.toLowerCase() || '';
  
  const colorMap = {
    'indigo': '#4f46e5',
    'gray': '#6b7280',
    'red': '#ef4444',
    'blue': '#3b82f6',
    'green': '#22c55e',
    'yellow': '#eab308',
    'purple': '#a855f7',
    'pink': '#ec4899',
    'orange': '#f97316'
  };

  return colorMap[dbColor] || dbColor || 'primary';
});
</script>