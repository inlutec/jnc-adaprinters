<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { usePrintersStore } from '@/stores/printers';
import { useConfigStore } from '@/stores/config';
import { useAppStore } from '@/stores/app';
import { api } from '@/services/httpClient';
import ConsumableLevel from '@/components/ConsumableLevel.vue';
import ConsumableBar from '@/components/ConsumableBar.vue';
import PrinterCustomFieldsModal from '@/components/PrinterCustomFieldsModal.vue';
import {
  TrashIcon,
  Cog6ToothIcon,
  XMarkIcon,
  CheckCircleIcon,
  XCircleIcon,
} from '@heroicons/vue/24/outline';

defineOptions({ name: 'PrintersView' });

const router = useRouter();
const printersStore = usePrintersStore();
const configStore = useConfigStore();
const appStore = useAppStore();

// Cargar preferencia de vista desde localStorage
const getStoredViewMode = (): 'cards' | 'list' => {
  const stored = localStorage.getItem('printers-view-mode');
  return (stored === 'cards' || stored === 'list') ? stored : 'cards';
};

const viewMode = ref<'cards' | 'list'>(getStoredViewMode());
const showFilters = ref(false);
const selectedPrinter = ref<any>(null);
const showDetailsModal = ref(false);
const showDeleteConfirm = ref(false);
const showCustomFieldsModal = ref(false);

const filters = ref({
  search: '',
  status: '',
  province_id: null as number | null,
  site_id: null as number | null,
  department_id: null as number | null,
});

// Guardar preferencia cuando cambie la vista
watch(viewMode, (newMode) => {
  localStorage.setItem('printers-view-mode', newMode);
});

onMounted(() => {
  printersStore.fetch();
  configStore.fetchProvinces();
  configStore.fetchSites();
  configStore.fetchDepartments();
  configStore.fetchSnmpSyncConfig();
  configStore.fetchCustomFields();
  
  // Escuchar eventos de sincronización para recargar datos
  window.addEventListener('printers-synced', () => {
    setTimeout(async () => {
      await printersStore.fetch();
      if (selectedPrinter.value) {
        await openDetails(selectedPrinter.value);
      }
      appStore.notify('Datos de impresoras actualizados', 'success');
    }, 2000); // Esperar 2 segundos para que los jobs terminen
  });
  
  // Escuchar evento para abrir detalles de impresora desde alertas
  window.addEventListener('open-printer-details', async (event: any) => {
    const printerId = event.detail?.printerId;
    if (printerId) {
      const printer = printersStore.printers.find((p: any) => p.id === printerId);
      if (printer) {
        await openDetails(printer);
      } else {
        // Si no está en la lista actual, cargarla desde la API
        try {
          const { data } = await api.get(`/printers/${printerId}`);
          await openDetails(data);
        } catch (error) {
          console.error('Error al cargar impresora:', error);
        }
      }
    }
  });
  
  // Función para abrir detalles de impresora por ID
  const openPrinterById = async (printerId: number) => {
    if (!printerId) return;
    
    // Buscar en la lista actual primero
    let printer = printersStore.printers.find((p: any) => p.id === printerId);
    if (!printer) {
      // Si no está, recargar la lista
      await printersStore.fetch();
      printer = printersStore.printers.find((p: any) => p.id === printerId);
    }
    
    if (printer) {
      await openDetails(printer);
    } else {
      // Si no está en la lista, cargarla desde la API directamente
      try {
        const { data } = await api.get(`/printers/${printerId}`);
        await openDetails(data);
      } catch (error) {
        console.error('Error al cargar impresora:', error);
      }
    }
  };
  
  // Watch para cambios en la ruta (cuando se navega desde otra vista)
  watch(() => router.currentRoute.value.query.printer, async (newPrinterId, oldPrinterId) => {
    if (newPrinterId && newPrinterId !== oldPrinterId) {
      const printerId = Number(newPrinterId);
      if (printerId) {
        await openPrinterById(printerId);
      }
    }
  }, { immediate: true });
});

const filteredPrinters = computed(() => {
  return printersStore.printers.filter((p) => {
    if (filters.value.search && !p.name.toLowerCase().includes(filters.value.search.toLowerCase())) return false;
    if (filters.value.status && p.status !== filters.value.status) return false;
    if (filters.value.province_id && p.province_id !== filters.value.province_id) return false;
    if (filters.value.site_id && p.site_id !== filters.value.site_id) return false;
    if (filters.value.department_id && p.department_id !== filters.value.department_id) return false;
    return true;
  });
});

const isOnline = (printer: any): boolean => {
  if (!printer.last_seen_at) return false;
  const lastSeen = new Date(printer.last_seen_at);
  const now = new Date();
  const diffMinutes = (now.getTime() - lastSeen.getTime()) / (1000 * 60);
  // Usar el intervalo de sincronización configurado, con un margen del 20% adicional
  // para evitar que se marque como offline justo antes de la próxima sincronización
  const syncInterval = configStore.snmpSyncConfig.auto_sync_frequency || 15;
  const maxOfflineMinutes = syncInterval * 1.2; // 20% de margen
  return diffMinutes < maxOfflineMinutes;
};

const getConsumables = (printer: any): any[] => {
  // Buscar consumibles en latest_snapshot primero, luego en snmp_data
  let consumablesData: any[] = [];
  
  if (printer.latest_snapshot?.consumables) {
    consumablesData = printer.latest_snapshot.consumables;
  } else if (printer.snmp_data?.consumables) {
    consumablesData = printer.snmp_data.consumables;
  } else {
    return [];
  }
  
  const colorMap: Record<string, string> = {
    black: 'black',
    negro: 'black',
    cyan: 'cyan',
    cian: 'cyan',
    magenta: 'magenta',
    yellow: 'yellow',
    amarillo: 'yellow',
  };
  
  const mapped = consumablesData.map((c: any) => {
    const name = (c.name || c.label || c.slot || '').toLowerCase();
    let color = c.color || 'black';
    
    // Detectar color por nombre
    if (!c.color) {
      for (const [key, value] of Object.entries(colorMap)) {
        if (name.includes(key)) {
          color = value;
          break;
        }
      }
    }
    
    // Priorizar nivel_porcentaje sobre level (nivel_porcentaje es el formato correcto)
    const level = Number(c.nivel_porcentaje ?? c.level ?? c.percentage ?? 0);
    
    return {
      level: isNaN(level) ? 0 : Math.max(0, Math.min(100, level)),
      color: color,
      label: c.label ?? c.name ?? c.slot ?? 'Consumible',
      slot: c.slot,
    };
  }).filter((c: any) => c.level > 0 || c.label);
  
  return mapped;
};

const openDetails = async (printer: any) => {
  try {
    const { data } = await api.get(`/printers/${printer.id}`);
    selectedPrinter.value = data;
    // Asegurar que installations esté disponible
    if (!selectedPrinter.value.installations) {
      selectedPrinter.value.installations = [];
    }
    showDetailsModal.value = true;
  } catch (error: any) {
    appStore.notify('Error al cargar detalles', 'error');
  }
};

const openPhoto = (url: string) => {
  window.open(url, '_blank');
};

const handleDelete = async () => {
  if (!selectedPrinter.value) return;
  try {
    await api.delete(`/printers/${selectedPrinter.value.id}`);
    appStore.notify('Impresora eliminada', 'success');
    showDeleteConfirm.value = false;
    showDetailsModal.value = false;
    await printersStore.fetch();
  } catch (error: any) {
    appStore.notify('Error al eliminar', 'error');
  }
};

const formatNumber = (num: number): string => {
  return new Intl.NumberFormat('es-ES').format(num);
};

const isNumeric = (value: any): boolean => {
  return !isNaN(parseFloat(value)) && isFinite(value);
};

const getTotalPages = (printer: any): number => {
  if (printer.latest_snapshot?.counters?.total_pages) {
    return printer.latest_snapshot.counters.total_pages;
  }
  if (printer.latest_snapshot?.total_pages) {
    return printer.latest_snapshot.total_pages;
  }
  // Buscar en snmp_data
  if (printer.snmp_data) {
    for (const [key, value] of Object.entries(printer.snmp_data)) {
      const keyLower = key.toLowerCase();
      if ((keyLower.includes('total') && keyLower.includes('pagina')) || keyLower.includes('total_de_paginas_impresas')) {
        return isNumeric(value) ? Number(value) : 0;
      }
    }
  }
  return 0;
};

const getColorPages = (printer: any): number => {
  if (printer.latest_snapshot?.counters?.color_pages) {
    return printer.latest_snapshot.counters.color_pages;
  }
  if (printer.latest_snapshot?.color_pages) {
    return printer.latest_snapshot.color_pages;
  }
  // Buscar en snmp_data
  if (printer.snmp_data) {
    for (const [key, value] of Object.entries(printer.snmp_data)) {
      const keyLower = key.toLowerCase();
      if (keyLower.includes('color') && keyLower.includes('pagina')) {
        return isNumeric(value) ? Number(value) : 0;
      }
    }
  }
  return 0;
};

const getBwPages = (printer: any): number => {
  if (printer.latest_snapshot?.counters?.bw_pages) {
    return printer.latest_snapshot.counters.bw_pages;
  }
  if (printer.latest_snapshot?.bw_pages) {
    return printer.latest_snapshot.bw_pages;
  }
  // Buscar en snmp_data
  if (printer.snmp_data) {
    for (const [key, value] of Object.entries(printer.snmp_data)) {
      const keyLower = key.toLowerCase();
      if ((keyLower.includes('monocromo') || (keyLower.includes('blanco') && keyLower.includes('negro'))) && keyLower.includes('pagina')) {
        return isNumeric(value) ? Number(value) : 0;
      }
    }
  }
  // Calcular como diferencia si tenemos total y color
  const total = getTotalPages(printer);
  const color = getColorPages(printer);
  return Math.max(0, total - color);
};

const getTableCustomFields = computed(() => {
  return configStore.customFields
    .filter((f: any) => f.entity_type === 'printer' && f.is_active && f.show_in_table)
    .sort((a: any, b: any) => a.table_order - b.table_order);
});

const getCustomFieldValue = (printer: any, fieldSlug: string): string => {
  if (!printer) return '—';
  if (!printer.custom_field_values || typeof printer.custom_field_values !== 'object') {
    return '—';
  }
  const value = printer.custom_field_values[fieldSlug];
  if (value === null || value === undefined || value === '') return '—';
  return String(value);
};

const getAllCustomFields = () => {
  return configStore.customFields
    .filter((f: any) => f.entity_type === 'printer' && f.is_active)
    .sort((a: any, b: any) => a.order - b.order);
};

</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-2xl font-bold text-slate-900">Impresoras</h2>
        <p class="text-sm text-slate-500">Gestión y monitoreo de impresoras</p>
      </div>
      <div class="flex gap-2">
        <button
          @click="showFilters = !showFilters"
          class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 text-sm font-semibold"
        >
          Filtros
        </button>
        <div class="flex rounded-lg border border-slate-300 overflow-hidden">
          <button
            @click="viewMode = 'cards'"
            class="px-4 py-2 text-sm font-semibold transition"
            :class="viewMode === 'cards' ? 'bg-ada-primary text-white' : 'text-slate-700 hover:bg-slate-50'"
          >
            Tarjetas
          </button>
          <button
            @click="viewMode = 'list'"
            class="px-4 py-2 text-sm font-semibold transition"
            :class="viewMode === 'list' ? 'bg-ada-primary text-white' : 'text-slate-700 hover:bg-slate-50'"
          >
            Lista
          </button>
        </div>
      </div>
    </div>

    <div v-if="showFilters" class="bg-white rounded-xl border border-slate-200 p-4 space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <input
          v-model="filters.search"
          type="text"
          placeholder="Buscar..."
          class="rounded-lg border border-slate-300 px-4 py-2"
        />
        <select v-model="filters.status" class="rounded-lg border border-slate-300 px-4 py-2">
          <option value="">Todos los estados</option>
          <option value="online">Online</option>
          <option value="offline">Offline</option>
          <option value="warning">Advertencia</option>
        </select>
        <select
          v-model="filters.province_id"
          @change="configStore.fetchSites(filters.province_id || undefined)"
          class="rounded-lg border border-slate-300 px-4 py-2"
        >
          <option :value="null">Todas las provincias</option>
          <option v-for="p in configStore.provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <select
          v-model="filters.site_id"
          @change="configStore.fetchDepartments(filters.site_id || undefined)"
          class="rounded-lg border border-slate-300 px-4 py-2"
        >
          <option :value="null">Todas las sedes</option>
          <option v-for="s in configStore.sites" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
      </div>
    </div>

    <!-- Vista Tarjetas -->
    <div v-if="viewMode === 'cards'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div
        v-for="printer in filteredPrinters"
        :key="printer.id"
        @click="openDetails(printer)"
        class="bg-white rounded-xl border border-slate-200 p-6 hover:shadow-lg transition cursor-pointer overflow-visible"
      >
        <div class="aspect-video bg-slate-100 rounded-lg mb-4 flex items-center justify-center overflow-hidden relative">
          <img
            v-if="(printer as any).photo_url || printer.photo_path"
            :src="(printer as any).photo_url || `/storage/${printer.photo_path}`"
            :alt="printer.name"
            class="max-w-full max-h-full object-contain"
          />
          <span v-else class="text-slate-400 text-sm">Sin foto</span>
          <div class="absolute top-2 right-2 flex gap-2">
            <div
              class="rounded-full p-1.5 shadow-lg"
              :class="isOnline(printer) ? 'bg-emerald-500' : 'bg-slate-400'"
            >
              <CheckCircleIcon v-if="isOnline(printer)" class="h-4 w-4 text-white" />
              <XCircleIcon v-else class="h-4 w-4 text-white" />
            </div>
          </div>
        </div>
        <h3 class="font-bold text-lg text-slate-900 mb-2">{{ printer.name }}</h3>
        <p class="text-sm text-slate-600 mb-4">{{ printer.ip_address }}</p>

        <!-- Campos personalizados en tarjetas -->
        <div v-if="getTableCustomFields.length > 0" class="mb-4 space-y-1">
          <div
            v-for="field in getTableCustomFields"
            :key="field.id"
            class="flex items-center justify-between text-xs"
          >
            <span class="text-slate-500 font-medium">{{ field.name }}:</span>
            <span class="text-slate-900 font-semibold">
              {{ getCustomFieldValue(printer, field.slug) }}
            </span>
          </div>
        </div>

        <!-- Consumibles -->
        <div class="mb-4" :style="{ minHeight: getConsumables(printer).length > 6 ? '200px' : '100px' }">
          <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Consumibles</p>
          <div v-if="getConsumables(printer).length > 0" class="flex gap-0.5 justify-center flex-wrap items-start w-full" style="max-width: 100%;">
            <ConsumableBar
              v-for="consumable in getConsumables(printer).slice(0, 6)"
              :key="consumable.slot || consumable.label"
              :level="Number(consumable.level)"
              :color="consumable.color"
              :label="consumable.label"
              size="small"
            />
          </div>
          <div v-else class="text-center py-2">
            <p class="text-xs text-slate-400 italic">
              {{ !isOnline(printer) ? 'Impresora offline' : 'Sin datos disponibles' }}
            </p>
          </div>
        </div>

        <div class="space-y-2">
          <div class="flex items-center justify-between text-sm">
            <span class="text-slate-600">Estado:</span>
            <span
              class="px-2 py-1 rounded text-xs font-semibold"
              :class="{
                'bg-emerald-100 text-emerald-700': isOnline(printer),
                'bg-slate-100 text-slate-700': !isOnline(printer),
              }"
            >
              {{ isOnline(printer) ? 'Online' : 'Offline' }}
            </span>
          </div>
          <div v-if="printer.site" class="text-sm text-slate-600">
            {{ printer.site.name }} {{ printer.department ? `· ${printer.department.name}` : '' }}
          </div>
        </div>
      </div>
    </div>

    <!-- Vista Lista -->
    <div v-else class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="w-full">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Impresora</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">IP</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Consumibles</th>
            <th
              v-for="field in getTableCustomFields"
              :key="field.id"
              class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase"
            >
              {{ field.name }}
            </th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Ubicación</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Estado</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <tr
            v-for="printer in filteredPrinters"
            :key="printer.id"
            @click="openDetails(printer)"
            class="hover:bg-slate-50 cursor-pointer"
          >
            <td class="px-6 py-4">
              <div class="flex items-center gap-3">
                <img
                  v-if="(printer as any).photo_url || printer.photo_path"
                  :src="(printer as any).photo_url || `/storage/${printer.photo_path}`"
                  :alt="printer.name"
                  class="w-12 h-12 object-contain rounded"
                />
                <div>
                  <p class="font-semibold text-slate-900">{{ printer.name }}</p>
                  <p class="text-sm text-slate-500">{{ printer.brand }} {{ printer.model }}</p>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 text-sm text-slate-600">{{ printer.ip_address }}</td>
            <td class="px-6 py-4">
              <div v-if="isOnline(printer) && getConsumables(printer).length > 0" class="flex gap-0.5 flex-wrap items-start max-w-xl">
                <ConsumableBar
                  v-for="consumable in getConsumables(printer).slice(0, 6)"
                  :key="consumable.slot || consumable.label"
                  :level="Number(consumable.level)"
                  :color="consumable.color"
                  :label="consumable.label"
                  size="small"
                />
              </div>
              <span v-else class="text-sm text-slate-400">—</span>
            </td>
            <td
              v-for="field in getTableCustomFields"
              :key="field.id"
              class="px-6 py-4 text-sm text-slate-600"
            >
              {{ getCustomFieldValue(printer, field.slug) }}
            </td>
            <td class="px-6 py-4 text-sm text-slate-600">
              {{ printer.site?.name }} {{ printer.department ? `· ${printer.department.name}` : '' }}
            </td>
            <td class="px-6 py-4">
              <div class="flex items-center gap-2">
                <div
                  class="w-2 h-2 rounded-full"
                  :class="isOnline(printer) ? 'bg-emerald-500' : 'bg-slate-400'"
                ></div>
                <span
                  class="px-2 py-1 rounded text-xs font-semibold"
                  :class="{
                    'bg-emerald-100 text-emerald-700': isOnline(printer),
                    'bg-slate-100 text-slate-700': !isOnline(printer),
                  }"
                >
                  {{ isOnline(printer) ? 'Online' : 'Offline' }}
                </span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal de Detalles -->
    <div
      v-if="showDetailsModal && selectedPrinter"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      @click.self="showDetailsModal = false"
    >
      <div class="bg-white rounded-xl shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
          <h3 class="text-xl font-bold text-slate-900">{{ selectedPrinter.name }}</h3>
          <div class="flex gap-2">
            <button
              @click="showCustomFieldsModal = true"
              class="p-2 rounded-lg border border-slate-300 hover:bg-slate-50"
              title="Ajustes"
            >
              <Cog6ToothIcon class="h-5 w-5 text-slate-600" />
            </button>
            <button
              @click="showDeleteConfirm = true"
              class="p-2 rounded-lg border border-red-300 hover:bg-red-50"
              title="Eliminar"
            >
              <TrashIcon class="h-5 w-5 text-red-600" />
            </button>
            <button @click="showDetailsModal = false" class="p-2 rounded-lg hover:bg-slate-50">
              <XMarkIcon class="h-5 w-5 text-slate-600" />
            </button>
          </div>
        </div>

        <div class="p-6 space-y-6">
          <!-- Información básica -->
          <section>
            <h4 class="text-lg font-semibold mb-4">Información básica</h4>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <p class="text-sm text-slate-500">IP Address</p>
                <p class="font-semibold">{{ selectedPrinter.ip_address }}</p>
              </div>
              <div>
                <p class="text-sm text-slate-500">Estado</p>
                <div class="flex items-center gap-2">
                  <div
                    class="w-2 h-2 rounded-full"
                    :class="isOnline(selectedPrinter) ? 'bg-emerald-500' : 'bg-slate-400'"
                  ></div>
                  <span class="font-semibold">{{ isOnline(selectedPrinter) ? 'Online' : 'Offline' }}</span>
                </div>
              </div>
              <div v-if="selectedPrinter.brand">
                <p class="text-sm text-slate-500">Marca</p>
                <p class="font-semibold">{{ selectedPrinter.brand }}</p>
              </div>
              <div v-if="selectedPrinter.model">
                <p class="text-sm text-slate-500">Modelo</p>
                <p class="font-semibold">{{ selectedPrinter.model }}</p>
              </div>
            </div>
          </section>

          <!-- Consumibles -->
          <section v-if="getConsumables(selectedPrinter).length > 0">
            <h4 class="text-lg font-semibold mb-4">Niveles de consumibles</h4>
            <p class="text-sm text-slate-500 mb-6">
              Estado actual de todos los consumibles de impresión descubiertos (toners y cartuchos)
            </p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8">
              <ConsumableBar
                v-for="consumable in getConsumables(selectedPrinter)"
                :key="consumable.slot || consumable.label"
                :level="Number(consumable.level)"
                :color="consumable.color"
                :label="consumable.label"
                size="large"
              />
            </div>
            <div class="space-y-3 bg-slate-50 rounded-xl p-4">
              <ConsumableLevel
                v-for="consumable in getConsumables(selectedPrinter)"
                :key="consumable.slot || consumable.label"
                :level="Number(consumable.level)"
                :color="consumable.color"
                :label="consumable.label"
              />
            </div>
          </section>
          <section v-else>
            <div class="bg-slate-50 rounded-xl p-6 text-center">
              <p class="text-slate-400 italic">No hay datos de consumibles disponibles</p>
            </div>
          </section>

          <!-- Contadores de páginas -->
          <section>
            <h4 class="text-lg font-semibold mb-4">Contadores de impresión</h4>
            <p class="text-sm text-slate-500 mb-6">
              Total de páginas impresas desde el último reinicio o desde que se descubrió la impresora
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl p-6 border-2 border-slate-200 shadow-sm">
                <div class="flex items-center gap-3 mb-3">
                  <div class="w-12 h-12 bg-slate-200 rounded-lg flex items-center justify-center">
                    <span class="text-2xl">📄</span>
                  </div>
                  <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total</p>
                    <p class="text-3xl font-black text-slate-900">
                      {{ formatNumber(getTotalPages(selectedPrinter)) }}
                    </p>
                  </div>
                </div>
                <p class="text-sm text-slate-500">páginas impresas</p>
              </div>
              <div class="bg-gradient-to-br from-rose-50 to-rose-100 rounded-xl p-6 border-2 border-rose-200 shadow-sm">
                <div class="flex items-center gap-3 mb-3">
                  <div class="w-12 h-12 bg-rose-200 rounded-lg flex items-center justify-center">
                    <span class="text-2xl">🎨</span>
                  </div>
                  <div>
                    <p class="text-xs font-semibold text-rose-600 uppercase tracking-wide">Color</p>
                    <p class="text-3xl font-black text-rose-600">
                      {{ formatNumber(getColorPages(selectedPrinter)) }}
                    </p>
                  </div>
                </div>
                <p class="text-sm text-rose-500">páginas en color</p>
              </div>
              <div class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl p-6 border-2 border-slate-200 shadow-sm">
                <div class="flex items-center gap-3 mb-3">
                  <div class="w-12 h-12 bg-slate-200 rounded-lg flex items-center justify-center">
                    <span class="text-2xl">⚫</span>
                  </div>
                  <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Monocromo</p>
                    <p class="text-3xl font-black text-slate-900">
                      {{ formatNumber(getBwPages(selectedPrinter)) }}
                    </p>
                  </div>
                </div>
                <p class="text-sm text-slate-500">páginas B&W</p>
              </div>
            </div>
          </section>

          <!-- Historial de instalaciones -->
          <section v-if="selectedPrinter.installations && selectedPrinter.installations.length > 0">
            <h4 class="text-lg font-semibold mb-4">Historial de instalaciones</h4>
            <div class="space-y-4">
              <div
                v-for="installation in selectedPrinter.installations"
                :key="installation.id"
                class="bg-slate-50 rounded-lg p-4 border border-slate-200"
              >
                <div class="flex justify-between items-start mb-2">
                  <div>
                    <p class="font-semibold text-slate-900">
                      {{ installation.stock?.consumable?.name || 'Consumible' }}
                    </p>
                    <p class="text-sm text-slate-500">
                      {{ installation.stock?.consumable?.sku || '' }} · Cantidad: {{ installation.quantity }}
                    </p>
                  </div>
                  <p class="text-xs text-slate-500">
                    {{ new Date(installation.installed_at).toLocaleString('es-ES') }}
                  </p>
                </div>
                <p v-if="installation.observations" class="text-sm text-slate-600 mt-2">
                  {{ installation.observations }}
                </p>
                <div v-if="installation.photos && installation.photos.length > 0" class="flex gap-2 mt-3">
                  <img
                    v-for="photo in installation.photos"
                    :key="photo.id"
                    :src="`/storage/${photo.photo_path}`"
                    :alt="`Foto instalación ${installation.id}`"
                    class="w-20 h-20 object-cover rounded border border-slate-300 cursor-pointer hover:opacity-80"
                    @click="openPhoto(`/storage/${photo.photo_path}`)"
                  />
                </div>
                <p v-if="installation.installer" class="text-xs text-slate-400 mt-2">
                  Instalado por: {{ installation.installer.name }}
                </p>
              </div>
            </div>
          </section>

          <!-- Campos Personalizados -->
          <section v-if="getAllCustomFields().length > 0">
            <h4 class="text-lg font-semibold mb-4">Campos personalizados</h4>
            <div class="grid grid-cols-2 gap-4">
              <div
                v-for="field in getAllCustomFields()"
                :key="field.id"
                class="bg-slate-50 rounded-lg p-4 border border-slate-200"
              >
                <p class="text-sm text-slate-500 mb-1">{{ field.name }}</p>
                <p class="font-semibold text-slate-900">
                  {{ getCustomFieldValue(selectedPrinter, field.slug) }}
                </p>
              </div>
            </div>
          </section>

          <!-- OIDs descubiertos -->
          <section>
            <h4 class="text-lg font-semibold mb-2">Datos SNMP descubiertos</h4>
            <p class="text-sm text-slate-500 mb-4">
              Todos los servicios y datos descubiertos durante el autodescubrimiento
            </p>
            <div v-if="selectedPrinter.snmp_data && Object.keys(selectedPrinter.snmp_data).length > 0" class="bg-slate-50 rounded-lg p-4 max-h-96 overflow-y-auto">
              <div class="space-y-2">
                <div
                  v-for="(value, key) in selectedPrinter.snmp_data"
                  :key="key"
                  class="flex items-start justify-between py-2 border-b border-slate-200 last:border-0 hover:bg-slate-100 rounded px-2 transition"
                >
                  <span class="text-xs font-mono text-slate-600 flex-1 break-all">{{ key }}</span>
                  <span class="text-sm font-semibold text-slate-900 ml-4 break-all text-right">
                    {{ typeof value === 'object' ? JSON.stringify(value) : String(value) }}
                  </span>
                </div>
              </div>
            </div>
            <div v-else class="text-sm text-slate-400 italic bg-slate-50 rounded-lg p-4">
              No hay datos SNMP disponibles
            </div>
          </section>
        </div>
      </div>
    </div>

    <!-- Confirmación de borrado -->
    <div
      v-if="showDeleteConfirm"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showDeleteConfirm = false"
    >
      <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-bold mb-4">Confirmar eliminación</h3>
        <p class="text-sm text-slate-600 mb-6">
          ¿Estás seguro de que quieres eliminar la impresora "{{ selectedPrinter?.name }}"? Esta acción no se puede
          deshacer.
        </p>
        <div class="flex gap-2">
          <button
            @click="handleDelete"
            class="flex-1 bg-red-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-red-700"
          >
            Eliminar
          </button>
          <button
            @click="showDeleteConfirm = false"
            class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50"
          >
            Cancelar
          </button>
        </div>
      </div>
    </div>

    <!-- Modal de Campos Personalizados -->
    <PrinterCustomFieldsModal
      :printer="selectedPrinter"
      :show="showCustomFieldsModal"
      @close="showCustomFieldsModal = false"
      @saved="async () => { await printersStore.fetch(); if (selectedPrinter) { const updated = printersStore.printers.find((p: any) => p.id === selectedPrinter.id); if (updated) { await openDetails(updated); } else { await openDetails(selectedPrinter); } } }"
    />
  </div>
</template>
