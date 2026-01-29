# Guía de Migración desde Docker a Instalación Nativa

Esta guía explica cómo migrar JNC-AdaPrinters desde una instalación Docker a una instalación nativa sin Docker.

## Tabla de Contenidos

1. [Preparación](#preparación)
2. [Exportar Datos desde Docker](#exportar-datos-desde-docker)
3. [Importar Datos en Instalación Nativa](#importar-datos-en-instalación-nativa)
4. [Migración de Archivos](#migración-de-archivos)
5. [Verificación Post-Migración](#verificación-post-migración)
6. [Rollback (si es necesario)](#rollback-si-es-necesario)

## Preparación

### 1. Verificar Instalación Docker Actual

```bash
cd /ruta/a/docker
docker compose ps
```

Asegúrate de que todos los servicios están corriendo.

### 2. Preparar Instalación Nativa

Sigue la guía [INSTALLATION_NATIVE.md](../../INSTALLATION_NATIVE.md) o [INSTALLATION_RHEL.md](../../INSTALLATION_RHEL.md) para instalar el sistema nativo, pero **NO ejecutes las migraciones ni seeders** todavía.

### 3. Hacer Backup Completo

**⚠️ IMPORTANTE**: Siempre haz backup antes de migrar.

```bash
# Backup de Docker
docker compose exec postgres pg_dump -U jnc_admin jnc_adaprinters > backup_docker_$(date +%Y%m%d_%H%M%S).sql

# Backup de archivos
tar -czf backup_storage_$(date +%Y%m%d_%H%M%S).tar.gz /ruta/a/docker/backend/storage
```

## Exportar Datos desde Docker

### 1. Exportar Base de Datos

```bash
cd /ruta/a/docker

# Exportar base de datos
docker compose exec postgres pg_dump -U jnc_admin -F c -f /tmp/jnc_adaprinters_backup.dump jnc_adaprinters

# Copiar el dump fuera del contenedor
docker compose cp postgres:/tmp/jnc_adaprinters_backup.dump ./jnc_adaprinters_backup.dump
```

O usando formato SQL plano:

```bash
docker compose exec postgres pg_dump -U jnc_admin jnc_adaprinters > jnc_adaprinters_backup.sql
```

### 2. Exportar Archivos de Storage

```bash
# Copiar directorio storage completo
docker compose cp app:/var/www/html/storage ./storage_backup

# O hacer backup con tar
docker compose exec app tar -czf /tmp/storage_backup.tar.gz -C /var/www/html storage
docker compose cp app:/tmp/storage_backup.tar.gz ./storage_backup.tar.gz
```

### 3. Exportar Configuración

```bash
# Copiar archivo .env (ajustar variables según instalación nativa)
docker compose exec app cat /var/www/html/.env > .env.docker.backup
```

## Importar Datos en Instalación Nativa

### 1. Preparar Base de Datos

```bash
# En el servidor nativo, crear base de datos vacía
sudo -u postgres psql << EOF
CREATE DATABASE jnc_adaprinters;
CREATE USER jnc_admin WITH PASSWORD 'TU_PASSWORD';
GRANT ALL PRIVILEGES ON DATABASE jnc_adaprinters TO jnc_admin;
\c jnc_adaprinters
GRANT ALL ON SCHEMA public TO jnc_admin;
EOF
```

### 2. Importar Base de Datos

**Opción A: Usando formato custom (dump)**

```bash
# Copiar el dump al servidor nativo
scp jnc_adaprinters_backup.dump usuario@servidor-nativo:/tmp/

# En el servidor nativo
sudo -u postgres pg_restore -d jnc_adaprinters -U postgres /tmp/jnc_adaprinters_backup.dump
```

**Opción B: Usando formato SQL**

```bash
# Copiar el SQL al servidor nativo
scp jnc_adaprinters_backup.sql usuario@servidor-nativo:/tmp/

# En el servidor nativo
sudo -u postgres psql -d jnc_adaprinters < /tmp/jnc_adaprinters_backup.sql
```

O con el usuario de la aplicación:

```bash
PGPASSWORD=TU_PASSWORD psql -h localhost -U jnc_admin -d jnc_adaprinters < /tmp/jnc_adaprinters_backup.sql
```

### 3. Verificar Importación

```bash
# Verificar tablas
sudo -u postgres psql -d jnc_adaprinters -c "\dt"

# Verificar conteo de registros
sudo -u postgres psql -d jnc_adaprinters -c "SELECT COUNT(*) FROM printers;"
sudo -u postgres psql -d jnc_adaprinters -c "SELECT COUNT(*) FROM users;"
```

### 4. Importar Archivos de Storage

```bash
# En el servidor nativo
cd /var/www/jnc-adaprinters/backend

# Copiar archivos de storage
sudo -u www-data cp -r /ruta/a/storage_backup/* storage/

# O desde tar
sudo -u www-data tar -xzf /tmp/storage_backup.tar.gz -C .

# Ajustar permisos
sudo chown -R www-data:www-data storage
sudo chmod -R 775 storage
```

### 5. Configurar Variables de Entorno

Editar `/var/www/jnc-adaprinters/backend/.env` y ajustar las siguientes variables:

```env
# Cambiar de valores Docker a valores nativos
DB_HOST=127.0.0.1  # En lugar de 'postgres'
REDIS_HOST=127.0.0.1  # En lugar de 'redis'
APP_URL=http://tu-dominio.com  # Ajustar según tu dominio
FRONTEND_URL=http://tu-dominio.com  # Ajustar según tu dominio
SANCTUM_STATEFUL_DOMAINS=tu-dominio.com  # Ajustar según tu dominio
```

**⚠️ IMPORTANTE**: 
- NO copiar directamente el `.env` de Docker
- Regenerar `APP_KEY` si es necesario: `php artisan key:generate`
- Ajustar todas las URLs y hosts

## Migración de Archivos

### 1. Migrar Archivos Subidos

Si hay archivos en `storage/app/public`, asegúrate de copiarlos:

```bash
# Desde Docker
docker compose cp app:/var/www/html/storage/app/public ./storage_app_public

# Al servidor nativo
scp -r storage_app_public/* usuario@servidor-nativo:/var/www/jnc-adaprinters/backend/storage/app/public/
```

### 2. Migrar Logs (Opcional)

```bash
# Los logs no son críticos, pero puedes migrarlos si quieres mantener historial
docker compose cp app:/var/www/html/storage/logs ./logs_backup
```

## Verificación Post-Migración

### 1. Verificar Conexión a Base de Datos

```bash
cd /var/www/jnc-adaprinters/backend
sudo -u www-data php artisan tinker
```

En tinker:
```php
DB::connection()->getPdo();
User::count();
Printer::count();
exit
```

### 2. Verificar Redis

```bash
redis-cli ping
```

### 3. Verificar Archivos

```bash
ls -la /var/www/jnc-adaprinters/backend/storage/app/public
```

### 4. Ejecutar Comandos de Verificación

```bash
cd /var/www/jnc-adaprinters/backend
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan view:clear

# Regenerar cachés
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

### 5. Probar Funcionalidad

1. Acceder a la aplicación web
2. Iniciar sesión con credenciales existentes
3. Verificar que las impresoras se muestran correctamente
4. Probar una sincronización SNMP manual
5. Verificar que Horizon procesa jobs

### 6. Verificar Servicios

```bash
sudo systemctl status laravel-horizon
sudo systemctl status laravel-scheduler.timer
```

## Rollback (si es necesario)

Si necesitas volver a Docker:

### 1. Detener Instalación Nativa

```bash
sudo systemctl stop laravel-horizon
sudo systemctl stop laravel-scheduler.timer
sudo systemctl stop nginx
sudo systemctl stop php8.3-fpm
```

### 2. Restaurar Docker

```bash
cd /ruta/a/docker
docker compose up -d
```

### 3. Restaurar Base de Datos en Docker

```bash
docker compose exec -T postgres psql -U jnc_admin jnc_adaprinters < jnc_adaprinters_backup.sql
```

### 4. Restaurar Archivos

```bash
docker compose cp storage_backup app:/var/www/html/storage
docker compose exec app chown -R www-data:www-data /var/www/html/storage
```

## Checklist de Migración

- [ ] Backup completo de Docker realizado
- [ ] Instalación nativa completada (sin migraciones)
- [ ] Base de datos exportada desde Docker
- [ ] Base de datos importada en instalación nativa
- [ ] Archivos de storage copiados
- [ ] Variables de entorno configuradas
- [ ] Permisos de archivos ajustados
- [ ] Cachés de Laravel limpiados y regenerados
- [ ] Servicios systemd configurados y activos
- [ ] Funcionalidad verificada
- [ ] Logs revisados sin errores críticos

## Problemas Comunes

### Problema: Error de permisos en archivos migrados

**Solución:**
```bash
sudo chown -R www-data:www-data /var/www/jnc-adaprinters/backend/storage
sudo chmod -R 775 /var/www/jnc-adaprinters/backend/storage
```

### Problema: Base de datos no conecta

**Solución:**
- Verificar que PostgreSQL está corriendo
- Verificar variables DB_HOST, DB_PORT, DB_USERNAME, DB_PASSWORD en .env
- Verificar que el usuario tiene permisos

### Problema: Archivos no se muestran

**Solución:**
- Verificar que el enlace simbólico de storage existe: `php artisan storage:link`
- Verificar permisos de Nginx para leer archivos
- Verificar configuración de Nginx para servir /storage

### Problema: Jobs no se procesan

**Solución:**
- Verificar que Horizon está corriendo: `sudo systemctl status laravel-horizon`
- Verificar conexión a Redis
- Verificar logs: `sudo journalctl -u laravel-horizon -f`

## Referencias

- [Guía de Instalación Nativa](../../INSTALLATION_NATIVE.md)
- [Guía de Instalación RHEL](../../INSTALLATION_RHEL.md)
- [Documentación de PostgreSQL Backup](https://www.postgresql.org/docs/current/backup.html)

