<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useConfigStore } from '@/stores/config';
import { useAppStore } from '@/stores/app';

defineOptions({ name: 'ConfigUsersTab' });

const configStore = useConfigStore();
const appStore = useAppStore();
const showForm = ref(false);
const editingUser = ref<any>(null);
const showPermissions = ref(false);

// Páginas disponibles del menú
const availablePages = [
  { value: 'dashboard', label: 'Dashboard' },
  { value: 'printers', label: 'Impresoras' },
  { value: 'references', label: 'Referencias' },
  { value: 'inventory', label: 'Inventario' },
  { value: 'orders', label: 'Pedidos' },
  { value: 'order-entries', label: 'Entradas' },
  { value: 'installations', label: 'Instalación de consumible' },
  { value: 'alerts', label: 'Alertas' },
  { value: 'print-log', label: 'Registro de impresiones' },
  { value: 'config', label: 'Configuración' },
];

// Módulos disponibles para permisos de lectura/escritura
const availableModules = [
  { value: 'printers', label: 'Impresoras' },
  { value: 'references', label: 'Referencias' },
  { value: 'inventory', label: 'Inventario' },
  { value: 'orders', label: 'Pedidos' },
  { value: 'order-entries', label: 'Entradas' },
  { value: 'installations', label: 'Instalaciones' },
  { value: 'locations', label: 'Ubicaciones (Provincias, Sedes, Departamentos)' },
];

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'manager',
  page_permissions: [] as string[],
  location_permissions: {
    provinces: [] as number[],
    sites: [] as number[],
    departments: [] as number[],
  },
  read_write_permissions: [] as string[],
});

onMounted(async () => {
  await configStore.fetchUsers();
  await configStore.fetchProvinces();
  await configStore.fetchSites();
  await configStore.fetchDepartments();
});

const openForm = (user?: any) => {
  if (user) {
    editingUser.value = user;
    form.value = {
      ...user,
      password: '',
      password_confirmation: '',
      page_permissions: user.page_permissions || [],
      location_permissions: user.location_permissions || {
        provinces: [],
        sites: [],
        departments: [],
      },
      read_write_permissions: user.read_write_permissions || [],
    };
  } else {
    editingUser.value = null;
    form.value = {
      name: '',
      email: '',
      password: '',
      password_confirmation: '',
      role: 'manager',
      page_permissions: [],
      location_permissions: {
        provinces: [],
        sites: [],
        departments: [],
      },
      read_write_permissions: [],
    };
  }
  showForm.value = true;
  showPermissions.value = false;
};

const togglePagePermission = (page: string) => {
  const index = form.value.page_permissions.indexOf(page);
  if (index > -1) {
    form.value.page_permissions.splice(index, 1);
  } else {
    form.value.page_permissions.push(page);
  }
};

const toggleModulePermission = (module: string) => {
  const index = form.value.read_write_permissions.indexOf(module);
  if (index > -1) {
    form.value.read_write_permissions.splice(index, 1);
  } else {
    form.value.read_write_permissions.push(module);
  }
};

const toggleProvincePermission = (provinceId: number) => {
  const index = form.value.location_permissions.provinces.indexOf(provinceId);
  if (index > -1) {
    form.value.location_permissions.provinces.splice(index, 1);
    // Eliminar también las sedes y departamentos de esa provincia
    const provinceSites = configStore.sites.filter((s) => s.province_id === provinceId);
    provinceSites.forEach((site) => {
      const siteIndex = form.value.location_permissions.sites.indexOf(site.id);
      if (siteIndex > -1) {
        form.value.location_permissions.sites.splice(siteIndex, 1);
      }
      const siteDepartments = configStore.departments.filter((d) => d.site_id === site.id);
      siteDepartments.forEach((dept) => {
        const deptIndex = form.value.location_permissions.departments.indexOf(dept.id);
        if (deptIndex > -1) {
          form.value.location_permissions.departments.splice(deptIndex, 1);
        }
      });
    });
  } else {
    form.value.location_permissions.provinces.push(provinceId);
  }
};

const toggleSitePermission = (siteId: number) => {
  const index = form.value.location_permissions.sites.indexOf(siteId);
  if (index > -1) {
    form.value.location_permissions.sites.splice(index, 1);
    // Eliminar también los departamentos de esa sede
    const siteDepartments = configStore.departments.filter((d) => d.site_id === siteId);
    siteDepartments.forEach((dept) => {
      const deptIndex = form.value.location_permissions.departments.indexOf(dept.id);
      if (deptIndex > -1) {
        form.value.location_permissions.departments.splice(deptIndex, 1);
      }
    });
  } else {
    form.value.location_permissions.sites.push(siteId);
    // Añadir la provincia si no está
    const site = configStore.sites.find((s) => s.id === siteId);
    if (site && !form.value.location_permissions.provinces.includes(site.province_id)) {
      form.value.location_permissions.provinces.push(site.province_id);
    }
  }
};

const toggleDepartmentPermission = (departmentId: number) => {
  const index = form.value.location_permissions.departments.indexOf(departmentId);
  if (index > -1) {
    form.value.location_permissions.departments.splice(index, 1);
  } else {
    form.value.location_permissions.departments.push(departmentId);
    // Añadir la sede y provincia si no están
    const department = configStore.departments.find((d) => d.id === departmentId);
    if (department) {
      if (!form.value.location_permissions.sites.includes(department.site_id)) {
        form.value.location_permissions.sites.push(department.site_id);
      }
      const site = configStore.sites.find((s) => s.id === department.site_id);
      if (site && !form.value.location_permissions.provinces.includes(site.province_id)) {
        form.value.location_permissions.provinces.push(site.province_id);
      }
    }
  }
};

const sitesByProvince = computed(() => {
  const grouped: Record<number, typeof configStore.sites> = {};
  configStore.sites.forEach((site) => {
    if (!grouped[site.province_id]) {
      grouped[site.province_id] = [];
    }
    grouped[site.province_id].push(site);
  });
  return grouped;
});

const departmentsBySite = computed(() => {
  const grouped: Record<number, typeof configStore.departments> = {};
  configStore.departments.forEach((dept) => {
    if (!grouped[dept.site_id]) {
      grouped[dept.site_id] = [];
    }
    grouped[dept.site_id].push(dept);
  });
  return grouped;
});

const saveUser = async () => {
  try {
    const updates: any = {
      name: form.value.name,
      email: form.value.email,
      role: form.value.role,
      page_permissions: form.value.page_permissions.length > 0 ? form.value.page_permissions : null,
      location_permissions:
        form.value.location_permissions.provinces.length > 0 ||
        form.value.location_permissions.sites.length > 0 ||
        form.value.location_permissions.departments.length > 0
          ? form.value.location_permissions
          : null,
      read_write_permissions:
        form.value.read_write_permissions.length > 0 ? form.value.read_write_permissions : null,
    };
    if (form.value.password) {
      updates.password = form.value.password;
      updates.password_confirmation = form.value.password_confirmation;
    }
    if (editingUser.value) {
      await configStore.updateUser(editingUser.value.id, updates);
    } else {
      await configStore.createUser({ ...form.value, ...updates });
    }
    appStore.notify('Usuario guardado', 'success');
    showForm.value = false;
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error', 'error');
  }
};

const deleteUser = async (id: number) => {
  if (!confirm('¿Estás seguro de eliminar este usuario?')) return;
  try {
    await configStore.deleteUser(id);
    appStore.notify('Usuario eliminado', 'success');
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error', 'error');
  }
};
</script>

<template>
  <div class="space-y-6">
    <div class="flex justify-between">
      <h3 class="text-lg font-semibold">Usuarios</h3>
      <button
        @click="openForm()"
        class="bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold text-sm"
      >
        + Nuevo usuario
      </button>
    </div>

    <div v-if="showForm" class="border border-slate-200 rounded-xl p-6 bg-slate-50 space-y-6">
      <h3 class="text-lg font-semibold">{{ editingUser ? 'Editar' : 'Nuevo' }} usuario</h3>

      <!-- Información básica -->
      <div class="space-y-4">
        <h4 class="font-semibold text-slate-700">Información básica</h4>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Nombre</label>
          <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-300 px-4 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Email</label>
          <input v-model="form.email" type="email" class="w-full rounded-lg border border-slate-300 px-4 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Contraseña</label>
          <input v-model="form.password" type="password" class="w-full rounded-lg border border-slate-300 px-4 py-2" />
          <p class="text-xs text-slate-500 mt-1">Dejar en blanco para mantener la actual (solo al editar)</p>
        </div>
        <div v-if="!editingUser">
          <label class="block text-sm font-medium text-slate-700 mb-2">Confirmar contraseña</label>
          <input
            v-model="form.password_confirmation"
            type="password"
            class="w-full rounded-lg border border-slate-300 px-4 py-2"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Rol</label>
          <select v-model="form.role" class="w-full rounded-lg border border-slate-300 px-4 py-2">
            <option value="admin">Administrador</option>
            <option value="manager">Gestor</option>
            <option value="viewer">Visualizador</option>
          </select>
        </div>
      </div>

      <!-- Permisos -->
      <div class="border-t border-slate-200 pt-4">
        <button
          @click="showPermissions = !showPermissions"
          class="flex items-center justify-between w-full text-left font-semibold text-slate-700 mb-4"
        >
          <span>Permisos</span>
          <span class="text-sm">{{ showPermissions ? '▼' : '▶' }}</span>
        </button>

        <div v-if="showPermissions" class="space-y-6">
          <!-- Permisos de páginas -->
          <div>
            <h4 class="font-semibold text-slate-700 mb-3">Acceso a páginas (menús)</h4>
            <p class="text-sm text-slate-500 mb-3">
              Selecciona las páginas a las que el usuario tendrá acceso. Si no se selecciona ninguna, tendrá acceso a
              todas.
            </p>
            <div class="grid grid-cols-2 gap-2">
              <label
                v-for="page in availablePages"
                :key="page.value"
                class="flex items-center gap-2 p-2 rounded-lg border border-slate-200 hover:bg-slate-50 cursor-pointer"
              >
                <input
                  type="checkbox"
                  :checked="form.page_permissions.includes(page.value)"
                  @change="togglePagePermission(page.value)"
                  class="rounded border-slate-300"
                />
                <span class="text-sm">{{ page.label }}</span>
              </label>
            </div>
          </div>

          <!-- Permisos de ubicaciones -->
          <div>
            <h4 class="font-semibold text-slate-700 mb-3">Visualización de ubicaciones</h4>
            <p class="text-sm text-slate-500 mb-3">
              Selecciona las provincias, sedes y departamentos que el usuario podrá ver. Si no se selecciona ninguna,
              podrá ver todas.
            </p>
            <div class="space-y-4 max-h-96 overflow-y-auto border border-slate-200 rounded-lg p-4">
              <div v-for="province in configStore.provinces" :key="province.id" class="space-y-2">
                <label class="flex items-center gap-2 font-medium cursor-pointer">
                  <input
                    type="checkbox"
                    :checked="form.location_permissions.provinces.includes(province.id)"
                    @change="toggleProvincePermission(province.id)"
                    class="rounded border-slate-300"
                  />
                  <span>{{ province.name }} {{ province.code ? `(${province.code})` : '' }}</span>
                </label>
                <div v-if="sitesByProvince[province.id]" class="ml-6 space-y-2">
                  <div v-for="site in sitesByProvince[province.id]" :key="site.id" class="space-y-1">
                    <label class="flex items-center gap-2 text-sm cursor-pointer">
                      <input
                        type="checkbox"
                        :checked="form.location_permissions.sites.includes(site.id)"
                        @change="toggleSitePermission(site.id)"
                        class="rounded border-slate-300"
                      />
                      <span>{{ site.name }} {{ site.code ? `(${site.code})` : '' }}</span>
                    </label>
                    <div v-if="departmentsBySite[site.id]" class="ml-6 space-y-1">
                      <label
                        v-for="dept in departmentsBySite[site.id]"
                        :key="dept.id"
                        class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer"
                      >
                        <input
                          type="checkbox"
                          :checked="form.location_permissions.departments.includes(dept.id)"
                          @change="toggleDepartmentPermission(dept.id)"
                          class="rounded border-slate-300"
                        />
                        <span>{{ dept.name }} {{ dept.code ? `(${dept.code})` : '' }}</span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Permisos de lectura/escritura -->
          <div>
            <h4 class="font-semibold text-slate-700 mb-3">Permisos de lectura y escritura</h4>
            <p class="text-sm text-slate-500 mb-3">
              Selecciona los módulos donde el usuario podrá crear, modificar y eliminar. Si no se selecciona ninguno,
              solo tendrá permisos de lectura.
            </p>
            <div class="grid grid-cols-2 gap-2">
              <label
                v-for="module in availableModules"
                :key="module.value"
                class="flex items-center gap-2 p-2 rounded-lg border border-slate-200 hover:bg-slate-50 cursor-pointer"
              >
                <input
                  type="checkbox"
                  :checked="form.read_write_permissions.includes(module.value)"
                  @change="toggleModulePermission(module.value)"
                  class="rounded border-slate-300"
                />
                <span class="text-sm">{{ module.label }}</span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <div class="flex gap-2 pt-4 border-t border-slate-200">
        <button @click="saveUser" class="flex-1 bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold">
          Guardar
        </button>
        <button @click="showForm = false" class="px-4 py-2 rounded-lg border border-slate-300">Cancelar</button>
      </div>
    </div>

    <div class="space-y-2">
      <div
        v-for="user in configStore.users"
        :key="user.id"
        class="border border-slate-200 rounded-lg p-4 flex items-center justify-between"
      >
        <div>
          <p class="font-semibold">{{ user.name }}</p>
          <p class="text-sm text-slate-500">{{ user.email }}</p>
          <p class="text-xs text-slate-400 mt-1">
            Páginas: {{ user.page_permissions?.length || 'Todas' }} | Ubicaciones:
            {{ user.location_permissions ? 'Limitadas' : 'Todas' }} | Escritura:
            {{ user.read_write_permissions?.length || 0 }} módulos
          </p>
        </div>
        <div class="flex gap-2">
          <button
            @click="openForm(user)"
            class="px-3 py-1.5 text-sm text-ada-primary bg-ada-light rounded-lg hover:bg-ada-primary/10"
          >
            Editar
          </button>
          <button
            @click="deleteUser(user.id)"
            class="px-3 py-1.5 text-sm text-red-600 bg-red-50 rounded-lg hover:bg-red-100"
          >
            Eliminar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
