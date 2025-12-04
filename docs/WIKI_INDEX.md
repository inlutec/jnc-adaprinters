# Índice de Documentación para Wiki - JNC-AdaPrinters

Este documento sirve como índice completo de toda la documentación del proyecto, organizado para facilitar la navegación en una Wiki.

## 📚 Documentación Principal

### Guías de Instalación y Despliegue

- [Guía de Instalación](../INSTALLATION.md)
  - Requisitos del sistema
  - Preparación del servidor
  - Instalación con Docker
  - Configuración inicial
  - Verificación de instalación
  - Solución de problemas

- [Guía de Despliegue en Producción](../DEPLOYMENT.md)
  - Preparación del servidor
  - Configuración de seguridad
  - Optimizaciones de rendimiento
  - Configuración de dominio
  - SSL/TLS
  - Backup y recuperación
  - Monitoreo
  - Mantenimiento

## 🔧 Backend (Laravel)

### Arquitectura y Estructura

- [Arquitectura Backend](backend/ARCHITECTURE.md)
  - Arquitectura Laravel
  - Patrones de diseño
  - Estructura de carpetas
  - Flujo de datos

- [Base de Datos](backend/DATABASE.md)
  - Estructura completa de BD
  - Todas las tablas y relaciones
  - Migraciones documentadas
  - Diagrama ER
  - Seeders

### API y Controladores

- [API REST](backend/API.md)
  - Documentación completa de endpoints
  - Autenticación (Sanctum)
  - Parámetros, respuestas, códigos de estado
  - Ejemplos de requests/responses

- [Controladores](backend/CONTROLLERS.md)
  - Listado de todos los controladores
  - Métodos de cada controlador
  - Validaciones
  - Lógica de negocio

### Modelos y Servicios

- [Modelos](backend/MODELS.md)
  - Documentación de todos los modelos Eloquent
  - Relaciones entre modelos
  - Atributos y métodos principales

- [Servicios](backend/SERVICES.md)
  - SnmpDiscoveryService
  - SnmpClient
  - AlertManager
  - NotificationService
  - Drivers SNMP

### Jobs y Comandos

- [Jobs](backend/JOBS.md)
  - PollPrinterSnmp
  - DiscoverPrintersSnmp
  - SendOrderEmail
  - Configuración de colas

- [Comandos Artisan](backend/COMMANDS.md)
  - printers:poll
  - printers:discover
  - Comandos personalizados

## 🎨 Frontend (Vue 3)

### Arquitectura

- [Arquitectura Frontend](frontend/ARCHITECTURE.md)
  - Arquitectura Vue 3 + Vite
  - Estructura de carpetas
  - Patrones de diseño (Composition API, Pinia)
  - Routing

### Componentes y Vistas

- [Componentes](frontend/COMPONENTS.md)
  - Componentes reutilizables
  - Props y eventos
  - Uso y ejemplos

- [Vistas](frontend/VIEWS.md)
  - DashboardView
  - PrintersView
  - InventoryView
  - AlertsView
  - OrdersView
  - Y todas las demás vistas

### Estado y Servicios

- [Stores](frontend/STORES.md)
  - Stores de Pinia
  - Estado global
  - Acciones y getters

- [Servicios](frontend/SERVICES.md)
  - Servicios de API (axios)
  - Configuración de interceptores
  - Manejo de errores

## 🐳 Infraestructura

### Docker

- [Docker Setup](docker/DOCKER_SETUP.md)
  - Estructura de Docker Compose
  - Servicios definidos
  - Volúmenes y redes
  - Puertos expuestos
  - Comandos útiles

- [Dockerfiles](docker/DOCKERFILES.md)
  - Dockerfile PHP
  - Dockerfile Node
  - Configuración de Nginx

- [Variables de Entorno](docker/ENVIRONMENT.md)
  - Variables de entorno completas
  - Configuración por servicio
  - Valores por defecto y producción

### Scripts

- [SNMP Sync](scripts/SNMP_SYNC.md)
  - Script Python snmp_sync.py
  - Configuración desde base de datos
  - Integración con cron
  - Logs y troubleshooting

## ⚙️ Configuración

- [SNMP](config/SNMP.md)
  - Configuración SNMP
  - Perfiles SNMP
  - OIDs utilizados
  - Descubrimiento de impresoras

- [Alertas](config/ALERTS.md)
  - Sistema de alertas
  - Reglas y umbrales
  - Notificaciones
  - Configuración de canales

- [Permisos](config/PERMISSIONS.md)
  - Sistema de permisos (Spatie)
  - Roles y permisos
  - Configuración

## 💻 Desarrollo

- [Setup de Desarrollo](development/SETUP_DEV.md)
  - Configuración del entorno de desarrollo
  - Instalación de dependencias
  - Hot reload
  - Debugging

- [Testing](development/TESTING.md)
  - Tests PHPUnit
  - Tests Vue
  - Cobertura de código

- [Contribuir](development/CONTRIBUTING.md)
  - Guía para contribuidores
  - Estándares de código
  - Proceso de PR

## 🔍 Operaciones

- [Monitoreo](operations/MONITORING.md)
  - Horizon UI
  - Logs
  - Métricas
  - Alertas del sistema

- [Backup](operations/BACKUP.md)
  - Estrategia de backup
  - Scripts de backup
  - Restauración

- [Troubleshooting](operations/TROUBLESHOOTING.md)
  - Problemas comunes
  - Soluciones
  - Logs importantes

## 📊 Diagramas y Visualizaciones

### Arquitectura del Sistema

```
┌─────────────┐
│   Nginx     │ (Puerto 8080)
└──────┬──────┘
       │
       ├───► /api ────► PHP-FPM (app)
       ├───► /horizon ──► PHP-FPM (app)
       └───► / ────────► Frontend (Vite)
```

### Servicios Docker

```
┌─────────────┐
│    app      │ PHP-FPM (Laravel API)
├─────────────┤
│  scheduler  │ Laravel Scheduler
├─────────────┤
│   horizon   │ Queue Worker (Horizon)
├─────────────┤
│  frontend   │ Vite Dev Server
├─────────────┤
│  postgres   │ PostgreSQL 16
├─────────────┤
│    redis    │ Redis 7
└─────────────┘
```

### Flujo de Datos SNMP

```
Impresora ──SNMP──► PollPrinterSnmp Job ──► SnmpClient
                                              │
                                              ├──► RealSnmpDriver
                                              └──► FakeSnmpDriver
                                                      │
                                                      ▼
                                            PrinterStatusSnapshot
                                                      │
                                                      ├──► AlertManager
                                                      └──► PrinterPrintLog
```

## 🔗 Enlaces Rápidos

### Para Desarrolladores

- [Setup de Desarrollo](development/SETUP_DEV.md)
- [API REST](backend/API.md)
- [Arquitectura Backend](backend/ARCHITECTURE.md)
- [Arquitectura Frontend](frontend/ARCHITECTURE.md)

### Para Administradores

- [Guía de Instalación](../INSTALLATION.md)
- [Guía de Despliegue](../DEPLOYMENT.md)
- [Monitoreo](operations/MONITORING.md)
- [Backup](operations/BACKUP.md)
- [Troubleshooting](operations/TROUBLESHOOTING.md)

### Para Usuarios

- [Guía de Instalación](../INSTALLATION.md) - Sección de configuración inicial
- [Configuración SNMP](config/SNMP.md)
- [Configuración de Alertas](config/ALERTS.md)

## 📝 Notas

- Toda la documentación está en formato Markdown
- Los diagramas utilizan formato Mermaid o texto ASCII
- Los ejemplos de código incluyen sintaxis destacada
- Los enlaces son relativos para facilitar la navegación

## 🆘 Soporte

Para problemas o preguntas:
1. Consulta [Troubleshooting](operations/TROUBLESHOOTING.md)
2. Revisa los logs del sistema
3. Abre un issue en el repositorio

---

**Última actualización**: Diciembre 2025

