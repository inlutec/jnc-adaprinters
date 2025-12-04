<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useConfigStore } from '@/stores/config';
import { useAppStore } from '@/stores/app';

defineOptions({ name: 'ConfigLogosTab' });

const configStore = useConfigStore();
const appStore = useAppStore();
const uploading = ref(false);
const selectedFile = ref<File | null>(null);
const logoType = ref('web');

const logoTypes = [
  { value: 'web', label: 'Logo Web' },
  { value: 'email', label: 'Logo Email' },
  { value: 'header', label: 'Header' },
  { value: 'footer', label: 'Footer' },
];

onMounted(() => {
  configStore.fetchLogos();
});

const handleFileSelect = (event: Event) => {
  const target = event.target as HTMLInputElement;
  if (target.files?.[0]) {
    selectedFile.value = target.files[0];
  }
};

const uploadLogo = async () => {
  if (!selectedFile.value) return;

  uploading.value = true;
  try {
    const formData = new FormData();
    formData.append('logo', selectedFile.value);
    formData.append('type', logoType.value);

    await configStore.uploadLogo(formData);
    appStore.notify('Logo subido correctamente', 'success');
    selectedFile.value = null;
    const input = document.getElementById('logo-file') as HTMLInputElement;
    if (input) input.value = '';
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al subir logo', 'error');
  } finally {
    uploading.value = false;
  }
};

const toggleLogo = async (id: number, isActive: boolean) => {
  try {
    await configStore.updateLogo(id, { is_active: !isActive });
    appStore.notify('Logo actualizado', 'success');
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al actualizar logo', 'error');
  }
};

const deleteLogo = async (id: number) => {
  if (!confirm('¿Estás seguro de eliminar este logo?')) return;

  try {
    await configStore.deleteLogo(id);
    appStore.notify('Logo eliminado', 'success');
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al eliminar logo', 'error');
  }
};
</script>

<template>
  <div class="space-y-6">
    <div class="border border-slate-200 rounded-xl p-6 bg-slate-50">
      <h3 class="text-lg font-semibold text-slate-900 mb-4">Subir nuevo logo</h3>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Tipo de logo</label>
          <select
            v-model="logoType"
            class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
          >
            <option v-for="type in logoTypes" :key="type.value" :value="type.value">
              {{ type.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Archivo</label>
          <input
            id="logo-file"
            type="file"
            accept="image/*"
            @change="handleFileSelect"
            class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
          />
        </div>
        <button
          @click="uploadLogo"
          :disabled="!selectedFile || uploading"
          class="w-full bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ uploading ? 'Subiendo...' : 'Subir logo' }}
        </button>
      </div>
    </div>

    <div>
      <h3 class="text-lg font-semibold text-slate-900 mb-4">Logos existentes</h3>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="logo in configStore.logos"
          :key="logo.id"
          class="border border-slate-200 rounded-xl p-4 hover:shadow-md transition"
        >
          <div class="aspect-video bg-slate-100 rounded-lg mb-3 flex items-center justify-center overflow-hidden">
            <img
              v-if="logo.path"
              :src="`/storage/${logo.path}`"
              :alt="logo.type"
              class="max-w-full max-h-full object-contain"
            />
          </div>
          <div class="space-y-2">
            <p class="text-sm font-semibold text-slate-900">{{ logoTypes.find(t => t.value === logo.type)?.label }}</p>
            <p class="text-xs text-slate-500">{{ logo.width }}x{{ logo.height }}px</p>
            <div class="flex items-center gap-2">
              <button
                @click="toggleLogo(logo.id, logo.is_active)"
                class="flex-1 px-3 py-1.5 text-xs font-semibold rounded-lg transition"
                :class="
                  logo.is_active
                    ? 'bg-ada-primary text-white'
                    : 'bg-slate-200 text-slate-700 hover:bg-slate-300'
                "
              >
                {{ logo.is_active ? 'Activo' : 'Inactivo' }}
              </button>
              <button
                @click="deleteLogo(logo.id)"
                class="px-3 py-1.5 text-xs font-semibold text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition"
              >
                Eliminar
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

