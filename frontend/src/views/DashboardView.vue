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
import { useDashboardStore } from '@/stores/dashboard';
import { usePrintersStore } from '@/stores/printers';
import { useAlertsStore } from '@/stores/alerts';
import { formatDistanceToNow, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';

Chart.register(LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip);

defineOptions({ name: 'DashboardView' });

const dashboard = useDashboardStore();
const printersStore = usePrintersStore();
const alertsStore = useAlertsStore();

onMounted(() => {
  dashboard.fetch();
  printersStore.fetch();
  alertsStore.fetch();
});

const cards = computed(() => [
  {
    label: 'Impresoras activas',
    value: dashboard.summary?.printers.online ?? 0,
    detail: `${dashboard.summary?.printers.total ?? 0} registradas`,
  },
  {
    label: 'Alertas abiertas',
    value: dashboard.summary?.alerts.open ?? 0,
    detail: `${dashboard.summary?.alerts.critical ?? 0} críticas`,
  },
  {
    label: 'Inventario disponible',
    value: dashboard.summary?.inventory.total_items ?? 0,
    detail: `${dashboard.summary?.inventory.low_stock ?? 0} en riesgo`,
  },
  {
    label: 'Volumen semanal',
    value: dashboard.summary?.printing.week ?? 0,
    detail: `${dashboard.summary?.printing.today ?? 0} hoy`,
  },
]);

const trendDataset = computed(() => ({
  labels: dashboard.printTrend.map((point) => point.date),
  datasets: [
    {
      label: 'Total impresiones',
      data: dashboard.printTrend.map((point) => point.value),
      fill: true,
      backgroundColor: 'rgba(0,168,89,0.12)',
      borderColor: '#00A859',
      borderWidth: 2,
      tension: 0.35,
      pointRadius: 0,
    },
  ],
}));

const trendOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        label: (context: any) => `${context.formattedValue} páginas`,
      },
      backgroundColor: '#0f172a',
    },
  },
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

const statusLegend = computed(() => Object.entries(dashboard.statusBreakdown));

const relativeTime = (date?: string) => {
  if (!date) return '—';
  return formatDistanceToNow(parseISO(date), { addSuffix: true, locale: es });
};
</script>

<template>
  <div class="space-y-8">
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <article
        v-for="card in cards"
        :key="card.label"
        class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm backdrop-blur-lg"
      >
        <p class="text-xs uppercase tracking-[0.3em] text-slate-400 font-semibold">
          {{ card.label }}
        </p>
        <p class="mt-3 text-4xl font-black text-slate-900">
          {{ new Intl.NumberFormat('es-ES').format(card.value) }}
        </p>
        <p class="mt-1 text-sm font-medium text-ada-primary">{{ card.detail }}</p>
      </article>
    </section>

    <section class="grid gap-6 xl:grid-cols-3">
      <article class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm backdrop-blur-lg xl:col-span-2">
        <div class="flex items-center justify-between flex-wrap gap-4">
          <div>
            <p class="text-xs uppercase tracking-[0.3em] text-slate-500 font-semibold">
              Evolución
            </p>
            <h3 class="text-2xl font-black text-slate-900">Impresiones semanales</h3>
          </div>
          <span class="rounded-full bg-ada-light px-4 py-1 text-xs font-semibold text-ada-primary">
            {{ relativeTime(dashboard.lastUpdated ?? undefined) }}
          </span>
        </div>
        <div class="mt-6 h-64">
          <Line :data="trendDataset" :options="trendOptions" />
        </div>
      </article>

      <article class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm backdrop-blur-lg">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs uppercase tracking-[0.3em] text-slate-500 font-semibold">Estado</p>
            <h3 class="text-xl font-black text-slate-900">Monitor SNMP</h3>
          </div>
        </div>
        <ul class="mt-6 space-y-3">
          <li
            v-for="[status, value] in statusLegend"
            :key="status"
            class="flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-3"
          >
            <div class="flex items-center gap-2">
              <span
                class="h-2.5 w-2.5 rounded-full"
                :class="{
                  'bg-emerald-500': status === 'online',
                  'bg-amber-400': status === 'warning',
                  'bg-rose-500': status === 'offline',
                  'bg-slate-400': !['online', 'warning', 'offline'].includes(status),
                }"
              />
              <p class="text-sm font-semibold capitalize text-slate-900">{{ status }}</p>
            </div>
            <p class="text-sm font-bold text-slate-900">{{ value }}</p>
          </li>
          <li v-if="!statusLegend.length" class="text-sm text-slate-500">Sin datos disponibles.</li>
        </ul>
      </article>
    </section>

    <section class="grid gap-6 xl:grid-cols-3">
      <article class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm backdrop-blur-lg xl:col-span-2">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs uppercase tracking-[0.3em] text-slate-500 font-semibold">Flota</p>
            <h3 class="text-2xl font-black text-slate-900">Top impresoras activas</h3>
          </div>
          <button class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600">
            Ver catálogo
          </button>
        </div>
        <div class="mt-6 overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="text-left text-slate-500">
                <th class="px-4 py-2 font-semibold">Equipo</th>
                <th class="px-4 py-2 font-semibold">Ubicación</th>
                <th class="px-4 py-2 font-semibold">Estado</th>
                <th class="px-4 py-2 font-semibold text-right">Volumen</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="printer in dashboard.topPrinters"
                :key="printer.id"
                class="border-b border-slate-100 last:border-none"
              >
                <td class="px-4 py-3 font-semibold text-slate-900">{{ printer.name }}</td>
                <td class="px-4 py-3 text-slate-500">{{ printer.site ?? '—' }}</td>
                <td class="px-4 py-3">
                  <span
                    class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                    :class="{
                      'bg-emerald-50 text-emerald-600': printer.status === 'online',
                      'bg-amber-50 text-amber-600': printer.status === 'warning',
                      'bg-rose-50 text-rose-600': printer.status === 'offline',
                    }"
                  >
                    {{ printer.status }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right font-bold text-slate-900">
                  {{ new Intl.NumberFormat('es-ES').format(printer.weekly_prints ?? 0) }}
                </td>
              </tr>
              <tr v-if="!dashboard.topPrinters.length">
                <td colspan="4" class="px-4 py-6 text-center text-slate-500">Sin datos suficientes.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>

      <article class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm backdrop-blur-lg">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs uppercase tracking-[0.3em] text-slate-500 font-semibold">Alertas</p>
            <h3 class="text-xl font-black text-slate-900">Eventos recientes</h3>
          </div>
          <button class="text-xs font-semibold uppercase tracking-wide text-ada-primary">
            Centro
          </button>
        </div>
        <div class="mt-6 space-y-4">
          <article
            v-for="alert in dashboard.latestAlerts"
            :key="alert.id"
            class="rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-3"
          >
            <div class="flex items-center justify-between gap-3">
              <div>
                <p class="font-semibold text-slate-900">{{ alert.title }}</p>
                <p class="text-xs text-slate-500">
                  {{ alert.printer ?? 'Impresora' }} · {{ relativeTime(alert.created_at) }}
                </p>
              </div>
              <span
                class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide"
                :class="{
                  'bg-rose-50 text-rose-600': ['critical', 'high'].includes(alert.severity),
                  'bg-amber-50 text-amber-600': alert.severity === 'medium',
                  'bg-slate-100 text-slate-600': alert.severity === 'low',
                }"
              >
                {{ alert.severity }}
              </span>
            </div>
          </article>
          <p v-if="!dashboard.latestAlerts.length" class="text-sm text-slate-500">Sin alertas recientes.</p>
        </div>
      </article>
    </section>

    <section class="grid gap-6 lg:grid-cols-2">
      <article class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm backdrop-blur-lg">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs uppercase tracking-[0.3em] text-slate-500 font-semibold">Inventario</p>
            <h3 class="text-2xl font-black text-slate-900">Consumibles en riesgo</h3>
          </div>
          <span class="text-xs font-semibold uppercase tracking-wide text-rose-500">
            {{ dashboard.lowStock.length }} alertas
          </span>
        </div>
        <div class="mt-6 space-y-3">
          <article
            v-for="item in dashboard.lowStock"
            :key="item.id"
            class="rounded-2xl border border-rose-100 bg-rose-50/60 px-4 py-3"
          >
            <div class="flex items-center justify-between">
              <div>
                <p class="font-semibold text-slate-900">{{ item.consumable ?? 'Consumible' }}</p>
                <p class="text-xs text-slate-500">{{ item.site ?? 'Almacén general' }}</p>
              </div>
              <p class="text-sm font-bold text-rose-600">
                {{ item.quantity }} / {{ item.minimum }}
              </p>
            </div>
          </article>
          <p v-if="!dashboard.lowStock.length" class="text-sm text-slate-500">Inventario estable.</p>
        </div>
      </article>

      <article class="rounded-3xl border border-slate-200 bg-white/95 p-6 shadow-sm backdrop-blur-lg">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs uppercase tracking-[0.3em] text-slate-500 font-semibold">Cobertura</p>
            <h3 class="text-2xl font-black text-slate-900">Sedes monitorizadas</h3>
          </div>
          <span class="text-xs font-semibold uppercase tracking-wide text-ada-primary">
            {{ dashboard.siteHealth.length }} sedes
          </span>
        </div>
        <div class="mt-6 space-y-3">
          <article
            v-for="site in dashboard.siteHealth"
            :key="site.id"
            class="rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-3"
          >
            <div class="flex items-center justify-between">
              <div>
                <p class="font-semibold text-slate-900">{{ site.name }}</p>
                <p class="text-xs text-slate-500">{{ site.province ?? '—' }}</p>
              </div>
              <div class="text-right">
                <p class="text-sm font-bold text-slate-900">{{ site.online }}/{{ site.printers }}</p>
                <p class="text-xs text-slate-500">Online</p>
              </div>
            </div>
          </article>
          <p v-if="!dashboard.siteHealth.length" class="text-sm text-slate-500">Sin datos de sedes.</p>
        </div>
      </article>
    </section>
  </div>
</template>

