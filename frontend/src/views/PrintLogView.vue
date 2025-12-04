<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { Line } from 'vue-chartjs';
import {
  Chart,
  LineElement,
  PointElement,
  LinearScale,
  CategoryScale,
  Filler,
  Tooltip,
} from 'chart.js';
import { usePrintLogsStore } from '@/stores/printLogs';
import { usePrintersStore } from '@/stores/printers';
import { useConfigStore } from '@/stores/config';
import { useAppStore } from '@/stores/app';
import { ref } from 'vue';
import { format } from 'date-fns';
import { es } from 'date-fns/locale';
import { api } from '@/services/httpClient';

defineOptions({ name: 'PrintLogView' });

Chart.register(LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip);

const logsStore = usePrintLogsStore();
const printersStore = usePrintersStore();
const configStore = useConfigStore();
const appStore = useAppStore();
const showFilters = ref(false);
const exporting = ref(false);

const proveedorValues = ref<string[]>([]);

onMounted(async () => {
  logsStore.fetch();
  printersStore.fetch();
  configStore.fetchProvinces();
  configStore.fetchSites();
  configStore.fetchDepartments();
  
  // Obtener valores únicos del campo personalizado "proveedor"
  try {
    const { data } = await api.get('/config/custom-fields/proveedor/values');
    proveedorValues.value = data.values || [];
  } catch (error) {
    console.error('Error al obtener valores de proveedor:', error);
  }
});

const trendDataset = computed(() => ({
  labels: logsStore.trend.map((point) => point.date),
  datasets: [
    {
      label: 'Volumen diario',
      data: logsStore.trend.map((point) => point.value),
      fill: true,
      backgroundColor: 'rgba(37,99,235,0.12)',
      borderColor: '#2563EB',
      borderWidth: 2,
      tension: 0.35,
      pointRadius: 0,
    },
  ],
}));

const trendOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: {
    x: {
      grid: { display: false },
      ticks: { color: '#94a3b8' },
    },
    y: {
      grid: { color: 'rgba(226,232,240,0.6)' },
      ticks: { color: '#94a3b8' },
    },
  },
};

const applyFilters = () => {
  logsStore.fetch();
};

const exportReport = async () => {
  exporting.value = true;
  try {
    await logsStore.export();
    appStore.notify('Informe exportado correctamente', 'success');
  } catch (error: any) {
    appStore.notify('Error al exportar informe', 'error');
  } finally {
    exporting.value = false;
  }
};

const ratioColor = computed(() => {
  if (!logsStore.aggregates.total_prints) return '0%';
  return `${Math.round(
    (logsStore.aggregates.color_prints / logsStore.aggregates.total_prints) * 100
  )}%`;
});
</script>

<template>
  <div class="space-y-8">
    <section class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-[0.3em] text-slate-500 font-semibold">
          Registro histórico
        </p>
        <h1 class="text-3xl font-black text-slate-900">Actividad de impresión</h1>
        <p class="text-sm text-slate-500">
          Consolida los contadores SNMP y genera el histórico para auditoría y contabilidad.
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <button
          @click="showFilters = !showFilters"
          class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 text-sm font-semibold"
        >
          {{ showFilters ? 'Ocultar' : 'Mostrar' }} filtros
        </button>
        <button
          @click="exportReport"
          :disabled="exporting"
          class="px-4 py-2 rounded-lg bg-ada-primary text-white hover:bg-ada-dark text-sm font-semibold disabled:opacity-50"
        >
          {{ exporting ? 'Exportando...' : '📄 Exportar informe HTML' }}
        </button>
      </div>
    </section>

    <section v-if="showFilters" class="bg-white rounded-xl border border-slate-200 p-6">
      <h3 class="text-lg font-semibold mb-4">Filtros avanzados</h3>
      <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Tipo</label>
          <select
            v-model="logsStore.filters.type"
            @change="applyFilters"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
          >
            <option value="all">Todas</option>
            <option value="color">Solo color</option>
            <option value="bw">Solo monocromo</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Provincia</label>
          <select
            v-model="logsStore.filters.province_id"
            @change="
              configStore.fetchSites(logsStore.filters.province_id || undefined);
              applyFilters();
            "
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
          >
            <option :value="null">Todas</option>
            <option v-for="p in configStore.provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Sede</label>
          <select
            v-model="logsStore.filters.site_id"
            @change="
              configStore.fetchDepartments(logsStore.filters.site_id || undefined);
              applyFilters();
            "
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
          >
            <option :value="null">Todas</option>
            <option v-for="s in configStore.sites" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Departamento</label>
          <select
            v-model="logsStore.filters.department_id"
            @change="applyFilters"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
          >
            <option :value="null">Todos</option>
            <option v-for="d in configStore.departments" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Proveedor</label>
          <select
            v-model="logsStore.filters.proveedor"
            @change="applyFilters"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
          >
            <option :value="null">Todos</option>
            <option v-for="proveedor in proveedorValues" :key="proveedor" :value="proveedor">{{ proveedor }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Impresora</label>
          <select
            v-model="logsStore.filters.printer_id"
            @change="applyFilters"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
          >
            <option :value="null">Todas</option>
            <option v-for="p in printersStore.items" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Desde</label>
          <input
            v-model="logsStore.filters.date_from"
            type="date"
            @change="applyFilters"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Hasta</label>
          <input
            v-model="logsStore.filters.date_to"
            type="date"
            @change="applyFilters"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
          />
        </div>
        <div class="flex items-end">
          <button
            @click="
              logsStore.filters = {
                type: 'all',
                printer_id: null,
                province_id: null,
                site_id: null,
                department_id: null,
                date_from: '',
                date_to: '',
                proveedor: null,
              };
              applyFilters();
            "
            class="w-full px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 text-sm font-semibold"
          >
            Limpiar filtros
          </button>
        </div>
      </div>
    </section>

    <section class="grid gap-4 md:grid-cols-3">
      <article class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        <p class="text-xs uppercase tracking-[0.3em] text-slate-500 font-semibold">Total</p>
        <p class="mt-3 text-3xl font-black text-slate-900">
          {{ new Intl.NumberFormat('es-ES').format(logsStore.aggregates.total_prints) }}
        </p>
        <p class="text-sm text-slate-500">Impresiones en el periodo</p>
      </article>
      <article class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        <p class="text-xs uppercase tracking-[0.3em] text-slate-500 font-semibold">Color</p>
        <p class="mt-3 text-3xl font-black text-slate-900">
          {{ new Intl.NumberFormat('es-ES').format(logsStore.aggregates.color_prints) }}
        </p>
        <p class="text-sm text-slate-500">({{ ratioColor }} del total)</p>
      </article>
      <article class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm">
        <p class="text-xs uppercase tracking-[0.3em] text-slate-500 font-semibold">Blanco y negro</p>
        <p class="mt-3 text-3xl font-black text-slate-900">
          {{ new Intl.NumberFormat('es-ES').format(logsStore.aggregates.bw_prints) }}
        </p>
        <p class="text-sm text-slate-500">Consumo monocromo</p>
      </article>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm">
      <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
          <p class="text-xs uppercase tracking-[0.3em] text-slate-500 font-semibold">Tendencia</p>
          <h2 class="text-2xl font-black text-slate-900">Curva de producción</h2>
        </div>
      </div>
      <div class="mt-6 h-64">
        <Line :data="trendDataset" :options="trendOptions" />
      </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white/95 shadow-sm">
      <header class="flex items-center justify-between px-6 py-5 border-b border-slate-100">
        <div>
          <h2 class="text-xl font-black text-slate-900">Histórico detallado</h2>
          <p class="text-sm text-slate-500">
            Página {{ logsStore.meta.current_page }} de {{ logsStore.meta.last_page }}
          </p>
        </div>
        <button
          class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
          @click="logsStore.fetch(logsStore.meta.current_page)"
        >
          Actualizar
        </button>
      </header>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
              <th class="px-6 py-4">Fecha</th>
              <th class="px-6 py-4">Impresora</th>
              <th class="px-6 py-4 text-right">Color</th>
              <th class="px-6 py-4 text-right">ByN</th>
              <th class="px-6 py-4 text-right">Total</th>
              <th class="px-6 py-4 text-right">Diff. ByN</th>
              <th class="px-6 py-4 text-right">Diff. Color</th>
              <th class="px-6 py-4 text-right">Diff. Total</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="log in logsStore.items"
              :key="log.id"
              class="border-t border-slate-100 last:border-b"
            >
              <td class="px-6 py-4 font-semibold text-slate-900">
                {{ format(new Date(log.started_at), "dd 'de' MMMM HH:mm", { locale: es }) }}
              </td>
              <td class="px-6 py-4 text-slate-600">
                <div>{{ log.printer?.name ?? 'Impresora' }}</div>
                <div v-if="log.printer?.ip_address" class="text-xs text-slate-400">
                  IP: {{ log.printer.ip_address }}
                </div>
              </td>
              <td class="px-6 py-4 text-right text-rose-600 font-semibold">
                {{ new Intl.NumberFormat('es-ES').format(log.color_counter_total ?? 0) }}
              </td>
              <td class="px-6 py-4 text-right text-slate-600 font-semibold">
                {{ new Intl.NumberFormat('es-ES').format(log.bw_counter_total ?? 0) }}
              </td>
              <td class="px-6 py-4 text-right text-slate-900 font-bold">
                {{ new Intl.NumberFormat('es-ES').format((log.color_counter_total ?? 0) + (log.bw_counter_total ?? 0)) }}
              </td>
              <td class="px-6 py-4 text-right text-slate-500">
                <span v-if="log.diff_bw !== null && log.diff_bw !== undefined">
                  {{ new Intl.NumberFormat('es-ES').format(log.diff_bw) }}
                </span>
                <span v-else class="text-slate-400">—</span>
              </td>
              <td class="px-6 py-4 text-right text-rose-500">
                <span v-if="log.diff_color !== null && log.diff_color !== undefined">
                  {{ new Intl.NumberFormat('es-ES').format(log.diff_color) }}
                </span>
                <span v-else class="text-slate-400">—</span>
              </td>
              <td class="px-6 py-4 text-right text-slate-600 font-semibold">
                <span v-if="log.diff_total !== null && log.diff_total !== undefined">
                  {{ new Intl.NumberFormat('es-ES').format(log.diff_total) }}
                </span>
                <span v-else class="text-slate-400">—</span>
              </td>
            </tr>
            <tr v-if="!logsStore.items.length">
              <td colspan="8" class="px-6 py-8 text-center text-slate-500">
                No se han registrado impresiones en el intervalo seleccionado.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <footer class="flex items-center justify-between px-6 py-4 border-t border-slate-100 text-sm text-slate-500">
        <button
          class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
          :disabled="logsStore.meta.current_page === 1"
          @click="logsStore.fetch(logsStore.meta.current_page - 1)"
        >
          Anterior
        </button>
        <span>Página {{ logsStore.meta.current_page }} de {{ logsStore.meta.last_page }}</span>
        <button
          class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
          :disabled="logsStore.meta.current_page === logsStore.meta.last_page"
          @click="logsStore.fetch(logsStore.meta.current_page + 1)"
        >
          Siguiente
        </button>
      </footer>
    </section>
  </div>
</template>

