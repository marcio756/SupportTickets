<template>
  <div class="border rounded-lg shadow-sm overflow-visible border-gray-200 dark:border-gray-700 transition-all duration-300 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500 bg-white dark:bg-gray-900">
    <div class="bg-gray-50 dark:bg-gray-800 p-2 border-b border-gray-200 dark:border-gray-700 flex flex-wrap gap-2 relative">
      
      <button 
        type="button" 
        @click.prevent="format('bold')" 
        :title="t('announcements.editor.bold')"
        :class="{
          'bg-indigo-100 text-indigo-700 shadow-inner dark:bg-indigo-900/60 dark:text-indigo-300': activeFormats.bold,
          'bg-white dark:bg-gray-700 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600': !activeFormats.bold
        }"
        class="flex items-center justify-center w-8 h-8 rounded shadow-sm text-sm font-bold transition-all duration-200"
      >B</button>
      
      <button 
        type="button" 
        @click.prevent="format('italic')" 
        :title="t('announcements.editor.italic')"
        :class="{
          'bg-indigo-100 text-indigo-700 shadow-inner dark:bg-indigo-900/60 dark:text-indigo-300': activeFormats.italic,
          'bg-white dark:bg-gray-700 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600': !activeFormats.italic
        }"
        class="flex items-center justify-center w-8 h-8 rounded shadow-sm text-sm italic transition-all duration-200"
      >I</button>
      
      <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 self-center mx-1"></div>
      
      <button 
        type="button" 
        @click.prevent="format('insertUnorderedList')" 
        :class="{
          'bg-indigo-100 text-indigo-700 shadow-inner dark:bg-indigo-900/60 dark:text-indigo-300': activeFormats.list,
          'bg-white dark:bg-gray-700 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600': !activeFormats.list
        }"
        class="px-3 py-1.5 rounded shadow-sm text-sm font-medium transition-all duration-200 flex items-center gap-1"
      >
        <span>•</span> {{ t('announcements.editor.list') }}
      </button>
      
      <button 
        type="button" 
        @click.prevent="promptLink" 
        class="px-3 py-1.5 bg-white dark:bg-gray-700 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 hover:text-indigo-600 rounded shadow-sm text-sm font-medium transition-all duration-200 relative"
      >
        {{ t('announcements.editor.link') }}
      </button>

      <transition name="pop">
        <div v-if="showLinkModal" class="absolute top-12 left-2 z-20 w-72 p-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl">
          <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-2">
            {{ t('announcements.editor.prompt_link') }}
          </label>
          <input 
            type="url" 
            v-model="linkUrl" 
            ref="linkInput"
            @keyup.enter="confirmLink"
            placeholder="https://" 
            class="w-full text-sm rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-600 focus:ring-indigo-500 focus:border-indigo-500 mb-3 shadow-sm"
          >
          <div class="flex justify-end gap-2">
            <button type="button" @click="cancelLink" class="px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors">
              {{ t('announcements.editor.btn_cancel') }}
            </button>
            <button type="button" @click="confirmLink" class="px-3 py-1.5 text-xs font-medium bg-indigo-600 text-white rounded hover:bg-indigo-700 shadow-sm transition-colors">
              {{ t('announcements.editor.btn_insert') }}
            </button>
          </div>
        </div>
      </transition>
      
    </div>

    <div
      ref="editor"
      contenteditable="true"
      class="editor-content p-5 min-h-[250px] outline-none prose dark:prose-invert max-w-none text-gray-800 dark:text-gray-200 leading-relaxed relative z-10"
      @input="updateContent"
      @keyup="syncActiveFormats"
      @mouseup="syncActiveFormats"
    ></div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
  modelValue: { type: String, default: '' }
});

const emit = defineEmits(['update:modelValue']);
const editor = ref(null);
const { t } = useI18n();

// Tracking active text formats
const activeFormats = reactive({
  bold: false,
  italic: false,
  list: false,
});

// Custom Link Modal Logic
const showLinkModal = ref(false);
const linkUrl = ref('');
const linkInput = ref(null);
const savedSelectionRange = ref(null);

/**
 * Validates the current DOM selection context to accurately toggle visual toolbar states.
 */
const syncActiveFormats = () => {
  if (document.activeElement === editor.value) {
    activeFormats.bold = document.queryCommandState('bold');
    activeFormats.italic = document.queryCommandState('italic');
    activeFormats.list = document.queryCommandState('insertUnorderedList');
  }
};

/**
 * Handles continuous format updates invoked by peripheral actions (e.g. arrow keys).
 */
const globalSelectionHandler = () => {
   syncActiveFormats();
};

/**
 * Executes standard browser commands to format the contenteditable area.
 * @param {string} command The standard DOM execCommand string.
 */
const format = (command) => {
  document.execCommand(command, false, null);
  editor.value.focus();
  syncActiveFormats();
  updateContent();
};

/**
 * Temporarily saves the active text selection matrix before blurring the editor
 * and triggering the Custom Link Card interface.
 */
const promptLink = () => {
  const selection = window.getSelection();
  if (selection.rangeCount > 0) {
    savedSelectionRange.value = selection.getRangeAt(0);
  }
  showLinkModal.value = true;
  nextTick(() => { linkInput.value?.focus(); });
};

/**
 * Discards the contextual popup and returns control to the editor.
 */
const cancelLink = () => {
  showLinkModal.value = false;
  linkUrl.value = '';
  editor.value.focus();
};

/**
 * Restores the suspended text range and inserts the anchor tag.
 */
const confirmLink = () => {
  if (savedSelectionRange.value) {
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(savedSelectionRange.value);
  }
  
  if (linkUrl.value) {
    // Ensures a proper HTTP format if missing for external redirects
    let finalUrl = linkUrl.value;
    if (!/^https?:\/\//i.test(finalUrl)) {
      finalUrl = 'https://' + finalUrl;
    }
    document.execCommand('createLink', false, finalUrl);
    updateContent();
  }
  
  cancelLink();
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
  document.addEventListener('selectionchange', globalSelectionHandler);
});

onBeforeUnmount(() => {
  document.removeEventListener('selectionchange', globalSelectionHandler);
});
</script>

<style scoped>
.pop-enter-active, .pop-leave-active { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
.pop-enter-from, .pop-leave-to { opacity: 0; transform: translateY(-5px) scale(0.98); }

/* Override ao reset do Tailwind (Preflight) para forçar estilos HTML padrão dentro da área do editor */
.editor-content :deep(ul) {
  list-style-type: disc !important;
  padding-left: 1.5rem !important;
  margin-top: 0.5em;
  margin-bottom: 0.5em;
}

.editor-content :deep(ol) {
  list-style-type: decimal !important;
  padding-left: 1.5rem !important;
  margin-top: 0.5em;
  margin-bottom: 0.5em;
}

.editor-content :deep(a) {
  color: #4f46e5;
  text-decoration: underline;
  cursor: pointer;
}
</style>