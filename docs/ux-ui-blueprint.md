# Blueprint UX/UI – Plataforma Gestión de Impresoras ADA

## 1. Principios de diseño
- **Identidad ADA:** paleta verde institucional (`#00A859`), acentos en azul petróleo y neutros cálidos. Tipografía: Inter (400/500/700).
- **Sistema de diseño:** tokens en Tailwind (`spacing`, `radius`, `elevation`). Componentes atómicos: tarjetas, tiles métricos, tablas con densidad ajustable, timelines, modales stepper.
- **Modo dual:** claro/oscuro con contraste AA mínimo 4.5:1.
- **Feedback inmediato:** toasts contextuales, skeletons para cargas, indicadores de tiempo real para SNMP/alertas.

## 2. Arquitectura de navegación
1. **Shell principal**
   - Sidebar fijo con secciones: Dashboard, Impresoras, Inventario, Alertas, Registro de impresiones, Configuración.
   - Header contextual: buscador global, selector de sede, botón “Sincronizar ahora”, centro de notificaciones y perfil.
2. **Breadcrumbs dinámicos** en cada vista detallada (p.ej. Dashboard › Impresoras › HP M607-ALM-021).
3. **Acciones rápidas** (floating panel) configurables por rol.

## 3. Páginas clave

### 3.1 Command Center (Dashboard Operativo)
- **Hero KPIs (4 cards):** Tótems con sparkline (impresiones hoy, % impresoras online, alertas activas, stock crítico).
- **Mapa calor por sede:** grid o choropleth con tooltip de impresoras/consumo.
- **Stream de eventos:** timeline en vivo (sincronizaciones SNMP, alertas creadas, pedidos registrados).
- **Widgets secundarios:** gráfico área de impresiones último mes, tabla de “top impresoras por consumo”, carrusel de alertas críticas.

### 3.2 Printers Hub
- **Toolbar:** búsqueda semántica (por IP, serie, modelo), filtros guardados, chips activos, botón “Descubrir por rango IP”.
- **Tabla virtualizada:** columnas personalizables (estado, sede, consumible más bajo, último ping, contador total). Badges de estado.
- **Detalle lateral (slide-over):**
  - Tabs: Resumen, Consumos, Logs de impresión, Alertas, SNMP.
  - Gráfico radial para consumibles, timeline de alertas asociadas, histórico de contadores por fecha.
- **Acciones masivas:** sincronización, asignar SNMP profile, mover de sede, generar ticket.

### 3.3 Inventory Hub
- **Overview cards:** disponibilidad por tipo (tóner, tambor, piezas), valor económico, pedidos abiertos.
- **Tabs principales:** Consumibles, Stock por sede, Pedidos, Entradas, Referencias.
- **Consumo proyectado:** gráfico stacked comparando consumo (basado en print logs) vs. stock actual.
- **Formularios:** wizard para entradas (datos generales → items → adjuntos), validaciones inline, resumen final.
- **Alertas contextuales:** recomendaciones de compra basadas en lead time + velocidad de consumo.

### 3.4 Alerts Center
- **Bandeja principal:** vista tipo Kanban (Criticas/Alta/Media/Baja) o tabla con quick actions.
- **Detalle alert:** panel con causa raíz, impresoras afectadas, timeline y botones “Reconocer”, “Asignar”, “Posponer”.
- **Configuración rápida:** modal para reglas (thresholds, canales, horarios silenciosos).
- **Metrics:** gráfico donut de alertas por severidad, tiempo medio de resolución.

### 3.5 Registro de Impresiones
- **Filtro avanzado:** rango de fechas, sede, impresora, tipo (color/bw), usuario (si se integra con AD).
- **KPIs:** total impresiones periodo, coste estimado, top impresoras.
- **Visualizaciones:** heatmap diario/horario, gráfico líneas comparando sedes, tabla detallada exportable.
- **Insights:** panel lateral con anomalías (picos, caídas), sugerencias de redistribución.

### 3.6 Configuración
- **SNMP Profiles:** tarjetas con versión (v2/v3), credenciales, botón “Test” que muestra latencia y resultado.
- **Ubicaciones:** organigrama interactivo provincia → sede → departamento.
- **Usuarios y roles:** UI tipo “matrix” para permisos, opción MFA.
- **Branding:** uploader con previsualización (favicon, logos, tema claro/oscuro).

## 4. Componentes compartidos
- **Data Tiles:** KPIs con iconos, trendlines y etiquetas.
- **Alert Badge:** color según severidad + countdown si tiene SLA.
- **Timeline/Event Stream:** items con icono, título, etiquetas, botón “ver más”.
- **Charts:** wrapper Vue para ECharts (soporte dark/light, tooltips, drill-down).
- **Tables:** sorting multi-columna, inline filters, densidad compacta, columnas “pinneables”, export CSV/Excel.
- **Modales Stepper:** múltiples pasos con progreso visual, validación por paso y resumen final.

## 5. Experiencia móvil/tablet
- Layout responsive: sidebar colapsable, dashboards reordenados (widgets apilados), tablas en cards scrollables.
- Atajos gestuales para abrir menú y notificaciones.

## 6. Accesibilidad
- Navegación completa por teclado (focus ring visible).
- Componentes ARIA etiquetados (alertas, timelines, tabs).
- Opciones de reducción de movimiento y tamaño de fuente global.

Este blueprint guiará el diseño visual y la implementación de componentes en la nueva SPA, alineado con la arquitectura propuesta.

