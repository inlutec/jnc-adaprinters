<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useConfigStore } from '@/stores/config';
import { useAppStore } from '@/stores/app';

defineOptions({ name: 'ConfigNotificationsTab' });

const configStore = useConfigStore();
const appStore = useAppStore();
const showForm = ref(false);
const editingConfig = ref<any>(null);
const testing = ref(false);

const form = ref({
  type: 'email',
  name: '',
  smtp_host: '',
  smtp_port: 587,
  smtp_username: '',
  smtp_password: '',
  smtp_encryption: 'tls',
  from_address: '',
  from_name: '',
  recipients: [] as string[],
  is_active: true,
});

onMounted(() => {
  configStore.fetchNotificationConfigs();
});

const openForm = (config?: any) => {
  if (config) {
    editingConfig.value = config;
    form.value = { ...config, recipients: config.recipients || [] };
  } else {
    editingConfig.value = null;
    form.value = {
      type: 'email',
      name: '',
      smtp_host: '',
      smtp_port: 587,
      smtp_username: '',
      smtp_password: '',
      smtp_encryption: 'tls',
      from_address: '',
      from_name: '',
      recipients: [],
      is_active: true,
    };
  }
  showForm.value = true;
};

const saveConfig = async () => {
  try {
    if (editingConfig.value) {
      await configStore.updateNotificationConfig(editingConfig.value.id, form.value);
    } else {
      await configStore.createNotificationConfig(form.value);
    }
    appStore.notify('Configuración guardada', 'success');
    showForm.value = false;
  } catch (error: any) {
    appStore.notify(error.response?.data?.message || 'Error', 'error');
  }
};

const testConnection = async (id: number) => {
  testing.value = true;
  try {
    const result = await configStore.testNotificationConfig(id);
    appStore.notify(result.message, result.success ? 'success' : 'error');
  } catch (error: any) {
    appStore.notify('Error al probar conexión', 'error');
  } finally {
    testing.value = false;
  }
};
</script>

<template>
  <div class="space-y-6">
    <div class="flex justify-between">
      <h3 class="text-lg font-semibold">Configuración de notificaciones</h3>
      <button
        @click="openForm()"
        class="bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold text-sm"
      >
        + Nueva configuración
      </button>
    </div>

    <div v-if="showForm" class="border border-slate-200 rounded-xl p-6 bg-slate-50">
      <h3 class="text-lg font-semibold mb-4">{{ editingConfig ? 'Editar' : 'Nueva' }} configuración</h3>
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Nombre</label>
          <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-300 px-4 py-2" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">SMTP Host</label>
            <input v-model="form.smtp_host" type="text" class="w-full rounded-lg border border-slate-300 px-4 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">SMTP Puerto</label>
            <input v-model.number="form.smtp_port" type="number" class="w-full rounded-lg border border-slate-300 px-4 py-2" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Usuario</label>
            <input v-model="form.smtp_username" type="text" class="w-full rounded-lg border border-slate-300 px-4 py-2" />
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Contraseña</label>
            <input v-model="form.smtp_password" type="password" class="w-full rounded-lg border border-slate-300 px-4 py-2" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-2">Email remitente</label>
          <input v-model="form.from_address" type="email" class="w-full rounded-lg border border-slate-300 px-4 py-2" />
        </div>
        <div class="flex gap-2">
          <button @click="saveConfig" class="flex-1 bg-ada-primary text-white px-4 py-2 rounded-lg font-semibold">
            Guardar
          </button>
          <button @click="showForm = false" class="px-4 py-2 rounded-lg border border-slate-300">
            Cancelar
          </button>
        </div>
      </div>
    </div>

    <div class="space-y-2">
      <div
        v-for="config in configStore.notificationConfigs"
        :key="config.id"
        class="border border-slate-200 rounded-lg p-4 flex items-center justify-between"
      >
        <div>
          <p class="font-semibold">{{ config.name }}</p>
          <p class="text-sm text-slate-500">{{ config.from_address }}</p>
        </div>
        <div class="flex gap-2">
          <button
            @click="testConnection(config.id)"
            :disabled="testing"
            class="px-3 py-1.5 text-sm text-ada-primary bg-ada-light rounded-lg hover:bg-ada-primary/10"
          >
            Probar
          </button>
          <button
            @click="openForm(config)"
            class="px-3 py-1.5 text-sm text-ada-primary bg-ada-light rounded-lg hover:bg-ada-primary/10"
          >
            Editar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

