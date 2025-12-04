# Guía de Despliegue en Producción - JNC-AdaPrinters

Esta guía cubre el despliegue del proyecto en un entorno de producción.

## Tabla de Contenidos

1. [Preparación del Servidor](#preparación-del-servidor)
2. [Configuración de Seguridad](#configuración-de-seguridad)
3. [Optimizaciones de Rendimiento](#optimizaciones-de-rendimiento)
4. [Configuración de Dominio](#configuración-de-dominio)
5. [SSL/TLS](#ssltls)
6. [Backup y Recuperación](#backup-y-recuperación)
7. [Monitoreo](#monitoreo)
8. [Mantenimiento](#mantenimiento)

## Preparación del Servidor

### Requisitos de Producción

- **CPU**: 4+ cores
- **RAM**: 8+ GB
- **Disco**: 50+ GB SSD
- **Red**: IP estática, firewall configurado

### Configuración Inicial del Servidor

```bash
# Actualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar herramientas básicas
sudo apt install -y curl wget git ufw fail2ban

# Configurar firewall
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable
```

## Configuración de Seguridad

### 1. Variables de Entorno de Producción

Asegurar que `docker/backend.env` tenga:

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=<GENERAR_NUEVA_CLAVE>

# Usar contraseñas seguras
DB_PASSWORD=<PASSWORD_SEGURA>
REDIS_PASSWORD=<PASSWORD_SEGURA>

# Configurar CORS y dominios permitidos
SANCTUM_STATEFUL_DOMAINS=tu-dominio.com,www.tu-dominio.com
FRONTEND_URL=https://tu-dominio.com
```

### 2. Generar Nueva Clave de Aplicación

```bash
docker compose exec app php artisan key:generate --force
```

### 3. Configurar Permisos

```bash
# Storage y cache
docker compose exec app chmod -R 755 storage bootstrap/cache
docker compose exec app chown -R www-data:www-data storage bootstrap/cache

# Asegurar que .env no sea accesible
chmod 600 docker/backend.env
```

### 4. Optimizar Autoloader

```bash
docker compose exec app composer install --optimize-autoloader --no-dev
```

### 5. Cachear Configuración

```bash
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

### 6. Deshabilitar Debug

Asegurar que en `docker/backend.env`:
- `APP_DEBUG=false`
- `LOG_LEVEL=error` (o `warning`)

## Optimizaciones de Rendimiento

### 1. Configurar PHP-FPM

Editar `docker/php/Dockerfile` o crear archivo de configuración PHP:

```ini
; php.ini optimizaciones
memory_limit = 256M
max_execution_time = 300
max_input_time = 300
upload_max_filesize = 20M
post_max_size = 25M

; OPcache
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
```

### 2. Configurar PostgreSQL

Ajustar `postgresql.conf` en el contenedor:

```conf
shared_buffers = 256MB
effective_cache_size = 1GB
maintenance_work_mem = 128MB
checkpoint_completion_target = 0.9
wal_buffers = 16MB
default_statistics_target = 100
random_page_cost = 1.1
effective_io_concurrency = 200
work_mem = 16MB
min_wal_size = 1GB
max_wal_size = 4GB
```

### 3. Configurar Redis

Ajustar `redis.conf`:

```conf
maxmemory 512mb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

### 4. Configurar Nginx

Optimizar `docker/nginx/conf.d/default.conf`:

```nginx
# Gzip compression
gzip on;
gzip_vary on;
gzip_proxied any;
gzip_comp_level 6;
gzip_types text/plain text/css text/xml text/javascript application/json application/javascript application/xml+rss;

# Cache estático
location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}

# Timeouts
proxy_connect_timeout 60s;
proxy_send_timeout 60s;
proxy_read_timeout 60s;
```

### 5. Configurar Horizon

Ajustar workers en `config/horizon.php`:

```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'snmp'],
            'balance' => 'auto',
            'processes' => 10,  // Ajustar según CPU
            'tries' => 3,
            'timeout' => 300,
        ],
    ],
],
```

## Configuración de Dominio

### 1. Configurar DNS

Añadir registros A o CNAME apuntando a la IP del servidor.

### 2. Configurar Nginx con Dominio

Crear configuración en `docker/nginx/conf.d/default.conf`:

```nginx
server {
    listen 80;
    server_name tu-dominio.com www.tu-dominio.com;
    
    # Redirigir a HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name tu-dominio.com www.tu-dominio.com;
    
    ssl_certificate /etc/nginx/ssl/cert.pem;
    ssl_certificate_key /etc/nginx/ssl/key.pem;
    
    # ... resto de configuración
}
```

## SSL/TLS

### Opción 1: Let's Encrypt (Recomendado)

```bash
# Instalar Certbot
sudo apt install certbot

# Obtener certificado
sudo certbot certonly --standalone -d tu-dominio.com -d www.tu-dominio.com

# Copiar certificados al contenedor
docker compose cp /etc/letsencrypt/live/tu-dominio.com/fullchain.pem nginx:/etc/nginx/ssl/cert.pem
docker compose cp /etc/letsencrypt/live/tu-dominio.com/privkey.pem nginx:/etc/nginx/ssl/key.pem

# Renovar automáticamente (añadir a crontab)
0 0 * * * certbot renew --quiet && docker compose restart nginx
```

### Opción 2: Certificado Propio

Copiar certificados a `docker/nginx/ssl/` y montarlos en el contenedor.

## Backup y Recuperación

### 1. Backup de Base de Datos

Crear script `scripts/backup-db.sh`:

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/jnc-adaprinters"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR

docker compose exec -T postgres pg_dump -U jnc_admin jnc_adaprinters | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Eliminar backups antiguos (mantener últimos 30 días)
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +30 -delete
```

### 2. Backup de Storage

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/jnc-adaprinters"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR

tar -czf $BACKUP_DIR/storage_$DATE.tar.gz -C /var/www/html/jnc-adaprinters/backend storage/

# Eliminar backups antiguos
find $BACKUP_DIR -name "storage_*.tar.gz" -mtime +30 -delete
```

### 3. Automatizar Backups

Añadir a crontab:

```bash
# Backup diario a las 2 AM
0 2 * * * /var/www/html/jnc-adaprinters/scripts/backup-db.sh
0 3 * * * /var/www/html/jnc-adaprinters/scripts/backup-storage.sh
```

### 4. Restauración

```bash
# Restaurar base de datos
gunzip < backup.sql.gz | docker compose exec -T postgres psql -U jnc_admin jnc_adaprinters

# Restaurar storage
tar -xzf storage_backup.tar.gz -C /var/www/html/jnc-adaprinters/backend/
```

## Monitoreo

### 1. Logs

```bash
# Ver logs en tiempo real
docker compose logs -f

# Logs de un servicio específico
docker compose logs -f app
docker compose logs -f horizon

# Rotar logs
docker compose exec app php artisan log:clear
```

### 2. Métricas de Horizon

Acceder a `https://tu-dominio.com/horizon` para ver:
- Jobs procesados
- Tiempo de procesamiento
- Errores
- Throughput

### 3. Monitoreo de Recursos

```bash
# Uso de recursos
docker stats

# Espacio en disco
df -h
docker system df
```

### 4. Alertas del Sistema

Configurar alertas para:
- Uso de CPU > 80%
- Uso de RAM > 80%
- Espacio en disco < 20%
- Servicios caídos

## Mantenimiento

### Actualización del Sistema

```bash
# 1. Backup
./scripts/backup-db.sh
./scripts/backup-storage.sh

# 2. Pull cambios
git pull origin main

# 3. Reconstruir contenedores
docker compose down
docker compose build --no-cache
docker compose up -d

# 4. Ejecutar migraciones
docker compose exec app php artisan migrate --force

# 5. Limpiar cache
docker compose exec app php artisan config:clear
docker compose exec app php artisan cache:clear
docker compose exec app php artisan view:clear

# 6. Reconstruir cache
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

### Limpieza Periódica

```bash
# Limpiar contenedores e imágenes no usadas
docker system prune -a

# Limpiar logs antiguos
docker compose exec app find storage/logs -name "*.log" -mtime +30 -delete

# Optimizar base de datos
docker compose exec postgres psql -U jnc_admin -d jnc_adaprinters -c "VACUUM ANALYZE;"
```

### Reinicio de Servicios

```bash
# Reinicio completo
docker compose restart

# Reinicio de un servicio específico
docker compose restart app
docker compose restart horizon

# Reinicio de Horizon (terminar workers)
docker compose exec horizon php artisan horizon:terminate
```

## Checklist de Producción

- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] Clave de aplicación generada
- [ ] Contraseñas seguras configuradas
- [ ] SSL/TLS configurado
- [ ] Firewall configurado
- [ ] Backups automatizados
- [ ] Monitoreo configurado
- [ ] Logs rotando correctamente
- [ ] Permisos de archivos correctos
- [ ] Cache de configuración activado
- [ ] Horizon configurado y corriendo
- [ ] Sincronización SNMP configurada
- [ ] Notificaciones SMTP configuradas

## Referencias

- [Guía de Instalación](INSTALLATION.md)
- [Documentación de Operaciones](docs/operations/MONITORING.md)
- [Documentación de Backup](docs/operations/BACKUP.md)

