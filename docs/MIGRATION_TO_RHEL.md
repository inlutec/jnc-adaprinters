# Guía de Migración a Servidor RHEL

Esta guía explica cómo migrar JNC-AdaPrinters desde un servidor actual a un nuevo servidor RHEL.

## Requisitos del Servidor RHEL

- **RHEL 8+** (o CentOS 8+, Rocky Linux 8+, AlmaLinux 8+)
- **PHP 8.0+** (recomendado 8.3)
- **PostgreSQL 16+**
- **Redis 7+**
- **Node.js 20+** y npm
- **Nginx 1.27+**
- **Composer** (se instalará con el script)
- **Git**

## Paso 1: Preparar el Repositorio Git

### 1.1 Verificar que no hay archivos sensibles en Git

```bash
cd /var/www/jnc-adaprinters
git status
```

Asegúrate de que los siguientes archivos NO estén en el repositorio:
- `backend/.env`
- `frontend/.env`
- `backend/storage/logs/*.log`
- `backend/storage/*.key`
- Cualquier archivo con contraseñas o datos sensibles

### 1.2 Subir cambios a Git

```bash
# Si no tienes repositorio remoto configurado:
git remote add origin https://github.com/tu-usuario/jnc-adaprinters.git

# O si ya existe:
git remote set-url origin https://github.com/tu-usuario/jnc-adaprinters.git

# Subir todo:
git add .
git commit -m "Preparación para migración a RHEL"
git push -u origin main
```

## Paso 2: Instalar Composer en el Servidor RHEL

### 2.1 Descargar el script de instalación

Si ya tienes acceso al repositorio Git, puedes clonarlo primero:

```bash
cd /tmp
git clone https://github.com/tu-usuario/jnc-adaprinters.git
cd jnc-adaprinters
```

### 2.2 Ejecutar el script de instalación de Composer

```bash
sudo chmod +x scripts/install-composer-rhel.sh
sudo ./scripts/install-composer-rhel.sh
```

Este script:
- Verifica que PHP esté instalado
- Descarga Composer desde getcomposer.org
- Verifica el checksum de seguridad
- Instala Composer en `/usr/local/bin/composer`
- Configura permisos

### 2.3 Verificar instalación

```bash
composer --version
```

Deberías ver algo como: `Composer version 2.x.x`

## Paso 3: Instalar JNC-AdaPrinters desde Git

### 3.1 Ejecutar el script de instalación completa

```bash
# Si ya clonaste el repositorio:
cd /var/www/jnc-adaprinters
sudo chmod +x scripts/install-from-git-rhel.sh
sudo ./scripts/install-from-git-rhel.sh

# O si quieres clonar directamente:
sudo chmod +x /tmp/jnc-adaprinters/scripts/install-from-git-rhel.sh
sudo /tmp/jnc-adaprinters/scripts/install-from-git-rhel.sh https://github.com/tu-usuario/jnc-adaprinters.git /var/www/jnc-adaprinters
```

El script:
- Verifica que todos los requisitos estén instalados
- Clona o actualiza el repositorio
- Instala dependencias de Composer (backend)
- Instala dependencias de npm (frontend)
- Compila el frontend
- Copia los assets al backend
- Configura permisos básicos
- Crea archivos .env si no existen

### 3.2 Configurar variables de entorno

Edita el archivo `.env` del backend:

```bash
sudo nano /var/www/jnc-adaprinters/backend/.env
```

Configura al menos estas variables:

```env
APP_NAME="JNC-AdaPrinters"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://TU_IP_O_DOMINIO

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=jnc_adaprinters
DB_USERNAME=jnc_admin
DB_PASSWORD=TU_PASSWORD_SEGURA

FRONTEND_URL=http://TU_IP_O_DOMINIO
SANCTUM_STATEFUL_DOMAINS=TU_IP_O_DOMINIO

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Edita el archivo `.env` del frontend:

```bash
sudo nano /var/www/jnc-adaprinters/frontend/.env
```

```env
VITE_API_URL=http://TU_IP_O_DOMINIO/api/v2
```

### 3.3 Generar clave de aplicación

```bash
cd /var/www/jnc-adaprinters/backend
php artisan key:generate
```

### 3.4 Ejecutar migraciones

```bash
cd /var/www/jnc-adaprinters/backend
php artisan migrate --seed
```

Esto creará todas las tablas y el usuario admin por defecto:
- **Usuario**: `admin@jnc-adaprinters.local`
- **Contraseña**: `admin123`

⚠️ **IMPORTANTE**: Cambia esta contraseña después del primer login.

## Paso 4: Configurar Nginx

Sigue las instrucciones en `INSTALLATION_RHEL.md` para configurar Nginx. Básicamente:

1. Crea un archivo de configuración en `/etc/nginx/conf.d/jnc-adaprinters.conf`
2. Configura el `server_name` con tu IP o dominio
3. Configura `root` apuntando a `/var/www/jnc-adaprinters/backend/public`
4. Configura PHP-FPM
5. Recarga Nginx: `sudo systemctl reload nginx`

## Paso 5: Configurar Permisos y SELinux

### 5.1 Permisos de archivos

```bash
sudo chown -R nginx:nginx /var/www/jnc-adaprinters/backend/storage
sudo chown -R nginx:nginx /var/www/jnc-adaprinters/backend/bootstrap/cache
sudo chmod -R 775 /var/www/jnc-adaprinters/backend/storage
sudo chmod -R 775 /var/www/jnc-adaprinters/backend/bootstrap/cache
```

### 5.2 SELinux (si está activo)

```bash
# Verificar estado
getenforce

# Si está en Enforcing, configurar contextos:
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/jnc-adaprinters/backend/storage(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/jnc-adaprinters/backend/bootstrap/cache(/.*)?"
sudo restorecon -Rv /var/www/jnc-adaprinters/backend/storage
sudo restorecon -Rv /var/www/jnc-adaprinters/backend/bootstrap/cache

# Permitir conexiones de red
sudo setsebool -P httpd_can_network_connect_db 1
```

## Paso 6: Configurar Servicios

### 6.1 PHP-FPM

```bash
sudo systemctl start php-fpm
sudo systemctl enable php-fpm
```

### 6.2 Redis

```bash
sudo systemctl start redis
sudo systemctl enable redis
```

### 6.3 PostgreSQL

```bash
sudo systemctl start postgresql-16
sudo systemctl enable postgresql-16
```

### 6.4 Nginx

```bash
sudo systemctl start nginx
sudo systemctl enable nginx
```

## Paso 7: Migrar Datos (Opcional)

Si necesitas migrar datos del servidor antiguo:

### 7.1 Exportar base de datos

En el servidor antiguo:

```bash
pg_dump -U jnc_admin -h localhost jnc_adaprinters > backup.sql
```

### 7.2 Importar en el nuevo servidor

```bash
psql -U jnc_admin -h localhost -d jnc_adaprinters < backup.sql
```

### 7.3 Migrar archivos

Si tienes fotos de impresoras u otros archivos en `backend/storage/app/public`:

```bash
# En servidor antiguo
tar -czf storage_backup.tar.gz backend/storage/app/public

# En servidor nuevo
tar -xzf storage_backup.tar.gz -C /var/www/jnc-adaprinters/backend/storage/app/
```

## Paso 8: Verificar Instalación

1. Accede a `http://TU_IP_O_DOMINIO` en el navegador
2. Deberías ver la pantalla de login
3. Inicia sesión con:
   - Usuario: `admin@jnc-adaprinters.local`
   - Contraseña: `admin123`
4. Cambia la contraseña inmediatamente

## Solución de Problemas

### Error: "Composer no encontrado"
- Ejecuta: `sudo ./scripts/install-composer-rhel.sh`

### Error: "Permission denied" en storage
- Ejecuta los comandos de permisos del Paso 5

### Error: "502 Bad Gateway"
- Verifica que PHP-FPM esté corriendo: `sudo systemctl status php-fpm`
- Verifica la configuración de Nginx

### Error: "Database connection failed"
- Verifica las credenciales en `.env`
- Verifica que PostgreSQL esté corriendo: `sudo systemctl status postgresql-16`
- Verifica que la base de datos existe: `sudo -u postgres psql -l`

### Error: "CORS" o "Connection timeout"
- Verifica `APP_URL`, `FRONTEND_URL` y `SANCTUM_STATEFUL_DOMAINS` en `.env`
- Verifica `VITE_API_URL` en `frontend/.env`
- Recompila el frontend: `cd frontend && npm run build && cp -r dist/* ../backend/public/`

## Notas Importantes

1. **PHP 8.0 vs 8.3**: El proyecto está optimizado para PHP 8.3, pero funcionará con 8.0. Si tienes problemas, considera actualizar a 8.3.

2. **Base de datos**: Asegúrate de crear la base de datos y el usuario antes de ejecutar las migraciones.

3. **Seguridad**: 
   - Cambia todas las contraseñas por defecto
   - Configura firewall adecuadamente
   - Considera usar SSL/TLS en producción

4. **Backups**: Configura backups regulares de la base de datos y archivos importantes.
