<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { api } from '@/services/httpClient';
import { useAppStore } from '@/stores/app';
import { useConfigStore } from '@/stores/config';
import { usePrintersStore } from '@/stores/printers';

defineOptions({ name: 'ConfigSnmpDiscoveryTab' });

const appStore = useAppStore();
const configStore = useConfigStore();
const printersStore = usePrintersStore();
const ipRange = ref('');
const discovering = ref(false);
const discoveredData = ref<any>(null);
const showImportModal = ref(false);
const importForm = ref({
  name: '',
  province_id: null as number | null,
  site_id: null as number | null,
  department_id: null as number | null,
  custom_field_values: {} as Record<string, any>,
});
const importing = ref(false);

const wizardCustomFields = computed(() => {
  return configStore.customFields
    .filter((f: any) => f.entity_type === 'printer' && f.is_active && f.show_in_creation_wizard)
    .sort((a: any, b: any) => a.order - b.order);
});

onMounted(() => {
  configStore.fetchProvinces();
  configStore.fetchSites();
  configStore.fetchDepartments();
  configStore.fetchCustomFields();
});

const discover = async () => {
  if (!ipRange.value.trim()) {
    appStore.notify('Por favor, introduce una IP', 'error');
    return;
  }

  discovering.value = true;
  discoveredData.value = null;

  try {
    const response = await api.post('/printers/discover', {
      ip_range: ipRange.value,
    });

    if (response.data.error) {
      appStore.notify(response.data.error, 'error');
      return;
    }

    discoveredData.value = response.data.data;
    
    // Prellenar formulario de importación
    importForm.value.name = response.data.data.sys_descr || `Impresora ${ipRange.value}`;
    importForm.value.custom_field_values = {};
    
    if (response.data.data.exists) {
      appStore.notify('Esta impresora ya existe en la base de datos', 'info');
    } else {
      appStore.notify('Impresora descubierta correctamente. Revisa los datos y haz clic en "Importar" para añadirla.', 'success');
    }
  } catch (error: any) {
    appStore.notify(error.response?.data?.error || error.response?.data?.message || 'Error al descubrir impresora', 'error');
  } finally {
    discovering.value = false;
  }
};

const openImportModal = () => {
  if (discoveredData.value?.exists) {
    appStore.notify('Esta impresora ya existe en la base de datos', 'error');
    return;
  }
  showImportModal.value = true;
};

const importPrinter = async () => {
  if (!discoveredData.value) return;

  importing.value = true;
  try {
    await api.post('/printers/import-discovered', {
      ip_address: discoveredData.value.ip_address,
      sys_descr: discoveredData.value.sys_descr,
      oids: discoveredData.value.oids,
      name: importForm.value.name,
      province_id: importForm.value.province_id,
      site_id: importForm.value.site_id,
      department_id: importForm.value.department_id,
      custom_field_values: importForm.value.custom_field_values,
    });

    appStore.notify('Impresora importada correctamente', 'success');
    showImportModal.value = false;
    discoveredData.value = null;
    ipRange.value = '';
    await printersStore.fetch();
  } catch (error: any) {
    appStore.notify(error.response?.data?.error || error.response?.data?.message || 'Error al importar impresora', 'error');
  } finally {
    importing.value = false;
  }
};
</script>

<template>
  <div class="space-y-6">
    <div class="border border-slate-200 rounded-xl p-6 bg-slate-50">
      <h3 class="text-lg font-semibold text-slate-900 mb-4">Autodescubrimiento SNMP</h3>
      <p class="text-sm text-slate-600 mb-6">
        Introduce una IP única (ej: 10.64.130.12) o un rango (ej: 10.64.130.0/24) para descubrir impresoras automáticamente.
      </p>

      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">IP o rango</label>
          <input
            v-model="ipRange"
            type="text"
            placeholder="10.64.130.12 o 10.64.130.0/24"
            class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary"
          />
        </div>


        <button
          @click="discover"
          :disabled="discovering || !ipRange.trim()"
          class="w-full bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark disabled:opacity-50"
        >
          {{ discovering ? 'Descubriendo...' : 'Iniciar descubrimiento' }}
        </button>
      </div>
    </div>

    <div v-if="discoveredData" class="border border-slate-200 rounded-xl p-6 bg-white">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-slate-900">Impresora descubierta</h3>
        <button
          v-if="!discoveredData.exists"
          @click="openImportModal"
          class="bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold text-sm hover:bg-ada-dark"
        >
          Importar impresora
        </button>
        <span v-else class="px-3 py-1 text-xs bg-yellow-100 text-yellow-700 rounded-lg font-semibold">
          Ya existe
        </span>
      </div>

      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <p class="text-xs text-slate-500 uppercase font-semibold mb-1">IP Address</p>
            <p class="font-mono text-sm font-semibold">{{ discoveredData.ip_address }}</p>
          </div>
          <div>
            <p class="text-xs text-slate-500 uppercase font-semibold mb-1">Descripción del sistema</p>
            <p class="text-sm">{{ discoveredData.sys_descr || 'No disponible' }}</p>
          </div>
        </div>

        <div v-if="discoveredData.oids && Object.keys(discoveredData.oids).length > 0">
          <p class="text-xs text-slate-500 uppercase font-semibold mb-2">Datos SNMP descubiertos</p>
          <div class="bg-slate-50 rounded-lg p-4 max-h-96 overflow-y-auto">
            <div class="space-y-2">
              <div
                v-for="(value, key) in discoveredData.oids"
                :key="key"
                class="flex items-start justify-between py-2 border-b border-slate-200 last:border-0"
              >
                <span class="text-xs font-mono text-slate-600 flex-1">{{ key }}</span>
                <span class="text-sm font-semibold text-slate-900 ml-4">{{ value }}</span>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="text-sm text-slate-500 italic">
          No se encontraron datos SNMP adicionales
        </div>
      </div>
    </div>

    <!-- Modal de importación -->
    <div
      v-if="showImportModal && discoveredData"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showImportModal = false"
    >
      <div class="bg-white rounded-xl p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-semibold mb-4">Importar impresora descubierta</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Nombre</label>
            <input
              v-model="importForm.name"
              type="text"
              class="w-full rounded-lg border border-slate-300 px-4 py-2"
              placeholder="Nombre de la impresora"
            />
          </div>
          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Provincia</label>
              <select
                v-model="importForm.province_id"
                @change="configStore.fetchSites(importForm.province_id || undefined)"
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
              >
                <option :value="null">Seleccionar</option>
                <option v-for="p in configStore.provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Sede</label>
              <select
                v-model="importForm.site_id"
                @change="configStore.fetchDepartments(importForm.site_id || undefined)"
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
              >
                <option :value="null">Seleccionar</option>
                <option v-for="s in configStore.sites" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Departamento</label>
              <select v-model="importForm.department_id" class="w-full rounded-lg border border-slate-300 px-4 py-2">
                <option :value="null">Seleccionar</option>
                <option v-for="d in configStore.departments" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>
            </div>
          </div>
          <!-- Campos personalizados del asistente -->
          <div v-if="wizardCustomFields.length > 0" class="space-y-4 border-t border-slate-200 pt-4">
            <h4 class="text-sm font-semibold text-slate-700">Campos personalizados</h4>
            <div v-for="field in wizardCustomFields" :key="field.id" class="space-y-2">
              <label class="block text-sm font-medium text-slate-700">
                {{ field.name }}
                <span v-if="field.is_required" class="text-red-500">*</span>
              </label>
              <input
                v-if="field.type === 'text'"
                v-model="importForm.custom_field_values[field.slug]"
                type="text"
                :required="field.is_required"
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
              />
              <input
                v-else-if="field.type === 'number'"
                v-model.number="importForm.custom_field_values[field.slug]"
                type="number"
                :required="field.is_required"
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
              />
              <input
                v-else-if="field.type === 'date'"
                v-model="importForm.custom_field_values[field.slug]"
                type="date"
                :required="field.is_required"
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
              />
              <select
                v-else-if="field.type === 'select'"
                v-model="importForm.custom_field_values[field.slug]"
                :required="field.is_required"
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
              >
                <option :value="null">Seleccionar</option>
                <option v-for="option in (field.options || [])" :key="option" :value="option">{{ option }}</option>
              </select>
              <textarea
                v-else-if="field.type === 'textarea'"
                v-model="importForm.custom_field_values[field.slug]"
                :required="field.is_required"
                rows="3"
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
              />
              <div v-else-if="field.type === 'checkbox'" class="flex items-center gap-2">
                <input
                  v-model="importForm.custom_field_values[field.slug]"
                  type="checkbox"
                  class="rounded border-slate-300"
                />
                <span class="text-sm text-slate-600">{{ field.name }}</span>
              </div>
              <p v-if="field.help_text" class="text-xs text-slate-500">{{ field.help_text }}</p>
            </div>
          </div>
          <div class="flex gap-2">
            <button
              @click="importPrinter"
              :disabled="importing || !importForm.name.trim()"
              class="flex-1 bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark disabled:opacity-50"
            >
              {{ importing ? 'Importando...' : 'Importar' }}
            </button>
            <button
              @click="showImportModal = false"
              class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50"
            >
              Cancelar
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

