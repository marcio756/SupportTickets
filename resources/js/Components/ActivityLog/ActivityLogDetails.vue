<template>
  <div class="space-y-4">
    <div v-if="hasChanges" class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="border-b border-gray-200 dark:border-gray-700">
            <th class="py-2 px-3 text-xs font-semibold text-gray-500 uppercase">{{ $t('activity_log.details.field') }}</th>
            <th v-if="hasOldValues" class="py-2 px-3 text-xs font-semibold text-gray-500 uppercase">{{ $t('activity_log.details.old_value') }}</th>
            <th class="py-2 px-3 text-xs font-semibold text-gray-500 uppercase">{{ $t('activity_log.details.new_value') }}</th>
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
              <span v-else class="text-xs text-gray-400 italic">{{ $t('common.none') }}</span>
            </td>
            <td class="py-2 px-3">
              <span v-if="change.new !== null" class="text-sm text-green-600 dark:text-green-400 bg-green-50 dark:bg-green-900/20 px-1 rounded font-medium">
                {{ resolveValue(change.new, change.key) }}
              </span>
              <span v-else class="text-xs text-gray-400 italic">{{ $t('common.none') }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else class="text-sm text-gray-400 italic">
      {{ $t('activity_log.details.no_properties') }}
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
  /** * The underlying JSON properties payload stored within the activity log record.
   * Contains attributes, old values, or custom structure depending on the event.
   */
  properties: {
    type: Object,
    default: () => ({})
  },
  /** * A mapping collection of system users to resolve foreign keys (IDs) 
   * into human-readable names automatically.
   */
  users: {
    type: Array,
    default: () => []
  }
});

const { t } = useI18n();

/**
 * Normalizes the divergent log payload structures into a standard array of changes.
 * Handles both Spatie ActivityLog format (attributes vs old) and generic custom JSON logging.
 */
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

  // Handling standard Spatie model logs via Set to prevent duplicate keys
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
 * Transforms backend snake_case column names into readable Title Case presentation.
 * Enhances UI readability without requiring database schema changes.
 * * @param {string} key - The raw database column name.
 * @returns {string} The formatted label string.
 */
const formatKey = (key) => {
  return key
    .replace(/_/g, ' ')
    .replace(/\b\w/g, l => l.toUpperCase());
};

/**
 * Parses and resolves raw database values into meaningful UI representations.
 * Evaluates booleans via i18n, auto-resolves User IDs to Names, and handles complex objects.
 * * @param {any} value - The raw value stored in the database.
 * @param {string} key - The column key associated with this value.
 * @returns {string} The human-readable resolved string.
 */
const resolveValue = (value, key) => {
  if (value === null) return t('common.null_value'); // Can map to a "null" i18n key or string
  if (typeof value === 'boolean') return value ? t('common.yes') : t('common.no');

  // Business Logic: Detect reference fields and resolve ID to Name mapping
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