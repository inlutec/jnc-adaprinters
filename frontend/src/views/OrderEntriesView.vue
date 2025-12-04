<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useOrdersStore } from '@/stores/orders';
import { useReferencesStore } from '@/stores/references';
import { useConfigStore } from '@/stores/config';
import { useAppStore } from '@/stores/app';

defineOptions({ name: 'OrderEntriesView' });

type OrderEntry = {
  id: number;
  order_id?: number | null;
  site_id?: number | null;
  department_id?: number | null;
  province_id?: number | null;
  received_at: string;
  delivery_note_path?: string;
  delivery_note_mime_type?: string;
  notes?: string;
  received_by?: number;
  order?: any;
  site?: { id: number; name: string; province_id?: number };
  department?: { id: number; name: string };
  items?: Array<{ id: number; consumable_reference_id: number; quantity: number; consumableReference?: any; consumable_reference?: any }>;
};

const ordersStore = useOrdersStore();
const referencesStore = useReferencesStore();
const configStore = useConfigStore();
const appStore = useAppStore();

const showForm = ref(false);
const isFromOrder = ref(true);
const searchQuery = ref('');
const showDetailsModal = ref(false);
const showEditModal = ref(false);
const selectedEntry = ref<OrderEntry | null>(null);
const deletingEntry = ref<number | null>(null);

const getToday = (): string => {
  const date = new Date().toISOString().split('T')[0];
  return date || new Date().toISOString().substring(0, 10);
};

const form = ref({
  is_from_order: true,
  order_id: null as number | null,
  site_id: null as number | null,
  department_id: null as number | null,
  received_at: getToday(),
  delivery_note: null as File | null,
  notes: '',
  items: [] as Array<{ consumable_reference_id: number; quantity: number }>,
});

const selectedReferences = ref<Array<{ id: number; name: string; sku: string; brand?: string; color?: string }>>([]);
const availableReferences = ref<any[]>([]);
const pendingOrders = ref<any[]>([]);

const isMobile = ref(false);

onMounted(() => {
  ordersStore.fetchOrderEntries();
  loadReferences();
  loadPendingOrders();
  loadSitesAndDepartments();
  isMobile.value = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
});

const loadReferences = async () => {
  try {
    await referencesStore.fetchReferences({ per_page: 100, is_active: true });
    availableReferences.value = referencesStore.references.filter((r: any) => r.is_active);
  } catch (error) {
    console.error('Error loading references:', error);
  }
};

const loadPendingOrders = async () => {
  try {
    // Cargar pedidos pendientes y en curso
    await ordersStore.fetchOrders();
    pendingOrders.value = ordersStore.orders.filter((o: any) => 
      o.status === 'pending' || o.status === 'in_progress'
    );
  } catch (error) {
    console.error('Error loading pending orders:', error);
  }
};

const loadSitesAndDepartments = async () => {
  await configStore.fetchSites();
  await configStore.fetchDepartments();
};

const filteredReferences = computed(() => {
  if (!searchQuery.value) return availableReferences.value;
  const query = searchQuery.value.toLowerCase();
  return availableReferences.value.filter(
    (ref: any) =>
      ref.name.toLowerCase().includes(query) ||
      ref.sku.toLowerCase().includes(query) ||
      ref.brand?.toLowerCase().includes(query)
  );
});

const filteredDepartments = computed(() => {
  if (!form.value.site_id) return [];
  return configStore.departments.filter((d: any) => d.site_id === form.value.site_id);
});

const addReference = (ref: any) => {
  if (!selectedReferences.value.find((r) => r.id === ref.id)) {
    selectedReferences.value.push({
      id: ref.id,
      name: ref.name,
      sku: ref.sku,
      brand: ref.brand,
      color: ref.color,
    });
    form.value.items.push({
      consumable_reference_id: ref.id,
      quantity: 1,
    });
  }
};

const removeReference = (index: number) => {
  selectedReferences.value.splice(index, 1);
  form.value.items.splice(index, 1);
};

const handleFileSelect = (event: Event) => {
  const target = event.target as HTMLInputElement;
  if (target.files?.[0]) {
    form.value.delivery_note = target.files[0];
  }
};

const resetForm = () => {
  showForm.value = false;
  isFromOrder.value = true;
  form.value = {
    is_from_order: true,
    order_id: null,
    site_id: null,
    department_id: null,
    received_at: getToday(),
    delivery_note: null,
    notes: '',
    items: [],
  };
  selectedReferences.value = [];
  searchQuery.value = '';
};

const submitEntry = async () => {
  try {
    // Validaciones
    if (isFromOrder.value && !form.value.order_id) {
      appStore.notify('Debes seleccionar un pedido', 'error');
      return;
    }

    if (!isFromOrder.value) {
      if (!form.value.site_id) {
        appStore.notify('Debes seleccionar una sede', 'error');
        return;
      }
      if (form.value.items.length === 0) {
        appStore.notify('Debes seleccionar al menos una referencia', 'error');
        return;
      }
    }

    const formData = new FormData();
    formData.append('is_from_order', isFromOrder.value ? '1' : '0');
    formData.append('received_at', form.value.received_at || getToday());

    if (isFromOrder.value) {
      formData.append('order_id', form.value.order_id!.toString());
    } else {
      formData.append('site_id', form.value.site_id!.toString());
      if (form.value.department_id) {
        formData.append('department_id', form.value.department_id.toString());
      }
      // Enviar items como JSON
      formData.append('items', JSON.stringify(form.value.items));
    }

    if (form.value.delivery_note) {
      formData.append('delivery_note', form.value.delivery_note);
    }
    if (form.value.notes && form.value.notes.trim()) {
      formData.append('notes', form.value.notes);
    }

    await ordersStore.createOrderEntry(formData);
    appStore.notify('Entrada registrada correctamente', 'success');
    resetForm();
    ordersStore.fetchOrderEntries();
    loadPendingOrders();
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al registrar entrada', 'error');
  }
};

const viewDetails = async (entry: OrderEntry) => {
  try {
    const response = await ordersStore.fetchOrderEntry(entry.id);
    selectedEntry.value = response.data;
    if (!selectedEntry.value) return;
    
    // Debug: ver qué datos recibimos
    console.log('Entry data received:', selectedEntry.value);
    console.log('Items:', selectedEntry.value.items);
    
    // Asegurar que items tenga la estructura correcta y que province_id se establezca desde site
    if (selectedEntry.value.site?.province_id) {
      selectedEntry.value.province_id = selectedEntry.value.site.province_id;
    }
    // Normalizar el acceso a consumableReference - Laravel devuelve snake_case
    if (selectedEntry.value.items) {
      selectedEntry.value.items = selectedEntry.value.items.map((item: any) => {
        // Laravel devuelve consumable_reference en snake_case, normalizar a consumableReference
        const ref = item.consumable_reference || item.consumableReference || null;
        return {
          ...item,
          consumableReference: ref,
          consumable_reference: ref, // Mantener ambos para compatibilidad
        };
      });
    }
    showDetailsModal.value = true;
  } catch (error: any) {
    appStore.notify('Error al cargar detalles de la entrada', 'error');
  }
};

const editEntry = async (entry: OrderEntry) => {
  try {
    const response = await ordersStore.fetchOrderEntry(entry.id);
    selectedEntry.value = response.data;
    if (!selectedEntry.value) return;
    
    // Convertir la fecha al formato correcto para el input type="date" (yyyy-MM-dd)
    if (selectedEntry.value.received_at) {
      const date = new Date(selectedEntry.value.received_at);
      selectedEntry.value.received_at = date.toISOString().split('T')[0] || getToday();
    } else {
      selectedEntry.value.received_at = getToday();
    }
    
    // Establecer province_id desde site si existe
    if (selectedEntry.value.site?.province_id) {
      selectedEntry.value.province_id = selectedEntry.value.site.province_id;
    }
    // Cargar provincias, sedes y departamentos si no están cargados
    if (configStore.provinces.length === 0) {
      await configStore.fetchProvinces();
    }
    if (configStore.sites.length === 0) {
      await configStore.fetchSites();
    }
    if (configStore.departments.length === 0) {
      await configStore.fetchDepartments();
    }
    // Si hay site_id, cargar los departamentos de esa sede
    if (selectedEntry.value.site_id) {
      await configStore.fetchDepartments(selectedEntry.value.site_id);
    }
    showEditModal.value = true;
  } catch (error: any) {
    appStore.notify('Error al cargar la entrada para editar', 'error');
  }
};

const deleteEntry = async (id: number) => {
  if (!confirm('¿Estás seguro de eliminar esta entrada? Esta acción revertirá el stock y no se puede deshacer.')) {
    return;
  }
  try {
    deletingEntry.value = id;
    await ordersStore.deleteOrderEntry(id);
    appStore.notify('Entrada eliminada correctamente', 'success');
    deletingEntry.value = null;
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al eliminar entrada', 'error');
    deletingEntry.value = null;
  }
};

const updateEntry = async () => {
  if (!selectedEntry.value) return;
  
  try {
    // Verificar si hay un nuevo archivo de albarán
    const fileInput = document.getElementById('edit-delivery-note') as HTMLInputElement;
    const hasFile = fileInput?.files?.[0];
    
    // Si hay archivo, usar FormData; si no, usar JSON
    if (hasFile) {
      const formData = new FormData();
      formData.append('received_at', selectedEntry.value.received_at || getToday());
      
      if (selectedEntry.value.notes !== undefined) {
        formData.append('notes', selectedEntry.value.notes || '');
      }
      
      // Agregar provincia, sede y departamento (solo si no es por pedido)
      if (!selectedEntry.value.order_id) {
        formData.append('site_id', selectedEntry.value.site_id?.toString() || '');
        formData.append('department_id', selectedEntry.value.department_id?.toString() || '');
      }
      
      formData.append('delivery_note', hasFile);
      
      await ordersStore.updateOrderEntry(selectedEntry.value.id, formData);
    } else {
      // Sin archivo, usar JSON para mejor compatibilidad
      const payload: any = {
        received_at: selectedEntry.value.received_at || getToday(),
      };
      
      if (selectedEntry.value.notes !== undefined) {
        payload.notes = selectedEntry.value.notes || '';
      }
      
      // Agregar provincia, sede y departamento (solo si no es por pedido)
      if (!selectedEntry.value.order_id) {
        payload.site_id = selectedEntry.value.site_id || null;
        payload.department_id = selectedEntry.value.department_id || null;
      }
      
      console.log('Updating entry (JSON):', {
        id: selectedEntry.value.id,
        payload,
      });
      
      await ordersStore.updateOrderEntryJson(selectedEntry.value.id, payload);
    }

    appStore.notify('Entrada actualizada correctamente', 'success');
    showEditModal.value = false;
    selectedEntry.value = null;
  } catch (error: any) {
    console.error('Error updating entry:', error);
    appStore.notify(error.response?.data?.message || 'Error al actualizar entrada', 'error');
  }
};
</script>

<template>
  <div class="space-y-6">
    <div class="flex justify-between">
      <h2 class="text-2xl font-bold text-slate-900">Entradas de Pedidos</h2>
      <button
        @click="showForm = !showForm"
        class="bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark"
      >
        + Registrar entrada
      </button>
    </div>

    <div v-if="showForm" class="bg-white rounded-xl border border-slate-200 p-6">
      <h3 class="text-lg font-semibold mb-4">Nueva entrada</h3>
      <div class="space-y-4">
        <!-- Tipo de entrada -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Tipo de entrada *</label>
          <div class="flex gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                type="radio"
                :value="true"
                v-model="isFromOrder"
                class="w-4 h-4 text-ada-primary focus:ring-ada-primary"
              />
              <span>Por pedido existente</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                type="radio"
                :value="false"
                v-model="isFromOrder"
                class="w-4 h-4 text-ada-primary focus:ring-ada-primary"
              />
              <span>Entrada directa (sin pedido)</span>
            </label>
          </div>
        </div>

        <!-- Si es por pedido -->
        <div v-if="isFromOrder">
          <label class="block text-sm font-medium text-slate-700 mb-2">Pedido *</label>
          <select
            v-model="form.order_id"
            class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
          >
            <option value="">Seleccionar pedido</option>
            <option v-for="order in pendingOrders" :key="order.id" :value="order.id">
              #{{ order.id }} - {{ order.supplier_name || 'Sin proveedor' }} ({{ order.email_to }})
            </option>
          </select>
          <p v-if="pendingOrders.length === 0" class="text-sm text-slate-500 mt-1">
            No hay pedidos pendientes o en curso
          </p>
        </div>

        <!-- Si no es por pedido -->
        <template v-else>
          <!-- Ubicación -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Sede *</label>
              <select
                v-model="form.site_id"
                @change="form.department_id = null"
                class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
              >
                <option value="">Seleccionar sede</option>
                <option v-for="site in configStore.sites" :key="site.id" :value="site.id">
                  {{ site.name }}
                </option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Departamento</label>
              <select
                v-model="form.department_id"
                :disabled="!form.site_id"
                class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary disabled:bg-slate-100"
              >
                <option value="">Seleccionar departamento (opcional)</option>
                <option v-for="dept in filteredDepartments" :key="dept.id" :value="dept.id">
                  {{ dept.name }}
                </option>
              </select>
            </div>
          </div>

          <!-- Selección de referencias -->
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Buscar referencias</label>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Buscar por SKU, nombre o marca..."
              class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
            />
          </div>
          <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg">
            <div
              v-for="ref in filteredReferences"
              :key="ref.id"
              @click="addReference(ref)"
              class="p-3 hover:bg-slate-50 cursor-pointer border-b border-slate-100 last:border-b-0"
              :class="{ 'bg-ada-light': selectedReferences.find((r) => r.id === ref.id) }"
            >
              <div class="flex items-center justify-between">
                <div>
                  <p class="font-semibold">{{ ref.name }}</p>
                  <p class="text-sm text-slate-500">SKU: {{ ref.sku }} | {{ ref.brand || 'Sin marca' }}</p>
                </div>
                <div v-if="ref.color" class="flex items-center gap-2">
                  <span
                    class="w-5 h-5 rounded-full border border-slate-300"
                    :class="{
                      'bg-slate-900': ref.color === 'Negro',
                      'bg-cyan-500': ref.color === 'Cyan',
                      'bg-pink-500': ref.color === 'Magenta',
                      'bg-yellow-400': ref.color === 'Amarillo',
                    }"
                  ></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Referencias seleccionadas con cantidades -->
          <div v-if="selectedReferences.length > 0" class="space-y-3">
            <p class="text-sm font-medium text-slate-700">Referencias seleccionadas:</p>
            <div
              v-for="(ref, index) in selectedReferences"
              :key="ref.id"
              class="p-4 border border-slate-200 rounded-lg"
            >
              <div class="flex items-center justify-between mb-2">
                <div>
                  <p class="font-semibold">{{ ref.name }} ({{ ref.sku }})</p>
                  <p class="text-sm text-slate-500">{{ ref.brand || 'Sin marca' }}</p>
                </div>
                <button
                  @click="removeReference(index)"
                  class="text-red-600 hover:text-red-700 text-sm font-semibold"
                >
                  Eliminar
                </button>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Cantidad *</label>
                <input
                  :value="form.items[index]?.quantity || 1"
                  @input="(e: any) => { if (form.items[index]) form.items[index].quantity = parseInt(e.target.value) || 1; }"
                  type="number"
                  min="1"
                  class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
                />
              </div>
            </div>
          </div>
        </template>

        <!-- Campos comunes -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Fecha de recepción *</label>
          <input
            v-model="form.received_at"
            type="date"
            class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Albarán (foto o PDF)</label>
          <input
            type="file"
            accept="image/*,application/pdf"
            :capture="isMobile ? 'environment' : undefined"
            @change="handleFileSelect"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Notas</label>
          <textarea
            v-model="form.notes"
            rows="3"
            placeholder="Notas adicionales..."
            class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
          ></textarea>
        </div>
        <div class="flex gap-2">
          <button
            @click="submitEntry"
            class="flex-1 bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark"
          >
            Guardar
          </button>
          <button @click="resetForm" class="px-4 py-2 rounded-lg border border-slate-300 hover:bg-slate-50">
            Cancelar
          </button>
        </div>
      </div>
    </div>

    <!-- Lista de entradas -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="w-full">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Fecha</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Tipo</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Pedido/Ubicación</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Albarán</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <tr v-for="entry in ordersStore.entries" :key="entry.id" class="hover:bg-slate-50">
            <td class="px-6 py-4 text-sm">
              {{ new Date(entry.received_at).toLocaleDateString('es-ES') }}
            </td>
            <td class="px-6 py-4 text-sm">
              <span
                class="px-2 py-1 rounded text-xs font-semibold"
                :class="entry.order_id ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'"
              >
                {{ entry.order_id ? 'Por pedido' : 'Directa' }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm">
              <span v-if="entry.order_id">Pedido #{{ entry.order_id }}</span>
              <span v-else-if="entry.site && entry.site.name">
                {{ entry.site.name }}{{ entry.department && entry.department.name ? ` - ${entry.department.name}` : '' }}
              </span>
              <span v-else class="text-slate-400">—</span>
            </td>
            <td class="px-6 py-4 text-sm">
              <a
                v-if="entry.delivery_note_path"
                :href="`/storage/${entry.delivery_note_path}`"
                target="_blank"
                class="text-ada-primary hover:underline"
              >
                Ver albarán
              </a>
              <span v-else class="text-slate-400">—</span>
            </td>
            <td class="px-6 py-4 text-sm">
              <div class="flex gap-2">
                <button
                  @click="viewDetails(entry)"
                  class="px-3 py-1 text-sm rounded-lg border border-slate-300 hover:bg-slate-50"
                >
                  Ver
                </button>
                <button
                  @click="editEntry(entry)"
                  class="px-3 py-1 text-sm rounded-lg border border-slate-300 hover:bg-slate-50"
                >
                  Editar
                </button>
                <button
                  @click="deleteEntry(entry.id)"
                  :disabled="deletingEntry === entry.id"
                  class="px-3 py-1 text-sm rounded-lg border border-red-300 text-red-600 hover:bg-red-50 disabled:opacity-50"
                >
                  {{ deletingEntry === entry.id ? 'Eliminando...' : 'Eliminar' }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal de Detalles -->
    <div
      v-if="showDetailsModal && selectedEntry"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="showDetailsModal = false"
    >
      <div class="bg-white rounded-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-slate-200 flex justify-between items-center">
          <h3 class="text-xl font-bold text-slate-900">Detalles de la Entrada #{{ selectedEntry.id }}</h3>
          <button @click="showDetailsModal = false" class="text-slate-500 hover:text-slate-700">
            ✕
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm text-slate-500">Fecha de recepción</p>
              <p class="font-semibold">{{ new Date(selectedEntry.received_at).toLocaleDateString('es-ES') }}</p>
            </div>
            <div>
              <p class="text-sm text-slate-500">Tipo</p>
              <p class="font-semibold">
                <span
                  class="px-2 py-1 rounded text-xs"
                  :class="selectedEntry.order_id ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700'"
                >
                  {{ selectedEntry.order_id ? 'Por pedido' : 'Directa' }}
                </span>
              </p>
            </div>
            <div v-if="selectedEntry.order_id">
              <p class="text-sm text-slate-500">Pedido</p>
              <p class="font-semibold">#{{ selectedEntry.order_id }}</p>
            </div>
            <div v-if="selectedEntry.site">
              <p class="text-sm text-slate-500">Ubicación</p>
              <p class="font-semibold">
                {{ selectedEntry.site.name }}{{ selectedEntry.department ? ` - ${selectedEntry.department.name}` : '' }}
              </p>
            </div>
            <div v-if="selectedEntry.notes">
              <p class="text-sm text-slate-500">Notas</p>
              <p class="font-semibold">{{ selectedEntry.notes }}</p>
            </div>
            <div v-if="selectedEntry.delivery_note_path">
              <p class="text-sm text-slate-500">Albarán</p>
              <a
                :href="`/storage/${selectedEntry.delivery_note_path}`"
                target="_blank"
                class="text-ada-primary hover:underline font-semibold"
              >
                Ver albarán
              </a>
            </div>
          </div>

          <!-- Referencias -->
          <div v-if="selectedEntry.items && selectedEntry.items.length > 0">
            <h4 class="text-lg font-semibold mb-3">Referencias registradas</h4>
            <div class="border border-slate-200 rounded-lg overflow-hidden">
              <table class="w-full">
                <thead class="bg-slate-50">
                  <tr>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Referencia</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">SKU</th>
                    <th class="px-4 py-2 text-left text-xs font-semibold text-slate-600">Marca</th>
                    <th class="px-4 py-2 text-right text-xs font-semibold text-slate-600">Cantidad</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                  <tr v-for="item in selectedEntry.items" :key="item.id">
                    <td class="px-4 py-3 text-sm font-semibold">
                      {{ (item.consumable_reference || item.consumableReference)?.name || 'N/A' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                      {{ (item.consumable_reference || item.consumableReference)?.sku || 'N/A' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                      {{ (item.consumable_reference || item.consumableReference)?.brand || 'N/A' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-right font-semibold">
                      {{ item.quantity }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="p-6 border-t border-slate-200 flex justify-end gap-2">
          <button
            @click="editEntry(selectedEntry)"
            class="px-4 py-2 rounded-lg border border-slate-300 hover:bg-slate-50"
          >
            Editar
          </button>
          <button
            @click="showDetailsModal = false"
            class="px-4 py-2 rounded-lg bg-ada-primary text-white hover:bg-ada-dark"
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>

    <!-- Modal de Edición -->
    <div
      v-if="showEditModal && selectedEntry"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="showEditModal = false"
    >
      <div class="bg-white rounded-xl max-w-2xl w-full mx-4">
        <div class="p-6 border-b border-slate-200 flex justify-between items-center">
          <h3 class="text-xl font-bold text-slate-900">Editar Entrada #{{ selectedEntry.id }}</h3>
          <button @click="showEditModal = false" class="text-slate-500 hover:text-slate-700">
            ✕
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Fecha de recepción *</label>
            <input
              v-model="selectedEntry.received_at"
              type="date"
              class="w-full rounded-lg border border-slate-300 px-4 py-2"
            />
          </div>
          
          <!-- Ubicación (solo si no es por pedido) -->
          <div v-if="selectedEntry && !selectedEntry.order_id" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Provincia</label>
              <select
                v-model="selectedEntry.province_id"
                @change="
                  if (selectedEntry) {
                    selectedEntry.site_id = null;
                    selectedEntry.department_id = null;
                    configStore.fetchSites(selectedEntry.province_id || undefined);
                  }
                "
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
              >
                <option :value="null">Seleccionar provincia</option>
                <option v-for="p in configStore.provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Sede</label>
              <select
                v-model="selectedEntry.site_id"
                @change="
                  if (selectedEntry) {
                    selectedEntry.department_id = null;
                    configStore.fetchDepartments(selectedEntry.site_id || undefined);
                  }
                "
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
                :disabled="!selectedEntry.province_id"
              >
                <option :value="null">Seleccionar sede</option>
                <option
                  v-for="s in configStore.sites.filter((s: any) => !selectedEntry?.province_id || s.province_id === selectedEntry.province_id)"
                  :key="s.id"
                  :value="s.id"
                >
                  {{ s.name }}
                </option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Departamento</label>
              <select
                v-model="selectedEntry.department_id"
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
                :disabled="!selectedEntry?.site_id"
              >
                <option :value="null">Seleccionar departamento (opcional)</option>
                <option
                  v-for="d in configStore.departments.filter((d: any) => !selectedEntry?.site_id || d.site_id === selectedEntry.site_id)"
                  :key="d.id"
                  :value="d.id"
                >
                  {{ d.name }}
                </option>
              </select>
            </div>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Notas</label>
            <textarea
              v-model="selectedEntry.notes"
              rows="3"
              class="w-full rounded-lg border border-slate-300 px-4 py-2"
            ></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Albarán (nuevo archivo)</label>
            <input
              id="edit-delivery-note"
              type="file"
              accept="image/*,application/pdf"
              class="w-full rounded-lg border border-slate-300 px-4 py-2"
            />
            <p class="text-xs text-slate-500 mt-1">Dejar vacío para mantener el archivo actual</p>
            <a
              v-if="selectedEntry.delivery_note_path"
              :href="`/storage/${selectedEntry.delivery_note_path}`"
              target="_blank"
              class="text-sm text-ada-primary hover:underline mt-2 inline-block"
            >
              Ver albarán actual
            </a>
          </div>
        </div>
        <div class="p-6 border-t border-slate-200 flex justify-end gap-2">
          <button
            @click="showEditModal = false"
            class="px-4 py-2 rounded-lg border border-slate-300 hover:bg-slate-50"
          >
            Cancelar
          </button>
          <button
            @click="updateEntry"
            class="px-4 py-2 rounded-lg bg-ada-primary text-white hover:bg-ada-dark"
          >
            Guardar cambios
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
