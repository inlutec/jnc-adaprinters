# Arquitectura propuesta para el nuevo sistema

## 1. Objetivos clave
1. Plataforma API-first capaz de gestionar **inventario de consumibles**, **parque de impresoras**, **alertas inteligentes** y **bitácora de impresiones** con datos procedentes de SNMP.
2. Experiencia de usuario premium: SPA modular con dashboards gráficos, flujos guiados y diseño responsive de alto contraste.
3. Motor SNMP fiable con historización: sincronizaciones periódicas, almacenamiento de métricas y generación de registros de impresión por fecha.

## 2. Stack recomendado
| Capa | Tecnología | Motivo |
| --- | --- | --- |
| Backend API | **Laravel 12 (API mode) + PHP 8.5** | Reutiliza conocimiento previo, ecosistema maduro, soporte first-class para colas, websockets y jobs. |
| Base de datos | **PostgreSQL 16** | JSONB para almacenar snapshots SNMP, funciones analíticas para reportes. |
| Colas / tiempo real | **Redis + Laravel Horizon** | Jobs de polling SNMP, emisión de alertas y broadcasting. |
| SNMP worker | **Daemon Laravel Octane** o microservicio Node.js con `net-snmp`, comunicándose vía Redis/HTTP | Separa el consumo SNMP del request cycle y permite escalar. |
| Frontend | **Vue 3 + Vite + Pinia + Vue Router + Tailwind + Headless UI + Chart.js/ECharts** | SPA moderna con componentes accesibles, animaciones fluidas y gráficos. |
| Diseño/UI | Design System propio basado en Tailwind + tokens (colores ADA, tipografía Inter) + librería de iconos (Phosphor o Heroicons). |
| Observabilidad | Laravel Telescope, Sentry, y métricas Prometheus/Grafana opcionales para jobs SNMP. |

## 3. Dominios y entidades principales
1. **Printers**
   - `printers`: datos estáticos (IP, modelo, ubicación, SNMP profile, color, serie).
   - `printer_status_snapshots`: registros periódicos (estado, alertas, contadores, consumibles).
   - `printer_print_logs`: nuevo historial de impresiones (contadores, rango de fechas, fuentes SNMP).
   - `snmp_profiles`: plantillas de OIDs, credenciales y versiones (v2/v3).
2. **Inventory**
   - `consumables` (tipo, marca, compatibilidad), `stocks` (por sede), `stock_movements` (entradas/salidas), `orders`, `order_lines`, `entries`, `entry_items`.
3. **Operations**
   - `installations`, `service_tickets`, `alerts`, `notifications`, `alert_channels`.
4. **Configuration & Access**
   - `locations` (provincias, sedes, departamentos), `users`, `roles`, `permissions`, `brand_assets`.

## 4. Flujos críticos
### 4.1 Polling SNMP y registro de impresiones
1. Scheduler (Laravel Task + Horizon) encola jobs `PollPrinterSNMP`.
2. Job lee la impresora (según perfil v2/v3) y devuelve:
   - Contadores: `total_pages`, `bw_pages`, `color_pages`.
   - Estado: `online`, `error_code`.
   - Consumibles: lista de cartuchos con `nivel_porcentaje`.
3. Se guarda snapshot en `printer_status_snapshots` y se calcula delta vs. snapshot previo:
   - Si hay incremento en contadores, se genera entrada en `printer_print_logs` con `desde`, `hasta`, `total_impresiones`, `color/bw`.
4. Se emiten eventos:
   - **Alertas:** si `nivel_porcentaje < threshold`, si `status=error`, o si no se alcanza la impresora.
   - **Websocket:** actualiza dashboard en tiempo real.

### 4.2 Inventario y consumibles
1. `stocks` mantiene la cantidad por sede/departamento.
2. Movimientos generados por:
   - Entradas (albaranes) → `entry_items`.
   - Instalaciones / intervenciones → `service_tickets`.
3. Alert engine cruza `low stock` con `printer consumption rate` para recomendar pedidos.

### 4.3 Alertas y notificaciones
- Tabla `alerts` con tipos (`SNMP_OFFLINE`, `LOW_TONER`, `STOCK_BELOW_MIN`, `PRINT_SPIKE`, etc.), severidad y estado.
- `alert_channels` define canales (email, Teams/Slack webhook, notificación in-app).
- Workflow:
  1. Evento SNMP o inventario genera alert.
  2. Policies determinan a quién notificar (roles, sedes).
  3. Se registra en `notifications` y se dispara canal externo si aplica.

## 5. API v2 (esbozo)
| Grupo | Endpoint (REST) | Notas |
| --- | --- | --- |
| Auth | `POST /v2/auth/login`, `POST /v2/auth/logout`, `GET /v2/auth/me` | Laravel Sanctum + refresh tokens. |
| Printers | `GET /v2/printers`, `POST /v2/printers`, `POST /v2/printers/{id}/poll`, `GET /v2/printers/{id}/logs`, `GET /v2/printers/{id}/snapshots` | Devuelve snapshots paginados y logs históricos. |
| SNMP Profiles | `GET /v2/snmp-profiles`, `POST /v2/snmp-profiles/test-connection` | Gestiona credenciales y pruebas SNMP. |
| Inventory | `GET /v2/consumables`, `GET /v2/stocks`, `POST /v2/stocks/movements`, `GET /v2/orders`, `POST /v2/orders`, `GET /v2/entries` | Movimientos auditables y trazados. |
| Alerts | `GET /v2/alerts`, `PATCH /v2/alerts/{id}`, `POST /v2/alerts/snooze` | Gestión del ciclo de vida de alertas. |
| Print Logs | `GET /v2/print-logs`, filtros por impresora, rango de fechas y sedes; export CSV/Excel. |
| Reports | `GET /v2/reports/{type}` (PDF/CSV) | Generador central de informes. |

## 6. Frontend SPA (modular)
1. **Shell/Layout**
   - Diseño tipo “command center” con navegación lateral, header contextual y soporte dark/light.
2. **Dashboards**
   - **Operativo:** mapa de calor por sedes, estado SNMP, alertas activas, widgets de impresión diaria/semana.
   - **Inventario:** gráficos de consumo mensual, stock vs. mínimos, órdenes abiertas.
   - **Print Log:** timeline interactivo + tabla con filtros y comparativas.
3. **Módulos**
   - **Printers Hub:** lista avanzada con búsqueda semántica, filtros guardados, pestañas (detalle, snapshots, logs, consumibles, tickets).
   - **Inventory Hub:** Kanban de pedidos, formulario guiado de entradas, comparador de precios.
   - **Alerts Center:** bandeja con prioridades, acciones rápidas (acknowledge, reassign).
   - **Configuración:** asistentes para SNMP profiles, ubicaciones y usuarios/roles.
4. **Componentes compartidos**
   - Charts (ECharts/Chart.js), tablas virtualizadas, formularios con validación stepper, timeline de eventos, tarjetas de estado.

## 7. Seguridad y roles
- **Roles propuestos:** Administrador global, Técnico SNMP, Responsable de almacén, Gestor de sedes, Auditor.
- Cada rol controla acceso a módulos (p. ej. Técnico puede lanzar pollings y ver logs, almacén puede editar stocks, auditor solo lectura).
- MFA opcional via Laravel Fortify + WebAuthn.

## 8. Estrategia de implementación (alto nivel)
1. **Fundación backend:** scaffolding Laravel API, migraciones base, seeders, módulos (Printers, Inventory, Alerts, Config).
2. **Motor SNMP:** servicio dedicado (job + worker) con colas, logging y pruebas contra impresoras de demo.
3. **Frontend base:** setup Vite + Tailwind + componentes UI, layout responsive, auth guard.
4. **Módulos incrementales:** Dashboard → Printers → Inventory → Alerts → Print Log.
5. **QA & Observabilidad:** pruebas unitarias/feature, Postman collection, Storybook/Chromatic para UI, monitoreo Sentry.

Esta arquitectura permite cubrir todas las funciones actuales y añadir las nuevas capacidades (registro de impresiones, alertas avanzadas) con una base escalable y mantenible.

