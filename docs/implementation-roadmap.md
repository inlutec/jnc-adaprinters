# Roadmap de implementación

## 1. Fases principales
| Fase | Objetivo | Duración estimada |
| --- | --- | --- |
| F0 – Preparación | Setup repos, pipelines CI/CD, convenciones | 1 semana |
| F1 – API Core & datos | Migraciones, modelos base, autenticación, SNMP profiles | 3 semanas |
| F2 – Motor SNMP & alertas | Jobs de polling, almacenamiento de snapshots/logs, alert engine | 3 semanas |
| F3 – SPA base + Dashboard | Shell, auth, dashboard operativo con datos mock → reales | 2 semanas |
| F4 – Módulos funcionales | Printers Hub, Inventory Hub, Alerts Center, Print Log | 4 semanas |
| F5 – QA & despliegue | Testing integral, hardening seguridad, documentación | 2 semanas |

## 2. Backlog técnico detallado

### 2.1 Backend/API
1. **Infraestructura**
   - Configuración `.env` multi-entorno, Docker Compose (nginx + php-fpm + postgres + redis).
   - CI GitHub Actions: lint (PHPStan), test, build assets.
2. **Módulo Core**
   - Migraciones para `printers`, `snmp_profiles`, `printer_status_snapshots`, `printer_print_logs`, `consumables`, `stocks`, `stock_movements`, `orders`, `entries`, `alerts`, `notifications`, `locations`.
   - Seeders de datos demo (impresoras, sedes, OIDs).
   - Laravel Sanctum + políticas de autorización por rol.
3. **SNMP Service**
   - Job `PollPrinterSNMP` con retries, manejo de timeouts, logging estructurado.
   - Scheduler configurable (cron) + Horizon metrics.
   - Repositorio para parsers de OIDs (consumibles, contadores, status).
4. **Alert Engine**
   - Estrategias de detección: offline, consumible < threshold, stock bajo, pico de impresiones.
   - Notificación multi-canal (email, webhook, in-app) con plantillas Markdown.
5. **API REST v2**
   - Recursos versionados (`/api/v2/...`), documentación OpenAPI/Swagger.
   - Paginación consistente, filtros avanzados (sede, rango fechas, estado).
   - Endpoints de export (CSV/PDF) via jobs asíncronos.
6. **Reporting / Print Logs**
   - Servicios que calculan deltas entre snapshots y escriben en `printer_print_logs`.
   - Endpoints de estadísticas (agrupaciones por sede, color vs. bw, coste estimado).

### 2.2 Frontend SPA
1. **Tooling**
   - Vite + Vue 3 + TypeScript + Pinia + Vue Router.
   - Tailwind + PostCSS + autoprefixer + modo dark.
   - Librerías: Headless UI, Heroicons, ECharts, VueUse, VeeValidate.
   - Storybook para componentes críticos.
2. **Fundamentos**
   - Shell (`AppShell`), layouts (dashboard vs. formularios), rutas protegidas (guard + roles).
   - Servicio API (Axios) con interceptores, control de errores y estado global (loading, toasts).
3. **Módulos**
   - **Dashboard:** KPIs, mapa calor, stream de eventos.
   - **Printers Hub:** tabla virtualizada, panel deslizable, gráficas de consumibles, logs.
   - **Inventory Hub:** tabs, formularios wizard, gráficos consumo vs. stock.
   - **Alerts Center:** bandeja + kanban + configurador de reglas.
   - **Print Log:** filtros avanzados, heatmap, exportaciones.
   - **Configuración:** SNMP profiles, ubicaciones, usuarios/roles, branding.
4. **Accesibilidad / UX**
   - Componentes con soporte teclado, ARIA, focus management.
   - Animaciones suaves y fallback skeletons.

### 2.3 Integraciones y DevOps
- Pipelines de despliegue (staging/prod), con testing automático y migraciones controladas.
- Observabilidad: Sentry (frontend/backend), logging estructurado (Monolog JSON), métricas Horizon.
- Backups automáticos BD + snapshots de configuración SNMP.

## 3. Estrategia de pruebas
- **Backend:** PHPUnit + Pest para dominios, tests de contratos API (HTTP tests), simulaciones SNMP con fixtures.
- **Frontend:** Vitest + Testing Library, Storybook visual regression, Cypress para flujos críticos.
- **Performance:** Artillery/k6 para endpoints de polling masivo, Lighthouse para SPA.
- **UAT:** scripts que validan escenarios (alta impresora, sincronización, alerta disparada, pedido generado, registro de impresiones).

## 4. Dependencias y riesgos
1. **Acceso SNMP real:** necesidad de impresoras de laboratorio o simuladores (SNMPSim).
2. **Gestión de estados heredados:** plan de migración desde BD actual si se aprovechan datos.
3. **Diseño UI:** requiriere mockups hi-fi (Figma) antes de desarrollo F4.
4. **Integraciones externas (alertas):** coordinar con canales (correo corporativo, Teams/Slack) para webhooks.

## 5. Entregables
- Documentación técnica (OpenAPI, diagramas, guías de despliegue).
- Manual de usuario/operaciones.
- Set de dashboards en Grafana/Sentry para monitoreo post-producción.

