<template>
  <div class="space-y-4">
    <div v-if="hasChanges" class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="border-b border-gray-200 dark:border-gray-700">
            <th class="py-2 px-3 text-xs font-semibold text-gray-500 uppercase">Field</th>
            <th v-if="hasOldValues" class="py-2 px-3 text-xs font-semibold text-gray-500 uppercase">Old Value</th>
            <th class="py-2 px-3 text-xs font-semibold text-gray-500 uppercase">New Value</th>
          </tr>
        </thead>
        <tbody>
          <tr 
            v-for="change in formattedChanges" 
            :key="change.key"
            class="border-b border-gray-100 dark:border-gray-800 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
          >
            <td class="py-2 px-3 text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ formatKey(change.key) }}
            </td>
            <td v-if="hasOldValues" class="py-2 px-3">
              <span v-if="change.old !== null" class="text-sm text-red-600 dark:text-red-400 line-through bg-red-50 dark:bg-red-900/20 px-1 rounded">
                {{ resolveValue(change.old, change.key) }}
              </span>
              <span v-else class="text-xs text-gray-400 italic">None</span>
            </td>
            <td class="py-2 px-3">
              <span v-if="change.new !== null" class="text-sm text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/20 px-1 rounded font-medium">
                {{ resolveValue(change.new, change.key) }}
              </span>
              <span v-else class="text-xs text-gray-400 italic">None</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else class="text-sm text-gray-400 italic">
      No detailed properties recorded.
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  /** @type {Object} The properties object from the activity log */
  properties: {
    type: Object,
    default: () => ({})
  },
  /** @type {Array} List of users to resolve IDs to names */
  users: {
    type: Array,
    default: () => []
  }
});

const formattedChanges = computed(() => {
  if (!props.properties || Object.keys(props.properties).length === 0) return [];

  const attributes = props.properties.attributes || {};
  const oldValues = props.properties.old || {};
  
  // Handling custom logs (where it's neither inside 'attributes' nor 'old')
  if (Object.keys(attributes).length === 0 && Object.keys(oldValues).length === 0) {
      return Object.keys(props.properties).map(key => ({
          key,
          old: null,
          new: props.properties[key]
      }));
  }

  // Handling standard Spatie model logs
  const keys = Array.from(new Set([...Object.keys(attributes), ...Object.keys(oldValues)]));
  
  return keys.map(key => ({
    key,
    old: oldValues[key] !== undefined ? oldValues[key] : null,
    new: attributes[key] !== undefined ? attributes[key] : null
  }));
});

const hasChanges = computed(() => {
  return formattedChanges.value.length > 0;
});

const hasOldValues = computed(() => {
  return formattedChanges.value.some(change => change.old !== null);
});

/**
 * Converts snake_case keys to Title Case.
 * @param {string} key 
 */
const formatKey = (key) => {
  return key
    .replace(/_/g, ' ')
    .replace(/\b\w/g, l => l.toUpperCase());
};

/**
 * Resolves a raw value to a human-readable format, 
 * including mapping User IDs to Names if applicable.
 * @param {any} value 
 * @param {string} key
 */
const resolveValue = (value, key) => {
  if (value === null) return 'null';
  if (typeof value === 'boolean') return value ? 'Yes' : 'No';

  // Logic to detect User IDs: fields ending in _id or specific names
  const userFields = ['causer_id', 'user_id', 'assigned_to', 'creator_id', 'author_id'];
  const isIdField = key.endsWith('_id') || userFields.includes(key);

  if (isIdField && (typeof value === 'number' || !isNaN(value))) {
    const user = props.users.find(u => String(u.id) === String(value));
    if (user) {
      return user.name;
    }
    return `#${value}`;
  }

  if (typeof value === 'object') return JSON.stringify(value);
  
  return String(value);
};
</script>