# Documentación Completa - JNC-AdaPrinters

Bienvenido a la documentación completa del proyecto JNC-AdaPrinters. Esta documentación cubre todos los aspectos técnicos del sistema.

## Tabla de Contenidos

### Documentación Principal

- [Guía de Instalación](../INSTALLATION.md) - Instalación paso a paso en servidor limpio
- [Guía de Despliegue](../DEPLOYMENT.md) - Despliegue en producción

### Backend (Laravel)

- [Arquitectura Backend](backend/ARCHITECTURE.md) - Arquitectura y estructura del backend
- [API REST](backend/API.md) - Documentación completa de endpoints
- [Modelos](backend/MODELS.md) - Modelos Eloquent y relaciones
- [Controladores](backend/CONTROLLERS.md) - Controladores y lógica de negocio
- [Servicios](backend/SERVICES.md) - Servicios principales
- [Jobs](backend/JOBS.md) - Jobs de cola (Horizon)
- [Comandos](backend/COMMANDS.md) - Comandos Artisan
- [Base de Datos](backend/DATABASE.md) - Estructura completa de BD

### Frontend (Vue 3)

- [Arquitectura Frontend](frontend/ARCHITECTURE.md) - Arquitectura y estructura del frontend
- [Componentes](frontend/COMPONENTS.md) - Componentes reutilizables
- [Vistas](frontend/VIEWS.md) - Vistas principales de la aplicación
- [Stores](frontend/STORES.md) - Stores de Pinia
- [Servicios](frontend/SERVICES.md) - Servicios de API

### Infraestructura

- [Docker Setup](docker/DOCKER_SETUP.md) - Configuración de Docker Compose
- [Dockerfiles](docker/DOCKERFILES.md) - Dockerfiles PHP y Node
- [Variables de Entorno](docker/ENVIRONMENT.md) - Configuración de entorno

### Scripts

- [SNMP Sync](scripts/SNMP_SYNC.md) - Script Python de sincronización

### Configuración

- [SNMP](config/SNMP.md) - Configuración SNMP y perfiles
- [Alertas](config/ALERTS.md) - Sistema de alertas
- [Permisos](config/PERMISSIONS.md) - Sistema de permisos

### Desarrollo

- [Setup de Desarrollo](development/SETUP_DEV.md) - Configuración del entorno de desarrollo
- [Testing](development/TESTING.md) - Tests y cobertura
- [Contribuir](development/CONTRIBUTING.md) - Guía para contribuidores

### Operaciones

- [Monitoreo](operations/MONITORING.md) - Monitoreo y logs
- [Backup](operations/BACKUP.md) - Estrategia de backup
- [Troubleshooting](operations/TROUBLESHOOTING.md) - Solución de problemas

## Stack Tecnológico

### Backend

- **Laravel 12** - Framework PHP
- **PHP 8.3** - Lenguaje de programación
- **PostgreSQL 16** - Base de datos
- **Redis 7** - Cache y cola de jobs
- **Laravel Horizon 5.40** - Monitor de colas
- **Laravel Sanctum 4.2** - Autenticación API
- **Spatie Laravel Permission 6.23** - Permisos y roles

### Frontend

- **Vue 3.5.24** - Framework JavaScript
- **Vite 7.2.4** - Build tool
- **Pinia 3.0.4** - Gestión de estado
- **Vue Router 4.6.3** - Routing
- **Tailwind CSS 3.4.15** - Framework CSS
- **TypeScript 5.9.3** - Tipado estático
- **Axios 1.13.2** - Cliente HTTP
- **Chart.js 4.4.7** - Gráficos

### Infraestructura

- **Docker Compose 3.9** - Orquestación
- **Nginx 1.27** - Servidor web
- **Python 3** - Scripts SNMP

## Estructura del Proyecto

```
jnc-adaprinters/
├── backend/          # API Laravel
│   ├── app/
│   ├── config/
│   ├── database/
│   └── routes/
├── frontend/         # SPA Vue 3
│   ├── src/
│   └── public/
├── docker/           # Configuración Docker
│   ├── php/
│   ├── node/
│   └── nginx/
└── docs/             # Documentación
```

## Inicio Rápido

1. **Instalación**: Ver [Guía de Instalación](../INSTALLATION.md)
2. **Desarrollo**: Ver [Setup de Desarrollo](development/SETUP_DEV.md)
3. **API**: Ver [Documentación de API](backend/API.md)
4. **Base de Datos**: Ver [Estructura de BD](backend/DATABASE.md)

## Contribuir

Si deseas contribuir al proyecto, consulta la [Guía de Contribución](development/CONTRIBUTING.md).

## Soporte

Para problemas o preguntas:
1. Consulta [Troubleshooting](operations/TROUBLESHOOTING.md)
2. Revisa los logs del sistema
3. Abre un issue en el repositorio

## Licencia

MIT License

