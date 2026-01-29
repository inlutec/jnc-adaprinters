# Guía Paso a Paso - Migración a Servidor RHEL

Esta guía te llevará paso a paso para instalar JNC-AdaPrinters en tu servidor RHEL sin necesidad de Cursor.

## 📋 Requisitos Previos

- Servidor RHEL 8+ (o CentOS/Rocky Linux/AlmaLinux)
- Acceso root o sudo
- PHP 8.0+ instalado
- Conexión a internet
- Mínimo 4 GB RAM y 20 GB espacio en disco

---

## PASO 1: Verificar Requisitos Básicos

```bash
# Verificar versión de PHP
php -v
# Debe mostrar PHP 8.0 o superior

# Verificar que Git esté instalado
git --version
# Si no está instalado:
sudo dnf install -y git

# Verificar espacio en disco
df -h
# Debe tener al menos 20 GB libres

# Verificar memoria
free -h
# Debe tener al menos 4 GB RAM
```

---

## PASO 2: Clonar el Repositorio

```bash
# Crear directorio si no existe
sudo mkdir -p /var/www
cd /var/www

# Clonar el repositorio
sudo git clone https://github.com/inlutec/jnc-adaprinters.git

# Cambiar propietario (ajusta el usuario según tu configuración)
sudo chown -R $USER:$USER /var/www/jnc-adaprinters
# O si usas nginx:
# sudo chown -R nginx:nginx /var/www/jnc-adaprinters

# Entrar al directorio
cd /var/www/jnc-adaprinters

# Verificar que se clonó correctamente
ls -la
```

---

## PASO 3: Instalar Composer

```bash
# Entrar al directorio del proyecto
cd /var/www/jnc-adaprinters

# Dar permisos de ejecución al script
chmod +x scripts/install-composer-rhel.sh

# Ejecutar el script de instalación de Composer
sudo ./scripts/install-composer-rhel.sh

# Verificar que Composer se instaló correctamente
composer --version
# Debe mostrar algo como: Composer version 2.x.x
```

**Si hay algún error**, el script intentará descargar Composer desde internet automáticamente.

---

## PASO 4: Instalar PostgreSQL 16

```bash
# Instalar repositorio de PostgreSQL
sudo dnf install -y https://download.postgresql.org/pub/repos/yum/reporpms/EL-$(rpm -E %{rhel})-x86_64/pgdg-redhat-repo-latest.noarch.rpm

# Instalar PostgreSQL 16
sudo dnf install -y postgresql16-server postgresql16

# Inicializar base de datos
sudo /usr/pgsql-16/bin/postgresql-16-setup initdb

# Iniciar y habilitar PostgreSQL
sudo systemctl start postgresql-16
sudo systemctl enable postgresql-16

# Verificar que está corriendo
sudo systemctl status postgresql-16
```

### Crear Base de Datos y Usuario

```bash
# Cambiar al usuario postgres
sudo -u postgres psql

# Dentro de psql, ejecutar:
CREATE DATABASE jnc_adaprinters;
CREATE USER jnc_admin WITH PASSWORD 'CAMBIAR_PASSWORD_SEGURA_AQUI';
ALTER ROLE jnc_admin SET client_encoding TO 'utf8';
ALTER ROLE jnc_admin SET default_transaction_isolation TO 'read committed';
ALTER ROLE jnc_admin SET timezone TO 'UTC';
GRANT ALL PRIVILEGES ON DATABASE jnc_adaprinters TO jnc_admin;
\c jnc_adaprinters
GRANT ALL ON SCHEMA public TO jnc_admin;
\q
```

**⚠️ IMPORTANTE**: Cambia `CAMBIAR_PASSWORD_SEGURA_AQUI` por una contraseña segura y guárdala, la necesitarás después.

---

## PASO 5: Instalar Redis

```bash
# Instalar Redis
sudo dnf install -y redis

# Editar configuración (opcional, para seguridad)
sudo nano /etc/redis.conf
# Asegúrate de que tenga:
# bind 127.0.0.1
# protected-mode yes

# Iniciar y habilitar Redis
sudo systemctl start redis
sudo systemctl enable redis

# Verificar que está corriendo
sudo systemctl status redis
```

---

## PASO 6: Instalar Node.js 20

```bash
# Opción 1: Usando NodeSource (Recomendado)
curl -fsSL https://rpm.nodesource.com/setup_20.x | sudo bash -
sudo dnf install -y nodejs

# Opción 2: Usando dnf (si la opción 1 no funciona)
sudo dnf install -y nodejs npm

# Verificar instalación
node -v
# Debe mostrar: v20.x.x o superior
npm -v
```

---

## PASO 7: Instalar Nginx

```bash
# Instalar Nginx
sudo dnf install -y nginx

# Iniciar y habilitar Nginx
sudo systemctl start nginx
sudo systemctl enable nginx

# Verificar que está corriendo
sudo systemctl status nginx
```

---

## PASO 8: Instalar PHP-FPM y Extensiones

```bash
# Instalar PHP-FPM y extensiones necesarias
sudo dnf install -y php-fpm php-pgsql php-redis php-zip php-gd php-mbstring php-xml php-curl php-bcmath php-intl php-snmp

# Verificar que PHP-FPM está instalado
php-fpm -v

# Iniciar y habilitar PHP-FPM
sudo systemctl start php-fpm
sudo systemctl enable php-fpm

# Verificar que está corriendo
sudo systemctl status php-fpm
```

---

## PASO 9: Instalar JNC-AdaPrinters

```bash
# Entrar al directorio del proyecto
cd /var/www/jnc-adaprinters

# Dar permisos de ejecución al script
chmod +x scripts/install-from-git-rhel.sh

# Ejecutar el script de instalación completa
sudo ./scripts/install-from-git-rhel.sh

# El script te pedirá la URL del servidor
# Ejemplo: http://10.47.12.13 o http://tu-dominio.com
```

El script hará:
- Instalar dependencias de Composer (backend)
- Instalar dependencias de npm (frontend)
- Compilar el frontend
- Copiar assets al backend
- Configurar permisos básicos

---

## PASO 10: Configurar Variables de Entorno del Backend

```bash
# Editar el archivo .env del backend
cd /var/www/jnc-adaprinters/backend
sudo nano .env
```

Configura al menos estas variables (ajusta según tu servidor):

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
DB_PASSWORD=LA_PASSWORD_QUE_CREASTE_EN_PASO_4

FRONTEND_URL=http://TU_IP_O_DOMINIO
SANCTUM_STATEFUL_DOMAINS=TU_IP_O_DOMINIO

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=redis
```

**⚠️ IMPORTANTE**: Reemplaza:
- `TU_IP_O_DOMINIO` por tu IP o dominio (ej: `http://10.47.12.13`)
- `LA_PASSWORD_QUE_CREASTE_EN_PASO_4` por la contraseña que creaste para el usuario de PostgreSQL

---

## PASO 11: Configurar Variables de Entorno del Frontend

```bash
# Editar el archivo .env del frontend
cd /var/www/jnc-adaprinters/frontend
sudo nano .env
```

Configura:

```env
VITE_API_URL=http://TU_IP_O_DOMINIO/api/v2
```

**⚠️ IMPORTANTE**: Reemplaza `TU_IP_O_DOMINIO` por tu IP o dominio (ej: `http://10.47.12.13`)

---

## PASO 12: Generar Clave de Aplicación

```bash
cd /var/www/jnc-adaprinters/backend
php artisan key:generate
```

Esto generará la clave `APP_KEY` en el archivo `.env`.

---

## PASO 13: Ejecutar Migraciones

```bash
cd /var/www/jnc-adaprinters/backend

# Ejecutar migraciones y seeders
php artisan migrate --seed
```

Esto creará todas las tablas y el usuario admin por defecto:
- **Usuario**: `admin@jnc-adaprinters.local`
- **Contraseña**: `admin123`

**⚠️ IMPORTANTE**: Cambia esta contraseña después del primer login.

---

## PASO 14: Configurar Nginx

```bash
# Crear archivo de configuración de Nginx
sudo nano /etc/nginx/conf.d/jnc-adaprinters.conf
```

Pega esta configuración (ajusta según tu IP/dominio):

```nginx
server {
    listen 80;
    server_name TU_IP_O_DOMINIO;  # Ejemplo: 10.47.12.13 o tu-dominio.com
    root /var/www/jnc-adaprinters/backend/public;
    index index.php index.html;

    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

**⚠️ IMPORTANTE**: Reemplaza `TU_IP_O_DOMINIO` por tu IP o dominio.

```bash
# Verificar configuración de Nginx
sudo nginx -t

# Si todo está bien, recargar Nginx
sudo systemctl reload nginx
```

---

## PASO 15: Configurar Permisos

```bash
# Configurar propietario (ajusta según tu usuario de Nginx)
sudo chown -R nginx:nginx /var/www/jnc-adaprinters/backend/storage
sudo chown -R nginx:nginx /var/www/jnc-adaprinters/backend/bootstrap/cache

# Configurar permisos
sudo chmod -R 775 /var/www/jnc-adaprinters/backend/storage
sudo chmod -R 775 /var/www/jnc-adaprinters/backend/bootstrap/cache
```

Si tu usuario de Nginx es diferente (ej: `apache`, `www-data`), ajusta los comandos.

---

## PASO 16: Configurar SELinux (si está activo)

```bash
# Verificar estado de SELinux
getenforce
# Si muestra "Enforcing", continúa con los siguientes pasos

# Configurar contextos SELinux
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/jnc-adaprinters/backend/storage(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/jnc-adaprinters/backend/bootstrap/cache(/.*)?"
sudo restorecon -Rv /var/www/jnc-adaprinters/backend/storage
sudo restorecon -Rv /var/www/jnc-adaprinters/backend/bootstrap/cache

# Permitir conexiones de red
sudo setsebool -P httpd_can_network_connect_db 1
```

Si no tienes `semanage` instalado:
```bash
sudo dnf install -y policycoreutils-python-utils
```

---

## PASO 17: Configurar Firewall

```bash
# Permitir HTTP y HTTPS
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https

# Recargar firewall
sudo firewall-cmd --reload

# Verificar reglas
sudo firewall-cmd --list-all
```

---

## PASO 18: Verificar Servicios

```bash
# Verificar que todos los servicios estén corriendo
sudo systemctl status postgresql-16
sudo systemctl status redis
sudo systemctl status php-fpm
sudo systemctl status nginx

# Si algún servicio no está corriendo, iniciarlo:
sudo systemctl start nombre-del-servicio
sudo systemctl enable nombre-del-servicio
```

---

## PASO 19: Verificar Instalación

1. **Abrir navegador** y acceder a: `http://TU_IP_O_DOMINIO`
2. Deberías ver la **pantalla de login** de JNC-AdaPrinters
3. **Iniciar sesión** con:
   - Usuario: `admin@jnc-adaprinters.local`
   - Contraseña: `admin123`
4. **Cambiar la contraseña** inmediatamente después del primer login

---

## PASO 20: Recompilar Frontend (si es necesario)

Si el frontend no se ve correctamente o hay errores de API:

```bash
cd /var/www/jnc-adaprinters/frontend

# Verificar que .env tiene la URL correcta
cat .env
# Debe mostrar: VITE_API_URL=http://TU_IP/api/v2

# Recompilar frontend
npm run build

# Copiar al backend
cp -r dist/* ../backend/public/
```

---

## 🔧 Solución de Problemas

### Error: "502 Bad Gateway"
```bash
# Verificar que PHP-FPM está corriendo
sudo systemctl status php-fpm

# Verificar el socket en la configuración de Nginx
# Debe coincidir con: /run/php-fpm/www.sock
ls -la /run/php-fpm/
```

### Error: "Database connection failed"
```bash
# Verificar que PostgreSQL está corriendo
sudo systemctl status postgresql-16

# Probar conexión
psql -U jnc_admin -h localhost -d jnc_adaprinters

# Verificar credenciales en .env
cat /var/www/jnc-adaprinters/backend/.env | grep DB_
```

### Error: "Permission denied" en storage
```bash
# Reconfigurar permisos
sudo chown -R nginx:nginx /var/www/jnc-adaprinters/backend/storage
sudo chmod -R 775 /var/www/jnc-adaprinters/backend/storage
```

### Error: CORS o "Connection timeout"
```bash
# Verificar URLs en .env
cat /var/www/jnc-adaprinters/backend/.env | grep -E "APP_URL|FRONTEND_URL|SANCTUM"
cat /var/www/jnc-adaprinters/frontend/.env

# Limpiar caché de Laravel
cd /var/www/jnc-adaprinters/backend
php artisan config:clear
php artisan cache:clear
```

### Ver logs
```bash
# Logs de Laravel
tail -f /var/www/jnc-adaprinters/backend/storage/logs/laravel.log

# Logs de Nginx
sudo tail -f /var/log/nginx/error.log

# Logs de PHP-FPM
sudo tail -f /var/log/php-fpm/error.log
```

---

## ✅ Checklist Final

- [ ] PHP 8.0+ instalado y funcionando
- [ ] Composer instalado y funcionando
- [ ] PostgreSQL 16 instalado, base de datos y usuario creados
- [ ] Redis instalado y corriendo
- [ ] Node.js 20+ instalado
- [ ] Nginx instalado y configurado
- [ ] PHP-FPM instalado y corriendo
- [ ] Repositorio clonado en `/var/www/jnc-adaprinters`
- [ ] Dependencias instaladas (Composer y npm)
- [ ] Frontend compilado
- [ ] Archivos `.env` configurados (backend y frontend)
- [ ] Clave de aplicación generada
- [ ] Migraciones ejecutadas
- [ ] Permisos configurados
- [ ] SELinux configurado (si está activo)
- [ ] Firewall configurado
- [ ] Todos los servicios corriendo
- [ ] Acceso a la aplicación en el navegador
- [ ] Login exitoso con credenciales por defecto
- [ ] Contraseña cambiada

---

## 📚 Documentación Adicional

- `INSTALLATION_RHEL.md` - Guía completa de instalación
- `docs/MIGRATION_TO_RHEL.md` - Guía detallada de migración
- `MIGRATION_SUMMARY.md` - Resumen rápido

---

¡Listo! Si sigues estos pasos en orden, tendrás JNC-AdaPrinters funcionando en tu servidor RHEL.
