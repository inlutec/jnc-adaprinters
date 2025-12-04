import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import AppShell from '@/layouts/AppShell.vue';
import DashboardView from '@/views/DashboardView.vue';
import LoginView from '@/views/LoginView.vue';
import InventoryView from '@/views/InventoryView.vue';
import AlertsView from '@/views/AlertsView.vue';
import PrintLogView from '@/views/PrintLogView.vue';
import ConfigView from '@/views/ConfigView.vue';
import PrintersView from '@/views/PrintersView.vue';
import ReferencesView from '@/views/ReferencesView.vue';
import OrdersView from '@/views/OrdersView.vue';
import OrderEntriesView from '@/views/OrderEntriesView.vue';
import InstallationsView from '@/views/InstallationsView.vue';
import { useAuthStore } from '@/stores/auth';

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: { guestOnly: true, title: 'Acceso - JNC AdaPrinters' },
  },
  {
    path: '/',
    component: AppShell,
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'dashboard',
        component: DashboardView,
        meta: { title: 'Panel principal' },
      },
      {
        path: 'printers',
        name: 'printers',
        component: PrintersView,
        meta: { title: 'Impresoras' },
      },
      {
        path: 'references',
        name: 'references',
        component: ReferencesView,
        meta: { title: 'Referencias' },
      },
      {
        path: 'inventory',
        name: 'inventory',
        component: InventoryView,
        meta: { title: 'Inventario' },
      },
      {
        path: 'orders',
        name: 'orders',
        component: OrdersView,
        meta: { title: 'Pedidos' },
      },
      {
        path: 'order-entries',
        name: 'order-entries',
        component: OrderEntriesView,
        meta: { title: 'Entradas de pedidos' },
      },
      {
        path: 'installations',
        name: 'installations',
        component: InstallationsView,
        meta: { title: 'Instalación de consumible' },
      },
      {
        path: 'alerts',
        name: 'alerts',
        component: AlertsView,
        meta: { title: 'Alertas' },
      },
      {
        path: 'print-log',
        name: 'print-log',
        component: PrintLogView,
        meta: { title: 'Registro de impresiones' },
      },
      {
        path: 'config',
        name: 'config',
        component: ConfigView,
        meta: { title: 'Configuración' },
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/',
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to, _, next) => {
  const auth = useAuthStore();

  if (!auth.initialized) {
    await auth.initialize();
  }

  document.title = `JNC AdaPrinters${to.meta.title ? ` · ${to.meta.title}` : ''}`;

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return next({ name: 'login', query: { redirect: to.fullPath } });
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return next({ name: 'dashboard' });
  }

  next();
});

export default router;

