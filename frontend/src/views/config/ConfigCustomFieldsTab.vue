<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useConfigStore } from '@/stores/config';
import { useAppStore } from '@/stores/app';

defineOptions({ name: 'ConfigCustomFieldsTab' });

const configStore = useConfigStore();
const appStore = useAppStore();
const showForm = ref(false);
const editingField = ref<any>(null);
const entityType = ref('printer');

const form = ref({
  entity_type: 'printer',
  name: '',
  type: 'text',
  options: '' as string | string[],
  is_required: false,
  help_text: '',
  show_in_table: false,
  table_order: 0,
  show_in_creation_wizard: false,
});

const entityTypes = [
  { value: 'printer', label: 'Impresoras' },
  { value: 'consumable', label: 'Consumibles' },
  { value: 'order', label: 'Pedidos' },
];

const fieldTypes = [
  { value: 'text', label: 'Texto' },
  { value: 'number', label: 'Número' },
  { value: 'date', label: 'Fecha' },
  { value: 'select', label: 'Selector' },
  { value: 'checkbox', label: 'Checkbox' },
  { value: 'textarea', label: 'Área de texto' },
];

onMounted(() => {
  configStore.fetchCustomFields();
});

const filteredFields = computed(() => {
  return configStore.customFields.filter((f) => f.entity_type === entityType.value);
});

const openForm = (field?: any) => {
  if (field) {
    editingField.value = field;
    // Convertir options de array a string si es necesario
    const fieldData = { ...field };
    if (fieldData.options && Array.isArray(fieldData.options)) {
      fieldData.options = fieldData.options.join('\n');
    }
    form.value = fieldData;
  } else {
    editingField.value = null;
    form.value = {
      entity_type: entityType.value,
      name: '',
      type: 'text',
      options: '',
      is_required: false,
      help_text: '',
      show_in_table: false,
      table_order: 0,
      show_in_creation_wizard: false,
    };
  }
  showForm.value = true;
};

const saveField = async () => {
  try {
    // Preparar datos para enviar
    const dataToSend: any = { ...form.value };
    
    // Convertir options de string a array si el tipo es select
    if (dataToSend.type === 'select' && typeof dataToSend.options === 'string') {
      dataToSend.options = dataToSend.options
        .split('\n')
        .map((opt: string) => opt.trim())
        .filter((opt: string) => opt.length > 0);
    } else if (dataToSend.type !== 'select') {
      // Si no es select, no enviar options
      dataToSend.options = undefined;
    }
    
    if (editingField.value) {
      await configStore.updateCustomField(editingField.value.id, dataToSend);
    } else {
      await configStore.createCustomField(dataToSend);
    }
    appStore.notify('Campo guardado correctamente', 'success');
    showForm.value = false;
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al guardar campo', 'error');
  }
};

const deleteField = async (id: number) => {
  if (!confirm('¿Estás seguro de eliminar este campo?')) return;
  try {
    await configStore.deleteCustomField(id);
    appStore.notify('Campo eliminado', 'success');
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al eliminar campo', 'error');
  }
};
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-2">Filtrar por entidad</label>
        <select
          v-model="entityType"
          @change="configStore.fetchCustomFields(entityType)"
          class="rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary"
        >
          <option v-for="type in entityTypes" :key="type.value" :value="type.value">
            {{ type.label }}
          </option>
        </select>
      </div>
      <button
        @click="openForm()"
        class="bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark"
      >
        + Añadir campo
      </button>
    </div>

    <div v-if="showForm" class="border border-slate-200 rounded-xl p-6 bg-slate-50">
      <h3 class="text-lg font-semibold mb-4">{{ editingField ? 'Editar' : 'Nuevo' }} campo</h3>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Nombre</label>
          <input
            v-model="form.name"
            type="text"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
            placeholder="Ej: Número de serie interno"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Tipo</label>
          <select v-model="form.type" class="w-full rounded-lg border border-slate-300 px-4 py-2">
            <option v-for="type in fieldTypes" :key="type.value" :value="type.value">
              {{ type.label }}
            </option>
          </select>
        </div>
        <div v-if="form.type === 'select'">
          <label class="block text-sm font-medium text-slate-700 mb-2">Opciones (una por línea)</label>
          <textarea
            v-model="form.options"
            rows="4"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
            placeholder="Opción 1&#10;Opción 2&#10;Opción 3"
          />
        </div>
        <div class="flex items-center gap-2">
          <input v-model="form.is_required" type="checkbox" id="required" />
          <label for="required" class="text-sm text-slate-700">Campo obligatorio</label>
        </div>
        <div class="flex items-center gap-2">
          <input v-model="form.show_in_table" type="checkbox" id="show_in_table" />
          <label for="show_in_table" class="text-sm text-slate-700">Mostrar en tabla</label>
        </div>
        <div v-if="form.show_in_table">
          <label class="block text-sm font-medium text-slate-700 mb-2">Orden en tabla</label>
          <input
            v-model.number="form.table_order"
            type="number"
            min="0"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
            placeholder="0"
          />
          <p class="text-xs text-slate-500 mt-1">Define el orden de la columna en la vista de tabla (0 = primero)</p>
        </div>
        <div class="flex items-center gap-2">
          <input v-model="form.show_in_creation_wizard" type="checkbox" id="show_in_creation_wizard" />
          <label for="show_in_creation_wizard" class="text-sm text-slate-700">Mostrar en asistente de creación</label>
        </div>
        <div class="flex gap-2">
          <button @click="saveField" class="flex-1 bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold">
            Guardar
          </button>
          <button
            @click="showForm = false"
            class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50"
          >
            Cancelar
          </button>
        </div>
      </div>
    </div>

    <div class="space-y-2">
      <div
        v-for="field in filteredFields"
        :key="field.id"
        class="border border-slate-200 rounded-lg p-4 flex items-center justify-between hover:bg-slate-50"
      >
        <div>
          <p class="font-semibold text-slate-900">{{ field.name }}</p>
          <p class="text-sm text-slate-500">{{ fieldTypes.find((t) => t.value === field.type)?.label }}</p>
        </div>
        <div class="flex gap-2">
          <button
            @click="openForm(field)"
            class="px-3 py-1.5 text-sm text-ada-primary bg-ada-light rounded-lg hover:bg-ada-primary/10"
          >
            Editar
          </button>
          <button
            @click="deleteField(field.id)"
            class="px-3 py-1.5 text-sm text-red-600 bg-red-50 rounded-lg hover:bg-red-100"
          >
            Eliminar
          </button>
        </div>
      </div>
      <p v-if="filteredFields.length === 0" class="text-center text-slate-500 py-8">
        No hay campos personalizados para {{ entityTypes.find((t) => t.value === entityType)?.label }}
      </p>
    </div>
  </div>
</template>

