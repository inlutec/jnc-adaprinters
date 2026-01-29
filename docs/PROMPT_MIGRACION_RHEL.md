# Prompt para Migración a Servidor RHEL

Copia y pega este prompt completo en Cursor o cualquier asistente de IA para iniciar la migración:

---

## Prompt para Asistente IA

```
Necesito migrar la aplicación JNC-AdaPrinters desde un servidor actual a un nuevo servidor RHEL. 
La aplicación está en el repositorio Git: https://github.com/inlutec/jnc-adaprinters.git

CONTEXTO:
- Servidor destino: RHEL 8+ (o CentOS/Rocky Linux/AlmaLinux)
- PHP 8.0 ya está instalado en el servidor
- Necesito instalar: Composer, PostgreSQL 16+, Redis, Node.js 20+, Nginx
- El repositorio Git ya contiene scripts de instalación y documentación completa

REQUISITOS DEL SERVIDOR:
- RHEL 8+ con acceso root/sudo
- PHP 8.0+ instalado
- Conexión a internet (para clonar desde Git)
- Mínimo 4 GB RAM y 20 GB espacio en disco

TAREAS A REALIZAR:

1. VERIFICAR REQUISITOS:
   - Verificar versión de PHP: `php -v`
   - Verificar que Git esté instalado: `git --version`
   - Verificar espacio en disco: `df -h`
   - Verificar memoria: `free -h`

2. CLONAR REPOSITORIO:
   - Clonar en `/var/www/jnc-adaprinters`
   - Verificar que se clonó correctamente

3. INSTALAR COMPOSER:
   - Ejecutar: `sudo ./scripts/install-composer-rhel.sh`
   - El script usará el instalador local incluido en el repo
   - Verificar: `composer --version`

4. INSTALAR DEPENDENCIAS DEL SISTEMA:
   - Seguir la guía: `INSTALLATION_RHEL.md`
   - Instalar PostgreSQL 16+, Redis, Node.js 20+, Nginx
   - Configurar servicios y habilitarlos

5. INSTALAR JNC-ADAPRINTERS:
   - Ejecutar: `sudo ./scripts/install-from-git-rhel.sh`
   - Este script instalará dependencias de Composer y npm
   - Compilará el frontend
   - Configurará permisos básicos

6. CONFIGURAR VARIABLES DE ENTORNO:
   - Crear/editar: `backend/.env` con configuración de DB, Redis, URLs
   - Crear/editar: `frontend/.env` con `VITE_API_URL`
   - Generar clave de aplicación: `php artisan key:generate`

7. CONFIGURAR BASE DE DATOS:
   - Crear base de datos PostgreSQL: `jnc_adaprinters`
   - Crear usuario: `jnc_admin` con contraseña segura
   - Ejecutar migraciones: `php artisan migrate --seed`

8. CONFIGURAR NGINX:
   - Crear configuración en `/etc/nginx/conf.d/jnc-adaprinters.conf`
   - Configurar PHP-FPM
   - Recargar Nginx

9. CONFIGURAR PERMISOS Y SELINUX:
   - Configurar permisos de storage y cache
   - Si SELinux está activo, configurar contextos

10. VERIFICAR INSTALACIÓN:
    - Verificar que todos los servicios estén corriendo
    - Acceder a la aplicación en el navegador
    - Probar login con credenciales por defecto

DOCUMENTACIÓN DISPONIBLE:
- `INSTALLATION_RHEL.md` - Guía completa de instalación
- `docs/MIGRATION_TO_RHEL.md` - Guía detallada de migración
- `MIGRATION_SUMMARY.md` - Resumen rápido
- `scripts/install-composer-rhel.sh` - Instalador de Composer
- `scripts/install-from-git-rhel.sh` - Instalador completo

IMPORTANTE:
- Las credenciales por defecto son: admin@jnc-adaprinters.local / admin123
- Cambiar contraseñas después del primer login
- Verificar que el frontend compilado esté en `backend/public/`
- Verificar que CORS esté configurado correctamente en `backend/config/cors.php`

Por favor, guíame paso a paso en la instalación, verificando cada paso antes de continuar al siguiente.
```

---

## Versión Corta (Prompt Resumido)

Si prefieres un prompt más corto:

```
Necesito instalar JNC-AdaPrinters en un servidor RHEL nuevo. 
El repositorio está en: https://github.com/inlutec/jnc-adaprinters.git

El servidor tiene PHP 8.0 instalado. Necesito:
1. Clonar el repositorio en /var/www/jnc-adaprinters
2. Instalar Composer usando ./scripts/install-composer-rhel.sh
3. Instalar dependencias del sistema (PostgreSQL, Redis, Node.js, Nginx) según INSTALLATION_RHEL.md
4. Ejecutar ./scripts/install-from-git-rhel.sh para instalar la aplicación
5. Configurar .env, base de datos, Nginx y permisos
6. Verificar que todo funcione

Guíame paso a paso, verificando cada paso antes de continuar.
```

---

## Checklist Rápido para el Asistente

```
CHECKLIST DE MIGRACIÓN RHEL:

□ 1. Verificar PHP 8.0+ instalado
□ 2. Clonar repositorio: git clone https://github.com/inlutec/jnc-adaprinters.git /var/www/jnc-adaprinters
□ 3. Instalar Composer: sudo ./scripts/install-composer-rhel.sh
□ 4. Instalar PostgreSQL 16+ (ver INSTALLATION_RHEL.md)
□ 5. Instalar Redis (ver INSTALLATION_RHEL.md)
□ 6. Instalar Node.js 20+ (ver INSTALLATION_RHEL.md)
□ 7. Instalar Nginx (ver INSTALLATION_RHEL.md)
□ 8. Ejecutar: sudo ./scripts/install-from-git-rhel.sh
□ 9. Configurar backend/.env (DB, Redis, APP_URL, etc.)
□ 10. Configurar frontend/.env (VITE_API_URL)
□ 11. php artisan key:generate
□ 12. Crear base de datos PostgreSQL
□ 13. php artisan migrate --seed
□ 14. Configurar Nginx (ver INSTALLATION_RHEL.md)
□ 15. Configurar permisos: chown -R nginx:nginx storage bootstrap/cache
□ 16. Configurar SELinux si está activo
□ 17. Iniciar servicios: php-fpm, redis, postgresql, nginx
□ 18. Verificar acceso en navegador
□ 19. Probar login: admin@jnc-adaprinters.local / admin123
□ 20. Cambiar contraseña por defecto
```

---

## Comandos Rápidos de Referencia

```bash
# Clonar repositorio
git clone https://github.com/inlutec/jnc-adaprinters.git /var/www/jnc-adaprinters
cd /var/www/jnc-adaprinters

# Instalar Composer
sudo ./scripts/install-composer-rhel.sh

# Instalar aplicación completa
sudo ./scripts/install-from-git-rhel.sh

# Configurar .env
sudo nano backend/.env
sudo nano frontend/.env

# Generar clave y migrar
cd backend
php artisan key:generate
php artisan migrate --seed

# Verificar servicios
sudo systemctl status php-fpm redis postgresql-16 nginx
```

---

## Información de Troubleshooting

Si el asistente encuentra problemas, referir a:
- `docs/MIGRATION_TO_RHEL.md` - Sección "Solución de Problemas"
- `docs/operations/TROUBLESHOOTING.md` - Troubleshooting general
- Logs: `backend/storage/logs/laravel.log`
- Nginx logs: `/var/log/nginx/error.log`
- PHP-FPM logs: `/var/log/php-fpm/error.log`
