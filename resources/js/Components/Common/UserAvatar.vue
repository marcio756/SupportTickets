<template>
  <va-avatar
    :size="size"
    :color="computedColor"
    :src="fallbackSrc"
    class="custom-user-avatar"
    :style="{ 
      width: size, 
      height: size, 
      minWidth: size, 
      minHeight: size,
      maxWidth: size,
      maxHeight: size
    }"
  >
    <span v-if="!fallbackSrc" class="font-bold text-white uppercase flex items-center justify-center w-full h-full text-sm tracking-widest">
      {{ initials }}
    </span>
  </va-avatar>
</template>

<script setup>
/**
 * UserAvatar.vue
 * * Standardized user avatar component with strict sizing rules.
 */
import { computed } from 'vue';

const props = defineProps({
  user: {
    type: Object,
    required: true,
  },
  size: {
    type: String,
    default: '40px',
  },
});

const fallbackSrc = computed(() => {
  if (!props.user) return undefined;
  if (props.user.avatar) return props.user.avatar;
  if (props.user.profile_photo_url) return props.user.profile_photo_url;
  return undefined; 
});

const initials = computed(() => {
  if (!props.user || !props.user.name) return '??';
  const names = props.user.name.split(' ');
  if (names.length >= 2) {
      return (names[0].charAt(0) + names[1].charAt(0)).substring(0, 2);
  }
  return props.user.name.substring(0, 2);
});

const computedColor = computed(() => {
  const colors = ['primary', 'success', 'info', 'warning', 'danger'];
  const charCode = (props.user && props.user.name) ? props.user.name.charCodeAt(0) : 0;
  return colors[charCode % colors.length];
});
</script>

<style scoped>
/* A regra flex: 0 0 auto impede perfeitamente o navegador de aumentar/diminuir o elemento */
.custom-user-avatar {
  flex: 0 0 auto !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  border-radius: 50% !important;
  overflow: hidden !important;
}

.custom-user-avatar :deep(img) {
  object-fit: cover !important;
  width: 100% !important;
  height: 100% !important;
  border-radius: 50% !important;
  display: block !important;
}
</style>