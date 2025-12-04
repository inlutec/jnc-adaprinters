# Auditoría del sistema actual

Fecha: 2025-12-02  
Backup asociado: `/var/www/html/ada-impresoras-backup-20251202-200902`

## 1. Resumen de arquitectura existente
- **Backend:** Laravel 12 con rutas web mínimas y un API REST centralizado en [`routes/api.php`](routes/api.php). Autenticación basada en sesiones de Laravel y endpoints `/api/login`, `/api/logout`, `/api/user`.
- **Frontend:** SPA en Vue 3 + Pinia + Vue Router, compilada con Vite. El entry point principal es `resources/js/app-spa.js`, que monta el componente raíz `App.vue` y registra layouts (`AppLayout`, `AppLayoutSimple`) y páginas (`Dashboard`, `PrintersList`, `InventoryHub`, `OrdersManager`, `ConfigManager`, etc.).
- **Estado global:** Stores en `resources/js/stores` para `auth`, `app`, `printers`, `inventory`, `config`. Cada store consume directamente los endpoints REST y publica getters para métricas (stats, alertas de stock, etc.).
- **Integración SNMP:** Los controladores `ImpresoraController`/`ImpresoraApiController` ofrecen sincronización puntual (`/api/impresoras/{id}/sync-snmp`) y masiva (`/api/impresoras/sync-all-snmp`). Los datos SNMP se almacenan como `snmp_data` en cada impresora y se consumen en el dashboard para detectar consumibles bajos.

## 2. Endpoints y módulos actuales

| Dominio | Endpoints clave | Función actual |
| --- | --- | --- |
| Autenticación | `POST /api/login`, `POST /api/logout`, `GET /api/user` | Login con tokens + fallback a sesión Laravel |
| Dashboard | `GET /api/dashboard/stats`, `.../recent-printers`, `.../alerts`, `.../system-status` | KPIs generales, listado rápido de impresoras y alertas |
| Impresoras | `GET/POST/PUT/DELETE /api/impresoras`, `POST /api/impresoras/{id}/sync-snmp`, `POST /api/impresoras/discover` | CRUD completo, sincronización SNMP individual o masiva y descubrimiento por rango IP |
| Inventario | `/api/inventory/*`, `/api/consumibles`, `/api/stocks`, `/api/consumible-referencias` | Gestión de consumibles, stock, referencias y ajustes |
| Configuración | `/api/configuracion*`, `/api/provincias|sedes|departamentos`, `/api/snmp-oids`, `/api/logos` | Parametrización de ubicaciones, OIDs SNMP, branding |
| Operaciones | `Route::resource` para `almacen`, `instalaciones`, `pedidos`, `entradas` (JSON cuando se accede vía `/api/...`) | Registro de instalaciones in situ, pedidos de consumibles y entradas de almacén |

## 3. Flujo del frontend
1. **Carga inicial:** `AppLayout` inicializa tema, sidebar, auth (lee `window.Laravel`) y el store de configuración (`useConfigStore.initializeConfig()`).
2. **Dashboard (`resources/js/components/Dashboard.vue`):** combina `printersStore` e `inventoryStore` para mostrar:
   - KPIs de impresoras (total, activas, tinta baja) provenientes de `snmp_data`.
   - Alertas de stock (`inventoryStore.lowStockItems`).
   - Acciones rápidas hacia `/impresoras`, `/pedidos`, `/almacen`, `/configuracion`.
3. **Inventario (`resources/js/components/inventory/InventoryHub.vue`):** tabs para consumibles, stock, referencias y entradas, con formularios modales (`ConsumibleForm`, `StockForm`, `EntryForm`).
4. **Configuración (`ConfigManager`):** consume el store `config` para CRUD de provincias, sedes, departamentos, OIDs y logotipos.

## 4. Estados y funcionalidades de los stores

### `usePrintersStore`
- Mantiene listado paginado, filtros (provincia, sede, departamento, color, estado), sort y progreso de sincronización.
- Acciones para CRUD completo, descubrimiento de impresoras y sincronización SNMP (individual o masiva).
- Getters pre-calculados: activos, color/monocromo, impresoras con consumibles al <20%, segmentación por provincia/sede/departamento.

### `useInventoryStore`
- Gestiona consumibles, stocks, referencias y métricas (valor total inventario, stock bajo, sin stock).
- Endpoints: `/api/consumibles`, `/api/stocks`, `/api/consumible-referencias`, `/api/inventory/*`.
- Incluye filtros por tipo, marca, color, sede y flag de stock bajo.

### `useConfigStore`
- Descarga masivamente provincias, sedes, departamentos, OIDs SNMP, logos y configuraciones generales.
- Provee helpers para relacionar sedes↔provincias y departamentos↔sedes, además de listas de OIDs por categoría.

### `useAuthStore`
- Maneja login por token (`/api/login`) y fallback a sesión Laravel (`window.Laravel` + `laravel_session`).
- Gestiona permisos, roles, actualización de perfil y password.

### `useAppStore`
- UI shell: tema claro/oscuro, estado del sidebar, notificaciones, modales globales (sincronización, formularios).

## 5. Funciones detectadas vs. nuevas necesidades
- **Ya implementado:** monitorización SNMP básica, inventario de consumibles, pedidos, entradas, descubrimiento de impresoras, parametrización geográfica, branding corporativo, alertas básicas (stock bajo en dashboard), notificaciones locales.
- **Pendiente para el rediseño:**\
  a) Registro histórico de impresiones por fecha alimentado por las sincronizaciones SNMP.\
  b) Sistema de alertas más robusto (usuario solicitó mecanismos avanzados).\
  c) Replantear UX/UI completo con experiencia “premium” y posiblemente stack mejorado.

## 6. Observaciones para el rediseño
1. **Consistencia API:** hay endpoints duplicados y alias (p. ej. `/api/logotipos` y `/api/logos`). Conviene consolidarlos en una nueva versión del API.
2. **SNMP:** actualmente expuesto como acciones manuales. El nuevo proyecto debería planificar jobs recurrentes, colas y almacenamiento histórico (logs de impresiones, alertas).
3. **Front-end:** la SPA actual mezcla componentes muy monolíticos; el rediseño debería modularizar dashboards, usar design tokens modernos y contemplar gráficos (charts) para KPIs e histórico de impresiones.
4. **Autenticación & permisos:** el store soporta roles/permisos, pero no se ve un control granular en el UI. Revisar si en la nueva versión se requieren perfiles diferenciados (operador, supervisor, admin).

Este documento cubre el estado del sistema previo al rediseño y servirá como referencia para las siguientes fases del plan.

