# Arquitectura Frontend - JNC-AdaPrinters

Documentación de la arquitectura del frontend Vue 3.

## Tabla de Contenidos

1. [Stack Tecnológico](#stack-tecnológico)
2. [Estructura de Carpetas](#estructura-de-carpetas)
3. [Patrones de Diseño](#patrones-de-diseño)
4. [Routing](#routing)
5. [Gestión de Estado](#gestión-de-estado)
6. [Servicios](#servicios)

## Stack Tecnológico

- **Vue 3.5.24** - Framework JavaScript progresivo
- **Vite 7.2.4** - Build tool y dev server
- **TypeScript 5.9.3** - Tipado estático
- **Pinia 3.0.4** - Gestión de estado
- **Vue Router 4.6.3** - Routing SPA
- **Tailwind CSS 3.4.15** - Framework CSS utility-first
- **Axios 1.13.2** - Cliente HTTP
- **Chart.js 4.4.7** - Gráficos
- **Vue Chart.js 5.3.3** - Wrapper Vue para Chart.js
- **@headlessui/vue 1.7.23** - Componentes UI sin estilos
- **@heroicons/vue 2.2.0** - Iconos SVG
- **@vueuse/core 14.1.0** - Utilidades composables
- **date-fns 4.1.0** - Manipulación de fechas

## Estructura de Carpetas

```
frontend/
├── src/
│   ├── assets/           # Recursos estáticos (imágenes, etc.)
│   ├── components/        # Componentes reutilizables
│   │   ├── ConsumableBar.vue
│   │   ├── ConsumableLevel.vue
│   │   └── PrinterCustomFieldsModal.vue
│   ├── composables/       # Composables Vue (hooks)
│   ├── layouts/           # Layouts de la aplicación
│   │   └── AppShell.vue   # Layout principal
│   ├── router/            # Configuración de rutas
│   │   └── index.ts
│   ├── services/          # Servicios (API, etc.)
│   │   └── httpClient.ts  # Cliente Axios configurado
│   ├── stores/            # Stores de Pinia
│   │   ├── alerts.ts
│   │   ├── app.ts
│   │   ├── auth.ts
│   │   ├── config.ts
│   │   ├── dashboard.ts
│   │   ├── installations.ts
│   │   ├── inventory.ts
│   │   ├── orders.ts
│   │   ├── printers.ts
│   │   ├── printLogs.ts
│   │   └── references.ts
│   ├── views/             # Vistas/páginas
│   │   ├── AlertsView.vue
│   │   ├── ConfigView.vue
│   │   ├── DashboardView.vue
│   │   ├── InstallationsView.vue
│   │   ├── InventoryView.vue
│   │   ├── LoginView.vue
│   │   ├── OrderEntriesView.vue
│   │   ├── OrdersView.vue
│   │   ├── PrintersView.vue
│   │   ├── PrintLogView.vue
│   │   ├── ReferencesView.vue
│   │   └── config/        # Subvistas de configuración
│   ├── App.vue            # Componente raíz
│   ├── main.ts            # Punto de entrada
│   └── style.css          # Estilos globales
├── public/                # Archivos públicos
├── index.html             # HTML principal
├── package.json           # Dependencias
├── tsconfig.json          # Configuración TypeScript
├── vite.config.ts         # Configuración Vite
└── tailwind.config.js     # Configuración Tailwind
```

## Patrones de Diseño

### Composition API

El proyecto utiliza **Composition API** de Vue 3 con `<script setup>`:

```vue
<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { usePrintersStore } from '@/stores/printers'

const printersStore = usePrintersStore()
const loading = ref(false)

onMounted(() => {
  printersStore.fetch()
})
</script>
```

### Stores de Pinia

Cada módulo tiene su propio store:

- **auth.ts**: Autenticación y sesión
- **printers.ts**: Gestión de impresoras
- **alerts.ts**: Alertas
- **inventory.ts**: Inventario
- **orders.ts**: Pedidos
- etc.

### Servicios

Los servicios encapsulan la lógica de comunicación con la API:

- **httpClient.ts**: Cliente Axios configurado con interceptores

## Routing

El routing está configurado en `src/router/index.ts`:

### Rutas Principales

- `/login` - Página de login (guest only)
- `/` - Dashboard (requiere auth)
- `/printers` - Lista de impresoras
- `/references` - Referencias de consumibles
- `/inventory` - Inventario
- `/orders` - Pedidos
- `/order-entries` - Entradas de pedidos
- `/installations` - Instalaciones de consumibles
- `/alerts` - Alertas
- `/print-log` - Registro de impresiones
- `/config` - Configuración

### Guards de Rutas

- **requiresAuth**: Redirige a login si no está autenticado
- **guestOnly**: Redirige a dashboard si está autenticado

## Gestión de Estado

### Pinia Stores

Cada store maneja el estado de su módulo:

```typescript
export const usePrintersStore = defineStore('printers', {
  state: () => ({
    printers: [],
    loading: false,
    filters: { ... }
  }),
  getters: {
    filteredPrinters: (state) => { ... }
  },
  actions: {
    async fetch() { ... },
    async create(data) { ... }
  }
})
```

### Estado Global

- **auth**: Estado de autenticación (usuario, token)
- **app**: Estado general de la aplicación
- **config**: Configuración del sistema

## Servicios

### HTTP Client

Cliente Axios configurado en `src/services/httpClient.ts`:

- Base URL desde `VITE_API_URL`
- Interceptor para añadir token Bearer
- Interceptor para manejar errores 401 (logout automático)

### Uso

```typescript
import { api } from '@/services/httpClient'

const response = await api.get('/printers')
const data = await api.post('/printers', printerData)
```

## Componentes

### Componentes Reutilizables

- **ConsumableBar.vue**: Barra de nivel de consumible
- **ConsumableLevel.vue**: Indicador de nivel
- **PrinterCustomFieldsModal.vue**: Modal para campos personalizados

## Estilos

### Tailwind CSS

El proyecto utiliza Tailwind CSS para estilos utility-first:

```vue
<template>
  <div class="flex items-center justify-between p-4 bg-white rounded-lg shadow">
    <h2 class="text-xl font-bold text-gray-800">Título</h2>
  </div>
</template>
```

### Configuración

La configuración de Tailwind está en `tailwind.config.js` con:
- Colores personalizados
- Fuentes
- Breakpoints

## Build y Desarrollo

### Desarrollo

```bash
npm run dev
```

Inicia Vite dev server en `http://localhost:5173`

### Build de Producción

```bash
npm run build
```

Genera archivos optimizados en `dist/`

### Preview

```bash
npm run preview
```

Sirve la build de producción localmente

## Variables de Entorno

Crear archivo `frontend/.env`:

```env
VITE_API_URL=http://localhost:8080/api/v2
```

## Referencias

- [Vista de Componentes](COMPONENTS.md)
- [Vista de Vistas](VIEWS.md)
- [Vista de Stores](STORES.md)
- [Vista de Servicios](SERVICES.md)
- [Documentación de Vue 3](https://vuejs.org/)
- [Documentación de Pinia](https://pinia.vuejs.org/)

