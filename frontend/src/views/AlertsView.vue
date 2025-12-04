<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAlertsStore } from '@/stores/alerts';

defineOptions({ name: 'AlertsView' });

const router = useRouter();
const alerts = useAlertsStore();
const selectedStatus = ref('');
const selectedSeverity = ref('');

onMounted(() => {
  alerts.fetch();
});

const goToPrinterDetails = async (printerId: number) => {
  // Navegar a la vista de impresoras y usar query param para abrir el modal
  await router.push({ name: 'printers', query: { printer: printerId } });
};

const statuses = [
  { label: 'Todas', value: '' },
  { label: 'Abiertas', value: 'open' },
  { label: 'Reconocidas', value: 'acknowledged' },
  { label: 'Resueltas', value: 'resolved' },
];

const severities = [
  { label: 'Todas', value: '' },
  { label: 'Críticas', value: 'critical' },
  { label: 'Altas', value: 'high' },
  { label: 'Medias', value: 'medium' },
  { label: 'Bajas', value: 'low' },
];

const badgeForSeverity = (severity: string) => {
  if (['critical', 'high'].includes(severity)) return 'bg-rose-50 text-rose-600';
  if (severity === 'medium') return 'bg-amber-50 text-amber-600';
  return 'bg-slate-100 text-slate-600';
};

const badgeForStatus = (status: string) => {
  if (status === 'open') return 'bg-rose-50 text-rose-600';
  if (status === 'acknowledged') return 'bg-amber-50 text-amber-600';
  return 'bg-emerald-50 text-emerald-600';
};

const applyFilters = () => {
  alerts.filters.status = selectedStatus.value;
  alerts.filters.severity = selectedSeverity.value;
  alerts.fetch();
};

const deleteAlert = async (id: number) => {
  if (!confirm('¿Estás seguro de eliminar esta alerta?')) return;
  try {
    await alerts.deleteAlert(id);
  } catch (error: any) {
    console.error('Error al eliminar alerta:', error);
  }
};
</script>

<template>
  <div class="space-y-8">
    <section class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-[0.3em] text-slate-500 font-semibold">
          Centro de alertas
        </p>
        <h1 class="text-3xl font-black text-slate-900">Eventos y telemetría</h1>
        <p class="text-sm text-slate-500">Monitoriza incidencias SNMP, inventario y mantenimiento.</p>
      </div>
      <div class="flex items-center gap-3">
        <select
          v-model="selectedStatus"
          class="rounded-2xl border border-slate-200 px-3 py-2 text-sm"
          @change="applyFilters"
        >
          <option v-for="status in statuses" :key="status.value" :value="status.value">
            {{ status.label }}
          </option>
        </select>
        <select
          v-model="selectedSeverity"
          class="rounded-2xl border border-slate-200 px-3 py-2 text-sm"
          @change="applyFilters"
        >
          <option v-for="severity in severities" :key="severity.value" :value="severity.value">
            {{ severity.label }}
          </option>
        </select>
      </div>
    </section>

    <section class="rounded-3xl border border-slate-200 bg-white/95 shadow-sm">
      <header class="flex items-center justify-between px-6 py-5 border-b border-slate-100">
        <div>
          <h2 class="text-xl font-black text-slate-900">Cronología</h2>
          <p class="text-sm text-slate-500">
            {{ alerts.meta.total }} registros · Página {{ alerts.meta.current_page }}
          </p>
        </div>
        <button
          class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
          @click="alerts.fetch(alerts.meta.current_page)"
        >
          Refrescar
        </button>
      </header>

      <div class="divide-y divide-slate-100">
        <article
          v-for="alert in alerts.items"
          :key="alert.id"
          class="flex flex-wrap items-center justify-between gap-4 px-6 py-5"
        >
          <div>
            <div class="flex items-center gap-3">
              <span class="text-xs font-semibold uppercase tracking-wide" :class="badgeForSeverity(alert.severity)">
                {{ alert.severity }}
              </span>
              <span class="text-xs font-semibold uppercase tracking-wide" :class="badgeForStatus(alert.status)">
                {{ alert.status }}
              </span>
            </div>
            <p class="mt-2 text-base font-semibold text-slate-900">
              {{ alert.title }}
            </p>
            <p class="text-sm text-slate-500">
              {{ (alert as any).printer?.name ?? 'Impresora' }}
              <span v-if="(alert as any).printer?.ip_address"> · IP: {{ (alert as any).printer.ip_address }}</span>
              <span v-if="(alert as any).printer?.site?.name"> · {{ (alert as any).printer.site.name }}</span>
              <span v-else-if="alert.site?.name"> · {{ alert.site.name }}</span>
            </p>
            <p class="text-xs text-slate-400">{{ new Date(alert.created_at).toLocaleString('es-ES') }}</p>
          </div>
          <div class="flex items-center gap-2">
            <button
              v-if="(alert as any).printer_id"
              class="rounded-full border border-blue-200 px-4 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-50"
              @click="goToPrinterDetails((alert as any).printer_id)"
            >
              Ver detalles
            </button>
            <button
              v-if="alert.status === 'open'"
              class="rounded-full border border-amber-200 px-4 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-50"
              @click="alerts.updateStatus(alert.id, 'acknowledged')"
            >
              Reconocer
            </button>
            <button
              v-if="alert.status !== 'resolved'"
              class="rounded-full border border-emerald-200 px-4 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"
              @click="alerts.updateStatus(alert.id, 'resolved')"
            >
              Resolver
            </button>
            <button
              v-if="alert.status === 'acknowledged'"
              class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
              @click="alerts.updateStatus(alert.id, 'open')"
            >
              Dejar pendiente
            </button>
            <button
              class="rounded-full border border-red-200 px-4 py-2 text-xs font-semibold text-red-700 hover:bg-red-50"
              @click="deleteAlert(alert.id)"
            >
              Borrar
            </button>
          </div>
        </article>

        <p v-if="!alerts.items.length" class="px-6 py-10 text-center text-slate-500">
          No se encontraron alertas con los filtros actuales.
        </p>
      </div>

      <footer class="flex items-center justify-between px-6 py-4 border-t border-slate-100 text-sm text-slate-500">
        <button
          class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
          :disabled="alerts.meta.current_page === 1"
          @click="alerts.fetch(alerts.meta.current_page - 1)"
        >
          Anterior
        </button>
        <span>Página {{ alerts.meta.current_page }} de {{ alerts.meta.last_page }}</span>
        <button
          class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"
          :disabled="alerts.meta.current_page === alerts.meta.last_page"
          @click="alerts.fetch(alerts.meta.current_page + 1)"
        >
          Siguiente
        </button>
      </footer>
    </section>
  </div>
</template>

