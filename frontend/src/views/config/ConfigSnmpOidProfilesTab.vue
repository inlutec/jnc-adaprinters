<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { api } from '@/services/httpClient';
import { useAppStore } from '@/stores/app';
import SnmpWalkProfileCreator from '@/components/SnmpWalkProfileCreator.vue';

defineOptions({ name: 'ConfigSnmpOidProfilesTab' });

const appStore = useAppStore();
const profiles = ref<any[]>([]);
const availableOids = ref<any[]>([]);
const loading = ref(false);
const showProfileModal = ref(false);
const showAddOidModal = ref(false);
const showCreateOidModal = ref(false);
const showWalkCreatorModal = ref(false);
const selectedProfile = ref<any>(null);
const testingOidInProfile = ref<number | null>(null);
const testOidIpInProfile = ref('');
const testOidResultInProfile = ref<any>(null);

const profileForm = ref({
  name: '',
  brand: '',
  model: '',
  description: '',
  is_default: false,
  is_active: true,
  oid_ids: [] as number[],
});

const addOidForm = ref({
  oid_id: null as number | null,
  test_ip: '',
  test_result: null as any,
  testing: false,
});

const createOidForm = ref({
  oid: '',
  name: '',
  description: '',
  category: 'counter',
  data_type: 'integer',
  unit: '',
  test_ip: '',
  test_result: null as any,
  testing: false,
  creating: false,
});

onMounted(async () => {
  await fetchProfiles();
  await fetchAvailableOids();
});

const fetchProfiles = async () => {
  loading.value = true;
  try {
    const { data } = await api.get('/snmp-oid-profiles');
    profiles.value = data;
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al cargar perfiles', 'error');
  } finally {
    loading.value = false;
  }
};

const fetchAvailableOids = async () => {
  try {
    const { data } = await api.get('/snmp-oid-profiles/available-oids');
    availableOids.value = data;
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al cargar OIDs', 'error');
  }
};

const openProfileModal = (profile: any = null) => {
  if (profile) {
    selectedProfile.value = profile;
    profileForm.value = {
      name: profile.name,
      brand: profile.brand || '',
      model: profile.model || '',
      description: profile.description || '',
      is_default: profile.is_default || false,
      is_active: profile.is_active !== false,
      oid_ids: profile.oids?.map((o: any) => o.id) || [],
    };
  } else {
    selectedProfile.value = null;
    profileForm.value = {
      name: '',
      brand: '',
      model: '',
      description: '',
      is_default: false,
      is_active: true,
      oid_ids: [],
    };
  }
  showProfileModal.value = true;
};

const openWalkCreator = () => {
  showWalkCreatorModal.value = true;
};

const onProfileCreatedFromWalk = async () => {
  showWalkCreatorModal.value = false;
  await fetchProfiles();
  await fetchAvailableOids();
};

const saveProfile = async () => {
  if (!profileForm.value.name.trim()) {
    appStore.notify('El nombre es obligatorio', 'error');
    return;
  }

  loading.value = true;
  try {
    if (selectedProfile.value) {
      await api.put(`/snmp-oid-profiles/${selectedProfile.value.id}`, profileForm.value);
      appStore.notify('Perfil actualizado correctamente', 'success');
    } else {
      await api.post('/snmp-oid-profiles', profileForm.value);
      appStore.notify('Perfil creado correctamente', 'success');
    }
    showProfileModal.value = false;
    await fetchProfiles();
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al guardar perfil', 'error');
  } finally {
    loading.value = false;
  }
};

const deleteProfile = async (profile: any) => {
  if (!confirm(`¿Estás seguro de eliminar el perfil "${profile.name}"?`)) {
    return;
  }

  loading.value = true;
  try {
    await api.delete(`/snmp-oid-profiles/${profile.id}`);
    appStore.notify('Perfil eliminado correctamente', 'success');
    await fetchProfiles();
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al eliminar perfil', 'error');
  } finally {
    loading.value = false;
  }
};

const openAddOidModal = (profile: any) => {
  selectedProfile.value = profile;
  addOidForm.value = {
    oid_id: null,
    test_ip: '',
    test_result: null,
    testing: false,
  };
  showAddOidModal.value = true;
};

const openCreateOidModal = (profile: any) => {
  selectedProfile.value = profile;
  createOidForm.value = {
    oid: '',
    name: '',
    description: '',
    category: 'counter',
    data_type: 'integer',
    unit: '',
    test_ip: '',
    test_result: null,
    testing: false,
    creating: false,
  };
  showCreateOidModal.value = true;
};

const testOid = async () => {
  if (!addOidForm.value.oid_id) {
    appStore.notify('Selecciona un OID primero', 'error');
    return;
  }

  if (!addOidForm.value.test_ip.trim()) {
    appStore.notify('Introduce una IP para probar', 'error');
    return;
  }

  const oid = availableOids.value.find((o: any) => o.id === addOidForm.value.oid_id);
  if (!oid) {
    appStore.notify('OID no encontrado', 'error');
    return;
  }

  addOidForm.value.testing = true;
  addOidForm.value.test_result = null;

  try {
    const { data } = await api.post('/snmp-oid-profiles/test-oid', {
      ip: addOidForm.value.test_ip,
      oid: oid.oid,
    });

    addOidForm.value.test_result = data;
    
    if (data.success) {
      appStore.notify('OID probado correctamente', 'success');
    } else {
      appStore.notify(data.error || 'Error al probar OID', 'error');
    }
  } catch (error: any) {
    addOidForm.value.test_result = {
      success: false,
      error: error.response?.data?.message || 'Error al probar OID',
    };
    appStore.notify('Error al probar OID', 'error');
  } finally {
    addOidForm.value.testing = false;
  }
};

const testOidInProfile = async (oid: any) => {
  if (!testOidIpInProfile.value.trim()) {
    appStore.notify('Introduce una IP para probar', 'error');
    return;
  }

  testingOidInProfile.value = oid.id;
  testOidResultInProfile.value = null;

  try {
    const { data } = await api.post('/snmp-oid-profiles/test-oid', {
      ip: testOidIpInProfile.value,
      oid: oid.oid,
    });

    testOidResultInProfile.value = { ...data, oid_id: oid.id };
    
    if (data.success) {
      appStore.notify('OID probado correctamente', 'success');
    } else {
      appStore.notify(data.error || 'Error al probar OID', 'error');
    }
  } catch (error: any) {
    testOidResultInProfile.value = {
      success: false,
      error: error.response?.data?.message || 'Error al probar OID',
      oid_id: oid.id,
    };
    appStore.notify('Error al probar OID', 'error');
  } finally {
    testingOidInProfile.value = null;
  }
};

const testNewOid = async () => {
  if (!createOidForm.value.oid.trim()) {
    appStore.notify('Introduce un OID primero', 'error');
    return;
  }

  if (!createOidForm.value.test_ip.trim()) {
    appStore.notify('Introduce una IP para probar', 'error');
    return;
  }

  createOidForm.value.testing = true;
  createOidForm.value.test_result = null;

  try {
    const { data } = await api.post('/snmp-oid-profiles/test-oid', {
      ip: createOidForm.value.test_ip,
      oid: createOidForm.value.oid,
    });

    createOidForm.value.test_result = data;
    
    if (data.success) {
      appStore.notify('OID probado correctamente', 'success');
    } else {
      appStore.notify(data.error || 'Error al probar OID', 'error');
    }
  } catch (error: any) {
    createOidForm.value.test_result = {
      success: false,
      error: error.response?.data?.message || 'Error al probar OID',
    };
    appStore.notify('Error al probar OID', 'error');
  } finally {
    createOidForm.value.testing = false;
  }
};

const createAndAddOid = async () => {
  if (!createOidForm.value.oid.trim() || !createOidForm.value.name.trim()) {
    appStore.notify('OID y nombre son obligatorios', 'error');
    return;
  }

  createOidForm.value.creating = true;

  try {
    // Crear el OID
    const { data: newOid } = await api.post('/config/snmp-oids', {
      oid: createOidForm.value.oid,
      name: createOidForm.value.name,
      description: createOidForm.value.description || null,
      category: createOidForm.value.category,
      data_type: createOidForm.value.data_type,
      unit: createOidForm.value.unit || null,
    });

    appStore.notify('OID creado correctamente', 'success');

    // Añadirlo al perfil
    if (selectedProfile.value) {
      await api.post(`/snmp-oid-profiles/${selectedProfile.value.id}/add-oid`, {
        oid_id: newOid.id,
        order: selectedProfile.value.oids?.length || 0,
        is_required: false,
      });

      appStore.notify('OID creado y añadido al perfil', 'success');
      showCreateOidModal.value = false;
      await fetchProfiles();
    } else {
      // Si no hay perfil seleccionado, solo crear el OID
      await fetchAvailableOids();
      showCreateOidModal.value = false;
    }

    // Resetear formulario
    createOidForm.value = {
      oid: '',
      name: '',
      description: '',
      category: 'counter',
      data_type: 'integer',
      unit: '',
      test_ip: '',
      test_result: null,
      testing: false,
      creating: false,
    };
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al crear OID', 'error');
  } finally {
    createOidForm.value.creating = false;
  }
};

const addOidToProfile = async () => {
  if (!addOidForm.value.oid_id) {
    appStore.notify('Selecciona un OID', 'error');
    return;
  }

  if (!selectedProfile.value) {
    return;
  }

  loading.value = true;
  try {
    await api.post(`/snmp-oid-profiles/${selectedProfile.value.id}/add-oid`, {
      oid_id: addOidForm.value.oid_id,
      order: selectedProfile.value.oids?.length || 0,
      is_required: false,
    });
    appStore.notify('OID añadido al perfil', 'success');
    showAddOidModal.value = false;
    await fetchProfiles();
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al añadir OID', 'error');
  } finally {
    loading.value = false;
  }
};

const removeOidFromProfile = async (profile: any, oid: any) => {
  if (!confirm(`¿Estás seguro de eliminar el OID "${oid.name}" del perfil?`)) {
    return;
  }

  loading.value = true;
  try {
    await api.post(`/snmp-oid-profiles/${profile.id}/remove-oid`, {
      oid_id: oid.id,
    });
    appStore.notify('OID eliminado del perfil', 'success');
    await fetchProfiles();
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error al eliminar OID', 'error');
  } finally {
    loading.value = false;
  }
};

const filteredOids = computed(() => {
  if (!selectedProfile.value) return availableOids.value;
  const profileOidIds = selectedProfile.value.oids?.map((o: any) => o.id) || [];
  return availableOids.value.filter((o: any) => !profileOidIds.includes(o.id));
});
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-lg font-semibold text-slate-900">Perfiles OID</h3>
        <p class="text-sm text-slate-600 mt-1">
          Gestiona perfiles de OIDs para diferentes modelos de impresoras
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          @click="openWalkCreator()"
          class="bg-green-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-green-700"
        >
          + Crear con SNMP walk
        </button>
        <button
          @click="openProfileModal()"
          class="bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark"
        >
          + Nuevo Perfil
        </button>
      </div>
    </div>

    <div v-if="loading && profiles.length === 0" class="text-center py-8 text-slate-500">
      Cargando perfiles...
    </div>

    <div v-else-if="profiles.length === 0" class="text-center py-8 text-slate-500">
      No hay perfiles creados. Crea uno para empezar.
    </div>

    <div v-else class="space-y-4">
      <div
        v-for="profile in profiles"
        :key="profile.id"
        class="border border-slate-200 rounded-xl p-6 bg-white"
      >
        <div class="flex items-start justify-between mb-4">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-2">
              <h4 class="text-lg font-semibold text-slate-900">{{ profile.name }}</h4>
              <span
                v-if="profile.is_default"
                class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded font-semibold"
              >
                Por defecto
              </span>
              <span
                v-if="!profile.is_active"
                class="px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded font-semibold"
              >
                Inactivo
              </span>
            </div>
            <div v-if="profile.brand || profile.model" class="text-sm text-slate-600 mb-2">
              <span v-if="profile.brand">{{ profile.brand }}</span>
              <span v-if="profile.brand && profile.model"> - </span>
              <span v-if="profile.model">{{ profile.model }}</span>
            </div>
            <p v-if="profile.description" class="text-sm text-slate-600">{{ profile.description }}</p>
          </div>
          <div class="flex gap-2">
            <button
              @click="openAddOidModal(profile)"
              class="px-3 py-1.5 text-sm bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200"
            >
              + Añadir OID
            </button>
            <button
              @click="openCreateOidModal(profile)"
              class="px-3 py-1.5 text-sm bg-green-100 text-green-700 rounded-lg hover:bg-green-200"
            >
              + Crear OID
            </button>
            <button
              @click="openProfileModal(profile)"
              class="px-3 py-1.5 text-sm bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200"
            >
              Editar
            </button>
            <button
              @click="deleteProfile(profile)"
              class="px-3 py-1.5 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200"
            >
              Eliminar
            </button>
          </div>
        </div>

        <div v-if="profile.oids && profile.oids.length > 0" class="mt-4">
          <div class="flex items-center justify-between mb-2">
            <p class="text-xs text-slate-500 uppercase font-semibold">OIDs del perfil ({{ profile.oids.length }})</p>
            <div class="flex gap-2">
              <input
                v-model="testOidIpInProfile"
                type="text"
                placeholder="IP para probar (ej: 10.64.130.34)"
                class="text-xs px-2 py-1 rounded border border-slate-300 w-48"
              />
            </div>
          </div>
          <div class="space-y-2">
            <div
              v-for="oid in profile.oids"
              :key="oid.id"
              class="p-3 bg-slate-50 rounded-lg"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <p class="text-sm font-semibold text-slate-900">{{ oid.name }}</p>
                  <p class="text-xs font-mono text-slate-600 mt-1">{{ oid.oid }}</p>
                  <p v-if="oid.description" class="text-xs text-slate-500 mt-1">{{ oid.description }}</p>
                  
                  <!-- Resultado de prueba -->
                  <div v-if="testOidResultInProfile && testOidResultInProfile.oid_id === oid.id" class="mt-2 p-2 rounded text-xs" :class="testOidResultInProfile.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
                    <div class="flex items-start gap-2">
                      <span>{{ testOidResultInProfile.success ? '✅' : '❌' }}</span>
                      <div class="flex-1">
                        <p class="font-semibold" :class="testOidResultInProfile.success ? 'text-green-800' : 'text-red-800'">
                          {{ testOidResultInProfile.success ? 'OID respondió correctamente' : 'Error al probar OID' }}
                        </p>
                        <div v-if="testOidResultInProfile.success" class="mt-1">
                          <p class="text-green-700">
                            <span class="font-semibold">Valor:</span> {{ testOidResultInProfile.formatted_value || testOidResultInProfile.clean_value }}
                          </p>
                          <p v-if="testOidResultInProfile.raw_value" class="text-green-600 font-mono">
                            <span class="font-semibold">Raw:</span> {{ testOidResultInProfile.raw_value }}
                          </p>
                        </div>
                        <p v-else class="text-red-700 mt-1">
                          {{ testOidResultInProfile.error }}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="ml-4 flex gap-2">
                  <button
                    @click="testOidInProfile(oid)"
                    :disabled="testingOidInProfile === oid.id || !testOidIpInProfile.trim()"
                    class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200 disabled:opacity-50"
                  >
                    {{ testingOidInProfile === oid.id ? 'Probando...' : 'Probar' }}
                  </button>
                  <button
                    @click="removeOidFromProfile(profile, oid)"
                    class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200"
                  >
                    Eliminar
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="mt-4 text-sm text-slate-500 italic">
          No hay OIDs en este perfil
        </div>
      </div>
    </div>

    <!-- Modal de perfil -->
    <div
      v-if="showProfileModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showProfileModal = false"
    >
      <div class="bg-white rounded-xl p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-semibold mb-4">
          {{ selectedProfile ? 'Editar Perfil' : 'Nuevo Perfil' }}
        </h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Nombre *</label>
            <input
              v-model="profileForm.name"
              type="text"
              class="w-full rounded-lg border border-slate-300 px-4 py-2"
              placeholder="Ej: Ricoh IM C4500"
            />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Marca</label>
              <input
                v-model="profileForm.brand"
                type="text"
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
                placeholder="Ej: Ricoh"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Modelo</label>
              <input
                v-model="profileForm.model"
                type="text"
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
                placeholder="Ej: IM C4500"
              />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Descripción</label>
            <textarea
              v-model="profileForm.description"
              rows="3"
              class="w-full rounded-lg border border-slate-300 px-4 py-2"
              placeholder="Descripción del perfil..."
            />
          </div>
          <div class="flex items-center gap-4">
            <label class="flex items-center gap-2">
              <input
                v-model="profileForm.is_default"
                type="checkbox"
                class="rounded border-slate-300"
              />
              <span class="text-sm text-slate-700">Perfil por defecto</span>
            </label>
            <label class="flex items-center gap-2">
              <input
                v-model="profileForm.is_active"
                type="checkbox"
                class="rounded border-slate-300"
              />
              <span class="text-sm text-slate-700">Activo</span>
            </label>
          </div>
          <div class="flex gap-2">
            <button
              @click="saveProfile"
              :disabled="loading || !profileForm.name.trim()"
              class="flex-1 bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark disabled:opacity-50"
            >
              {{ loading ? 'Guardando...' : 'Guardar' }}
            </button>
            <button
              @click="showProfileModal = false"
              class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50"
            >
              Cancelar
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de añadir OID -->
    <div
      v-if="showAddOidModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showAddOidModal = false"
    >
      <div class="bg-white rounded-xl p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-semibold mb-4">Añadir OID al perfil</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Seleccionar OID *</label>
            <select
              v-model="addOidForm.oid_id"
              class="w-full rounded-lg border border-slate-300 px-4 py-2"
            >
              <option :value="null">Seleccionar OID...</option>
              <option
                v-for="oid in filteredOids"
                :key="oid.id"
                :value="oid.id"
              >
                {{ oid.name }} ({{ oid.oid }})
              </option>
            </select>
          </div>

          <!-- Sección de prueba de OID -->
          <div v-if="addOidForm.oid_id" class="border-t border-slate-200 pt-4 space-y-4">
            <h4 class="text-sm font-semibold text-slate-700">Probar OID</h4>
            <div class="flex gap-2">
              <input
                v-model="addOidForm.test_ip"
                type="text"
                placeholder="IP de impresora (ej: 10.64.130.34)"
                class="flex-1 rounded-lg border border-slate-300 px-4 py-2"
              />
              <button
                @click="testOid"
                :disabled="addOidForm.testing || !addOidForm.test_ip.trim()"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 disabled:opacity-50"
              >
                {{ addOidForm.testing ? 'Probando...' : 'Probar' }}
              </button>
            </div>

            <!-- Resultado de la prueba -->
            <div v-if="addOidForm.test_result" class="p-4 rounded-lg" :class="addOidForm.test_result.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
              <div class="flex items-start gap-2">
                <span class="text-lg">{{ addOidForm.test_result.success ? '✅' : '❌' }}</span>
                <div class="flex-1">
                  <p class="text-sm font-semibold" :class="addOidForm.test_result.success ? 'text-green-800' : 'text-red-800'">
                    {{ addOidForm.test_result.success ? 'OID respondió correctamente' : 'Error al probar OID' }}
                  </p>
                  <div v-if="addOidForm.test_result.success" class="mt-2 space-y-1">
                    <p class="text-xs text-green-700">
                      <span class="font-semibold">Valor:</span> {{ addOidForm.test_result.formatted_value || addOidForm.test_result.clean_value }}
                    </p>
                    <p v-if="addOidForm.test_result.raw_value" class="text-xs text-green-600 font-mono">
                      <span class="font-semibold">Raw:</span> {{ addOidForm.test_result.raw_value }}
                    </p>
                  </div>
                  <p v-else class="text-xs text-red-700 mt-1">
                    {{ addOidForm.test_result.error }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="flex gap-2 pt-4 border-t border-slate-200">
            <button
              @click="addOidToProfile"
              :disabled="loading || !addOidForm.oid_id"
              class="flex-1 bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-ada-dark disabled:opacity-50"
            >
              {{ loading ? 'Añadiendo...' : 'Añadir OID' }}
            </button>
            <button
              @click="showAddOidModal = false"
              class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50"
            >
              Cancelar
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de crear OID -->
    <div
      v-if="showCreateOidModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showCreateOidModal = false"
    >
      <div class="bg-white rounded-xl p-6 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-semibold mb-4">Crear nuevo OID y añadirlo al perfil</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">OID *</label>
            <input
              v-model="createOidForm.oid"
              type="text"
              placeholder="Ej: 1.3.6.1.4.1.367.3.2.1.2.19.5.1.9.1"
              class="w-full rounded-lg border border-slate-300 px-4 py-2 font-mono text-sm"
            />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Nombre *</label>
              <input
                v-model="createOidForm.name"
                type="text"
                placeholder="Ej: Total páginas Ricoh"
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Categoría *</label>
              <select
                v-model="createOidForm.category"
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
              >
                <option value="counter">Contador</option>
                <option value="consumable">Consumible</option>
                <option value="status">Estado</option>
                <option value="environment">Ambiente</option>
                <option value="system">Sistema</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Descripción</label>
            <textarea
              v-model="createOidForm.description"
              rows="2"
              class="w-full rounded-lg border border-slate-300 px-4 py-2"
              placeholder="Descripción del OID..."
            />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Tipo de dato</label>
              <select
                v-model="createOidForm.data_type"
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
              >
                <option value="integer">Integer</option>
                <option value="string">String</option>
                <option value="gauge">Gauge</option>
                <option value="counter">Counter</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-2">Unidad</label>
              <input
                v-model="createOidForm.unit"
                type="text"
                placeholder="Ej: páginas, %, etc."
                class="w-full rounded-lg border border-slate-300 px-4 py-2"
              />
            </div>
          </div>

          <!-- Sección de prueba de OID -->
          <div class="border-t border-slate-200 pt-4 space-y-4">
            <h4 class="text-sm font-semibold text-slate-700">Probar OID antes de crear</h4>
            <div class="flex gap-2">
              <input
                v-model="createOidForm.test_ip"
                type="text"
                placeholder="IP de impresora (ej: 10.64.130.34)"
                class="flex-1 rounded-lg border border-slate-300 px-4 py-2"
              />
              <button
                @click="testNewOid"
                :disabled="createOidForm.testing || !createOidForm.test_ip.trim() || !createOidForm.oid.trim()"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg font-semibold hover:bg-blue-700 disabled:opacity-50"
              >
                {{ createOidForm.testing ? 'Probando...' : 'Probar' }}
              </button>
            </div>

            <!-- Resultado de la prueba -->
            <div v-if="createOidForm.test_result" class="p-4 rounded-lg" :class="createOidForm.test_result.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
              <div class="flex items-start gap-2">
                <span class="text-lg">{{ createOidForm.test_result.success ? '✅' : '❌' }}</span>
                <div class="flex-1">
                  <p class="text-sm font-semibold" :class="createOidForm.test_result.success ? 'text-green-800' : 'text-red-800'">
                    {{ createOidForm.test_result.success ? 'OID respondió correctamente' : 'Error al probar OID' }}
                  </p>
                  <div v-if="createOidForm.test_result.success" class="mt-2 space-y-1">
                    <p class="text-xs text-green-700">
                      <span class="font-semibold">Valor:</span> {{ createOidForm.test_result.formatted_value || createOidForm.test_result.clean_value }}
                    </p>
                    <p v-if="createOidForm.test_result.raw_value" class="text-xs text-green-600 font-mono">
                      <span class="font-semibold">Raw:</span> {{ createOidForm.test_result.raw_value }}
                    </p>
                  </div>
                  <p v-else class="text-xs text-red-700 mt-1">
                    {{ createOidForm.test_result.error }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="flex gap-2 pt-4 border-t border-slate-200">
            <button
              @click="createAndAddOid"
              :disabled="createOidForm.creating || !createOidForm.oid.trim() || !createOidForm.name.trim()"
              class="flex-1 bg-green-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-green-700 disabled:opacity-50"
            >
              {{ createOidForm.creating ? 'Creando...' : 'Crear y añadir OID' }}
            </button>
            <button
              @click="showCreateOidModal = false"
              class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50"
            >
              Cancelar
            </button>
          </div>
        </div>
      </div>
    </div>

    <SnmpWalkProfileCreator
      v-model="showWalkCreatorModal"
      @created="onProfileCreatedFromWalk"
    />
  </div>
</template>

