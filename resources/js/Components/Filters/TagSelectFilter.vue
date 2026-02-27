<script setup>
defineProps({
    tag: {
        type: Object,
        required: true
    }
});
</script>

<template>
    <span 
        class="px-2 py-1 text-xs font-semibold rounded-full border"
        :style="{ backgroundColor: tag.color + '20', color: tag.color, borderColor: tag.color + '40' }"
    >
        {{ tag.name }}
    </span>
</template>