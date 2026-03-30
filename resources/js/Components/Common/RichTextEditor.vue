<template>
  <div class="border rounded-lg shadow-sm overflow-hidden border-gray-200 dark:border-gray-700 transition-all duration-300 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500 bg-white dark:bg-gray-900">
    <div class="bg-gray-50 dark:bg-gray-800 p-2 border-b border-gray-200 dark:border-gray-700 flex flex-wrap gap-2">
      <button 
        type="button" 
        @click.prevent="format('bold')" 
        :title="t('announcements.editor.bold')"
        class="flex items-center justify-center w-8 h-8 bg-white dark:bg-gray-700 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 rounded shadow-sm text-sm font-bold transition-colors duration-200"
      >B</button>
      <button 
        type="button" 
        @click.prevent="format('italic')" 
        :title="t('announcements.editor.italic')"
        class="flex items-center justify-center w-8 h-8 bg-white dark:bg-gray-700 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 rounded shadow-sm text-sm italic transition-colors duration-200"
      >I</button>
      <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 self-center mx-1"></div>
      <button 
        type="button" 
        @click.prevent="format('insertUnorderedList')" 
        class="px-3 py-1.5 bg-white dark:bg-gray-700 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 rounded shadow-sm text-sm font-medium transition-colors duration-200 flex items-center gap-1"
      >
        <span>•</span> {{ t('announcements.editor.list') }}
      </button>
      <button 
        type="button" 
        @click.prevent="addLink" 
        class="px-3 py-1.5 bg-white dark:bg-gray-700 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 rounded shadow-sm text-sm font-medium transition-colors duration-200"
      >
        {{ t('announcements.editor.link') }}
      </button>
    </div>
    <div
      ref="editor"
      contenteditable="true"
      class="p-5 min-h-[250px] outline-none prose dark:prose-invert max-w-none text-gray-800 dark:text-gray-200 leading-relaxed"
      @input="updateContent"
    ></div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
  modelValue: { type: String, default: '' }
});

const emit = defineEmits(['update:modelValue']);
const editor = ref(null);
const { t } = useI18n();

/**
 * Executes standard browser commands to format the contenteditable area.
 * Keeps the focus on the editor to ensure seamless typing continuity.
 * * @param {string} command The standard DOM execCommand string.
 */
const format = (command) => {
  document.execCommand(command, false, null);
  editor.value.focus();
  updateContent();
};

/**
 * Prompts the user for a URL and securely wraps the selected text in an anchor tag.
 */
const addLink = () => {
  const url = prompt(t('announcements.editor.prompt_link'));
  if (url) {
    document.execCommand('createLink', false, url);
    updateContent();
  }
};

/**
 * Syncs the internal HTML state with the parent Vue v-model boundary.
 */
const updateContent = () => {
  emit('update:modelValue', editor.value.innerHTML);
};

onMounted(() => {
  if (editor.value && props.modelValue) {
    editor.value.innerHTML = props.modelValue;
  }
});
</script>