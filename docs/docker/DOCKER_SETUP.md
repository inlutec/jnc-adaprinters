# Documentación de Docker - JNC-AdaPrinters

Documentación completa de la configuración Docker del proyecto.

## Tabla de Contenidos

1. [Estructura General](#estructura-general)
2. [Servicios](#servicios)
3. [Redes y Volúmenes](#redes-y-volúmenes)
4. [Configuración de Servicios](#configuración-de-servicios)
5. [Comandos Útiles](#comandos-útiles)

## Estructura General

El proyecto utiliza Docker Compose para orquestar múltiples servicios:

```
docker/
├── docker-compose.yml      # Configuración principal
├── backend.env             # Variables de entorno backend
├── php/
│   └── Dockerfile          # Imagen PHP-FPM
├── node/
│   └── Dockerfile          # Imagen Node.js
├── nginx/
│   └── conf.d/
│       └── default.conf    # Configuración Nginx
└── scripts/
    ├── init_cron.sh        # Script de inicialización cron
    └── update_cron.sh      # Script de actualización cron
```

## Servicios

### app (PHP-FPM)

**Imagen**: Construida desde `docker/php/Dockerfile`  
**Puerto interno**: 9000 (PHP-FPM)  
**Volúmenes**:
- `../backend:/var/www/html` - Código fuente Laravel

**Descripción**: Contenedor principal que ejecuta PHP-FPM para servir la API Laravel.

**Extensiones PHP instaladas**:
- `bcmath` - Cálculos matemáticos
- `intl` - Internacionalización
- `pcntl` - Control de procesos
- `pdo_pgsql` - Driver PostgreSQL
- `pgsql` - Extensión PostgreSQL
- `zip` - Manejo de archivos ZIP
- `gd` - Procesamiento de imágenes
- `snmp` - Protocolo SNMP
- `redis` - Cliente Redis

**Herramientas adicionales**:
- Composer 2.7
- Python 3 con psycopg2
- Cron

### scheduler

**Imagen**: Misma que `app`  
**Comando**: `php artisan schedule:work --verbose`  
**Volúmenes**: Mismo que `app`

**Descripción**: Ejecuta el scheduler de Laravel para tareas programadas:
- Sincronización SNMP automática (cada minuto)
- Snapshots de Horizon (cada 5 minutos)

### horizon

**Imagen**: Misma que `app`  
**Comando**: `php artisan horizon`  
**Volúmenes**: Mismo que `app`

**Descripción**: Procesa jobs de cola de Redis usando Laravel Horizon.

**UI disponible en**: `http://localhost:8080/horizon` (requiere autenticación)

### frontend

**Imagen**: Construida desde `docker/node/Dockerfile`  
**Puerto**: 5173 (Vite dev server)  
**Comando**: `npm run dev -- --host 0.0.0.0 --port 5173`  
**Volúmenes**:
- `../frontend:/app` - Código fuente Vue

**Descripción**: Servidor de desarrollo Vite para el frontend Vue 3.

### postgres

**Imagen**: `postgres:16-alpine`  
**Puerto**: 5432  
**Variables de entorno**:
- `POSTGRES_DB`: jnc_adaprinters
- `POSTGRES_USER`: jnc_admin
- `POSTGRES_PASSWORD`: secretpassword

**Volúmenes**:
- `postgres_data:/var/lib/postgresql/data` - Datos persistentes

**Descripción**: Base de datos PostgreSQL 16.

### redis

**Imagen**: `redis:7-alpine`  
**Puerto**: 6379  
**Comando**: `redis-server --save 60 1 --loglevel warning`

**Volúmenes**:
- `redis_data:/data` - Datos persistentes

**Descripción**: Cache y cola de jobs (Redis 7).

### nginx

**Imagen**: `nginx:1.27-alpine`  
**Puerto**: 8080 (mapeado a 80 interno)  
**Volúmenes**:
- `../backend/public:/var/www/html/public` - Archivos públicos
- `../backend/storage:/var/www/html/storage` - Storage Laravel
- `./nginx/conf.d/default.conf:/etc/nginx/conf.d/default.conf:ro` - Configuración

**Descripción**: Servidor web Nginx que actúa como reverse proxy para PHP-FPM y sirve archivos estáticos.

## Redes y Volúmenes

### Red: jnc-net

**Tipo**: bridge  
**Descripción**: Red interna que conecta todos los servicios.

### Volúmenes

#### postgres_data

Almacena los datos de PostgreSQL de forma persistente.

#### redis_data

Almacena los datos de Redis de forma persistente.

## Configuración de Servicios

### Variables de Entorno

Las variables de entorno del backend se definen en `docker/backend.env` y se cargan mediante `env_file` en docker-compose.yml.

**Variables principales**:

```env
# Aplicación
APP_NAME="JNC-AdaPrinters"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8080

# Base de datos
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=jnc_adaprinters
DB_USERNAME=jnc_admin
DB_PASSWORD=secretpassword

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail
MAIL_MAILER=log
MAIL_FROM_ADDRESS="hola@jnc-adaprinters.local"

# Frontend
FRONTEND_URL=http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=localhost:8080,localhost:5173

# SNMP
SNMP_DRIVER=real
SNMP_QUEUE=default
SNMP_TIMEOUT_MS=1500
SNMP_RETRIES=2

# Alertas
ALERT_CONSUMABLE_THRESHOLD=15
ALERT_CONSUMABLE_RELEASE=30
ALERT_DEFAULT_TTL=1440
```

### Nginx Configuration

El archivo `docker/nginx/conf.d/default.conf` configura:

1. **Rutas API** (`/api`): Proxy a PHP-FPM
2. **Horizon UI** (`/horizon`): Proxy a PHP-FPM
3. **Storage** (`/storage`): Servir archivos de storage
4. **SPA** (`/`): Servir index.html para Vue Router

**Configuración importante**:
- `client_max_body_size 20M` - Límite de subida de archivos
- Headers de seguridad (X-Frame-Options, X-Content-Type-Options)
- FastCGI pass a `app:9000`

## Comandos Útiles

### Gestión de Contenedores

```bash
# Levantar todos los servicios
docker compose up -d

# Levantar y reconstruir
docker compose up -d --build

# Ver logs
docker compose logs -f

# Ver logs de un servicio específico
docker compose logs -f app
docker compose logs -f horizon

# Detener servicios
docker compose down

# Detener y eliminar volúmenes
docker compose down -v
```

### Ejecutar Comandos en Contenedores

```bash
# Ejecutar Artisan
docker compose exec app php artisan migrate

# Ejecutar Composer
docker compose exec app composer install

# Acceder a shell
docker compose exec app bash

# Ejecutar tinker
docker compose exec app php artisan tinker

# Ejecutar tests
docker compose exec app php artisan test
```

### Base de Datos

```bash
# Acceder a PostgreSQL
docker compose exec postgres psql -U jnc_admin -d jnc_adaprinters

# Backup
docker compose exec postgres pg_dump -U jnc_admin jnc_adaprinters > backup.sql

# Restaurar
docker compose exec -T postgres psql -U jnc_admin jnc_adaprinters < backup.sql
```

### Redis

```bash
# Acceder a Redis CLI
docker compose exec redis redis-cli

# Ver todas las claves
docker compose exec redis redis-cli KEYS "*"

# Limpiar cache
docker compose exec redis redis-cli FLUSHALL
```

### Frontend

```bash
# Instalar dependencias
docker compose exec frontend npm install

# Ejecutar build
docker compose exec frontend npm run build

# Ver logs
docker compose logs -f frontend
```

### Horizon

```bash
# Ver estado
docker compose exec horizon php artisan horizon:status

# Terminar workers (reinicio suave)
docker compose exec horizon php artisan horizon:terminate

# Reiniciar contenedor
docker compose restart horizon
```

### Limpieza

```bash
# Limpiar contenedores parados
docker compose down

# Limpiar imágenes no usadas
docker system prune -a

# Limpiar volúmenes no usados
docker volume prune
```

## Troubleshooting

### Problema: Contenedor no inicia

```bash
# Ver logs detallados
docker compose logs [servicio]

# Verificar configuración
docker compose config

# Reconstruir sin cache
docker compose build --no-cache
```

### Problema: Puerto ocupado

```bash
# Verificar puertos en uso
sudo netstat -tulpn | grep -E '8080|5173|5432|6379'

# Cambiar puertos en docker-compose.yml
ports:
  - "8081:80"  # Cambiar 8080 a 8081
```

### Problema: Permisos de archivos

```bash
# Ajustar permisos
docker compose exec app chmod -R 775 storage bootstrap/cache
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### Problema: Base de datos no conecta

```bash
# Verificar que PostgreSQL está corriendo
docker compose ps postgres

# Verificar variables de entorno
docker compose exec app env | grep DB_

# Probar conexión
docker compose exec app php artisan tinker
# En tinker: DB::connection()->getPdo();
```

## Referencias

- [Dockerfile PHP](DOCKERFILES.md#php-dockerfile)
- [Dockerfile Node](DOCKERFILES.md#node-dockerfile)
- [Configuración de Entorno](ENVIRONMENT.md)
- [Guía de Instalación](../../INSTALLATION.md)

