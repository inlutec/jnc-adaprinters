# JNC-AdaPrinters

[![GitHub](https://img.shields.io/badge/GitHub-inlutec%2Fjnc--adaprinters-blue)](https://github.com/inlutec/jnc-adaprinters)

Plataforma de nueva generación para la Agencia Digital de Andalucía orientada a la **gestión integral de impresoras y consumibles**: descubrimiento SNMP, inventario inteligente, alertas multicanal y registro histórico de impresiones.

**Repositorio**: https://github.com/inlutec/jnc-adaprinters

## 📚 Documentación Completa

**Toda la documentación está disponible en la carpeta [`docs/`](docs/README.md)**

### Documentación Principal

- 📖 [Guía de Instalación](INSTALLATION.md) - Instalación paso a paso en servidor limpio
- 🚀 [Guía de Despliegue](DEPLOYMENT.md) - Despliegue en producción
- 📋 [Índice de Documentación](docs/README.md) - Documentación completa del proyecto

### Documentación Técnica

- **Backend**: [Arquitectura](docs/backend/ARCHITECTURE.md) | [API](docs/backend/API.md) | [Modelos](docs/backend/MODELS.md) | [Base de Datos](docs/backend/DATABASE.md)
- **Frontend**: [Arquitectura](docs/frontend/ARCHITECTURE.md) | [Componentes](docs/frontend/COMPONENTS.md) | [Vistas](docs/frontend/VIEWS.md)
- **Infraestructura**: [Docker](docs/docker/DOCKER_SETUP.md) | [Scripts](docs/scripts/SNMP_SYNC.md)
- **Operaciones**: [Monitoreo](docs/operations/MONITORING.md) | [Backup](docs/operations/BACKUP.md) | [Troubleshooting](docs/operations/TROUBLESHOOTING.md)

## Estructura del monorepo

```
jnc-adaprinters/
├── backend/      # API Laravel (PostgreSQL + Redis)
├── frontend/     # SPA Vue 3 + Vite + Pinia + Tailwind
├── docker/       # Infraestructura docker-compose, Nginx, PHP y Node
├── docs/         # Documentación completa del proyecto
└── .github/      # Workflows CI
```

## 🚀 Inicio Rápido

Para una implementación rápida desde cero, consulta la **[Guía Rápida](QUICKSTART.md)**.

## Requisitos

- Docker Desktop o Docker Engine 24+
- Make (opcional, ver comandos más abajo)
- Node 20+ y Composer 2.7+ si se desea ejecutar sin contenedores

## Puesta en marcha (modo contenedor)

```bash
cd docker
docker compose up -d --build
```

Servicios expuestos:
- API Laravel: `http://localhost:8080`
- SPA Vite: `http://localhost:5173`
- PostgreSQL: `localhost:5432` (jnc_adaprinters / jnc_admin / secretpassword)
- Redis: `localhost:6379`
- Horizon UI: `http://localhost:8080/horizon` (sólo accesible con login local)

> Las variables utilizadas por PHP/Artisan se definen en `docker/backend.env`.

Credenciales demo generadas por el seeder (`php artisan migrate --seed`):
- Usuario: `admin@jnc-adaprinters.local`
- Contraseña: `admin123`

**⚠️ IMPORTANTE**: Cambiar estas credenciales en producción.

## Scripts útiles

```bash
# Ejecutar migraciones dentro del contenedor PHP
docker compose exec app php artisan migrate

# Instalar dependencias backend/frontend sin Docker
composer install --working-dir=backend
npm install --prefix frontend

# Variables de entorno del frontend
Crear un archivo `frontend/.env` con:

```
VITE_API_URL=http://localhost:8080/api/v2
```

Modifica la URL si el backend vive en otra ruta.

# Ejecutar test suites
docker compose exec app php artisan test
cd frontend && npm run test

# Encolar sondeo SNMP manual
docker compose exec app php artisan printers:poll --limit=10
```

## Monitorización de colas

- **Horizon** corre en el servicio `horizon` de Docker (`php artisan horizon`) y expone métricas UI en `/horizon`.
- El scheduler (`php artisan schedule:work`) ya ejecuta `horizon:snapshot` para mantener gráficos actualizados.
- Para reiniciar los workers: `docker compose exec horizon php artisan horizon:terminate`.

## Stack Tecnológico

### Backend
- Laravel 12
- PHP 8.3
- PostgreSQL 16
- Redis 7
- Laravel Horizon 5.40
- Laravel Sanctum 4.2
- Spatie Laravel Permission 6.23

### Frontend
- Vue 3.5.24
- Vite 7.2.4
- Pinia 3.0.4
- Vue Router 4.6.3
- Tailwind CSS 3.4.15
- TypeScript 5.9.3
- Axios 1.13.2
- Chart.js 4.4.7

### Infraestructura
- Docker Compose 3.9
- Nginx 1.27
- Python 3 (para scripts SNMP)

## Características Principales

- 🔍 **Descubrimiento SNMP**: Descubrimiento automático de impresoras en la red
- 📊 **Monitoreo en Tiempo Real**: Estado de impresoras y consumibles
- 📦 **Gestión de Inventario**: Control de stock por ubicación
- 🚨 **Sistema de Alertas**: Alertas automáticas por consumibles bajos o impresoras offline
- 📝 **Registro de Impresiones**: Histórico de impresiones con contadores
- 📋 **Gestión de Pedidos**: Sistema completo de pedidos y entradas
- 🎨 **Campos Personalizados**: Extensibilidad mediante campos personalizados
- 🔐 **Control de Acceso**: Sistema de permisos y roles

## Próximas fases

1. **Motor SNMP & alertas** (en curso): jobs `PollPrinterSNMP`, almacenamiento de snapshots/logs y reglas de alerta inteligentes.
2. **SPA avanzada**: dashboards operativos, Inventario/Alerts Hub y registro de impresiones.
3. **Observabilidad & despliegue**: pipelines CI/CD, métricas Sentry/Prometheus y hardening de seguridad.

## Licencia

MIT License

