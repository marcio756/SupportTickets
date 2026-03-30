<template>
  <div 
    class="border rounded-xl shadow-sm overflow-visible transition-all duration-300 bg-white dark:bg-gray-900"
    :class="{
        'border-red-300 dark:border-red-800 ring-1 ring-red-100 dark:ring-red-900/30': error,
        'border-gray-200 dark:border-gray-700 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:border-indigo-500': !error
    }"
  >
    <div class="bg-white dark:bg-gray-900 p-2.5 border-b border-gray-200 dark:border-gray-700 flex flex-wrap gap-2 relative rounded-t-xl z-20">
      
      <div class="flex items-center gap-1.5 border border-gray-200 dark:border-gray-700 rounded-lg p-1 bg-gray-50 dark:bg-gray-800 shadow-inner">
          <button 
            type="button" 
            @click.prevent="format('bold')" 
            :title="t('announcements.editor.bold')"
            class="flex items-center justify-center w-8 h-8 rounded-md transition-all duration-200"
            :class="{
                'bg-indigo-600 text-white shadow-md scale-105': activeFormats.bold,
                'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600': !activeFormats.bold
            }"
          >
            <va-icon name="format_bold" size="small" :color="activeFormats.bold ? 'white' : 'currentColor'" />
          </button>
          
          <button 
            type="button" 
            @click.prevent="format('italic')" 
            :title="t('announcements.editor.italic')"
            class="flex items-center justify-center w-8 h-8 rounded-md transition-all duration-200"
            :class="{
                'bg-indigo-600 text-white shadow-md scale-105': activeFormats.italic,
                'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-600': !activeFormats.italic
            }"
          >
             <va-icon name="format_italic" size="small" :color="activeFormats.italic ? 'white' : 'currentColor'" />
          </button>
      </div>
      
      <div class="w-px h-6 bg-gray-200 dark:bg-gray-600 self-center mx-1"></div>
      
      <button 
        type="button" 
        @click.prevent="format('insertUnorderedList')" 
        :title="t('announcements.editor.list')"
        class="flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg border transition-all duration-200 text-sm font-medium"
        :class="{
            'bg-indigo-600 text-white border-indigo-700 shadow-md scale-105': activeFormats.list,
            'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600': !activeFormats.list
        }"
      >
        <va-icon name="format_list_bulleted" size="small" :color="activeFormats.list ? 'white' : 'currentColor'" />
        <span>{{ t('announcements.editor.list') }}</span>
      </button>
      
      <div class="relative">
          <button 
            type="button" 
            @click.prevent="promptLink" 
            class="flex items-center justify-center gap-1.5 px-3 py-1.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-all duration-200 text-sm font-medium"
            ref="linkButton"
          >
             <va-icon name="link" size="small" color="currentColor" />
             <span>{{ t('announcements.editor.link') }}</span>
          </button>

          <transition name="pop">
            <div v-if="showLinkModal" class="absolute top-12 left-0 z-30 w-80 p-5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-xl shadow-gray-200/50 dark:shadow-black/30">
              <label class="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2.5">
                {{ t('announcements.editor.prompt_link') }}
              </label>
              
              <va-input 
                type="url" 
                v-model="linkUrl" 
                ref="linkInput"
                @keyup.enter="confirmLink"
                placeholder="https://" 
                class="w-full mb-4"
                color="indigo"
                bordered
                size="small"
              />
              
              <div class="flex justify-end gap-2.5">
                <va-button type="button" preset="text" color="gray" size="small" @click="cancelLink">
                  {{ t('announcements.editor.btn_cancel') }}
                </va-button>
                <va-button type="button" color="indigo" size="small" @click="confirmLink">
                  {{ t('announcements.editor.btn_insert') }}
                </va-button>
              </div>
            </div>
          </transition>
      </div>
      
    </div>

    <div
      ref="editor"
      contenteditable="true"
      class="editor-content p-6 min-h-[300px] outline-none prose dark:prose-invert max-w-none text-gray-950 dark:text-gray-50 leading-relaxed relative z-10 bg-gray-50 dark:bg-gray-950/40 rounded-b-xl transition-colors duration-300"
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
  modelValue: { type: String, default: '' },
  error: { type: Boolean, default: false }
});

const emit = defineEmits(['update:modelValue']);
const editor = ref(null);
const linkButton = ref(null);
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