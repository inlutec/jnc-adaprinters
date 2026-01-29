<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { api } from '@/services/httpClient';
import { useAppStore } from '@/stores/app';
import { useConfigStore } from '@/stores/config';
import { usePrintersStore } from '@/stores/printers';

defineOptions({ name: 'ConfigPrinterImportTab' });

const appStore = useAppStore();
const configStore = useConfigStore();
const printersStore = usePrintersStore();

// Estados del asistente
const currentStep = ref(1);
const totalSteps = 4;
const csvFile = ref<File | null>(null);
const csvColumns = ref<string[]>([]);
const csvData = ref<any[]>([]);
const columnMapping = ref<Record<string, string>>({});
const oidProfileMode = ref<'single' | 'per-row'>('single');
const defaultOidProfileId = ref<number | null>(null);
const rowOidProfiles = ref<Record<number, number>>({});
const previewData = ref<any[]>([]);
const importing = ref(false);
const uploadProgress = ref(0);

// Opciones de mapeo
const mappingOptions = computed(() => {
  const options = [
    { value: '', label: '-- No mapear --' },
    { value: 'name', label: 'Nombre' },
    { value: 'ip_address', label: 'Dirección IP' },
    { value: 'hostname', label: 'Hostname' },
    { value: 'mac_address', label: 'Dirección MAC' },
    { value: 'serial_number', label: 'Número de Serie' },
    { value: 'brand', label: 'Marca' },
    { value: 'model', label: 'Modelo' },
    { value: 'firmware_version', label: 'Versión de Firmware' },
    { value: 'province_id', label: 'Provincia (ID o Nombre)' },
    { value: 'site_id', label: 'Sede (ID o Nombre)' },
    { value: 'department_id', label: 'Departamento (ID o Nombre)' },
    { value: 'notes', label: 'Notas' },
  ];

  // Agregar campos personalizados
  const customFields = configStore.customFields
    .filter((f: any) => f.entity_type === 'printer' && f.is_active)
    .map((f: any) => ({
      value: `custom_field:${f.slug}`,
      label: `${f.name} (Campo Personalizado)`,
    }));

  return [...options, ...customFields];
});

const oidProfiles = ref<any[]>([]);

onMounted(async () => {
  await configStore.fetchProvinces();
  await configStore.fetchSites();
  await configStore.fetchDepartments();
  await configStore.fetchCustomFields();
  await fetchOidProfiles();
});

const fetchOidProfiles = async () => {
  try {
    const { data } = await api.get('/snmp-oid-profiles');
    oidProfiles.value = data.filter((p: any) => p.is_active);
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al cargar perfiles OID', 'error');
  }
};

const handleFileSelect = (event: Event) => {
  const target = event.target as HTMLInputElement;
  if (target.files?.[0]) {
    csvFile.value = target.files[0];
  }
};

const uploadCsv = async () => {
  if (!csvFile.value) {
    appStore.notify('Por favor, selecciona un archivo CSV', 'error');
    return;
  }

  uploadProgress.value = 0;
  try {
    const formData = new FormData();
    formData.append('csv', csvFile.value);

    const { data } = await api.post('/printers/import/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (progressEvent) => {
        if (progressEvent.total) {
          uploadProgress.value = Math.round((progressEvent.loaded * 100) / progressEvent.total);
        }
      },
    });

    csvColumns.value = data.columns;
    csvData.value = data.rows;
    
    // Auto-mapear columnas comunes
    const autoMapping: Record<string, string> = {};
    csvColumns.value.forEach((col: string) => {
      const colLower = col.toLowerCase().trim();
      if (colLower.includes('nombre') || colLower.includes('name')) {
        autoMapping[col] = 'name';
      } else if (colLower.includes('ip') || colLower.includes('direccion')) {
        autoMapping[col] = 'ip_address';
      } else if (colLower.includes('hostname')) {
        autoMapping[col] = 'hostname';
      } else if (colLower.includes('mac')) {
        autoMapping[col] = 'mac_address';
      } else if (colLower.includes('serie') || colLower.includes('serial')) {
        autoMapping[col] = 'serial_number';
      } else if (colLower.includes('marca') || colLower.includes('brand')) {
        autoMapping[col] = 'brand';
      } else if (colLower.includes('modelo') || colLower.includes('model')) {
        autoMapping[col] = 'model';
      } else if (colLower.includes('provincia') || colLower.includes('province')) {
        autoMapping[col] = 'province_id';
      } else if (colLower.includes('sede') || colLower.includes('site')) {
        autoMapping[col] = 'site_id';
      } else if (colLower.includes('departamento') || colLower.includes('department')) {
        autoMapping[col] = 'department_id';
      }
    });
    columnMapping.value = autoMapping;

    appStore.notify(`CSV cargado correctamente. ${csvData.value.length} filas encontradas.`, 'success');
    currentStep.value = 2;
  } catch (error: any) {
    console.error('Error al cargar CSV:', error);
    const errorMessage = error.response?.data?.message || 
                        error.response?.data?.error || 
                        error.message || 
                        'Error al cargar el CSV';
    appStore.notify(errorMessage, 'error');
    
    // Si hay errores de validación, mostrarlos también
    if (error.response?.data?.errors) {
      console.error('Errores de validación:', error.response.data.errors);
    }
  }
};

const generatePreview = async () => {
  // Validar que IP esté mapeada
  const hasIp = Object.values(columnMapping.value).includes('ip_address');

  if (!hasIp) {
    appStore.notify('Debes mapear al menos la columna de Dirección IP', 'error');
    return;
  }

  try {
    const { data } = await api.post('/printers/import/preview', {
      rows: csvData.value,
      column_mapping: columnMapping.value,
    });

    previewData.value = data.preview;
    currentStep.value = 3;
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al generar la vista previa', 'error');
  }
};

const processImport = async () => {
  if (oidProfileMode.value === 'single' && !defaultOidProfileId.value) {
    appStore.notify('Debes seleccionar un perfil OID', 'error');
    return;
  }

  if (oidProfileMode.value === 'per-row') {
    const missingProfiles = previewData.value.filter((_, index: number) => !rowOidProfiles.value[index]);
    if (missingProfiles.length > 0) {
      appStore.notify('Debes seleccionar un perfil OID para todas las filas', 'error');
      return;
    }
  }

  importing.value = true;
  try {
    appStore.notify('Iniciando importación... Esto puede tardar unos minutos.', 'info');
    
    const { data } = await api.post('/printers/import/process', {
      rows: previewData.value,
      column_mapping: columnMapping.value,
      oid_profile_mode: oidProfileMode.value,
      default_oid_profile_id: defaultOidProfileId.value,
      row_oid_profiles: oidProfileMode.value === 'per-row' ? rowOidProfiles.value : null,
      sync_after_import: true,
    });

    // Calcular cuántas fueron omitidas por duplicados (sin errores)
    const skippedByDuplicates = data.skipped - (data.errors?.length || 0);
    const skippedByErrors = data.errors?.length || 0;
    
    let message = `✅ Importación completada:\n`;
    message += `• ${data.imported} impresoras importadas\n`;
    if (skippedByDuplicates > 0) {
      message += `• ${skippedByDuplicates} omitidas (ya existían en la base de datos)\n`;
    }
    if (skippedByErrors > 0) {
      message += `• ${skippedByErrors} con errores\n`;
    }
    if (data.synced) {
      message += `• Sincronización SNMP iniciada para las nuevas impresoras`;
    }
    
    if (data.errors && data.errors.length > 0) {
      console.error('Errores en la importación:', data.errors);
      appStore.notify(
        message + '\n\nRevisa la consola del navegador (F12) para ver los errores detallados.',
        data.imported > 0 ? 'info' : 'error'
      );
    } else {
      appStore.notify(message, 'success');
    }

    // Resetear todo
    resetWizard();
    await printersStore.fetch();
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al procesar la importación', 'error');
  } finally {
    importing.value = false;
  }
};

const resetWizard = () => {
  currentStep.value = 1;
  csvFile.value = null;
  csvColumns.value = [];
  csvData.value = [];
  columnMapping.value = {};
  oidProfileMode.value = 'single';
  defaultOidProfileId.value = null;
  rowOidProfiles.value = {};
  previewData.value = [];
  uploadProgress.value = 0;
  const input = document.getElementById('csv-file') as HTMLInputElement;
  if (input) input.value = '';
};

const getOidProfileName = (id: number) => {
  const profile = oidProfiles.value.find((p: any) => p.id === id);
  return profile?.name || `ID: ${id}`;
};
</script>

<template>
  <div class="space-y-6">
    <div class="bg-white rounded-xl border border-slate-200 p-6">
      <h3 class="text-xl font-bold text-slate-900 mb-2">Importación Masiva de Impresoras</h3>
      <p class="text-slate-600">Importa múltiples impresoras desde un archivo CSV</p>
    </div>

    <!-- Indicador de pasos -->
    <div class="bg-white rounded-xl border border-slate-200 p-6">
      <div class="flex items-center justify-between">
        <div
          v-for="step in totalSteps"
          :key="step"
          class="flex items-center flex-1"
        >
          <div class="flex items-center">
            <div
              class="w-10 h-10 rounded-full flex items-center justify-center font-semibold transition"
              :class="
                step < currentStep
                  ? 'bg-ada-primary text-white'
                  : step === currentStep
                  ? 'bg-ada-primary text-white ring-4 ring-ada-primary/20'
                  : 'bg-slate-200 text-slate-600'
              "
            >
              {{ step }}
            </div>
            <div class="ml-3 hidden sm:block">
              <div
                class="text-sm font-medium"
                :class="step <= currentStep ? 'text-slate-900' : 'text-slate-500'"
              >
                {{ step === 1 ? 'Subir CSV' : step === 2 ? 'Mapear Columnas' : step === 3 ? 'Configurar Perfiles' : 'Confirmar' }}
              </div>
            </div>
          </div>
          <div v-if="step < totalSteps" class="flex-1 h-1 mx-4 bg-slate-200">
            <div
              class="h-full bg-ada-primary transition-all"
              :style="{ width: step < currentStep ? '100%' : '0%' }"
            ></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Paso 1: Subir CSV -->
    <div v-if="currentStep === 1" class="bg-white rounded-xl border border-slate-200 p-6">
      <h4 class="text-lg font-semibold mb-4">Paso 1: Subir archivo CSV</h4>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Seleccionar archivo CSV *</label>
          <input
            id="csv-file"
            type="file"
            accept=".csv"
            @change="handleFileSelect"
            class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
          />
          <p class="text-xs text-slate-500 mt-1">
            El archivo debe tener encabezados en la primera fila. Formatos soportados: CSV (delimitado por comas o punto y coma)
          </p>
        </div>

        <div v-if="uploadProgress > 0 && uploadProgress < 100" class="bg-slate-100 rounded-lg p-4">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-slate-700">Subiendo...</span>
            <span class="text-sm text-slate-600">{{ uploadProgress }}%</span>
          </div>
          <div class="w-full bg-slate-200 rounded-full h-2">
            <div
              class="bg-ada-primary h-2 rounded-full transition-all"
              :style="{ width: `${uploadProgress}%` }"
            ></div>
          </div>
        </div>

        <div class="flex gap-2">
          <button
            @click="uploadCsv"
            :disabled="!csvFile || uploadProgress > 0"
            class="flex-1 bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ uploadProgress > 0 ? 'Subiendo...' : 'Subir y Continuar' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Paso 2: Mapear Columnas -->
    <div v-if="currentStep === 2" class="bg-white rounded-xl border border-slate-200 p-6">
      <h4 class="text-lg font-semibold mb-4">Paso 2: Mapear columnas del CSV</h4>
      <p class="text-sm text-slate-600 mb-4">
        Selecciona a qué campo corresponde cada columna de tu CSV. La columna de <strong>Dirección IP</strong> es obligatoria.
      </p>

      <div class="space-y-3 max-h-96 overflow-y-auto">
        <div
          v-for="column in csvColumns"
          :key="column"
          class="flex items-center gap-4 p-3 bg-slate-50 rounded-lg"
        >
          <div class="flex-1 font-medium text-slate-700">{{ column }}</div>
          <select
            v-model="columnMapping[column]"
            class="flex-1 rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
          >
            <option v-for="option in mappingOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>
      </div>

      <div class="mt-6 flex gap-2">
        <button
          @click="currentStep = 1"
          class="px-4 py-2 rounded-lg border border-slate-300 hover:bg-slate-50"
        >
          Atrás
        </button>
        <button
          @click="generatePreview"
          class="flex-1 bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark"
        >
          Generar Vista Previa
        </button>
      </div>
    </div>

    <!-- Paso 3: Configurar Perfiles OID -->
    <div v-if="currentStep === 3" class="bg-white rounded-xl border border-slate-200 p-6">
      <h4 class="text-lg font-semibold mb-4">Paso 3: Configurar perfiles OID SNMP</h4>

      <div class="mb-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Modo de asignación de perfil</label>
          <div class="flex gap-4">
            <label class="flex items-center">
              <input
                type="radio"
                v-model="oidProfileMode"
                value="single"
                class="mr-2"
              />
              <span>Mismo perfil para todas las impresoras</span>
            </label>
            <label class="flex items-center">
              <input
                type="radio"
                v-model="oidProfileMode"
                value="per-row"
                class="mr-2"
              />
              <span>Perfil diferente por fila</span>
            </label>
          </div>
        </div>

        <div v-if="oidProfileMode === 'single'">
          <label class="block text-sm font-medium text-slate-700 mb-2">Perfil OID SNMP *</label>
          <select
            v-model="defaultOidProfileId"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
          >
            <option :value="null">-- Seleccionar perfil --</option>
            <option v-for="profile in oidProfiles" :key="profile.id" :value="profile.id">
              {{ profile.name }} {{ profile.brand ? `(${profile.brand})` : '' }}
            </option>
          </select>
        </div>

        <div v-else class="space-y-2 max-h-96 overflow-y-auto">
          <div
            v-for="(row, index) in previewData"
            :key="index"
            class="flex items-center gap-4 p-3 bg-slate-50 rounded-lg"
          >
            <div class="flex-1">
              <div class="font-medium text-slate-900">{{ row.name || `Fila ${index + 1}` }}</div>
              <div class="text-sm text-slate-600">{{ row.ip_address }}</div>
            </div>
            <select
              v-model="rowOidProfiles[index]"
              class="w-64 rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
            >
              <option :value="null">-- Seleccionar perfil --</option>
              <option v-for="profile in oidProfiles" :key="profile.id" :value="profile.id">
                {{ profile.name }} {{ profile.brand ? `(${profile.brand})` : '' }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <div class="flex gap-2">
        <button
          @click="currentStep = 2"
          class="px-4 py-2 rounded-lg border border-slate-300 hover:bg-slate-50"
        >
          Atrás
        </button>
        <button
          @click="currentStep = 4"
          class="flex-1 bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark"
        >
          Continuar a Confirmación
        </button>
      </div>
    </div>

    <!-- Paso 4: Confirmar e Importar -->
    <div v-if="currentStep === 4" class="bg-white rounded-xl border border-slate-200 p-6">
      <h4 class="text-lg font-semibold mb-4">Paso 4: Confirmar importación</h4>
      <p class="text-sm text-slate-600 mb-4">
        Se importarán <strong>{{ previewData.length }}</strong> impresoras. Después de la importación, se iniciará la sincronización automática de todas las impresoras.
      </p>

      <div class="mb-6 max-h-96 overflow-y-auto border border-slate-200 rounded-lg">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 sticky top-0">
            <tr>
              <th class="px-4 py-2 text-left font-semibold text-slate-700">Nombre</th>
              <th class="px-4 py-2 text-left font-semibold text-slate-700">IP</th>
              <th class="px-4 py-2 text-left font-semibold text-slate-700">Marca</th>
              <th class="px-4 py-2 text-left font-semibold text-slate-700">Modelo</th>
              <th class="px-4 py-2 text-left font-semibold text-slate-700">Perfil OID</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(row, index) in previewData"
              :key="index"
              class="border-t border-slate-200 hover:bg-slate-50"
            >
              <td class="px-4 py-2">{{ row.name || 'N/A' }}</td>
              <td class="px-4 py-2">{{ row.ip_address }}</td>
              <td class="px-4 py-2">{{ row.brand || 'N/A' }}</td>
              <td class="px-4 py-2">{{ row.model || 'N/A' }}</td>
              <td class="px-4 py-2">
                {{
                  oidProfileMode === 'single'
                    ? getOidProfileName(defaultOidProfileId || 0)
                    : getOidProfileName(rowOidProfiles[index] || 0)
                }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex gap-2">
        <button
          @click="currentStep = 3"
          class="px-4 py-2 rounded-lg border border-slate-300 hover:bg-slate-50"
        >
          Atrás
        </button>
        <button
          @click="processImport"
          :disabled="importing"
          class="flex-1 bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ importing ? 'Importando...' : `Importar ${previewData.length} impresoras` }}
        </button>
      </div>
    </div>
  </div>
</template>

