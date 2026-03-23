<template>
  <AppLayout :title="$t('teams.title')">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
      <h1 class="text-2xl font-bold" style="color: var(--va-text-primary)">{{ $t('teams.title') }}</h1>
      <va-button color="primary" icon="add" @click="openCreateModal">
        {{ $t('teams.add_new') }}
      </va-button>
    </div>

    <div class="p-6 rounded-lg border border-solid overflow-x-auto" style="background-color: var(--va-background-secondary); border-color: var(--va-background-border);">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr style="border-bottom: 1px solid var(--va-background-border);">
            <th class="py-3 px-4 font-semibold text-sm uppercase tracking-wider" style="color: var(--va-secondary)">{{ $t('teams.columns.id') }}</th>
            <th class="py-3 px-4 font-semibold text-sm uppercase tracking-wider" style="color: var(--va-secondary)">{{ $t('teams.columns.name') }}</th>
            <th class="py-3 px-4 font-semibold text-sm uppercase tracking-wider" style="color: var(--va-secondary)">{{ $t('teams.columns.shift') }}</th>
            <th class="py-3 px-4 font-semibold text-sm uppercase tracking-wider" style="color: var(--va-secondary)">{{ $t('teams.columns.members') }}</th>
            <th class="py-3 px-4 text-right font-semibold text-sm uppercase tracking-wider" style="color: var(--va-secondary)">{{ $t('teams.columns.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="team in teams" :key="team.id" style="border-bottom: 1px solid var(--va-background-border);">
            <td class="py-3 px-4" style="color: var(--va-text-primary)">#{{ team.id }}</td>
            <td class="py-3 px-4 font-medium" style="color: var(--va-text-primary)">{{ team.name }}</td>
            <td class="py-3 px-4 capitalize" style="color: var(--va-secondary)">
                <va-badge :text="$t(`teams.shifts.${team.shift}`)" color="info" size="small" />
            </td>
            <td class="py-3 px-4" style="color: var(--va-secondary)">
                <div class="flex items-center gap-2">
                    <va-icon name="group" size="small" color="secondary" />
                    <span>{{ $t('teams.assigned', { count: team.supporters ? team.supporters.length : 0 }) }}</span>
                </div>
            </td>
            <td class="py-3 px-4 text-right space-x-2">
              <va-button preset="plain" icon="edit" color="info" @click="openEditModal(team)" :title="$t('teams.actions.edit')" />
              <va-button preset="plain" icon="delete" color="danger" @click="deleteTeam(team.id)" :title="$t('teams.actions.delete')" />
            </td>
          </tr>
          <tr v-if="teams.length === 0">
            <td colspan="5" class="py-6 text-center" style="color: var(--va-secondary)">{{ $t('teams.no_teams') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <va-modal v-model="showModal" hide-default-actions size="small">
      <h3 class="va-h5 mb-6" style="color: var(--va-text-primary)">
          {{ isEditing ? $t('teams.form.edit_title') : $t('teams.form.create_title') }}
      </h3>
      <form @submit.prevent="submitTeam" class="flex flex-col gap-4">
        
        <va-input 
            v-model="form.name" 
            :label="$t('teams.form.name_label')" 
            :error="!!form.errors.name" 
            :error-messages="form.errors.name" 
            required 
        />
        
        <va-select 
            v-model="form.shift" 
            :options="shiftOptions"
            value-by="value"
            text-by="text"
            :label="$t('teams.form.shift_label')" 
            :error="!!form.errors.shift" 
            :error-messages="form.errors.shift" 
            required 
        />

        <va-select 
            v-model="form.supporter_ids" 
            :options="supporterOptions" 
            :label="$t('teams.form.assign_supporters')" 
            multiple
            value-by="value"
            text-by="text"
            clearable
            :error="!!form.errors.supporter_ids" 
            :error-messages="form.errors.supporter_ids" 
        >
            <template #prependInner>
                <va-icon name="person_add" size="small" color="secondary" class="mr-2" />
            </template>
        </va-select>

        <div class="flex justify-end gap-3 mt-4">
          <va-button preset="secondary" @click="showModal = false">{{ $t('teams.form.cancel') }}</va-button>
          <va-button type="submit" :loading="form.processing">{{ $t('teams.form.save') }}</va-button>
        </div>
      </form>
    </va-modal>
  </AppLayout>
</template>

<script setup>
/**
 * Teams Management Component.
 * Handles the CRUD operations for support teams and their member assignments.
 * Fully internationalized and isolated via the global vue-i18n instance.
 */
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ 
    teams: Array,
    supporters: Array,
});

const { t } = useI18n();

const showModal = ref(false);
const isEditing = ref(false);
const editingTeamId = ref(null);

const form = useForm({ 
    name: '', 
    shift: '',
    supporter_ids: []
});

/**
 * Maps the globally available shifts into the standardized object structure 
 * expected by the Vuestic Select component, injecting localized labels.
 * @returns {Array<{text: string, value: string}>}
 */
const shiftOptions = computed(() => [
    { text: t('teams.shifts.morning'), value: 'morning' },
    { text: t('teams.shifts.afternoon'), value: 'afternoon' },
    { text: t('teams.shifts.night'), value: 'night' }
]);

/**
 * Maps the supporters payload into a compatible format for selection binding.
 * @returns {Array<{text: string, value: number|string}>}
 */
const supporterOptions = computed(() => {
    return props.supporters.map(s => ({
        text: s.name,
        value: s.id
    }));
});

/**
 * Initializes and opens the modal for creating a new team.
 */
const openCreateModal = () => {
    isEditing.value = false;
    editingTeamId.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

/**
 * Initializes and opens the modal for editing an existing team.
 * Pre-fills the form state including associated team members.
 * @param {Object} team - The team entity to edit.
 */
const openEditModal = (team) => {
    isEditing.value = true;
    editingTeamId.value = team.id;
    form.name = team.name;
    form.shift = team.shift;
    // Pre-populates the select binding with assigned supporters IDs
    form.supporter_ids = team.supporters ? team.supporters.map(s => s.id) : [];
    form.clearErrors();
    showModal.value = true;
};

/**
 * Dispatches the active form payload to the backend to create or update a team.
 */
const submitTeam = () => {
    if (isEditing.value) {
        form.put(route('teams.update', editingTeamId.value), {
            onSuccess: () => { showModal.value = false; }
        });
    } else {
        form.post(route('teams.store'), {
            onSuccess: () => { showModal.value = false; }
        });
    }
};

/**
 * Prompts the user and safely issues a delete request for the selected team.
 * @param {number|string} id - The team ID to delete.
 */
const deleteTeam = (id) => {
    if (confirm(t('teams.delete.confirm_msg'))) {
        router.delete(route('teams.destroy', id));
    }
};
</script>