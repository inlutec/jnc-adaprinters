<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useReferencesStore } from '@/stores/references';
import { useAppStore } from '@/stores/app';

defineOptions({ name: 'ReferencesView' });

const referencesStore = useReferencesStore();
const appStore = useAppStore();
const showForm = ref(false);
const editingRef = ref<any>(null);

const form = ref({
  sku: '',
  name: '',
  brand: '',
  type: '',
  custom_type: '',
  color: '',
  description: '',
  minimum_quantity: 0,
});

const showHistoryModal = ref(false);
const selectedRefForHistory = ref<any>(null);
const movements = ref<any[]>([]);

const typeOptions = [
  { value: 'Toner', label: 'Toner' },
  { value: 'Cartucho', label: 'Cartucho' },
  { value: 'Otro', label: 'Otro' },
];

const colorOptions = [
  { value: 'Negro', label: 'Negro', class: 'bg-slate-900' },
  { value: 'Cyan', label: 'Cyan', class: 'bg-cyan-500' },
  { value: 'Magenta', label: 'Magenta', class: 'bg-pink-500' },
  { value: 'Amarillo', label: 'Amarillo', class: 'bg-yellow-400' },
];

const getColorClass = (color: string) => {
  const option = colorOptions.find((c) => c.value === color);
  return option?.class || 'bg-slate-400';
};

onMounted(() => {
  referencesStore.fetchReferences();
});

const openForm = (ref?: any) => {
  if (ref) {
    editingRef.value = ref;
    form.value = { ...ref };
  } else {
    editingRef.value = null;
    form.value = {
      sku: '',
      name: '',
      brand: '',
      type: '',
      custom_type: '',
      color: '',
      description: '',
      minimum_quantity: 0,
    };
  }
  showForm.value = true;
};

const viewHistory = async (ref: any) => {
  selectedRefForHistory.value = ref;
  try {
    const { data } = await referencesStore.fetchMovements(ref.id);
    movements.value = data;
    showHistoryModal.value = true;
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al cargar histórico', 'error');
  }
};

const saveReference = async () => {
  try {
    if (editingRef.value) {
      await referencesStore.updateReference(editingRef.value.id, form.value);
    } else {
      await referencesStore.createReference(form.value);
    }
    appStore.notify('Referencia guardada', 'success');
    showForm.value = false;
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error', 'error');
  }
};

const deleteReference = async (id: number) => {
  if (!confirm('¿Estás seguro de eliminar esta referencia?')) return;
  try {
    await referencesStore.deleteReference(id);
    appStore.notify('Referencia eliminada', 'success');
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error', 'error');
  }
};
</script>

<template>
  <div class="space-y-6">
    <div class="flex justify-between">
      <h2 class="text-2xl font-bold text-slate-900">Referencias de Consumibles</h2>
      <button
        @click="openForm()"
        class="bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold"
      >
        + Nueva referencia
      </button>
    </div>

    <div v-if="showForm" class="bg-white rounded-xl border border-slate-200 p-6">
      <h3 class="text-lg font-semibold mb-4">{{ editingRef ? 'Editar' : 'Nueva' }} referencia</h3>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">SKU</label>
          <input v-model="form.sku" type="text" class="w-full rounded-lg border border-slate-300 px-4 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Nombre</label>
          <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-300 px-4 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Marca</label>
          <input v-model="form.brand" type="text" class="w-full rounded-lg border border-slate-300 px-4 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Tipo</label>
          <select
            v-model="form.type"
            class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
          >
            <option value="">Seleccionar tipo</option>
            <option v-for="option in typeOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div v-if="form.type === 'Otro'">
          <label class="block text-sm font-medium text-slate-700 mb-2">Tipo personalizado</label>
          <input
            v-model="form.custom_type"
            type="text"
            placeholder="Especificar tipo"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
          />
        </div>
        <div v-if="form.type === 'Toner' || form.type === 'Cartucho'">
          <label class="block text-sm font-medium text-slate-700 mb-2">Color</label>
          <select
            v-model="form.color"
            class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
          >
            <option value="">Seleccionar color</option>
            <option v-for="option in colorOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Cantidad mínima de stock</label>
          <input
            v-model.number="form.minimum_quantity"
            type="number"
            min="0"
            class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
          />
        </div>
        <div class="col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-2">Descripción</label>
          <textarea v-model="form.description" rows="3" class="w-full rounded-lg border border-slate-300 px-4 py-2"></textarea>
        </div>
        <div class="col-span-2 flex gap-2">
          <button @click="saveReference" class="flex-1 bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold">
            Guardar
          </button>
          <button @click="showForm = false" class="px-4 py-2 rounded-lg border border-slate-300">
            Cancelar
          </button>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="w-full">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">SKU</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Nombre</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Marca</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Tipo</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Color</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <tr v-for="ref in referencesStore.references" :key="ref.id" class="hover:bg-slate-50">
            <td class="px-6 py-4 font-mono text-sm">{{ ref.sku }}</td>
            <td class="px-6 py-4 font-semibold">{{ ref.name }}</td>
            <td class="px-6 py-4 text-sm text-slate-600">{{ ref.brand }}</td>
            <td class="px-6 py-4 text-sm text-slate-600">
              {{ ref.type === 'Otro' ? ref.custom_type || ref.type : ref.type }}
            </td>
            <td class="px-6 py-4">
              <div v-if="ref.color" class="flex items-center gap-2">
                <span
                  class="w-6 h-6 rounded-full border border-slate-300"
                  :class="getColorClass(ref.color)"
                ></span>
                <span class="text-sm text-slate-600">{{ ref.color }}</span>
              </div>
              <span v-else class="text-sm text-slate-400">—</span>
            </td>
            <td class="px-6 py-4">
              <div class="flex gap-2">
                <button
                  @click="openForm(ref)"
                  class="px-3 py-1.5 text-sm text-ada-primary bg-ada-light rounded-lg hover:bg-ada-primary/10"
                >
                  Editar
                </button>
                <button
                  @click="viewHistory(ref)"
                  class="px-3 py-1.5 text-sm text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100"
                >
                  Histórico
                </button>
                <button
                  @click="deleteReference(ref.id)"
                  class="px-3 py-1.5 text-sm text-red-600 bg-red-50 rounded-lg hover:bg-red-100"
                >
                  Eliminar
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Histórico -->
    <div
      v-if="showHistoryModal && selectedRefForHistory"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showHistoryModal = false"
    >
      <div class="bg-white rounded-xl p-6 max-w-4xl w-full mx-4 max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-semibold">
            Histórico de movimientos - {{ selectedRefForHistory.name }}
          </h3>
          <button
            @click="showHistoryModal = false"
            class="text-slate-400 hover:text-slate-600"
          >
            ✕
          </button>
        </div>
        <div v-if="movements.length === 0" class="text-center text-slate-500 py-8">
          No hay movimientos registrados para esta referencia
        </div>
        <table v-else class="w-full text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Fecha</th>
              <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Tipo</th>
              <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Cantidad</th>
              <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600 uppercase">Motivo</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <tr v-for="movement in movements" :key="movement.id" class="hover:bg-slate-50">
              <td class="px-4 py-2">
                {{ new Date(movement.movement_at || movement.created_at).toLocaleString('es-ES') }}
              </td>
              <td class="px-4 py-2">
                <span
                  class="px-2 py-1 rounded text-xs font-semibold"
                  :class="{
                    'bg-green-100 text-green-700': movement.movement_type === 'in',
                    'bg-red-100 text-red-700': movement.movement_type === 'out',
                    'bg-blue-100 text-blue-700': movement.movement_type === 'adjustment',
                  }"
                >
                  {{ movement.movement_type === 'in' ? 'Entrada' : movement.movement_type === 'out' ? 'Salida' : 'Ajuste' }}
                </span>
              </td>
              <td class="px-4 py-2 font-semibold">{{ movement.quantity }}</td>
              <td class="px-4 py-2 text-slate-600">{{ movement.note || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

