<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { api } from '@/services/httpClient';
import { useAppStore } from '@/stores/app';
import { useConfigStore } from '@/stores/config';
import { XMarkIcon, PhotoIcon } from '@heroicons/vue/24/outline';

const props = defineProps<{
  printer: any;
  show: boolean;
}>();

const emit = defineEmits<{
  close: [];
  saved: [];
}>();

const appStore = useAppStore();
const configStore = useConfigStore();
const fieldValues = ref<Record<string, any>>({});
const printerName = ref('');
const saving = ref(false);
const uploadingPhoto = ref(false);
const photoFile = ref<File | null>(null);
const photoPreview = ref<string | null>(null);

watch(
  () => props.show,
  async (newVal) => {
    if (newVal && props.printer) {
      await configStore.fetchCustomFields();
      await loadCustomFieldValues();
    }
  }
);

const loadCustomFieldValues = async () => {
  try {
    const { data } = await api.get(`/printers/${props.printer.id}`);
    // Los campos personalizados vienen en custom_field_values o similar
    if (data.custom_field_values) {
      fieldValues.value = { ...data.custom_field_values };
    } else {
      // Inicializar con valores vacíos para todos los campos
      fieldValues.value = {};
    }
    // Cargar nombre de la impresora
    printerName.value = data.name || props.printer.name || '';
    
    // Inicializar valores vacíos para campos que no tienen valor
    printerCustomFields.value.forEach((field: any) => {
      if (!(field.slug in fieldValues.value)) {
        fieldValues.value[field.slug] = field.type === 'checkbox' ? false : '';
      }
    });
  } catch (error) {
    // Si no hay campos personalizados, continuar
    printerName.value = props.printer.name || '';
    fieldValues.value = {};
  }
};

const printerCustomFields = computed(() => {
  return configStore.customFields
    .filter((f) => f.entity_type === 'printer')
    .sort((a: any, b: any) => a.order - b.order);
});

const saveCustomFields = async () => {
  saving.value = true;
  try {
    // Preparar valores de campos personalizados
    const customFieldValues: Record<string, any> = {};
    
    // Solo incluir valores de campos que existen en printerCustomFields
    printerCustomFields.value.forEach((field: any) => {
      const value = fieldValues.value[field.slug];
      // Convertir valores null/undefined a string vacío, pero mantener otros valores
      if (value !== null && value !== undefined) {
        customFieldValues[field.slug] = value;
      } else if (field.type === 'checkbox') {
        customFieldValues[field.slug] = false;
      } else {
        customFieldValues[field.slug] = '';
      }
    });
    
    console.log('Enviando datos:', { name: printerName.value, custom_field_values: customFieldValues });
    
    const response = await api.put(`/printers/${props.printer.id}`, {
      name: printerName.value,
      custom_field_values: customFieldValues,
    });
    
    // Actualizar los valores locales con la respuesta del servidor
    if (response.data.custom_field_values) {
      fieldValues.value = { ...response.data.custom_field_values };
    }
    
    // Actualizar el nombre en el objeto printer
    if (props.printer) {
      props.printer.name = printerName.value;
      props.printer.custom_field_values = response.data.custom_field_values || {};
    }
    
    appStore.notify('Cambios guardados correctamente', 'success');
    emit('saved');
    emit('close');
  } catch (error: any) {
    console.error('Error al guardar:', error);
    console.error('Response:', error.response?.data);
    appStore.notify(error.response?.data?.message || 'Error al guardar cambios', 'error');
  } finally {
    saving.value = false;
  }
};

const handlePhotoSelect = (event: Event) => {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  if (file) {
    photoFile.value = file;
    // Crear preview
    const reader = new FileReader();
    reader.onload = (e) => {
      photoPreview.value = e.target?.result as string;
    };
    reader.readAsDataURL(file);
  }
};

const uploadPhoto = async () => {
  if (!photoFile.value) return;
  
  uploadingPhoto.value = true;
  try {
    const formData = new FormData();
    formData.append('photo', photoFile.value);
    
    const { data } = await api.post(`/printers/${props.printer.id}/photo`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    
    appStore.notify(
      data.message || `Foto subida correctamente. Se aplicó a ${data.applied_to_count || 1} impresoras con el mismo nombre.`,
      'success'
    );
    
    // Limpiar
    photoFile.value = null;
    photoPreview.value = null;
    
    // Recargar datos de la impresora
    emit('saved');
    
    // Actualizar la URL de la foto en el objeto printer
    if (data.photo_url) {
      props.printer.photo_url = data.photo_url;
      props.printer.photo_path = data.photo_path || props.printer.photo_path;
    }
  } catch (error: any) {
    appStore.notify(error.response?.data?.error || 'Error al subir la foto', 'error');
  } finally {
    uploadingPhoto.value = false;
  }
};

watch(
  () => props.show,
  (newVal) => {
    if (newVal && props.printer) {
      // Resetear foto al abrir
      photoFile.value = null;
      photoPreview.value = null;
    }
  }
);
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="emit('close')">
    <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
      <div class="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
        <h3 class="text-xl font-bold text-slate-900">Campos personalizados</h3>
        <button @click="emit('close')" class="p-2 rounded-lg hover:bg-slate-50">
          <XMarkIcon class="h-5 w-5 text-slate-600" />
        </button>
      </div>
      <div class="p-6 space-y-6">
        <!-- Sección de Información Básica -->
        <section class="border-b border-slate-200 pb-6">
          <h4 class="text-lg font-semibold mb-4">Información básica</h4>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Nombre de la impresora</label>
              <input
                v-model="printerName"
                type="text"
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
                placeholder="Nombre de la impresora"
              />
            </div>
          </div>
        </section>

        <!-- Sección de Foto -->
        <section class="border-b border-slate-200 pb-6">
          <h4 class="text-lg font-semibold mb-4 flex items-center gap-2">
            <PhotoIcon class="h-5 w-5 text-slate-600" />
            Foto de la impresora
          </h4>
          <p class="text-sm text-slate-500 mb-4">
            La foto se aplicará a todas las impresoras con el mismo nombre exacto. Se redimensionará automáticamente.
          </p>
          
          <div class="space-y-4">
            <!-- Preview de foto actual -->
            <div v-if="printer.photo_url || printer.photo_path || photoPreview" class="flex items-center gap-4">
              <div class="relative">
                <img
                  :src="photoPreview || printer.photo_url || (printer.photo_path ? `/storage/${printer.photo_path}` : '')"
                  alt="Foto de impresora"
                  class="w-32 h-32 object-cover rounded-lg border-2 border-slate-200"
                />
              </div>
              <div class="flex-1">
                <p class="text-sm text-slate-600">
                  <span v-if="photoPreview">Nueva foto seleccionada</span>
                  <span v-else>Foto actual</span>
                </p>
              </div>
            </div>
            
            <!-- Input de archivo -->
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Seleccionar foto</label>
              <input
                type="file"
                accept="image/*"
                @change="handlePhotoSelect"
                class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-ada-primary file:text-white hover:file:bg-ada-dark"
              />
              <p class="text-xs text-slate-500 mt-1">Formatos: JPEG, PNG, GIF, WebP. Máximo 5MB</p>
            </div>
            
            <!-- Botón de subir -->
            <button
              @click="uploadPhoto"
              :disabled="!photoFile || uploadingPhoto"
              class="w-full bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
              <PhotoIcon class="h-5 w-5" />
              {{ uploadingPhoto ? 'Subiendo...' : 'Subir foto' }}
            </button>
          </div>
        </section>

        <!-- Sección de Campos Personalizados -->
        <section>
          <h4 class="text-lg font-semibold mb-4">Campos personalizados</h4>
          <div class="space-y-4">
            <div v-for="field in printerCustomFields" :key="field.id" class="space-y-2">
              <label class="block text-sm font-medium text-slate-700">
                {{ field.name }}
                <span v-if="field.is_required" class="text-red-500">*</span>
              </label>
              <input
                v-if="field.type === 'text'"
                v-model="fieldValues[field.slug]"
                type="text"
                :required="field.is_required"
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
                :placeholder="field.help_text || ''"
              />
              <input
                v-else-if="field.type === 'number'"
                v-model.number="fieldValues[field.slug]"
                type="number"
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
              />
              <input
                v-else-if="field.type === 'date'"
                v-model="fieldValues[field.slug]"
                type="date"
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
              />
              <select
                v-else-if="field.type === 'select'"
                v-model="fieldValues[field.slug]"
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
              >
                <option :value="null">Seleccionar</option>
                <option v-for="option in (field.options || [])" :key="option" :value="option">{{ option }}</option>
              </select>
              <textarea
                v-else-if="field.type === 'textarea'"
                v-model="fieldValues[field.slug]"
                rows="4"
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
              />
              <input
                v-else-if="field.type === 'checkbox'"
                v-model="fieldValues[field.slug]"
                type="checkbox"
                class="rounded border-slate-300"
              />
            </div>
            <div v-if="printerCustomFields.length === 0" class="text-sm text-slate-500 italic">
              No hay campos personalizados configurados para impresoras
            </div>
          </div>
        </section>

        <!-- Botones de acción -->
        <div class="flex gap-2 pt-4 border-t border-slate-200">
          <button
            @click="saveCustomFields"
            :disabled="saving"
            class="flex-1 bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark disabled:opacity-50"
          >
            {{ saving ? 'Guardando...' : 'Guardar campos' }}
          </button>
          <button @click="emit('close')" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50">
            Cerrar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

