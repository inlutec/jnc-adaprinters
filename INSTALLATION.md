# Guía de Instalación - JNC-AdaPrinters

Esta guía proporciona instrucciones paso a paso para instalar JNC-AdaPrinters en un servidor limpio.

## Tabla de Contenidos

1. [Requisitos del Sistema](#requisitos-del-sistema)
2. [Preparación del Servidor](#preparación-del-servidor)
3. [Instalación con Docker](#instalación-con-docker)
4. [Configuración Inicial](#configuración-inicial)
5. [Verificación de Instalación](#verificación-de-instalación)
6. [Solución de Problemas](#solución-de-problemas)

## Requisitos del Sistema

### Hardware Mínimo

- **CPU**: 2 cores
- **RAM**: 4 GB
- **Disco**: 20 GB de espacio libre
- **Red**: Acceso a la red donde están las impresoras (para SNMP)

### Software Requerido

- **Sistema Operativo**: Linux (Ubuntu 20.04+, Debian 11+, CentOS 8+)
- **Docker**: 24.0 o superior
- **Docker Compose**: 2.0 o superior
- **Git**: Para clonar el repositorio

### Puertos Necesarios

- **8080**: API Laravel (Nginx)
- **5173**: Frontend Vite (desarrollo)
- **5432**: PostgreSQL
- **6379**: Redis
- **9000**: PHP-FPM (interno)

## Preparación del Servidor

### 1. Actualizar el Sistema

```bash
sudo apt update && sudo apt upgrade -y
```

### 2. Instalar Docker

```bash
# Instalar dependencias
sudo apt install -y apt-transport-https ca-certificates curl gnupg lsb-release

# Añadir repositorio de Docker
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg

echo "deb [arch=amd64 signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

# Instalar Docker
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin

# Añadir usuario al grupo docker (opcional, para no usar sudo)
sudo usermod -aG docker $USER
newgrp docker
```

### 3. Verificar Instalación de Docker

```bash
docker --version
docker compose version
```

### 4. Instalar Git

```bash
sudo apt install -y git
```

## Instalación con Docker

### 1. Clonar el Repositorio

```bash
cd /var/www/html
git clone <URL_DEL_REPOSITORIO> jnc-adaprinters
cd jnc-adaprinters
```

### 2. Configurar Variables de Entorno

#### Backend

Copiar y editar el archivo de configuración:

```bash
cp docker/backend.env docker/backend.env.local
nano docker/backend.env.local
```

**Variables importantes a configurar:**

```env
APP_NAME="JNC-AdaPrinters"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://tu-dominio.com

# Base de datos
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=jnc_adaprinters
DB_USERNAME=jnc_admin
DB_PASSWORD=<CAMBIAR_PASSWORD_SEGURA>

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail (configurar SMTP real)
MAIL_MAILER=smtp
MAIL_HOST=smtp.tu-servidor.com
MAIL_PORT=587
MAIL_USERNAME=tu-usuario
MAIL_PASSWORD=tu-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@tu-dominio.com"
MAIL_FROM_NAME="JNC-AdaPrinters"

# Frontend URL
FRONTEND_URL=http://tu-dominio.com

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

#### Frontend

Crear archivo `.env` en `frontend/`:

```bash
cd frontend
cat > .env << EOF
VITE_API_URL=http://tu-dominio.com/api/v2
EOF
cd ..
```

### 3. Construir y Levantar Contenedores

```bash
cd docker
docker compose up -d --build
```

Este comando:
- Construye las imágenes Docker (PHP y Node)
- Descarga imágenes base (PostgreSQL, Redis, Nginx)
- Crea volúmenes y redes
- Inicia todos los servicios

### 4. Generar Clave de Aplicación

```bash
docker compose exec app php artisan key:generate
```

### 5. Ejecutar Migraciones

```bash
docker compose exec app php artisan migrate --force
```

### 6. Poblar Base de Datos (Opcional)

Para datos de prueba:

```bash
docker compose exec app php artisan db:seed
```

**Credenciales por defecto del seeder:**
- Email: `admin@jnc-adaprinters.local`
- Password: `admin123`

**⚠️ IMPORTANTE**: Cambiar estas credenciales en producción.

### 7. Configurar Permisos de Storage

```bash
docker compose exec app chmod -R 775 storage bootstrap/cache
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### 8. Instalar Dependencias Frontend (si es necesario)

```bash
docker compose exec frontend npm install
```

## Configuración Inicial

### 1. Configurar Perfil SNMP

Acceder a la aplicación y configurar un perfil SNMP:

1. Ir a **Configuración > SNMP > Perfiles**
2. Crear un nuevo perfil con:
   - **Nombre**: Perfil por defecto
   - **Community**: `public` (o la comunidad SNMP de tu red)
   - **Versión**: `2c` o `3`

### 2. Configurar Ubicaciones

1. Ir a **Configuración > Ubicaciones**
2. Crear Provincias, Sedes y Departamentos según tu organización

### 3. Configurar Notificaciones

1. Ir a **Configuración > Notificaciones**
2. Configurar SMTP para envío de emails
3. Probar la conexión

### 4. Descubrir Impresoras

#### Opción A: Descubrimiento Automático

```bash
docker compose exec app php artisan printers:discover 10.64.130.0/24 --province=1 --site=1
```

#### Opción B: Añadir Manualmente

1. Ir a **Impresoras > Nueva Impresora**
2. Completar formulario con datos de la impresora

### 5. Configurar Sincronización Automática

1. Ir a **Configuración > SNMP > Sincronización**
2. Activar sincronización automática
3. Configurar frecuencia (recomendado: 15 minutos)

## Verificación de Instalación

### 1. Verificar Servicios

```bash
docker compose ps
```

Todos los servicios deben estar en estado "Up".

### 2. Verificar Logs

```bash
# Logs generales
docker compose logs --tail=50

# Logs de un servicio específico
docker compose logs app --tail=50
docker compose logs horizon --tail=50
```

### 3. Verificar API

```bash
curl http://localhost:8080/api/v2/auth/login
```

Debería devolver un error de validación (esperado sin credenciales).

### 4. Verificar Frontend

Abrir en navegador: `http://localhost:5173` (desarrollo) o `http://tu-dominio.com` (producción)

### 5. Verificar Base de Datos

```bash
docker compose exec postgres psql -U jnc_admin -d jnc_adaprinters -c "\dt"
```

Debería listar todas las tablas.

### 6. Verificar Redis

```bash
docker compose exec redis redis-cli ping
```

Debería responder "PONG".

### 7. Verificar Horizon

Acceder a: `http://localhost:8080/horizon` (requiere autenticación)

### 8. Probar Sincronización SNMP

```bash
docker compose exec app php artisan printers:poll --limit=1
```

Verificar en los logs que se ejecutó correctamente.

## Solución de Problemas

### Problema: Contenedores no inician

**Solución:**
```bash
# Ver logs detallados
docker compose logs

# Verificar puertos ocupados
sudo netstat -tulpn | grep -E '8080|5173|5432|6379'

# Reiniciar servicios
docker compose down
docker compose up -d
```

### Problema: Error de permisos en storage

**Solución:**
```bash
docker compose exec app chmod -R 775 storage bootstrap/cache
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### Problema: Base de datos no conecta

**Solución:**
```bash
# Verificar que PostgreSQL está corriendo
docker compose ps postgres

# Verificar variables de entorno
docker compose exec app env | grep DB_

# Probar conexión manual
docker compose exec app php artisan tinker
# En tinker: DB::connection()->getPdo();
```

### Problema: SNMP no funciona

**Solución:**
```bash
# Verificar que el driver SNMP está instalado
docker compose exec app php -m | grep snmp

# Probar SNMP manualmente
docker compose exec app php artisan printers:discover 127.0.0.1

# Verificar configuración
docker compose exec app php artisan tinker
# En tinker: config('snmp.driver')
```

### Problema: Horizon no procesa jobs

**Solución:**
```bash
# Verificar que Horizon está corriendo
docker compose ps horizon

# Reiniciar Horizon
docker compose exec horizon php artisan horizon:terminate
docker compose restart horizon

# Ver logs
docker compose logs horizon
```

### Problema: Frontend no carga

**Solución:**
```bash
# Verificar que el contenedor frontend está corriendo
docker compose ps frontend

# Ver logs
docker compose logs frontend

# Reinstalar dependencias
docker compose exec frontend npm install
docker compose restart frontend
```

## Próximos Pasos

Una vez completada la instalación:

1. **Cambiar credenciales por defecto**
2. **Configurar backup automático** (ver `docs/operations/BACKUP.md`)
3. **Configurar monitoreo** (ver `docs/operations/MONITORING.md`)
4. **Revisar configuración de seguridad** (ver `DEPLOYMENT.md`)

## Referencias

- [Guía de Despliegue](DEPLOYMENT.md)
- [Documentación de Docker](docs/docker/DOCKER_SETUP.md)
- [Documentación de Backend](docs/backend/ARCHITECTURE.md)
- [Documentación de Frontend](docs/frontend/ARCHITECTURE.md)

