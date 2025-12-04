<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { api } from '@/services/httpClient';
import { useAppStore } from '@/stores/app';

defineOptions({ name: 'ConfigPrinterImagesTab' });

const appStore = useAppStore();
const printerGroups = ref<Array<{
  name: string;
  model?: string;
  count: number;
  printers: Array<{ id: number; name: string; ip_address?: string; photo_path?: string }>;
}>>([]);
const loading = ref(false);
const selectedGroup = ref<string | null>(null);
const uploading = ref(false);
const photoFile = ref<File | null>(null);

onMounted(() => {
  loadPrinterGroups();
});

const loadPrinterGroups = async () => {
  loading.value = true;
  try {
    const { data } = await api.get('/printers/groups-by-name');
    printerGroups.value = Array.isArray(data) ? data : [];
    console.log('Grupos cargados:', printerGroups.value);
  } catch (error: any) {
    console.error('Error cargando grupos:', error);
    appStore.notify(error.response?.data?.message || 'Error al cargar grupos de impresoras', 'error');
  } finally {
    loading.value = false;
  }
};

const selectGroup = (groupName: string) => {
  selectedGroup.value = groupName;
  photoFile.value = null;
};

const handlePhotoSelect = (event: Event) => {
  const target = event.target as HTMLInputElement;
  if (target.files && target.files[0]) {
    photoFile.value = target.files[0];
  }
};

const uploadMassivePhoto = async () => {
  if (!selectedGroup.value || !photoFile.value) {
    appStore.notify('Debes seleccionar un grupo y una foto', 'error');
    return;
  }

  uploading.value = true;
  try {
    const formData = new FormData();
    formData.append('photo', photoFile.value);
    formData.append('printer_name', selectedGroup.value);

    const { data } = await api.post('/printers/upload-massive-photo', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    appStore.notify(
      `Foto aplicada correctamente a ${data.applied_to_count} impresora${data.applied_to_count > 1 ? 's' : ''}`,
      'success'
    );
    selectedGroup.value = null;
    photoFile.value = null;
    await loadPrinterGroups();
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al subir foto masiva', 'error');
  } finally {
    uploading.value = false;
  }
};

const getPhotoUrl = (photoPath?: string | null): string | null => {
  if (!photoPath) return null;
  return `/storage/${photoPath}`;
};

const getPreviewUrl = (file: File): string => {
  return URL.createObjectURL(file);
};
</script>

<template>
  <div class="space-y-6">
    <div>
      <h3 class="text-lg font-semibold text-slate-900 mb-2">Imágenes Masivas de Impresoras</h3>
      <p class="text-sm text-slate-500">
        Sube una foto que se aplicará automáticamente a todas las impresoras con el mismo nombre. Útil cuando tienes
        múltiples impresoras del mismo modelo.
      </p>
    </div>

    <div v-if="loading" class="text-center py-8">
      <p class="text-slate-500">Cargando grupos de impresoras...</p>
    </div>

    <div v-else class="space-y-4">
      <!-- Lista de grupos -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="group in printerGroups"
          :key="group.name"
          @click="selectGroup(group.name)"
          class="bg-slate-50 rounded-lg border-2 p-4 cursor-pointer transition hover:border-ada-primary"
          :class="selectedGroup === group.name ? 'border-ada-primary bg-ada-primary/5' : 'border-slate-200'"
        >
          <div class="flex items-start justify-between mb-2">
            <div class="flex-1">
              <h4 class="font-semibold text-slate-900 mb-1">{{ group.name }}</h4>
              <p v-if="group.model" class="text-xs text-slate-500 mb-2">{{ group.model }}</p>
              <p class="text-sm text-slate-600">
                <span class="font-semibold">{{ group.count }}</span> impresora{{ group.count > 1 ? 's' : '' }}
              </p>
            </div>
            <div
              v-if="group.printers[0]?.photo_path"
              class="w-16 h-16 rounded border border-slate-300 overflow-hidden flex-shrink-0 ml-2"
            >
              <img
                :src="getPhotoUrl(group.printers[0].photo_path) || ''"
                :alt="group.name"
                class="w-full h-full object-cover"
              />
            </div>
            <div
              v-else
              class="w-16 h-16 rounded border border-slate-300 bg-slate-100 flex items-center justify-center flex-shrink-0 ml-2"
            >
              <span class="text-xs text-slate-400">Sin foto</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Formulario de subida -->
      <div v-if="selectedGroup" class="bg-white rounded-xl border border-slate-200 p-6">
        <h4 class="text-lg font-semibold mb-4">
          Subir foto para: <span class="text-ada-primary">{{ selectedGroup }}</span>
        </h4>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Seleccionar foto *</label>
            <input
              type="file"
              accept="image/*"
              @change="handlePhotoSelect"
              class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
            />
            <p class="text-xs text-slate-500 mt-1">La foto se redimensionará automáticamente a 800x800px</p>
          </div>

          <div v-if="photoFile" class="bg-slate-50 rounded-lg p-4">
            <p class="text-sm font-medium text-slate-700 mb-2">Vista previa:</p>
            <img
              :src="getPreviewUrl(photoFile)"
              alt="Vista previa"
              class="max-w-full max-h-64 rounded-lg border border-slate-300"
            />
          </div>

          <div class="flex gap-2">
            <button
              @click="uploadMassivePhoto"
              :disabled="!photoFile || uploading"
              class="flex-1 bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ uploading ? 'Subiendo...' : 'Subir foto masiva' }}
            </button>
            <button
              @click="selectedGroup = null; photoFile = null"
              class="px-4 py-2 rounded-lg border border-slate-300 hover:bg-slate-50"
            >
              Cancelar
            </button>
          </div>
        </div>
      </div>

      <div v-if="printerGroups.length === 0" class="text-center py-8 text-slate-500">
        No hay impresoras registradas
      </div>
    </div>
  </div>
</template>

