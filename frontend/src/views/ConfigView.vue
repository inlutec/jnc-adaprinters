<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useConfigStore } from '@/stores/config';
import ConfigLogosTab from '@/views/config/ConfigLogosTab.vue';
import ConfigCustomFieldsTab from '@/views/config/ConfigCustomFieldsTab.vue';
import ConfigSnmpOidsTab from '@/views/config/ConfigSnmpOidsTab.vue';
import ConfigSnmpDiscoveryTab from '@/views/config/ConfigSnmpDiscoveryTab.vue';
import ConfigLocationsTab from '@/views/config/ConfigLocationsTab.vue';
import ConfigNotificationsTab from '@/views/config/ConfigNotificationsTab.vue';
import ConfigUsersTab from '@/views/config/ConfigUsersTab.vue';
import ConfigPrinterImagesTab from '@/views/config/ConfigPrinterImagesTab.vue';

defineOptions({ name: 'ConfigView' });

const configStore = useConfigStore();
const activeTab = ref('logos');

const tabs = [
  { id: 'logos', name: 'Logos', icon: '🖼️' },
  { id: 'custom-fields', name: 'Campos Personalizados', icon: '📝' },
  { id: 'printer-images', name: 'Imágenes de Impresoras', icon: '🖨️' },
  { id: 'snmp-oids', name: 'Configuración SNMP', icon: '⚙️' },
  { id: 'snmp-discovery', name: 'Autodescubrimiento SNMP', icon: '🔍' },
  { id: 'locations', name: 'Provincias, Sedes y Departamentos', icon: '📍' },
  { id: 'notifications', name: 'Configuración de Notificaciones', icon: '📧' },
  { id: 'users', name: 'Usuarios', icon: '👥' },
];

onMounted(() => {
  configStore.fetchProvinces();
  configStore.fetchSites();
  configStore.fetchDepartments();
});
</script>

<template>
  <div class="space-y-6">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
      <h2 class="text-2xl font-bold text-slate-900 mb-2">Configuración</h2>
      <p class="text-slate-600">Gestiona la configuración general del sistema</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
      <div class="border-b border-slate-200">
        <nav class="flex overflow-x-auto scrollbar-hide" style="scrollbar-width: none; -ms-overflow-style: none;">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            class="px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition flex-shrink-0"
            :class="
              activeTab === tab.id
                ? 'border-ada-primary text-ada-primary'
                : 'border-transparent text-slate-600 hover:text-slate-900 hover:border-slate-300'
            "
          >
            <span class="mr-1.5">{{ tab.icon }}</span>
            <span class="hidden sm:inline">{{ tab.name }}</span>
            <span class="sm:hidden">{{ tab.name.split(' ')[0] }}</span>
          </button>
        </nav>
        <style>
          .scrollbar-hide::-webkit-scrollbar {
            display: none;
          }
        </style>
      </div>

      <div class="p-6">
        <ConfigLogosTab v-if="activeTab === 'logos'" />
        <ConfigCustomFieldsTab v-else-if="activeTab === 'custom-fields'" />
        <ConfigPrinterImagesTab v-else-if="activeTab === 'printer-images'" />
        <ConfigSnmpOidsTab v-else-if="activeTab === 'snmp-oids'" />
        <ConfigSnmpDiscoveryTab v-else-if="activeTab === 'snmp-discovery'" />
        <ConfigLocationsTab v-else-if="activeTab === 'locations'" />
        <ConfigNotificationsTab v-else-if="activeTab === 'notifications'" />
        <ConfigUsersTab v-else-if="activeTab === 'users'" />
      </div>
    </div>
  </div>
</template>

