<script setup lang="ts">
import { computed, ref, onMounted } from 'vue';
import { RouterLink, RouterView, useRoute } from 'vue-router';
import {
  HomeIcon,
  ChartBarIcon,
  BellAlertIcon,
  ClipboardDocumentListIcon,
  ArrowRightOnRectangleIcon,
  Bars3Icon,
  Cog6ToothIcon,
} from '@heroicons/vue/24/outline';
import { useAuthStore } from '@/stores/auth';
import { useAppStore } from '@/stores/app';
import { useConfigStore } from '@/stores/config';
import { usePermissions } from '@/composables/usePermissions';

const auth = useAuthStore();
const appStore = useAppStore();
const configStore = useConfigStore();
const route = useRoute();
const isMobileMenuOpen = ref(false);
const { canAccessPage } = usePermissions();

onMounted(() => {
  configStore.fetchLogos();
});

const activeLogo = computed(() => configStore.getActiveLogo('web'));
const logoUrl = computed(() => {
  if (!activeLogo.value) return null;
  return `/storage/${activeLogo.value.path}`;
});

const allNavigation = [
  { name: 'Dashboard', icon: HomeIcon, to: { name: 'dashboard' }, page: 'dashboard' },
  { name: 'Impresoras', icon: ChartBarIcon, to: { name: 'printers' }, page: 'printers' },
  { name: 'Referencias', icon: ClipboardDocumentListIcon, to: { name: 'references' }, page: 'references' },
  { name: 'Inventario', icon: ClipboardDocumentListIcon, to: { name: 'inventory' }, page: 'inventory' },
  { name: 'Pedidos', icon: ClipboardDocumentListIcon, to: { name: 'orders' }, page: 'orders' },
  { name: 'Entradas', icon: ClipboardDocumentListIcon, to: { name: 'order-entries' }, page: 'order-entries' },
  { name: 'Instalación de consumible', icon: ClipboardDocumentListIcon, to: { name: 'installations' }, page: 'installations' },
  { name: 'Alertas', icon: BellAlertIcon, to: { name: 'alerts' }, page: 'alerts' },
  { name: 'Registro de impresiones', icon: ChartBarIcon, to: { name: 'print-log' }, page: 'print-log' },
  { name: 'Configuración', icon: Cog6ToothIcon, to: { name: 'config' }, page: 'config' },
];

// Filtrar navegación según permisos del usuario
const navigation = computed(() => {
  return allNavigation.filter((item) => canAccessPage(item.page));
});

const activeTitle = computed(() => route.meta.title ?? 'Panel principal');

const logout = async () => {
  await auth.logout();
};
</script>

<template>
  <div class="min-h-screen bg-slate-100">
    <div class="flex">
      <aside
        class="hidden lg:flex sticky top-0 h-screen w-72 flex-col border-r border-slate-200 bg-white/90 backdrop-blur-xl"
      >
        <div class="px-6 py-8">
          <p class="text-sm uppercase tracking-[0.2em] text-ada-dark/70 font-semibold">
            Agencia Digital de Andalucía
          </p>
          <h1 class="text-2xl font-black text-ada-primary">JNC • AdaPrinters</h1>
        </div>
        <nav class="flex-1 px-4 space-y-2">
          <RouterLink
            v-for="item in navigation"
            :key="item.name"
            :to="item.to"
            class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium transition"
            :class="
              route.name === item.to.name
                ? 'bg-ada-primary text-white shadow-lg shadow-ada-primary/20'
                : 'text-slate-600 hover:bg-slate-100'
            "
          >
            <component :is="item.icon" class="h-5 w-5" />
            <span>{{ item.name }}</span>
          </RouterLink>
        </nav>
        <div class="px-6 py-6">
          <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50">
            <p class="text-xs text-slate-500 uppercase font-semibold">Sesión</p>
            <p class="text-sm font-medium text-slate-900">{{ auth.user?.name ?? 'Usuario' }}</p>
            <p class="text-xs text-slate-500">{{ auth.user?.email }}</p>
            <button
              class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
              @click="logout"
            >
              <ArrowRightOnRectangleIcon class="h-4 w-4" />
              Cerrar sesión
            </button>
          </div>
        </div>
      </aside>

      <div class="flex-1 min-h-screen">
        <header class="sticky top-0 z-10 border-b border-slate-200 bg-white/80 backdrop-blur-xl">
          <div class="flex items-center justify-between px-6 py-4">
            <div class="flex items-center gap-3">
              <button
                class="lg:hidden rounded-xl border border-slate-200 p-2"
                @click="isMobileMenuOpen = !isMobileMenuOpen"
              >
                <Bars3Icon class="h-5 w-5 text-slate-600" />
              </button>
              <div v-if="logoUrl" class="flex items-center gap-3">
                <img :src="logoUrl" :alt="activeLogo?.type" class="h-10 max-w-[200px] object-contain" />
              </div>
              <div>
                <p class="text-xs uppercase tracking-[0.3em] text-slate-400 font-semibold">Panel</p>
                <h2 class="text-2xl font-black text-slate-900">{{ activeTitle }}</h2>
              </div>
            </div>
            <div class="hidden lg:flex items-center gap-3">
              <div class="text-right">
                <p class="text-sm font-semibold text-slate-900">{{ auth.user?.name }}</p>
                <p class="text-xs text-slate-500">{{ auth.user?.email }}</p>
              </div>
              <button
                class="rounded-full border border-ada-primary/30 bg-ada-light px-4 py-2 text-sm font-semibold text-ada-primary"
                @click="logout"
              >
                Salir
              </button>
            </div>
          </div>
        </header>

        <main class="px-4 py-6 sm:px-6 lg:px-10">
          <RouterView />
        </main>
      </div>
    </div>

    <transition name="fade">
      <div
        v-if="isMobileMenuOpen"
        class="fixed inset-0 z-40 bg-slate-900/60 backdrop-blur-sm lg:hidden"
        @click="isMobileMenuOpen = false"
      >
        <div
          class="absolute left-0 top-0 h-full w-72 bg-white shadow-2xl p-6"
          @click.stop
        >
          <p class="text-sm uppercase tracking-[0.3em] text-ada-dark/70 font-semibold mb-4">
            Navegación
          </p>
          <nav class="space-y-2">
            <RouterLink
              v-for="item in navigation"
              :key="item.name"
              :to="item.to"
              class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition"
              :class="
                route.name === item.to.name
                  ? 'bg-ada-primary text-white shadow-lg shadow-ada-primary/20'
                  : 'text-slate-600 hover:bg-slate-100'
              "
              @click="isMobileMenuOpen = false"
            >
              <component :is="item.icon" class="h-5 w-5" />
              <span>{{ item.name }}</span>
            </RouterLink>
          </nav>
          <button
            class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
            @click="logout"
          >
            <ArrowRightOnRectangleIcon class="h-4 w-4" />
            Cerrar sesión
          </button>
        </div>
      </div>
    </transition>
  </div>

  <div class="fixed bottom-6 right-6 space-y-3 z-50">
    <div
      v-for="notification in appStore.notifications"
      :key="notification.id"
      class="rounded-2xl border px-4 py-3 shadow-lg backdrop-blur bg-white/90 min-w-[220px]"
      :class="{
        'border-emerald-200 text-emerald-700': notification.type === 'success',
        'border-rose-200 text-rose-600': notification.type === 'error',
        'border-slate-200 text-slate-700': notification.type === 'info',
      }"
    >
      <p class="text-sm font-semibold">{{ notification.message }}</p>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>

