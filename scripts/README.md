# Scripts de Instalación - JNC-AdaPrinters

Este directorio contiene scripts automatizados para facilitar la instalación de JNC-AdaPrinters en servidores Linux sin Docker.

## Scripts Disponibles

### 1. `install-dependencies.sh`
Instala todas las dependencias del sistema necesarias (librerías de desarrollo, herramientas, etc.).

**Uso:**
```bash
sudo ./scripts/install-dependencies.sh
```

**Compatibilidad:** Ubuntu/Debian y RHEL/CentOS

### 2. `setup-php.sh`
Instala y configura PHP 8.3 con todas las extensiones necesarias.

**Uso:**
```bash
sudo ./scripts/setup-php.sh
```

**Compatibilidad:** Ubuntu/Debian (RHEL requiere configuración manual, ver INSTALLATION_RHEL.md)

### 3. `setup-database.sh`
Configura PostgreSQL 16, crea la base de datos y el usuario.

**Uso:**
```bash
# Con variables de entorno
export DB_NAME=jnc_adaprinters
export DB_USER=jnc_admin
export DB_PASSWORD=tu_password_segura
sudo ./scripts/setup-database.sh

# O interactivo (solicitará la contraseña)
sudo ./scripts/setup-database.sh
```

**Compatibilidad:** Ubuntu/Debian y RHEL/CentOS

### 4. `setup-redis.sh`
Instala y configura Redis 7.

**Uso:**
```bash
sudo ./scripts/setup-redis.sh
```

**Compatibilidad:** Ubuntu/Debian y RHEL/CentOS

### 5. `setup-nginx.sh`
Instala y configura Nginx con la configuración del proyecto.

**Uso:**
```bash
# Con variables de entorno
export APP_PATH=/var/www/jnc-adaprinters
export DOMAIN=tu-dominio.com
sudo ./scripts/setup-nginx.sh

# O con valores por defecto
sudo ./scripts/setup-nginx.sh
```

**Compatibilidad:** Ubuntu/Debian y RHEL/CentOS

### 6. `setup-laravel.sh`
Instala dependencias de Composer, configura Laravel y ejecuta migraciones.

**Uso:**
```bash
# Con variables de entorno
export APP_PATH=/var/www/jnc-adaprinters
export WWW_USER=www-data
sudo ./scripts/setup-laravel.sh

# O con valores por defecto
sudo ./scripts/setup-laravel.sh
```

**Compatibilidad:** Ubuntu/Debian y RHEL/CentOS (ajustar WWW_USER para RHEL: `nginx`)

### 7. `setup-frontend.sh`
Instala dependencias de npm y compila el frontend para producción.

**Uso:**
```bash
# Con variables de entorno
export APP_PATH=/var/www/jnc-adaprinters
export WWW_USER=www-data
./scripts/setup-frontend.sh

# O con valores por defecto
./scripts/setup-frontend.sh
```

**Compatibilidad:** Ubuntu/Debian y RHEL/CentOS

### 8. `setup-services.sh`
Configura e inicia los servicios systemd (Horizon y Scheduler).

**Uso:**
```bash
# Con variables de entorno
export APP_PATH=/var/www/jnc-adaprinters
export WWW_USER=www-data
sudo ./scripts/setup-services.sh

# O con valores por defecto
sudo ./scripts/setup-services.sh
```

**Compatibilidad:** Ubuntu/Debian y RHEL/CentOS (ajustar WWW_USER para RHEL: `nginx`)

### 9. `verify-installation.sh`
Verifica que todos los componentes están instalados y funcionando correctamente.

**Uso:**
```bash
# Con variables de entorno
export APP_PATH=/var/www/jnc-adaprinters
./scripts/verify-installation.sh

# O con valores por defecto
./scripts/verify-installation.sh
```

**Compatibilidad:** Ubuntu/Debian y RHEL/CentOS

## Instalación Completa Automatizada

Para una instalación completa, ejecuta los scripts en orden:

```bash
# 1. Dependencias del sistema
sudo ./scripts/install-dependencies.sh

# 2. PHP
sudo ./scripts/setup-php.sh

# 3. Base de datos
sudo ./scripts/setup-database.sh

# 4. Redis
sudo ./scripts/setup-redis.sh

# 5. Nginx
sudo ./scripts/setup-nginx.sh

# 6. Laravel (requiere que el proyecto esté clonado)
sudo ./scripts/setup-laravel.sh

# 7. Frontend
./scripts/setup-frontend.sh

# 8. Servicios systemd
sudo ./scripts/setup-services.sh

# 9. Verificación
./scripts/verify-installation.sh
```

## Variables de Entorno

Los scripts aceptan las siguientes variables de entorno:

- `APP_PATH`: Ruta donde está instalado el proyecto (default: `/var/www/jnc-adaprinters`)
- `WWW_USER`: Usuario del servidor web (default: `www-data`, usar `nginx` para RHEL)
- `DOMAIN`: Dominio del sitio (default: `localhost`)
- `DB_NAME`: Nombre de la base de datos (default: `jnc_adaprinters`)
- `DB_USER`: Usuario de la base de datos (default: `jnc_admin`)
- `DB_PASSWORD`: Contraseña de la base de datos (se solicita si no está definida)

## Ejemplo de Instalación Completa con Variables

```bash
export APP_PATH=/var/www/jnc-adaprinters
export WWW_USER=www-data  # o 'nginx' para RHEL
export DOMAIN=adaprinters.ejemplo.com
export DB_PASSWORD=password_segura_123

sudo ./scripts/install-dependencies.sh
sudo ./scripts/setup-php.sh
sudo ./scripts/setup-database.sh
sudo ./scripts/setup-redis.sh
sudo ./scripts/setup-nginx.sh
sudo ./scripts/setup-laravel.sh
./scripts/setup-frontend.sh
sudo ./scripts/setup-services.sh
./scripts/verify-installation.sh
```

## Notas Importantes

1. **Permisos**: Algunos scripts requieren ejecución con `sudo`
2. **Orden**: Ejecuta los scripts en el orden indicado
3. **RHEL**: Para RHEL/CentOS, ajusta `WWW_USER=nginx` y sigue las instrucciones en `INSTALLATION_RHEL.md`
4. **Configuración Manual**: Después de ejecutar los scripts, revisa y ajusta manualmente:
   - Variables de entorno en `backend/.env`
   - Configuración de Nginx si es necesario
   - Configuración de firewall
   - Configuración de SELinux (RHEL)

## Solución de Problemas

Si un script falla:

1. Revisa los mensajes de error
2. Verifica los logs del sistema: `sudo journalctl -xe`
3. Ejecuta el script de verificación: `./scripts/verify-installation.sh`
4. Consulta la documentación principal: `INSTALLATION_NATIVE.md` o `INSTALLATION_RHEL.md`

## Referencias

- [Guía de Instalación Nativa](../INSTALLATION_NATIVE.md)
- [Guía de Instalación RHEL](../INSTALLATION_RHEL.md)
- [Guía de Migración desde Docker](../docs/MIGRATION_FROM_DOCKER.md)

