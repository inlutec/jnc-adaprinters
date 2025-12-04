<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useOrdersStore } from '@/stores/orders';
import { useReferencesStore } from '@/stores/references';
import { useAppStore } from '@/stores/app';

defineOptions({ name: 'OrdersView' });

const ordersStore = useOrdersStore();
const referencesStore = useReferencesStore();
const appStore = useAppStore();

const showWizard = ref(false);
const currentStep = ref(1);
const totalSteps = 4;

const form = ref({
  supplier_name: '',
  email_to: '',
  notes: '',
  items: [] as Array<{ consumable_reference_id: number; quantity: number; description?: string }>,
});

const selectedReferences = ref<Array<{ id: number; name: string; sku: string; brand?: string; color?: string }>>([]);
const searchQuery = ref('');
const availableReferences = ref<any[]>([]);

onMounted(() => {
  ordersStore.fetchOrders();
  loadReferences();
});

const loadReferences = async () => {
  try {
    await referencesStore.fetchReferences({ per_page: 100, is_active: true });
    availableReferences.value = referencesStore.references.filter((r: any) => r.is_active);
  } catch (error) {
    console.error('Error loading references:', error);
  }
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
      description: `${ref.name} (${ref.sku})`,
    });
  }
};

const removeReference = (index: number) => {
  selectedReferences.value.splice(index, 1);
  form.value.items.splice(index, 1);
};

const nextStep = () => {
  if (currentStep.value < totalSteps) {
    if (currentStep.value === 2 && selectedReferences.value.length === 0) {
      appStore.notify('Debes seleccionar al menos una referencia', 'error');
      return;
    }
    currentStep.value++;
  }
};

const prevStep = () => {
  if (currentStep.value > 1) {
    currentStep.value--;
  }
};

const resetWizard = () => {
  showWizard.value = false;
  currentStep.value = 1;
  form.value = {
    supplier_name: '',
    email_to: '',
    notes: '',
    items: [],
  };
  selectedReferences.value = [];
  searchQuery.value = '';
};

const submitOrder = async () => {
  try {
    await ordersStore.createOrder({
      supplier_name: form.value.supplier_name,
      email_to: form.value.email_to,
      notes: form.value.notes,
      items: form.value.items,
    } as any);
    appStore.notify('Pedido creado correctamente', 'success');
    resetWizard();
    ordersStore.fetchOrders();
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al crear pedido', 'error');
  }
};

const statusColors: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-700',
  in_progress: 'bg-blue-100 text-blue-700',
  sent: 'bg-blue-100 text-blue-700',
  received: 'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-700',
};

const statusLabels: Record<string, string> = {
  pending: 'Pendiente',
  in_progress: 'Pedido en curso',
  sent: 'Enviado',
  received: 'Pedido recibido',
  cancelled: 'Cancelado',
};

const selectedOrder = ref<any>(null);
const showOrderDetails = ref(false);
const newComment = ref('');
const orderComments = ref<any[]>([]);

const openOrderDetails = async (order: any) => {
  selectedOrder.value = order;
  showOrderDetails.value = true;
  await loadComments(order.id);
};

const loadComments = async (orderId: number) => {
  try {
    const comments = await ordersStore.getComments(orderId);
    orderComments.value = comments;
  } catch (error) {
    console.error('Error loading comments:', error);
  }
};

const addComment = async () => {
  if (!newComment.value.trim() || !selectedOrder.value) return;
  try {
    await ordersStore.addComment(selectedOrder.value.id, newComment.value);
    newComment.value = '';
    await loadComments(selectedOrder.value.id);
    appStore.notify('Comentario añadido', 'success');
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al añadir comentario', 'error');
  }
};

const changeStatus = async (status: string) => {
  if (!selectedOrder.value) return;
  try {
    await ordersStore.updateOrder(selectedOrder.value.id, { status });
    selectedOrder.value.status = status;
    await ordersStore.fetchOrders();
    appStore.notify('Estado actualizado', 'success');
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al actualizar estado', 'error');
  }
};
</script>

<template>
  <div class="space-y-6">
    <div class="flex justify-between">
      <h2 class="text-2xl font-bold text-slate-900">Pedidos</h2>
      <button
        @click="showWizard = true"
        class="bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark"
      >
        + Nuevo pedido
      </button>
    </div>

    <!-- Asistente de Pedidos -->
    <div
      v-if="showWizard"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="resetWizard"
    >
      <div class="bg-white rounded-xl p-6 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
          <h3 class="text-xl font-bold">Nuevo Pedido</h3>
          <button @click="resetWizard" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <!-- Indicador de pasos -->
        <div class="flex items-center justify-between mb-8">
          <div
            v-for="step in totalSteps"
            :key="step"
            class="flex items-center flex-1"
          >
            <div class="flex items-center">
              <div
                class="w-10 h-10 rounded-full flex items-center justify-center font-semibold"
                :class="
                  currentStep >= step
                    ? 'bg-ada-primary text-white'
                    : 'bg-slate-200 text-slate-600'
                "
              >
                {{ step }}
              </div>
              <div class="ml-2 text-sm font-medium" :class="currentStep >= step ? 'text-ada-primary' : 'text-slate-500'">
                {{ step === 1 ? 'Información' : step === 2 ? 'Referencias' : step === 3 ? 'Cantidades' : 'Resumen' }}
              </div>
            </div>
            <div v-if="step < totalSteps" class="flex-1 h-1 mx-4" :class="currentStep > step ? 'bg-ada-primary' : 'bg-slate-200'"></div>
          </div>
        </div>

        <!-- Paso 1: Información básica -->
        <div v-if="currentStep === 1" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Proveedor *</label>
            <input
              v-model="form.supplier_name"
              type="text"
              placeholder="Nombre del proveedor"
              class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Email *</label>
            <input
              v-model="form.email_to"
              type="email"
              placeholder="email@proveedor.com"
              class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
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
        </div>

        <!-- Paso 2: Selección de referencias -->
        <div v-if="currentStep === 2" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Buscar referencias</label>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Buscar por SKU, nombre o marca..."
              class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
            />
          </div>
          <div class="max-h-64 overflow-y-auto border border-slate-200 rounded-lg">
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
          <div v-if="selectedReferences.length > 0" class="mt-4">
            <p class="text-sm font-medium text-slate-700 mb-2">Referencias seleccionadas:</p>
            <div class="space-y-2">
              <div
                v-for="(ref, index) in selectedReferences"
                :key="ref.id"
                class="flex items-center justify-between p-2 bg-slate-50 rounded-lg"
              >
                <span class="text-sm">{{ ref.name }} ({{ ref.sku }})</span>
                <button
                  @click="removeReference(index)"
                  class="text-red-600 hover:text-red-700 text-sm"
                >
                  Eliminar
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Paso 3: Cantidades -->
        <div v-if="currentStep === 3" class="space-y-4">
          <div
            v-for="(item, index) in form.items"
            :key="index"
            class="p-4 border border-slate-200 rounded-lg"
          >
            <p class="font-semibold mb-2">
              {{ selectedReferences[index]?.name }} ({{ selectedReferences[index]?.sku }})
            </p>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Cantidad *</label>
              <input
                v-model.number="item.quantity"
                type="number"
                min="1"
                class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
              />
            </div>
            <div class="mt-2">
              <label class="block text-sm font-medium text-slate-700 mb-2">Descripción (opcional)</label>
              <input
                v-model="item.description"
                type="text"
                class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
              />
            </div>
          </div>
        </div>

        <!-- Paso 4: Resumen -->
        <div v-if="currentStep === 4" class="space-y-4">
          <div class="bg-slate-50 p-4 rounded-lg">
            <p class="font-semibold mb-2">Proveedor:</p>
            <p class="text-slate-600">{{ form.supplier_name }}</p>
          </div>
          <div class="bg-slate-50 p-4 rounded-lg">
            <p class="font-semibold mb-2">Email:</p>
            <p class="text-slate-600">{{ form.email_to }}</p>
          </div>
          <div v-if="form.notes" class="bg-slate-50 p-4 rounded-lg">
            <p class="font-semibold mb-2">Notas:</p>
            <p class="text-slate-600">{{ form.notes }}</p>
          </div>
          <div class="bg-slate-50 p-4 rounded-lg">
            <p class="font-semibold mb-4">Items del pedido:</p>
            <div class="space-y-2">
              <div
                v-for="(item, index) in form.items"
                :key="index"
                class="flex justify-between text-sm"
              >
                <span>{{ selectedReferences[index]?.name }} ({{ selectedReferences[index]?.sku }})</span>
                <span class="font-semibold">x{{ item.quantity }}</span>
              </div>
            </div>
            <div class="mt-4 pt-4 border-t border-slate-200">
              <p class="font-semibold">
                Total de items: {{ form.items.reduce((sum, item) => sum + item.quantity, 0) }}
              </p>
            </div>
          </div>
        </div>

        <!-- Botones de navegación -->
        <div class="flex justify-between mt-6 pt-6 border-t border-slate-200">
          <button
            v-if="currentStep > 1"
            @click="prevStep"
            class="px-4 py-2 rounded-lg border border-slate-300 hover:bg-slate-50"
          >
            Anterior
          </button>
          <div v-else></div>
          <div class="flex gap-2">
            <button
              @click="resetWizard"
              class="px-4 py-2 rounded-lg border border-slate-300 hover:bg-slate-50"
            >
              Cancelar
            </button>
            <button
              v-if="currentStep < totalSteps"
              @click="nextStep"
              class="px-4 py-2 rounded-lg bg-ada-primary text-white hover:bg-ada-dark"
            >
              Siguiente
            </button>
            <button
              v-else
              @click="submitOrder"
              class="px-4 py-2 rounded-lg bg-ada-primary text-white hover:bg-ada-dark"
            >
              Crear Pedido
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Lista de pedidos -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="w-full">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">ID</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Proveedor</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Estado</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Fecha</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Email</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <tr v-for="order in ordersStore.orders" :key="order.id" class="hover:bg-slate-50">
            <td class="px-6 py-4 font-mono text-sm">#{{ order.id }}</td>
            <td class="px-6 py-4">{{ order.supplier_name || '-' }}</td>
            <td class="px-6 py-4">
              <span
                class="px-2 py-1 rounded text-xs font-semibold"
                :class="statusColors[order.status] || 'bg-slate-100 text-slate-700'"
              >
                {{ statusLabels[order.status] || order.status }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm text-slate-600">
              {{ order.requested_at ? new Date(order.requested_at).toLocaleDateString('es-ES') : '-' }}
            </td>
            <td class="px-6 py-4 text-sm text-slate-600">{{ order.email_to || '-' }}</td>
            <td class="px-6 py-4">
              <button
                @click="openOrderDetails(order)"
                class="px-3 py-1 text-xs bg-ada-primary text-white rounded hover:bg-ada-dark"
              >
                Ver detalles
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal de detalles del pedido -->
    <div
      v-if="showOrderDetails && selectedOrder"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showOrderDetails = false"
    >
      <div class="bg-white rounded-xl p-6 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
          <h3 class="text-xl font-bold">Pedido #{{ selectedOrder.id }}</h3>
          <button @click="showOrderDetails = false" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>

        <div class="space-y-6">
          <!-- Información básica -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <p class="text-sm text-slate-500">Proveedor</p>
              <p class="font-semibold">{{ selectedOrder.supplier_name }}</p>
            </div>
            <div>
              <p class="text-sm text-slate-500">Email</p>
              <p class="font-semibold">{{ selectedOrder.email_to }}</p>
            </div>
            <div>
              <p class="text-sm text-slate-500">Estado</p>
              <span
                class="px-2 py-1 rounded text-xs font-semibold inline-block"
                :class="statusColors[selectedOrder.status] || 'bg-slate-100 text-slate-700'"
              >
                {{ statusLabels[selectedOrder.status] || selectedOrder.status }}
              </span>
            </div>
            <div>
              <p class="text-sm text-slate-500">Fecha</p>
              <p class="font-semibold">
                {{ selectedOrder.requested_at ? new Date(selectedOrder.requested_at).toLocaleDateString('es-ES') : '-' }}
              </p>
            </div>
          </div>

          <!-- Cambio de estado -->
          <div class="border-t pt-4">
            <p class="text-sm font-semibold text-slate-700 mb-2">Cambiar estado:</p>
            <div class="flex gap-2">
              <button
                v-if="selectedOrder.status === 'pending'"
                @click="changeStatus('in_progress')"
                class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm"
              >
                Marcar como "Pedido en curso"
              </button>
              <button
                v-if="selectedOrder.status === 'in_progress'"
                @click="changeStatus('received')"
                class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 text-sm"
              >
                Marcar como "Pedido recibido"
              </button>
            </div>
          </div>

          <!-- Items del pedido -->
          <div v-if="selectedOrder.items && selectedOrder.items.length > 0" class="border-t pt-4">
            <p class="text-sm font-semibold text-slate-700 mb-2">Items del pedido:</p>
            <div class="space-y-2">
              <div
                v-for="item in selectedOrder.items"
                :key="item.id"
                class="p-3 bg-slate-50 rounded-lg flex justify-between"
              >
                <div>
                  <p class="font-semibold">{{ item.consumableReference?.name || item.description }}</p>
                  <p class="text-xs text-slate-500">SKU: {{ item.consumableReference?.sku || '-' }}</p>
                </div>
                <p class="font-semibold">x{{ item.quantity }}</p>
              </div>
            </div>
          </div>

          <!-- Historial de comentarios -->
          <div class="border-t pt-4">
            <p class="text-sm font-semibold text-slate-700 mb-2">Historial de comentarios:</p>
            <div class="space-y-3 mb-4 max-h-64 overflow-y-auto">
              <div
                v-for="comment in orderComments"
                :key="comment.id"
                class="p-3 bg-slate-50 rounded-lg"
              >
                <div class="flex justify-between items-start mb-1">
                  <p class="text-xs text-slate-500">
                    {{ comment.creator?.name || 'Usuario' }} - {{ new Date(comment.created_at).toLocaleString('es-ES') }}
                  </p>
                </div>
                <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ comment.comment }}</p>
              </div>
              <p v-if="orderComments.length === 0" class="text-sm text-slate-400 text-center py-4">
                No hay comentarios aún
              </p>
            </div>
            <div class="flex gap-2">
              <textarea
                v-model="newComment"
                rows="3"
                placeholder="Añadir comentario..."
                class="flex-1 rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
              ></textarea>
              <button
                @click="addComment"
                :disabled="!newComment.trim()"
                class="px-4 py-2 bg-ada-primary text-white rounded hover:bg-ada-dark disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Añadir
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
