<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useInstallationsStore } from '@/stores/installations';
import { usePrintersStore } from '@/stores/printers';
import { useInventoryStore } from '@/stores/inventory';
import { useAppStore } from '@/stores/app';
import { XMarkIcon } from '@heroicons/vue/24/outline';

defineOptions({ name: 'InstallationsView' });

const installationsStore = useInstallationsStore();
const printersStore = usePrintersStore();
const inventoryStore = useInventoryStore();
const appStore = useAppStore();

const showForm = ref(false);
const showDetailsModal = ref(false);
const showEditModal = ref(false);
const isMobile = ref(false);
const editingInstallation = ref<any>(null);
const selectedInstallation = ref<any>(null);

const form = ref({
  printer_id: null as number | null,
  stock_id: null as number | null,
  quantity: 1,
  observations: '',
  installed_at: new Date().toISOString().split('T')[0] as string,
  installed_at_time: new Date().toTimeString().slice(0, 5) as string, // HH:mm
  photos: [] as File[],
});

const availablePrinters = ref<any[]>([]);
const availableStocks = ref<any[]>([]);
const printerSearch = ref('');
const stockSearch = ref('');
const showPrinterDropdown = ref(false);
const showStockDropdown = ref(false);

onMounted(() => {
  installationsStore.fetch();
  loadPrinters();
  loadStocks();
  isMobile.value = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
});

const loadPrinters = async () => {
  try {
    await printersStore.fetch(1);
    availablePrinters.value = printersStore.printers;
  } catch (error) {
    console.error('Error loading printers:', error);
  }
};

const loadStocks = async () => {
  try {
    await inventoryStore.fetch(1);
    availableStocks.value = inventoryStore.stocks.filter((s: any) => s.quantity > 0);
  } catch (error) {
    console.error('Error loading stocks:', error);
  }
};

const selectedStock = computed(() => {
  if (!form.value.stock_id) return null;
  return availableStocks.value.find((s: any) => s.id === form.value.stock_id);
});

const filteredPrinters = computed(() => {
  if (!printerSearch.value) return availablePrinters.value;
  const search = printerSearch.value.toLowerCase();
  return availablePrinters.value.filter((p: any) => 
    p.name?.toLowerCase().includes(search) ||
    p.ip_address?.toLowerCase().includes(search) ||
    p.brand?.toLowerCase().includes(search) ||
    p.model?.toLowerCase().includes(search)
  );
});

const filteredStocks = computed(() => {
  if (!stockSearch.value) return availableStocks.value;
  const search = stockSearch.value.toLowerCase();
  return availableStocks.value.filter((s: any) => 
    s.consumable?.name?.toLowerCase().includes(search) ||
    s.consumable?.sku?.toLowerCase().includes(search) ||
    s.site?.name?.toLowerCase().includes(search) ||
    s.department?.name?.toLowerCase().includes(search)
  );
});

const selectedPrinterName = computed(() => {
  if (!form.value.printer_id) return '';
  const printer = availablePrinters.value.find((p: any) => p.id === form.value.printer_id);
  return printer ? `${printer.name} (${printer.ip_address})` : '';
});

const selectedStockName = computed(() => {
  if (!form.value.stock_id) return '';
  const stock = selectedStock.value;
  if (!stock) return '';
  return `${stock.consumable?.name || ''} (SKU: ${stock.consumable?.sku || ''}) - Stock: ${stock.quantity}${stock.site?.name ? ` - ${stock.site.name}` : ''}`;
});

const maxQuantity = computed(() => {
  return selectedStock.value?.quantity || 0;
});

const openForm = () => {
  editingInstallation.value = null;
  form.value = {
    printer_id: null,
    stock_id: null,
    quantity: 1,
    observations: '',
    installed_at: new Date().toISOString().split('T')[0] as string,
    installed_at_time: new Date().toTimeString().slice(0, 5) as string,
    photos: [],
  };
  printerSearch.value = '';
  stockSearch.value = '';
  showPrinterDropdown.value = false;
  showStockDropdown.value = false;
  showForm.value = true;
};

const openDetails = async (installation: any) => {
  try {
    const data = await installationsStore.fetchOne(installation.id);
    console.log('Installation data loaded for details:', data);
    console.log('Printer:', data?.printer);
    console.log('Stock:', data?.stock);
    console.log('Consumable:', data?.stock?.consumable);
    
    if (!data) {
      throw new Error('No se recibieron datos de la instalación');
    }
    
    selectedInstallation.value = data;
    showDetailsModal.value = true;
  } catch (error: any) {
    console.error('Error loading installation details:', error);
    appStore.notify(error.response?.data?.message || error.message || 'Error al cargar detalles', 'error');
  }
};

const openEdit = async (installation: any) => {
  try {
    const data = await installationsStore.fetchOne(installation.id);
    console.log('Installation data for edit:', data);
    editingInstallation.value = data;
    
    if (!editingInstallation.value) {
      throw new Error('No se pudieron cargar los datos de la instalación');
    }
    
    // Manejar la fecha de instalación
    let installedDate: Date;
    if (editingInstallation.value.installed_at) {
      installedDate = new Date(editingInstallation.value.installed_at);
      // Validar que la fecha sea válida
      if (isNaN(installedDate.getTime())) {
        installedDate = new Date();
      }
    } else {
      installedDate = new Date();
    }
    
    form.value = {
      printer_id: editingInstallation.value.printer_id,
      stock_id: editingInstallation.value.stock_id,
      quantity: editingInstallation.value.quantity,
      observations: editingInstallation.value.observations || '',
      installed_at: installedDate.toISOString().split('T')[0] as string,
      installed_at_time: installedDate.toTimeString().slice(0, 5) as string,
      photos: [],
    };
    
    // Cargar impresoras y stocks si no están cargados
    if (availablePrinters.value.length === 0) {
      await loadPrinters();
    }
    if (availableStocks.value.length === 0) {
      await loadStocks();
    }
    
    const printer = availablePrinters.value.find((p: any) => p.id === editingInstallation.value.printer_id);
    if (printer) {
      printerSearch.value = `${printer.name} (${printer.ip_address})`;
    }
    const stock = availableStocks.value.find((s: any) => s.id === editingInstallation.value.stock_id);
    if (stock) {
      stockSearch.value = `${stock.consumable?.name || ''} (SKU: ${stock.consumable?.sku || ''}) - Stock: ${stock.quantity}`;
    }
    showEditModal.value = true;
  } catch (error: any) {
    console.error('Error loading installation for edit:', error);
    appStore.notify(error.response?.data?.message || error.message || 'Error al cargar instalación', 'error');
  }
};

const closeEdit = () => {
  showEditModal.value = false;
  editingInstallation.value = null;
  openForm(); // Reset form
};

const selectPrinter = (printer: any) => {
  form.value.printer_id = printer.id;
  printerSearch.value = `${printer.name} (${printer.ip_address})`;
  showPrinterDropdown.value = false;
};

const selectStock = (stock: any) => {
  form.value.stock_id = stock.id;
  stockSearch.value = `${stock.consumable?.name || ''} (SKU: ${stock.consumable?.sku || ''}) - Stock: ${stock.quantity}`;
  showStockDropdown.value = false;
};

const handlePrinterBlur = () => {
  setTimeout(() => {
    showPrinterDropdown.value = false;
  }, 200);
};

const handleStockBlur = () => {
  setTimeout(() => {
    showStockDropdown.value = false;
  }, 200);
};

const handlePhotoSelect = (event: Event) => {
  const target = event.target as HTMLInputElement;
  if (target.files) {
    form.value.photos = Array.from(target.files);
  }
};

const removePhoto = (index: number) => {
  form.value.photos.splice(index, 1);
};

const formatDateTime = (date: string, time: string): string => {
  // Combinar fecha y hora en formato ISO
  return `${date}T${time}:00`;
};

const formatInstallationDate = (dateString: string | null | undefined): string => {
  if (!dateString) return '—';
  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '—';
    return date.toLocaleString('es-ES');
  } catch {
    return '—';
  }
};

const submitInstallation = async () => {
  if (!form.value.printer_id || !form.value.stock_id) {
    appStore.notify('Debes seleccionar una impresora y un consumible', 'error');
    return;
  }

  if (form.value.quantity > maxQuantity.value) {
    appStore.notify(`Cantidad no disponible. Stock disponible: ${maxQuantity.value}`, 'error');
    return;
  }

  try {
    const date = (form.value.installed_at || new Date().toISOString().split('T')[0]) as string;
    const time = (form.value.installed_at_time || new Date().toTimeString().slice(0, 5)) as string;
    const installedAt = formatDateTime(date, time);
    
    // Si hay fotos nuevas, usar FormData, sino usar JSON
    const hasNewPhotos = form.value.photos.length > 0;
    
    if (hasNewPhotos) {
      const formData = new FormData();
      formData.append('printer_id', form.value.printer_id!.toString());
      formData.append('stock_id', form.value.stock_id!.toString());
      formData.append('quantity', form.value.quantity.toString());
      if (form.value.observations) {
        formData.append('observations', form.value.observations);
      }
      formData.append('installed_at', installedAt);

      form.value.photos.forEach((photo, index) => {
        formData.append(`photos[${index}]`, photo);
      });

      if (editingInstallation.value) {
        await installationsStore.update(editingInstallation.value.id, formData);
        appStore.notify('Instalación actualizada correctamente', 'success');
        showEditModal.value = false;
      } else {
        await installationsStore.create(formData);
        appStore.notify('Instalación registrada correctamente', 'success');
        showForm.value = false;
      }
    } else {
      const payload: any = {
        printer_id: form.value.printer_id,
        stock_id: form.value.stock_id,
        quantity: form.value.quantity,
        observations: form.value.observations || null,
        installed_at: installedAt,
      };

      if (editingInstallation.value) {
        await installationsStore.updateJson(editingInstallation.value.id, payload);
        appStore.notify('Instalación actualizada correctamente', 'success');
        showEditModal.value = false;
      } else {
        const formData = new FormData();
        Object.keys(payload).forEach(key => {
          if (payload[key] !== null) {
            formData.append(key, payload[key].toString());
          }
        });
        await installationsStore.create(formData);
        appStore.notify('Instalación registrada correctamente', 'success');
        showForm.value = false;
      }
    }
    
    await loadStocks(); // Recargar stocks actualizados
    editingInstallation.value = null;
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al guardar instalación', 'error');
  }
};

const getPhotoUrl = (photo: File): string => {
  return URL.createObjectURL(photo);
};

const deleteInstallation = async (id: number) => {
  if (!confirm('¿Estás seguro de eliminar esta instalación? Se restaurará el stock.')) return;
  try {
    await installationsStore.delete(id);
    appStore.notify('Instalación eliminada', 'success');
    await loadStocks(); // Recargar stocks actualizados
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al eliminar instalación', 'error');
  }
};
</script>

<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <div>
        <h2 class="text-2xl font-bold text-slate-900">Instalación de Consumibles</h2>
        <p class="text-sm text-slate-500">Registrar instalación de consumibles en impresoras</p>
      </div>
      <button
        @click="openForm"
        class="bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark"
      >
        + Nueva instalación
      </button>
    </div>

    <!-- Formulario -->
    <div v-if="showForm" class="bg-white rounded-xl border border-slate-200 p-6">
      <h3 class="text-lg font-semibold mb-4">Nueva Instalación</h3>
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div class="relative">
            <label class="block text-sm font-medium text-slate-700 mb-2">Impresora *</label>
            <input
              v-model="printerSearch"
              @focus="showPrinterDropdown = true"
              @blur="handlePrinterBlur"
              type="text"
              placeholder="Buscar impresora por nombre, IP, marca o modelo..."
              class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
            />
            <div
              v-if="showPrinterDropdown && filteredPrinters.length > 0"
              class="absolute z-50 w-full mt-1 bg-white border border-slate-300 rounded-lg shadow-lg max-h-60 overflow-y-auto"
            >
              <div
                v-for="printer in filteredPrinters"
                :key="printer.id"
                @mousedown="selectPrinter(printer)"
                class="px-4 py-2 hover:bg-ada-primary/10 cursor-pointer border-b border-slate-100 last:border-0"
              >
                <p class="font-semibold text-slate-900">{{ printer.name }}</p>
                <p class="text-xs text-slate-500">{{ printer.ip_address }}</p>
                <p v-if="printer.brand || printer.model" class="text-xs text-slate-400">
                  {{ printer.brand }} {{ printer.model }}
                </p>
              </div>
            </div>
            <div
              v-if="showPrinterDropdown && filteredPrinters.length === 0 && printerSearch"
              class="absolute z-50 w-full mt-1 bg-white border border-slate-300 rounded-lg shadow-lg p-4 text-sm text-slate-500"
            >
              No se encontraron impresoras
            </div>
            <p v-if="form.printer_id" class="text-xs text-slate-600 mt-1">
              Seleccionada: {{ selectedPrinterName }}
            </p>
          </div>
          <div class="relative">
            <label class="block text-sm font-medium text-slate-700 mb-2">Consumible en stock *</label>
            <input
              v-model="stockSearch"
              @focus="showStockDropdown = true"
              @blur="handleStockBlur"
              type="text"
              placeholder="Buscar consumible por nombre, SKU o ubicación..."
              class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
            />
            <div
              v-if="showStockDropdown && filteredStocks.length > 0"
              class="absolute z-50 w-full mt-1 bg-white border border-slate-300 rounded-lg shadow-lg max-h-60 overflow-y-auto"
            >
              <div
                v-for="stock in filteredStocks"
                :key="stock.id"
                @mousedown="selectStock(stock)"
                class="px-4 py-2 hover:bg-ada-primary/10 cursor-pointer border-b border-slate-100 last:border-0"
              >
                <p class="font-semibold text-slate-900">{{ stock.consumable?.name }}</p>
                <p class="text-xs text-slate-500">SKU: {{ stock.consumable?.sku }}</p>
                <p class="text-xs text-slate-600">
                  Stock: <span class="font-semibold">{{ stock.quantity }}</span>
                  <span v-if="stock.site?.name"> · {{ stock.site.name }}</span>
                  <span v-if="stock.department?.name"> · {{ stock.department.name }}</span>
                </p>
              </div>
            </div>
            <div
              v-if="showStockDropdown && filteredStocks.length === 0 && stockSearch"
              class="absolute z-50 w-full mt-1 bg-white border border-slate-300 rounded-lg shadow-lg p-4 text-sm text-slate-500"
            >
              No se encontraron consumibles
            </div>
            <p v-if="form.stock_id" class="text-xs text-slate-600 mt-1">
              Seleccionado: {{ selectedStockName }}
            </p>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Cantidad *</label>
            <input
              v-model.number="form.quantity"
              type="number"
              :min="1"
              :max="maxQuantity"
              class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
            />
            <p class="text-xs text-slate-500 mt-1">Disponible: {{ maxQuantity }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Fecha de instalación *</label>
            <input
              v-model="form.installed_at"
              type="date"
              class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Hora de instalación *</label>
            <input
              v-model="form.installed_at_time"
              type="time"
              class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
            />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Observaciones</label>
          <textarea
            v-model="form.observations"
            rows="3"
            placeholder="Observaciones sobre la instalación..."
            class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
          ></textarea>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Fotos (opcional)</label>
          <input
            type="file"
            multiple
            accept="image/*"
            :capture="isMobile ? 'environment' : undefined"
            @change="handlePhotoSelect"
            class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
          />
          <div v-if="form.photos.length > 0" class="flex gap-2 mt-2 flex-wrap">
            <div
              v-for="(photo, index) in form.photos"
              :key="index"
              class="relative w-20 h-20 border border-slate-300 rounded-lg overflow-hidden"
            >
              <img :src="getPhotoUrl(photo)" :alt="`Foto ${index + 1}`" class="w-full h-full object-cover" />
              <button
                @click="removePhoto(index)"
                class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600"
              >
                ×
              </button>
            </div>
          </div>
        </div>

        <div class="flex gap-2">
          <button
            @click="submitInstallation"
            class="flex-1 bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark"
          >
            Registrar instalación
          </button>
          <button
            @click="showForm = false"
            class="px-4 py-2 rounded-lg border border-slate-300 hover:bg-slate-50"
          >
            Cancelar
          </button>
        </div>
      </div>
    </div>

    <!-- Lista de instalaciones -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table class="w-full">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Fecha</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Impresora</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Consumible</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Cantidad</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Instalado por</th>
            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          <tr v-for="installation in installationsStore.installations" :key="installation.id" class="hover:bg-slate-50">
            <td class="px-6 py-4 text-sm text-slate-600">
              {{ new Date(installation.installed_at).toLocaleDateString('es-ES') }}
            </td>
            <td class="px-6 py-4">
              <p class="font-semibold">{{ installation.printer?.name }}</p>
              <p class="text-xs text-slate-500">{{ installation.printer?.ip_address }}</p>
            </td>
            <td class="px-6 py-4">
              <p class="font-semibold">{{ installation.stock?.consumable?.name }}</p>
              <p class="text-xs text-slate-500">{{ installation.stock?.consumable?.sku }}</p>
            </td>
            <td class="px-6 py-4 font-semibold">{{ installation.quantity }}</td>
            <td class="px-6 py-4 text-sm text-slate-600">{{ installation.installer?.name || '—' }}</td>
            <td class="px-6 py-4">
              <div class="flex gap-2">
                <button
                  @click="openDetails(installation)"
                  class="px-3 py-1 text-sm text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100"
                >
                  Ver
                </button>
                <button
                  @click="openEdit(installation)"
                  class="px-3 py-1 text-sm text-ada-primary bg-ada-primary/10 rounded-lg hover:bg-ada-primary/20"
                >
                  Editar
                </button>
                <button
                  @click="deleteInstallation(installation.id)"
                  class="px-3 py-1 text-sm text-red-600 bg-red-50 rounded-lg hover:bg-red-100"
                >
                  Eliminar
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="installationsStore.installations.length === 0">
            <td colspan="6" class="px-6 py-8 text-center text-slate-500">No hay instalaciones registradas</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal de Detalles -->
    <div
      v-if="showDetailsModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      @click.self="showDetailsModal = false"
    >
      <div class="bg-white rounded-xl shadow-xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center">
          <h3 class="text-xl font-bold text-slate-900">Detalles de Instalación</h3>
          <button
            @click="showDetailsModal = false"
            class="text-slate-400 hover:text-slate-600"
          >
            <XMarkIcon class="w-6 h-6" />
          </button>
        </div>
        <div v-if="selectedInstallation" class="p-6 space-y-6">
          <div class="grid grid-cols-2 gap-6">
            <div>
              <h4 class="text-sm font-semibold text-slate-500 mb-2">Impresora</h4>
              <p class="text-lg font-semibold text-slate-900">{{ selectedInstallation.printer?.name || '—' }}</p>
              <p class="text-sm text-slate-600">{{ selectedInstallation.printer?.ip_address || '' }}</p>
            </div>
            <div>
              <h4 class="text-sm font-semibold text-slate-500 mb-2">Consumible</h4>
              <p class="text-lg font-semibold text-slate-900">{{ selectedInstallation.stock?.consumable?.name || '—' }}</p>
              <p class="text-sm text-slate-600">
                <template v-if="selectedInstallation.stock?.consumable?.sku">
                  SKU: {{ selectedInstallation.stock.consumable.sku }}
                </template>
                <template v-else>SKU: —</template>
              </p>
            </div>
            <div>
              <h4 class="text-sm font-semibold text-slate-500 mb-2">Cantidad</h4>
              <p class="text-lg font-semibold text-slate-900">{{ selectedInstallation.quantity }}</p>
            </div>
            <div>
              <h4 class="text-sm font-semibold text-slate-500 mb-2">Fecha y Hora</h4>
              <p class="text-lg font-semibold text-slate-900">
                <template v-if="selectedInstallation.installed_at">
                  {{ formatInstallationDate(selectedInstallation.installed_at) }}
                </template>
                <template v-else>—</template>
              </p>
            </div>
            <div>
              <h4 class="text-sm font-semibold text-slate-500 mb-2">Instalado por</h4>
              <p class="text-lg font-semibold text-slate-900">{{ selectedInstallation.installer?.name || '—' }}</p>
            </div>
          </div>
          <div v-if="selectedInstallation.observations">
            <h4 class="text-sm font-semibold text-slate-500 mb-2">Observaciones</h4>
            <p class="text-slate-700 whitespace-pre-wrap">{{ selectedInstallation.observations }}</p>
          </div>
          <div v-if="selectedInstallation.photos && selectedInstallation.photos.length > 0">
            <h4 class="text-sm font-semibold text-slate-500 mb-3">Fotos</h4>
            <div class="grid grid-cols-3 gap-4">
              <div
                v-for="photo in selectedInstallation.photos"
                :key="photo.id"
                class="relative aspect-square rounded-lg overflow-hidden border border-slate-200"
              >
                <img
                  :src="`/storage/${photo.photo_path}`"
                  :alt="`Foto instalación ${selectedInstallation.id}`"
                  class="w-full h-full object-cover"
                />
              </div>
            </div>
          </div>
        </div>
        <div class="sticky bottom-0 bg-slate-50 border-t border-slate-200 px-6 py-4 flex justify-end gap-2">
          <button
            @click="showDetailsModal = false"
            class="px-4 py-2 rounded-lg border border-slate-300 hover:bg-slate-50"
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>

    <!-- Modal de Edición -->
    <div
      v-if="showEditModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      @click.self="closeEdit"
    >
      <div class="bg-white rounded-xl shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex justify-between items-center">
          <h3 class="text-xl font-bold text-slate-900">Editar Instalación</h3>
          <button
            @click="closeEdit"
            class="text-slate-400 hover:text-slate-600"
          >
            <XMarkIcon class="w-6 h-6" />
          </button>
        </div>
        <div class="p-6">
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div class="relative">
                <label class="block text-sm font-medium text-slate-700 mb-2">Impresora *</label>
                <input
                  v-model="printerSearch"
                  @focus="showPrinterDropdown = true"
                  @blur="handlePrinterBlur"
                  type="text"
                  placeholder="Buscar impresora..."
                  class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
                />
                <div
                  v-if="showPrinterDropdown && filteredPrinters.length > 0"
                  class="absolute z-50 w-full mt-1 bg-white border border-slate-300 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                >
                  <div
                    v-for="printer in filteredPrinters"
                    :key="printer.id"
                    @mousedown="selectPrinter(printer)"
                    class="px-4 py-2 hover:bg-ada-primary/10 cursor-pointer border-b border-slate-100 last:border-0"
                  >
                    <p class="font-semibold text-slate-900">{{ printer.name }}</p>
                    <p class="text-xs text-slate-500">{{ printer.ip_address }}</p>
                  </div>
                </div>
              </div>
              <div class="relative">
                <label class="block text-sm font-medium text-slate-700 mb-2">Consumible en stock *</label>
                <input
                  v-model="stockSearch"
                  @focus="showStockDropdown = true"
                  @blur="handleStockBlur"
                  type="text"
                  placeholder="Buscar consumible..."
                  class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
                />
                <div
                  v-if="showStockDropdown && filteredStocks.length > 0"
                  class="absolute z-50 w-full mt-1 bg-white border border-slate-300 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                >
                  <div
                    v-for="stock in filteredStocks"
                    :key="stock.id"
                    @mousedown="selectStock(stock)"
                    class="px-4 py-2 hover:bg-ada-primary/10 cursor-pointer border-b border-slate-100 last:border-0"
                  >
                    <p class="font-semibold text-slate-900">{{ stock.consumable?.name }}</p>
                    <p class="text-xs text-slate-500">SKU: {{ stock.consumable?.sku }}</p>
                    <p class="text-xs text-slate-600">Stock: {{ stock.quantity }}</p>
                  </div>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Cantidad *</label>
                <input
                  v-model.number="form.quantity"
                  type="number"
                  :min="1"
                  :max="maxQuantity"
                  class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
                />
                <p class="text-xs text-slate-500 mt-1">Disponible: {{ maxQuantity }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Fecha de instalación *</label>
                <input
                  v-model="form.installed_at"
                  type="date"
                  class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Hora de instalación *</label>
                <input
                  v-model="form.installed_at_time"
                  type="time"
                  class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
                />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Observaciones</label>
              <textarea
                v-model="form.observations"
                rows="3"
                placeholder="Observaciones sobre la instalación..."
                class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
              ></textarea>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Fotos adicionales (opcional)</label>
              <input
                type="file"
                multiple
                accept="image/*"
                :capture="isMobile ? 'environment' : undefined"
                @change="handlePhotoSelect"
                class="w-full rounded-lg border border-slate-300 px-4 py-2 focus:ring-2 focus:ring-ada-primary focus:border-ada-primary"
              />
              <div v-if="form.photos.length > 0" class="flex gap-2 mt-2 flex-wrap">
                <div
                  v-for="(photo, index) in form.photos"
                  :key="index"
                  class="relative w-20 h-20 border border-slate-300 rounded-lg overflow-hidden"
                >
                  <img :src="getPhotoUrl(photo)" :alt="`Foto ${index + 1}`" class="w-full h-full object-cover" />
                  <button
                    @click="removePhoto(index)"
                    class="absolute top-1 right-1 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600"
                  >
                    ×
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="sticky bottom-0 bg-slate-50 border-t border-slate-200 px-6 py-4 flex justify-end gap-2">
          <button
            @click="closeEdit"
            class="px-4 py-2 rounded-lg border border-slate-300 hover:bg-slate-50"
          >
            Cancelar
          </button>
          <button
            @click="submitInstallation"
            class="px-4 py-2 rounded-lg bg-ada-primary text-white font-semibold hover:bg-ada-dark"
          >
            Guardar cambios
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

