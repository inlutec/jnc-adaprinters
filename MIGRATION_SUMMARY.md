# Resumen de Preparación para Migración a RHEL

## ✅ Archivos Creados

Se han creado los siguientes archivos para facilitar la migración:

### 1. Scripts de Instalación

- **`scripts/install-composer-rhel.sh`**
  - Instala Composer en servidores RHEL/CentOS/Rocky Linux/AlmaLinux
  - Verifica PHP y checksum de seguridad
  - Instala Composer en `/usr/local/bin/composer`

- **`scripts/install-from-git-rhel.sh`**
  - Instala JNC-AdaPrinters completo desde un repositorio Git
  - Verifica requisitos (git, php, composer, node, npm, psql)
  - Clona/actualiza el repositorio
  - Instala dependencias (Composer y npm)
  - Compila el frontend
  - Configura permisos básicos

### 2. Documentación

- **`docs/MIGRATION_TO_RHEL.md`**
  - Guía completa paso a paso para migrar a RHEL
  - Incluye instalación de Composer
  - Instrucciones de configuración
  - Solución de problemas comunes

## 📋 Checklist Pre-Migración

Antes de subir a Git, verifica:

- [ ] `.gitignore` está configurado correctamente (✓ ya verificado)
- [ ] No hay archivos `.env` en el repositorio
- [ ] No hay logs sensibles en `backend/storage/logs/`
- [ ] No hay backups de base de datos (`.sql`, `.sql.gz`)
- [ ] Los scripts tienen permisos de ejecución (✓ ya configurado)

## 🚀 Pasos para Migrar

### En el Servidor Actual (este servidor)

1. **Verificar que todo esté listo para Git:**
   ```bash
   cd /var/www/jnc-adaprinters
   git status
   ```

2. **Subir a Git:**
   ```bash
   git add .
   git commit -m "Preparación para migración a RHEL - Scripts de instalación"
   git push origin main
   ```

### En el Servidor RHEL Nuevo

1. **Instalar Composer:**
   ```bash
   # Opción 1: Si ya tienes el repo clonado
   cd /var/www/jnc-adaprinters
   sudo ./scripts/install-composer-rhel.sh
   
   # Opción 2: Descargar solo el script
   curl -O https://raw.githubusercontent.com/tu-usuario/jnc-adaprinters/main/scripts/install-composer-rhel.sh
   sudo chmod +x install-composer-rhel.sh
   sudo ./install-composer-rhel.sh
   ```

2. **Instalar JNC-AdaPrinters completo:**
   ```bash
   sudo ./scripts/install-from-git-rhel.sh https://github.com/tu-usuario/jnc-adaprinters.git /var/www/jnc-adaprinters
   ```

3. **Seguir la guía completa:**
   - Ver `docs/MIGRATION_TO_RHEL.md` para los pasos detallados

## 📝 Notas Importantes

1. **PHP 8.0**: El servidor RHEL tiene PHP 8.0. El proyecto está optimizado para 8.3, pero funcionará con 8.0. Si encuentras problemas, considera actualizar a 8.3.

2. **Variables de Entorno**: Después de clonar, necesitarás configurar:
   - `backend/.env` - Configuración de Laravel (DB, Redis, URLs)
   - `frontend/.env` - URL de la API (`VITE_API_URL`)

3. **Base de Datos**: Asegúrate de tener PostgreSQL 16+ instalado y configurado antes de ejecutar las migraciones.

4. **Permisos**: Los scripts configuran permisos básicos, pero puede que necesites ajustarlos según tu configuración de Nginx/usuario.

## 🔍 Verificación Post-Instalación

Después de instalar en RHEL, verifica:

- [ ] Composer funciona: `composer --version`
- [ ] PHP funciona: `php -v`
- [ ] Node.js funciona: `node -v` y `npm -v`
- [ ] PostgreSQL está corriendo: `sudo systemctl status postgresql-16`
- [ ] Redis está corriendo: `sudo systemctl status redis`
- [ ] Nginx está configurado y sirve la aplicación
- [ ] Puedes acceder a la aplicación en el navegador
- [ ] El login funciona con las credenciales por defecto

## 📚 Documentación Adicional

- **Instalación RHEL completa**: `INSTALLATION_RHEL.md`
- **Guía de migración detallada**: `docs/MIGRATION_TO_RHEL.md`
- **Troubleshooting**: `docs/operations/TROUBLESHOOTING.md`

## 🆘 Si Algo Sale Mal

1. Revisa los logs:
   - Laravel: `backend/storage/logs/laravel.log`
   - Nginx: `/var/log/nginx/error.log`
   - PHP-FPM: `/var/log/php-fpm/error.log`

2. Verifica permisos:
   ```bash
   sudo chown -R nginx:nginx /var/www/jnc-adaprinters/backend/storage
   sudo chmod -R 775 /var/www/jnc-adaprinters/backend/storage
   ```

3. Limpia cachés:
   ```bash
   cd /var/www/jnc-adaprinters/backend
   php artisan config:clear
   php artisan cache:clear
   ```

4. Recompila frontend:
   ```bash
   cd /var/www/jnc-adaprinters/frontend
   npm run build
   cp -r dist/* ../backend/public/
   ```
