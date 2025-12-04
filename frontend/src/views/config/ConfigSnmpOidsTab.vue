<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useConfigStore } from '@/stores/config';
import { useAppStore } from '@/stores/app';
import { api } from '@/services/httpClient';

defineOptions({ name: 'ConfigSnmpOidsTab' });

const configStore = useConfigStore();
const appStore = useAppStore();
const showForm = ref(false);
const editingOid = ref<any>(null);
const category = ref('');
const syncing = ref(false);
const syncConfig = ref({
  auto_sync_enabled: true,
  auto_sync_frequency: 15,
});
const loadingConfig = ref(false);
const savingConfig = ref(false);
const syncHistory = ref<any[]>([]);
const loadingHistory = ref(false);

const form = ref({
  oid: '',
  name: '',
  description: '',
  category: 'consumable',
  data_type: 'string',
  unit: '',
  color: '',
});

const categories = [
  { value: 'consumable', label: 'Consumible' },
  { value: 'counter', label: 'Contador' },
  { value: 'status', label: 'Estado' },
  { value: 'environment', label: 'Ambiente' },
  { value: 'system', label: 'Sistema' },
];

onMounted(() => {
  configStore.fetchSnmpOids();
  fetchSyncConfig();
  fetchSyncHistory();
});

const fetchSyncConfig = async () => {
  loadingConfig.value = true;
  try {
    const { data } = await api.get('/config/snmp-sync/config');
    syncConfig.value = data;
  } catch (error: any) {
    appStore.notify('Error al cargar configuración de sincronización', 'error');
  } finally {
    loadingConfig.value = false;
  }
};

const syncAllPrinters = async () => {
  if (!confirm('¿Estás seguro de sincronizar todas las impresoras? Esto puede tardar varios minutos.')) {
    return;
  }
  
  syncing.value = true;
  try {
    const { data } = await api.post('/config/snmp-sync/sync-all');
    appStore.notify(`Se encolaron ${data.dispatched} impresoras para sincronización. Los datos se actualizarán en breve.`, 'success');
    
    // Esperar un poco y luego recargar el historial periódicamente
    await fetchSyncHistory();
    
    // Polling para actualizar el historial cada 3 segundos durante 120 segundos
    let attempts = 0;
    const maxAttempts = 40;
    const pollInterval = setInterval(async () => {
      attempts++;
      await fetchSyncHistory();
      
      // Verificar si la sincronización ha terminado
      const latestHistory = syncHistory.value[0];
      if (latestHistory && (latestHistory.status === 'completed' || latestHistory.status === 'failed')) {
        clearInterval(pollInterval);
        syncing.value = false;
        
        // Esperar un poco más para que los jobs terminen de procesarse
        setTimeout(() => {
          appStore.notify('Sincronización completada. Recargando datos de impresoras...', 'success');
          // Emitir evento para que otras vistas recarguen los datos
          window.dispatchEvent(new CustomEvent('printers-synced'));
        }, 3000);
      } else if (attempts >= maxAttempts) {
        clearInterval(pollInterval);
        syncing.value = false;
        appStore.notify('Sincronización en progreso. Recarga la página manualmente para ver los datos actualizados.', 'info');
        window.dispatchEvent(new CustomEvent('printers-synced'));
      }
    }, 3000);
    
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al sincronizar impresoras', 'error');
    syncing.value = false;
  }
};

const fetchSyncHistory = async () => {
  loadingHistory.value = true;
  try {
    const { data } = await api.get('/config/snmp-sync/history', {
      params: { limit: 10 },
    });
    syncHistory.value = data;
  } catch (error: any) {
    appStore.notify('Error al cargar historial de sincronizaciones', 'error');
  } finally {
    loadingHistory.value = false;
  }
};

const formatDate = (date: string): string => {
  if (!date) return '—';
  return new Date(date).toLocaleString('es-ES', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const getStatusColor = (status: string): string => {
  const colors: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800',
    running: 'bg-blue-100 text-blue-800',
    completed: 'bg-emerald-100 text-emerald-800',
    failed: 'bg-red-100 text-red-800',
  };
  return colors[status] || 'bg-slate-100 text-slate-800';
};

const getStatusLabel = (status: string): string => {
  const labels: Record<string, string> = {
    pending: 'Pendiente',
    running: 'En ejecución',
    completed: 'Completada',
    failed: 'Fallida',
  };
  return labels[status] || status;
};

const getTypeLabel = (type: string): string => {
  return type === 'manual' ? 'Manual' : 'Automática';
};

const getDuration = (start: string, end: string): string => {
  if (!start || !end) return '—';
  const startDate = new Date(start);
  const endDate = new Date(end);
  const diffMs = endDate.getTime() - startDate.getTime();
  const diffSeconds = Math.floor(diffMs / 1000);
  
  if (diffSeconds < 60) {
    return `${diffSeconds}s`;
  }
  
  const diffMinutes = Math.floor(diffSeconds / 60);
  if (diffMinutes < 60) {
    return `${diffMinutes}m`;
  }
  
  const diffHours = Math.floor(diffMinutes / 60);
  const remainingMinutes = diffMinutes % 60;
  return `${diffHours}h ${remainingMinutes}m`;
};

const saveSyncConfig = async () => {
  savingConfig.value = true;
  try {
    await api.put('/config/snmp-sync/config', syncConfig.value);
    appStore.notify('Configuración de sincronización guardada', 'success');
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al guardar configuración', 'error');
  } finally {
    savingConfig.value = false;
  }
};

const getFrequencyLabel = (minutes: number): string => {
  if (minutes < 60) {
    return `Cada ${minutes} ${minutes === 1 ? 'minuto' : 'minutos'}`;
  }
  const hours = Math.floor(minutes / 60);
  const remainingMinutes = minutes % 60;
  if (remainingMinutes === 0) {
    return `Cada ${hours} ${hours === 1 ? 'hora' : 'horas'}`;
  }
  return `Cada ${hours}h ${remainingMinutes}m`;
};

const filteredOids = computed(() => {
  if (!category.value) return configStore.snmpOids;
  return configStore.snmpOids.filter((o) => o.category === category.value);
});

const openForm = (oid?: any) => {
  if (oid) {
    editingOid.value = oid;
    form.value = { ...oid };
  } else {
    editingOid.value = null;
    form.value = {
      oid: '',
      name: '',
      description: '',
      category: 'consumable',
      data_type: 'string',
      unit: '',
      color: '',
    };
  }
  showForm.value = true;
};

const saveOid = async () => {
  try {
    if (editingOid.value) {
      await configStore.updateSnmpOid(editingOid.value.id, form.value);
    } else {
      await configStore.createSnmpOid(form.value);
    }
    appStore.notify('OID guardado correctamente', 'success');
    showForm.value = false;
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al guardar OID', 'error');
  }
};

const deleteOid = async (id: number) => {
  if (!confirm('¿Estás seguro de eliminar este OID?')) return;
  try {
    await configStore.deleteSnmpOid(id);
    appStore.notify('OID eliminado', 'success');
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al eliminar OID', 'error');
  }
};
</script>

<template>
  <div class="space-y-6">
    <!-- Configuración de Sincronización -->
    <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl border-2 border-blue-200 p-6">
      <h3 class="text-lg font-bold text-slate-900 mb-4 flex items-center gap-2">
        <span>🔄</span>
        Sincronización SNMP
      </h3>
      
      <div class="space-y-4">
        <!-- Sincronización Manual -->
        <div class="bg-white rounded-lg border border-slate-200 p-4">
          <div class="flex items-center justify-between mb-3">
            <div>
              <h4 class="font-semibold text-slate-900">Sincronización Manual</h4>
              <p class="text-sm text-slate-600">Sincroniza todas las impresoras ahora mismo</p>
            </div>
            <button
              @click="syncAllPrinters"
              :disabled="syncing"
              class="bg-ada-primary text-white px-6 py-2 rounded-lg font-semibold hover:bg-ada-dark disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <span v-if="syncing" class="animate-spin">⏳</span>
              <span v-else>🔄</span>
              {{ syncing ? 'Sincronizando...' : 'Sincronizar Todas' }}
            </button>
          </div>
        </div>

        <!-- Configuración de Sincronización Automática -->
        <div class="bg-white rounded-lg border border-slate-200 p-4">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h4 class="font-semibold text-slate-900">Sincronización Automática</h4>
              <p class="text-sm text-slate-600">Configura la frecuencia de sincronización automática</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input
                v-model="syncConfig.auto_sync_enabled"
                type="checkbox"
                class="sr-only peer"
                @change="saveSyncConfig"
              />
              <div
                class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-ada-primary/20 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-ada-primary"
              ></div>
            </label>
          </div>

          <div v-if="syncConfig.auto_sync_enabled" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">
                Frecuencia de sincronización (en minutos)
              </label>
              <div class="flex items-center gap-4">
                <input
                  v-model.number="syncConfig.auto_sync_frequency"
                  type="number"
                  min="1"
                  max="1440"
                  step="1"
                  class="w-32 rounded-lg border border-slate-300 px-4 py-2 text-lg font-semibold text-ada-primary focus:outline-none focus:ring-2 focus:ring-ada-primary focus:border-transparent"
                  @input="saveSyncConfig"
                  placeholder="15"
                />
                <div class="flex-1">
                  <p class="text-sm font-medium text-slate-700">
                    {{ getFrequencyLabel(syncConfig.auto_sync_frequency) }}
                  </p>
                  <p class="text-xs text-slate-500 mt-1">
                    La sincronización se ejecutará automáticamente cada {{ syncConfig.auto_sync_frequency }} {{ syncConfig.auto_sync_frequency === 1 ? 'minuto' : 'minutos' }}
                  </p>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-sm text-slate-500 italic">
            La sincronización automática está deshabilitada
          </div>
        </div>
      </div>
    </div>

    <!-- Historial de Sincronizaciones -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-lg font-bold text-slate-900">Historial de Sincronizaciones</h3>
          <p class="text-sm text-slate-600">Últimas sincronizaciones ejecutadas</p>
        </div>
        <button
          @click="fetchSyncHistory"
          :disabled="loadingHistory"
          class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-50 flex items-center gap-2"
        >
          <span v-if="loadingHistory" class="animate-spin">⏳</span>
          <span v-else>🔄</span>
          Actualizar
        </button>
      </div>

      <div v-if="loadingHistory" class="text-center py-8 text-slate-500">
        Cargando historial...
      </div>

      <div v-else-if="syncHistory.length === 0" class="text-center py-8 text-slate-500 italic">
        No hay historial de sincronizaciones aún
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="entry in syncHistory"
          :key="entry.id"
          class="border border-slate-200 rounded-lg p-4 hover:bg-slate-50 transition-colors"
        >
          <div class="flex items-start justify-between mb-3">
            <div class="flex items-center gap-3">
              <span
                class="px-3 py-1 rounded-full text-xs font-semibold"
                :class="getStatusColor(entry.status)"
              >
                {{ getStatusLabel(entry.status) }}
              </span>
              <span
                class="px-3 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700"
              >
                {{ getTypeLabel(entry.type) }}
              </span>
            </div>
            <div class="text-right text-sm text-slate-600">
              <p class="font-semibold">{{ formatDate(entry.started_at) }}</p>
              <p v-if="entry.completed_at" class="text-xs">
                Duración: {{ getDuration(entry.started_at, entry.completed_at) }}
              </p>
            </div>
          </div>

          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
            <div class="bg-slate-50 rounded-lg p-3">
              <p class="text-xs text-slate-500 mb-1">Total</p>
              <p class="text-lg font-bold text-slate-900">{{ entry.total_printers }}</p>
            </div>
            <div class="bg-blue-50 rounded-lg p-3">
              <p class="text-xs text-blue-600 mb-1">Encoladas</p>
              <p class="text-lg font-bold text-blue-900">{{ entry.dispatched }}</p>
            </div>
            <div class="bg-emerald-50 rounded-lg p-3">
              <p class="text-xs text-emerald-600 mb-1">Completadas</p>
              <p class="text-lg font-bold text-emerald-900">{{ entry.completed || 0 }}</p>
            </div>
            <div class="bg-red-50 rounded-lg p-3">
              <p class="text-xs text-red-600 mb-1">Fallidas</p>
              <p class="text-lg font-bold text-red-900">{{ entry.failed || 0 }}</p>
            </div>
          </div>

          <div v-if="entry.error_message" class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg">
            <p class="text-xs font-semibold text-red-800 mb-1">Error:</p>
            <p class="text-sm text-red-700">{{ entry.error_message }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Gestión de OIDs -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h3 class="text-lg font-bold text-slate-900">Gestión de OIDs SNMP</h3>
          <p class="text-sm text-slate-600">Administra los OIDs utilizados para el descubrimiento SNMP</p>
        </div>
        <button
          @click="openForm()"
          class="bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark"
        >
          + Añadir OID
        </button>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium text-slate-700 mb-2">Filtrar por categoría</label>
        <select
          v-model="category"
          @change="configStore.fetchSnmpOids(category || undefined)"
          class="rounded-lg border border-slate-300 px-4 py-2"
        >
          <option value="">Todas</option>
          <option v-for="cat in categories" :key="cat.value" :value="cat.value">
            {{ cat.label }}
          </option>
        </select>
      </div>

      <div v-if="showForm" class="border border-slate-200 rounded-xl p-6 bg-slate-50 mt-4 mb-4">
      <h3 class="text-lg font-semibold mb-4">{{ editingOid ? 'Editar' : 'Nuevo' }} OID</h3>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">OID</label>
          <input
            v-model="form.oid"
            type="text"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
            placeholder="1.3.6.1.2.1.43.11.1.1.9.1.1"
            :disabled="!!editingOid"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Nombre</label>
          <input
            v-model="form.name"
            type="text"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
            placeholder="Nivel de toner negro"
          />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Categoría</label>
            <select v-model="form.category" class="w-full rounded-lg border border-slate-300 px-4 py-2">
              <option v-for="cat in categories" :key="cat.value" :value="cat.value">
                {{ cat.label }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Unidad</label>
            <input
              v-model="form.unit"
              type="text"
              class="w-full rounded-lg border border-slate-300 px-4 py-2"
              placeholder="%, páginas, etc."
            />
          </div>
        </div>
        <div class="flex gap-2">
          <button @click="saveOid" class="flex-1 bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold">
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
        v-for="oid in filteredOids"
        :key="oid.id"
        class="border border-slate-200 rounded-lg p-4 flex items-center justify-between hover:bg-slate-50"
      >
        <div>
          <p class="font-mono text-sm text-slate-600">{{ oid.oid }}</p>
          <p class="font-semibold text-slate-900">{{ oid.name }}</p>
          <p class="text-xs text-slate-500">{{ categories.find((c) => c.value === oid.category)?.label }}</p>
        </div>
        <div class="flex gap-2">
          <button
            v-if="!oid.is_system"
            @click="openForm(oid)"
            class="px-3 py-1.5 text-sm text-ada-primary bg-ada-light rounded-lg hover:bg-ada-primary/10"
          >
            Editar
          </button>
          <button
            v-if="!oid.is_system"
            @click="deleteOid(oid.id)"
            class="px-3 py-1.5 text-sm text-red-600 bg-red-50 rounded-lg hover:bg-red-100"
          >
            Eliminar
          </button>
          <span v-else class="px-3 py-1.5 text-xs text-slate-500 bg-slate-100 rounded-lg">Sistema</span>
        </div>
      </div>
    </div>
    </div>
  </div>
</template>

