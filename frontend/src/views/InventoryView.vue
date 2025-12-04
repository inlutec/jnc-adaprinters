<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useInventoryStore } from '@/stores/inventory';
import { useAppStore } from '@/stores/app';

defineOptions({ name: 'InventoryView' });

const inventory = useInventoryStore();
const app = useAppStore();
const showRegularizeModal = ref(false);
const showMovementModal = ref(false);
const selectedStock = ref<any>(null);
const regularizeForm = ref({ quantity: 0, justification: '' });
const movementForm = ref({ type: 'in' as 'in' | 'out' | 'adjustment', quantity: 0, justification: '' });

onMounted(() => {
  inventory.fetch();
});

const statusBadge = (stock: { quantity: number; minimum_quantity: number }) => {
  if (stock.quantity <= stock.minimum_quantity) {
    return 'bg-rose-50 text-rose-600';
  }
  if (stock.quantity <= stock.minimum_quantity * 1.5) {
    return 'bg-amber-50 text-amber-600';
  }
  return 'bg-emerald-50 text-emerald-600';
};

const openRegularize = (stock: any) => {
  selectedStock.value = stock;
  regularizeForm.value = { quantity: stock.quantity, justification: '' };
  showRegularizeModal.value = true;
};

const openMovement = (stock: any, type: 'in' | 'out' = 'in') => {
  selectedStock.value = stock;
  movementForm.value = { type, quantity: 0, justification: '' };
  showMovementModal.value = true;
};

const submitRegularize = async () => {
  if (!selectedStock.value) return;
  try {
    await inventory.regularize(selectedStock.value.id, regularizeForm.value.quantity, regularizeForm.value.justification);
    app.notify('Stock regularizado', 'success');
    showRegularizeModal.value = false;
  } catch (error: any) {
    app.notify(error.response?.data?.message || 'Error', 'error');
  }
};

const submitMovement = async () => {
  if (!selectedStock.value) return;
  try {
    await inventory.addMovement(
      selectedStock.value.id,
      movementForm.value.type,
      movementForm.value.quantity,
      movementForm.value.justification
    );
    app.notify('Movimiento registrado', 'success');
    showMovementModal.value = false;
  } catch (error: any) {
    app.notify(error.response?.data?.message || 'Error', 'error');
  }
};

const refresh = () => {
  inventory.fetch(inventory.meta.current_page);
  app.notify('Inventario actualizado', 'success');
};

const updateMinimumQuantity = async (stockId: number, minimumQuantity: number) => {
  try {
    await inventory.updateMinimumQuantity(stockId, minimumQuantity);
    app.notify('Cantidad mínima actualizada', 'success');
  } catch (error: any) {
    app.notify(error.response?.data?.message || 'Error al actualizar cantidad mínima', 'error');
  }
};
</script>

<template>
  <div class="space-y-8">
    <section class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-[0.3em] text-slate-500 font-semibold">
          Inventario central
        </p>
        <h1 class="text-3xl font-black text-slate-900">Consumo y existencias</h1>
        <p class="text-sm text-slate-500">
          Control en tiempo real de tóneres, tambores y repuestos críticos.
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <button
          class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
          @click="inventory.toggleLowOnly"
        >
          {{ inventory.lowOnly ? 'Ver todos' : 'Solo críticos' }}
        </button>
        <button
          class="rounded-full bg-ada-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-ada-dark"
          @click="refresh"
        >
          Actualizar datos
        </button>
      </div>
    </section>

    <section class="grid gap-4 md:grid-cols-2">
      <article class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        <p class="text-xs uppercase tracking-[0.3em] text-slate-500 font-semibold">Referencias</p>
        <p class="mt-3 text-3xl font-black text-slate-900">{{ inventory.meta.total }}</p>
        <p class="text-sm text-slate-500">Consumibles controlados</p>
      </article>
      <article class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        <p class="text-xs uppercase tracking-[0.3em] text-slate-500 font-semibold">Críticos</p>
        <p class="mt-3 text-3xl font-black text-rose-600">
          {{
            inventory.stocks.filter((stock) => stock.quantity <= stock.minimum_quantity).length
          }}
        </p>
        <p class="text-sm text-slate-500">Por debajo del mínimo</p>
      </article>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white/95 shadow-sm">
      <div class="flex items-center justify-between px-6 py-5 border-b border-slate-100">
        <div>
          <h2 class="text-xl font-black text-slate-900">Detalle de existencias</h2>
          <p class="text-sm text-slate-500">
            {{ inventory.meta.total }} registros · página {{ inventory.meta.current_page }} de
            {{ inventory.meta.last_page }}
          </p>
        </div>
        <div class="flex items-center gap-2">
          <label class="text-xs font-semibold text-slate-500">Por página</label>
          <select
            v-model.number="inventory.meta.per_page"
            class="rounded-2xl border border-slate-200 px-3 py-1 text-sm"
            @change="inventory.fetch()"
          >
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="50">50</option>
          </select>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
              <th class="px-6 py-4">Consumible</th>
              <th class="px-6 py-4">Ubicación</th>
              <th class="px-6 py-4">Stock</th>
              <th class="px-6 py-4">Estado</th>
              <th class="px-6 py-4">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="stock in inventory.stocks"
              :key="stock.id"
              class="border-t border-slate-100 last:border-b"
            >
              <td class="px-6 py-4">
                <p class="font-semibold text-slate-900">{{ stock.consumable?.name }}</p>
                <p class="text-xs text-slate-500">{{ stock.consumable?.sku ?? '—' }}</p>
              </td>
              <td class="px-6 py-4 text-slate-600">
                {{ stock.site?.name ?? 'Almacén central' }}
                {{ stock.department ? `· ${stock.department.name}` : '' }}
              </td>
              <td class="px-6 py-4">
                <p class="font-semibold text-slate-900">{{ stock.quantity }}</p>
                <div class="flex items-center gap-2 mt-1">
                  <span class="text-xs text-slate-500">Mínimo:</span>
                  <input
                    :value="stock.minimum_quantity"
                    @blur="(e: any) => updateMinimumQuantity(stock.id, parseInt(e.target.value) || 0)"
                    @keyup.enter="(e: any) => { e.target.blur(); }"
                    type="number"
                    min="0"
                    class="w-16 px-2 py-0.5 text-xs border border-slate-300 rounded focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
                  />
                </div>
              </td>
              <td class="px-6 py-4">
                <span
                  class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                  :class="statusBadge(stock)"
                >
                  {{
                    stock.quantity <= stock.minimum_quantity
                      ? 'Crítico'
                      : stock.quantity <= stock.minimum_quantity * 1.5
                        ? 'Atención'
                        : 'Óptimo'
                  }}
                </span>
              </td>
              <td class="px-6 py-4">
                <div class="flex gap-2">
                  <button
                    @click="openMovement(stock, 'in')"
                    class="px-2 py-1 text-xs text-green-600 bg-green-50 rounded hover:bg-green-100"
                    title="Entrada"
                  >
                    + Entrada
                  </button>
                  <button
                    @click="openMovement(stock, 'out')"
                    class="px-2 py-1 text-xs text-red-600 bg-red-50 rounded hover:bg-red-100"
                    title="Salida"
                  >
                    - Salida
                  </button>
                  <button
                    @click="openRegularize(stock)"
                    class="px-2 py-1 text-xs text-ada-primary bg-ada-light rounded hover:bg-ada-primary/10"
                    title="Regularizar"
                  >
                    Regularizar
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!inventory.stocks.length">
              <td colspan="5" class="px-6 py-8 text-center text-slate-500">Sin datos para mostrar.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="flex items-center justify-between px-6 py-4 border-t border-slate-100 text-sm text-slate-500">
        <button
          class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
          :disabled="inventory.meta.current_page === 1"
          @click="inventory.fetch(inventory.meta.current_page - 1)"
        >
          Anterior
        </button>
        <span>Página {{ inventory.meta.current_page }} de {{ inventory.meta.last_page }}</span>
        <button
          class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
          :disabled="inventory.meta.current_page === inventory.meta.last_page"
          @click="inventory.fetch(inventory.meta.current_page + 1)"
        >
          Siguiente
        </button>
      </div>
    </section>

    <!-- Modal Regularización -->
    <div
      v-if="showRegularizeModal && selectedStock"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showRegularizeModal = false"
    >
      <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold mb-4">Regularizar stock</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Cantidad actual</label>
            <p class="text-2xl font-bold text-slate-900">{{ selectedStock.quantity }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Nueva cantidad</label>
            <input
              v-model.number="regularizeForm.quantity"
              type="number"
              min="0"
              class="w-full rounded-lg border border-slate-300 px-4 py-2"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Justificación</label>
            <textarea
              v-model="regularizeForm.justification"
              rows="3"
              class="w-full rounded-lg border border-slate-300 px-4 py-2"
            ></textarea>
          </div>
          <div class="flex gap-2">
            <button @click="submitRegularize" class="flex-1 bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold">
              Guardar
            </button>
            <button @click="showRegularizeModal = false" class="px-4 py-2 rounded-lg border border-slate-300">
              Cancelar
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Movimiento -->
    <div
      v-if="showMovementModal && selectedStock"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showMovementModal = false"
    >
      <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-semibold mb-4">
          {{ movementForm.type === 'in' ? 'Registrar entrada' : movementForm.type === 'out' ? 'Registrar salida' : 'Ajuste' }}
        </h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Cantidad</label>
            <input
              v-model.number="movementForm.quantity"
              type="number"
              min="1"
              class="w-full rounded-lg border border-slate-300 px-4 py-2"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Justificación</label>
            <textarea
              v-model="movementForm.justification"
              rows="3"
              class="w-full rounded-lg border border-slate-300 px-4 py-2"
              placeholder="Ej: Pedido #123, Inventario físico, etc."
            ></textarea>
          </div>
          <div class="flex gap-2">
            <button @click="submitMovement" class="flex-1 bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold">
              Guardar
            </button>
            <button @click="showMovementModal = false" class="px-4 py-2 rounded-lg border border-slate-300">
              Cancelar
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

