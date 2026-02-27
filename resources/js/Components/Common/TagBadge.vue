<template>
    <span 
        class="px-2 py-1 text-xs font-semibold rounded-full border whitespace-nowrap inline-flex items-center justify-center w-fit"
        :style="{ 
            backgroundColor: tag.color + '20', 
            color: tag.color, 
            borderColor: tag.color + '40' 
        }"
    >
        {{ tag.name }}
    </span>
</template>

<script setup>
/**
 * Renders a visual pill/badge for a given tag object.
 * Applies custom inline styles based on the tag's configured hex color.
 * The Tailwind class "w-fit" prevents external flex containers from forcing height/width stretches.
 */
defineProps({
    tag: {
        type: Object,
        required: true
    }
});
</script>