<template>
  <AppLayout title="Teams Management">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
      <h1 class="text-2xl font-bold" style="color: var(--va-text-primary)">Teams Management</h1>
      <va-button color="primary" icon="add" @click="openCreateModal">
        Add New Team
      </va-button>
    </div>

    <div class="p-6 rounded-lg border border-solid overflow-x-auto" style="background-color: var(--va-background-secondary); border-color: var(--va-background-border);">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr style="border-bottom: 1px solid var(--va-background-border);">
            <th class="py-3 px-4 font-semibold text-sm uppercase tracking-wider" style="color: var(--va-secondary)">ID</th>
            <th class="py-3 px-4 font-semibold text-sm uppercase tracking-wider" style="color: var(--va-secondary)">Name</th>
            <th class="py-3 px-4 font-semibold text-sm uppercase tracking-wider" style="color: var(--va-secondary)">Shift</th>
            <th class="py-3 px-4 font-semibold text-sm uppercase tracking-wider" style="color: var(--va-secondary)">Members</th>
            <th class="py-3 px-4 text-right font-semibold text-sm uppercase tracking-wider" style="color: var(--va-secondary)">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="team in teams" :key="team.id" style="border-bottom: 1px solid var(--va-background-border);">
            <td class="py-3 px-4" style="color: var(--va-text-primary)">#{{ team.id }}</td>
            <td class="py-3 px-4 font-medium" style="color: var(--va-text-primary)">{{ team.name }}</td>
            <td class="py-3 px-4 capitalize" style="color: var(--va-secondary)">
                <va-badge :text="team.shift" color="info" size="small" />
            </td>
            <td class="py-3 px-4" style="color: var(--va-secondary)">
                <div class="flex items-center gap-2">
                    <va-icon name="group" size="small" color="secondary" />
                    <span>{{ team.supporters ? team.supporters.length : 0 }} Assigned</span>
                </div>
            </td>
            <td class="py-3 px-4 text-right space-x-2">
              <va-button preset="plain" icon="edit" color="info" @click="openEditModal(team)" title="Edit & Manage Members" />
              <va-button preset="plain" icon="delete" color="danger" @click="deleteTeam(team.id)" title="Delete Team" />
            </td>
          </tr>
          <tr v-if="teams.length === 0">
            <td colspan="5" class="py-6 text-center" style="color: var(--va-secondary)">No teams found. Create one above!</td>
          </tr>
        </tbody>
      </table>
    </div>

    <va-modal v-model="showModal" hide-default-actions size="small">
      <h3 class="va-h5 mb-6" style="color: var(--va-text-primary)">
          {{ isEditing ? 'Edit Team & Members' : 'Create New Team' }}
      </h3>
      <form @submit.prevent="submitTeam" class="flex flex-col gap-4">
        
        <va-input 
            v-model="form.name" 
            label="Team Name" 
            :error="!!form.errors.name" 
            :error-messages="form.errors.name" 
            required 
        />
        
        <va-select 
            v-model="form.shift" 
            :options="['morning', 'afternoon', 'night']" 
            label="Shift" 
            :error="!!form.errors.shift" 
            :error-messages="form.errors.shift" 
            required 
        />

        <va-select 
            v-model="form.supporter_ids" 
            :options="supporterOptions" 
            label="Assign Supporters" 
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
          <va-button preset="secondary" @click="showModal = false">Cancel</va-button>
          <va-button type="submit" :loading="form.processing">Save Changes</va-button>
        </div>
      </form>
    </va-modal>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ 
    teams: Array,
    supporters: Array,
});

const showModal = ref(false);
const isEditing = ref(false);
const editingTeamId = ref(null);

const form = useForm({ 
    name: '', 
    shift: '',
    supporter_ids: []
});

// Mapeia os supporters para o formato exigido pelo Vuestic Select
const supporterOptions = computed(() => {
    return props.supporters.map(s => ({
        text: s.name,
        value: s.id
    }));
});

const openCreateModal = () => {
    isEditing.value = false;
    editingTeamId.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
};

const openEditModal = (team) => {
    isEditing.value = true;
    editingTeamId.value = team.id;
    form.name = team.name;
    form.shift = team.shift;
    // Pré-preenche o select com os IDs dos supporters associados a esta equipa
    form.supporter_ids = team.supporters ? team.supporters.map(s => s.id) : [];
    form.clearErrors();
    showModal.value = true;
};

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

const deleteTeam = (id) => {
    if (confirm('Are you sure you want to delete this team? All assigned supporters will be left unassigned.')) {
        router.delete(route('teams.destroy', id));
    }
};
</script>