<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useConfigStore } from '@/stores/config';
import { useAppStore } from '@/stores/app';

defineOptions({ name: 'ConfigLocationsTab' });

const configStore = useConfigStore();
const appStore = useAppStore();
const activeLevel = ref<'provinces' | 'sites' | 'departments'>('provinces');
const showProvinceForm = ref(false);
const showSiteForm = ref(false);
const showDeptForm = ref(false);
const editingProvince = ref<number | null>(null);
const editingSite = ref<number | null>(null);
const editingDept = ref<number | null>(null);

onMounted(() => {
  configStore.fetchProvinces();
  configStore.fetchSites();
  configStore.fetchDepartments();
});

const provinceForm = ref({ name: '', code: '' });
const siteForm = ref({ province_id: null as number | null, name: '', code: '', address: '' });
const deptForm = ref({ site_id: null as number | null, name: '', code: '', is_warehouse: false });

const saveProvince = async () => {
  try {
    if (editingProvince.value) {
      await configStore.updateProvince(editingProvince.value, provinceForm.value);
      appStore.notify('Provincia actualizada', 'success');
    } else {
      await configStore.createProvince(provinceForm.value);
      appStore.notify('Provincia creada', 'success');
    }
    showProvinceForm.value = false;
    editingProvince.value = null;
    provinceForm.value = { name: '', code: '' };
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error', 'error');
  }
};

const editProvince = (province: any) => {
  editingProvince.value = province.id;
  provinceForm.value = { name: province.name, code: province.code || '' };
  showProvinceForm.value = true;
};

const deleteProvince = async (id: number) => {
  if (!confirm('¿Estás seguro de eliminar esta provincia? No se puede eliminar si tiene sedes asociadas.')) {
    return;
  }
  try {
    await configStore.deleteProvince(id);
    appStore.notify('Provincia eliminada', 'success');
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al eliminar', 'error');
  }
};

const saveSite = async () => {
  try {
    if (editingSite.value) {
      await configStore.updateSite(editingSite.value, {
        ...siteForm.value,
        province_id: siteForm.value.province_id || undefined,
      });
      appStore.notify('Sede actualizada', 'success');
    } else {
      await configStore.createSite({
        ...siteForm.value,
        province_id: siteForm.value.province_id || undefined,
      });
      appStore.notify('Sede creada', 'success');
    }
    showSiteForm.value = false;
    editingSite.value = null;
    siteForm.value = { province_id: null, name: '', code: '', address: '' };
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error', 'error');
  }
};

const editSite = (site: any) => {
  editingSite.value = site.id;
  siteForm.value = {
    province_id: site.province_id,
    name: site.name,
    code: site.code || '',
    address: site.address || '',
  };
  showSiteForm.value = true;
};

const deleteSite = async (id: number) => {
  if (!confirm('¿Estás seguro de eliminar esta sede? No se puede eliminar si tiene departamentos asociados.')) {
    return;
  }
  try {
    await configStore.deleteSite(id);
    appStore.notify('Sede eliminada', 'success');
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al eliminar', 'error');
  }
};

const saveDepartment = async () => {
  try {
    if (editingDept.value) {
      await configStore.updateDepartment(editingDept.value, {
        ...deptForm.value,
        site_id: deptForm.value.site_id || undefined,
      });
      appStore.notify('Departamento actualizado', 'success');
    } else {
      await configStore.createDepartment({
        ...deptForm.value,
        site_id: deptForm.value.site_id || undefined,
      });
      appStore.notify('Departamento creado', 'success');
    }
    showDeptForm.value = false;
    editingDept.value = null;
    deptForm.value = { site_id: null, name: '', code: '', is_warehouse: false };
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error', 'error');
  }
};

const editDepartment = (dept: any) => {
  editingDept.value = dept.id;
  deptForm.value = {
    site_id: dept.site_id,
    name: dept.name,
    code: dept.code || '',
    is_warehouse: dept.is_warehouse || false,
  };
  showDeptForm.value = true;
};

const deleteDepartment = async (id: number) => {
  if (!confirm('¿Estás seguro de eliminar este departamento? No se puede eliminar si tiene impresoras o stock asociado.')) {
    return;
  }
  try {
    await configStore.deleteDepartment(id);
    appStore.notify('Departamento eliminado', 'success');
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al eliminar', 'error');
  }
};
</script>

<template>
  <div class="space-y-6">
    <div class="flex gap-2 border-b border-slate-200">
      <button
        @click="activeLevel = 'provinces'"
        class="px-4 py-2 font-semibold border-b-2 transition"
        :class="activeLevel === 'provinces' ? 'border-ada-primary text-ada-primary' : 'border-transparent text-slate-600'"
      >
        Provincias
      </button>
      <button
        @click="activeLevel = 'sites'"
        class="px-4 py-2 font-semibold border-b-2 transition"
        :class="activeLevel === 'sites' ? 'border-ada-primary text-ada-primary' : 'border-transparent text-slate-600'"
      >
        Sedes
      </button>
      <button
        @click="activeLevel = 'departments'"
        class="px-4 py-2 font-semibold border-b-2 transition"
        :class="activeLevel === 'departments' ? 'border-ada-primary text-ada-primary' : 'border-transparent text-slate-600'"
      >
        Departamentos
      </button>
    </div>

    <!-- Provincias -->
    <div v-if="activeLevel === 'provinces'">
      <div class="flex justify-between mb-4">
        <h3 class="text-lg font-semibold">Provincias</h3>
        <button
          @click="
            editingProvince = null;
            provinceForm = { name: '', code: '' };
            showProvinceForm = !showProvinceForm;
          "
          class="bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold text-sm"
        >
          + Nueva provincia
        </button>
      </div>
      <div v-if="showProvinceForm" class="border border-slate-200 rounded-lg p-4 mb-4 bg-slate-50">
        <h4 class="font-semibold mb-3">{{ editingProvince ? 'Editar provincia' : 'Nueva provincia' }}</h4>
        <div class="space-y-3">
          <input
            v-model="provinceForm.name"
            type="text"
            placeholder="Nombre"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
          />
          <input
            v-model="provinceForm.code"
            type="text"
            placeholder="Código (opcional)"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
          />
          <div class="flex gap-2">
            <button @click="saveProvince" class="flex-1 bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold">
              {{ editingProvince ? 'Actualizar' : 'Guardar' }}
            </button>
            <button
              @click="
                showProvinceForm = false;
                editingProvince = null;
                provinceForm = { name: '', code: '' };
              "
              class="px-4 py-2 rounded-lg border border-slate-300"
            >
              Cancelar
            </button>
          </div>
        </div>
      </div>
      <div class="space-y-2">
        <div
          v-for="province in configStore.provinces"
          :key="province.id"
          class="border border-slate-200 rounded-lg p-4 flex items-center justify-between"
        >
          <div>
            <p class="font-semibold">{{ province.name }}</p>
            <p class="text-sm text-slate-500">{{ province.sites_count || 0 }} sedes</p>
          </div>
          <div class="flex gap-2">
            <button
              @click="editProvince(province)"
              class="px-3 py-1 text-sm rounded-lg border border-slate-300 hover:bg-slate-50"
            >
              Editar
            </button>
            <button
              @click="deleteProvince(province.id)"
              class="px-3 py-1 text-sm rounded-lg border border-red-300 text-red-600 hover:bg-red-50"
            >
              Eliminar
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Sedes -->
    <div v-if="activeLevel === 'sites'">
      <div class="flex justify-between mb-4">
        <h3 class="text-lg font-semibold">Sedes</h3>
        <button
          @click="
            editingSite = null;
            siteForm = { province_id: null, name: '', code: '', address: '' };
            showSiteForm = !showSiteForm;
          "
          class="bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold text-sm"
        >
          + Nueva sede
        </button>
      </div>
      <div v-if="showSiteForm" class="border border-slate-200 rounded-lg p-4 mb-4 bg-slate-50">
        <h4 class="font-semibold mb-3">{{ editingSite ? 'Editar sede' : 'Nueva sede' }}</h4>
        <div class="space-y-3">
          <select
            v-model="siteForm.province_id"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
            required
          >
            <option :value="null">Seleccionar provincia</option>
            <option v-for="p in configStore.provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
          <input
            v-model="siteForm.name"
            type="text"
            placeholder="Nombre"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
          />
          <input
            v-model="siteForm.code"
            type="text"
            placeholder="Código (opcional)"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
          />
          <input
            v-model="siteForm.address"
            type="text"
            placeholder="Dirección (opcional)"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
          />
          <div class="flex gap-2">
            <button @click="saveSite" class="flex-1 bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold">
              {{ editingSite ? 'Actualizar' : 'Guardar' }}
            </button>
            <button
              @click="
                showSiteForm = false;
                editingSite = null;
                siteForm = { province_id: null, name: '', code: '', address: '' };
              "
              class="px-4 py-2 rounded-lg border border-slate-300"
            >
              Cancelar
            </button>
          </div>
        </div>
      </div>
      <div class="space-y-2">
        <div
          v-for="site in configStore.sites"
          :key="site.id"
          class="border border-slate-200 rounded-lg p-4 flex items-center justify-between"
        >
          <div>
            <p class="font-semibold">{{ site.name }}</p>
            <p class="text-sm text-slate-500">{{ site.province?.name }}</p>
          </div>
          <div class="flex gap-2">
            <button
              @click="editSite(site)"
              class="px-3 py-1 text-sm rounded-lg border border-slate-300 hover:bg-slate-50"
            >
              Editar
            </button>
            <button
              @click="deleteSite(site.id)"
              class="px-3 py-1 text-sm rounded-lg border border-red-300 text-red-600 hover:bg-red-50"
            >
              Eliminar
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Departamentos -->
    <div v-if="activeLevel === 'departments'">
      <div class="flex justify-between mb-4">
        <h3 class="text-lg font-semibold">Departamentos</h3>
        <button
          @click="
            editingDept = null;
            deptForm = { site_id: null, name: '', code: '', is_warehouse: false };
            showDeptForm = !showDeptForm;
          "
          class="bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold text-sm"
        >
          + Nuevo departamento
        </button>
      </div>
      <div v-if="showDeptForm" class="border border-slate-200 rounded-lg p-4 mb-4 bg-slate-50">
        <h4 class="font-semibold mb-3">{{ editingDept ? 'Editar departamento' : 'Nuevo departamento' }}</h4>
        <div class="space-y-3">
          <select
            v-model="deptForm.site_id"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
            required
          >
            <option :value="null">Seleccionar sede</option>
            <option v-for="s in configStore.sites" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
          <input
            v-model="deptForm.name"
            type="text"
            placeholder="Nombre"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
          />
          <input
            v-model="deptForm.code"
            type="text"
            placeholder="Código (opcional)"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
          />
          <label class="flex items-center gap-2">
            <input v-model="deptForm.is_warehouse" type="checkbox" />
            <span class="text-sm">Marcar como almacén</span>
          </label>
          <div class="flex gap-2">
            <button @click="saveDepartment" class="flex-1 bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold">
              {{ editingDept ? 'Actualizar' : 'Guardar' }}
            </button>
            <button
              @click="
                showDeptForm = false;
                editingDept = null;
                deptForm = { site_id: null, name: '', code: '', is_warehouse: false };
              "
              class="px-4 py-2 rounded-lg border border-slate-300"
            >
              Cancelar
            </button>
          </div>
        </div>
      </div>
      <div class="space-y-2">
        <div
          v-for="dept in configStore.departments"
          :key="dept.id"
          class="border border-slate-200 rounded-lg p-4 flex items-center justify-between"
        >
          <div>
            <p class="font-semibold">{{ dept.name }}</p>
            <p class="text-sm text-slate-500">
              {{ dept.site?.name }} {{ dept.is_warehouse ? '· Almacén' : '' }}
            </p>
          </div>
          <div class="flex gap-2">
            <button
              @click="editDepartment(dept)"
              class="px-3 py-1 text-sm rounded-lg border border-slate-300 hover:bg-slate-50"
            >
              Editar
            </button>
            <button
              @click="deleteDepartment(dept.id)"
              class="px-3 py-1 text-sm rounded-lg border border-red-300 text-red-600 hover:bg-red-50"
            >
              Eliminar
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

