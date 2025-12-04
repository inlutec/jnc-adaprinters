# Troubleshooting - JNC-AdaPrinters

Guía de solución de problemas comunes.

## Tabla de Contenidos

1. [Problemas Comunes](#problemas-comunes)
2. [Problemas de Docker](#problemas-de-docker)
3. [Problemas de Base de Datos](#problemas-de-base-de-datos)
4. [Problemas de SNMP](#problemas-de-snmp)
5. [Problemas de Frontend](#problemas-de-frontend)

## Problemas Comunes

### Contenedores no inician

**Síntomas**: `docker compose ps` muestra servicios como "Exit" o "Restarting"

**Solución**:
```bash
# Ver logs detallados
docker compose logs [servicio]

# Verificar configuración
docker compose config

# Reconstruir sin cache
docker compose down
docker compose build --no-cache
docker compose up -d
```

### Error de permisos en storage

**Síntomas**: Errores al subir archivos o acceder a storage

**Solución**:
```bash
docker compose exec app chmod -R 775 storage bootstrap/cache
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### API no responde

**Síntomas**: Errores 500 o timeout en peticiones API

**Solución**:
```bash
# Verificar que el servicio app está corriendo
docker compose ps app

# Ver logs
docker compose logs app --tail=50

# Verificar PHP-FPM
docker compose exec app php-fpm -v

# Reiniciar servicio
docker compose restart app
```

## Problemas de Docker

### Puerto ocupado

**Síntomas**: Error "port is already allocated"

**Solución**:
```bash
# Verificar puertos en uso
sudo netstat -tulpn | grep -E '8080|5173|5432|6379'

# Cambiar puertos en docker-compose.yml
ports:
  - "8081:80"  # Cambiar 8080 a 8081
```

### Volúmenes no montan

**Síntomas**: Cambios en archivos no se reflejan

**Solución**:
```bash
# Verificar volúmenes
docker compose config | grep volumes

# Reiniciar servicios
docker compose restart

# Verificar permisos de archivos
ls -la backend/
```

### Imágenes no se construyen

**Síntomas**: Error al construir imágenes Docker

**Solución**:
```bash
# Limpiar cache de Docker
docker system prune -a

# Reconstruir forzando
docker compose build --no-cache --pull
```

## Problemas de Base de Datos

### No se puede conectar a PostgreSQL

**Síntomas**: Error "could not connect to server"

**Solución**:
```bash
# Verificar que PostgreSQL está corriendo
docker compose ps postgres

# Ver logs
docker compose logs postgres

# Verificar variables de entorno
docker compose exec app env | grep DB_

# Probar conexión manual
docker compose exec postgres psql -U jnc_admin -d jnc_adaprinters -c "SELECT 1;"
```

### Migraciones fallan

**Síntomas**: Error al ejecutar `php artisan migrate`

**Solución**:
```bash
# Ver estado de migraciones
docker compose exec app php artisan migrate:status

# Revertir última migración
docker compose exec app php artisan migrate:rollback

# Ejecutar migraciones forzadas
docker compose exec app php artisan migrate --force

# Verificar permisos de BD
docker compose exec postgres psql -U jnc_admin -d jnc_adaprinters -c "\du"
```

### Base de datos corrupta

**Síntomas**: Errores inesperados, datos inconsistentes

**Solución**:
```bash
# Verificar integridad
docker compose exec postgres psql -U jnc_admin -d jnc_adaprinters -c "VACUUM ANALYZE;"

# Restaurar desde backup
# Ver docs/operations/BACKUP.md
```

## Problemas de SNMP

### SNMP no funciona

**Síntomas**: Error al sincronizar impresoras

**Solución**:
```bash
# Verificar que el driver SNMP está instalado
docker compose exec app php -m | grep snmp

# Verificar configuración
docker compose exec app php artisan tinker
# En tinker: config('snmp.driver')

# Probar SNMP manualmente
docker compose exec app php artisan printers:discover 127.0.0.1

# Ver logs
docker compose logs app | grep -i snmp
```

### Sincronización automática no funciona

**Síntomas**: No se sincronizan impresoras automáticamente

**Solución**:
```bash
# Verificar scheduler
docker compose ps scheduler
docker compose logs scheduler

# Verificar configuración de sincronización
docker compose exec app php artisan tinker
# En tinker:
# \App\Models\SnmpSyncConfig::isEnabled('auto_sync_enabled')
# \App\Models\SnmpSyncConfig::get('auto_sync_frequency')

# Probar sincronización manual
docker compose exec app php artisan printers:poll --auto-check
```

### Horizon no procesa jobs SNMP

**Síntomas**: Jobs quedan en cola sin procesar

**Solución**:
```bash
# Verificar Horizon
docker compose ps horizon
docker compose logs horizon

# Ver estado
docker compose exec horizon php artisan horizon:status

# Reiniciar Horizon
docker compose exec horizon php artisan horizon:terminate
docker compose restart horizon
```

## Problemas de Frontend

### Frontend no carga

**Síntomas**: Error al acceder a `http://localhost:5173`

**Solución**:
```bash
# Verificar que el contenedor está corriendo
docker compose ps frontend

# Ver logs
docker compose logs frontend

# Reinstalar dependencias
docker compose exec frontend npm install

# Reiniciar
docker compose restart frontend
```

### Errores de API en frontend

**Síntomas**: Errores 401, 404, o CORS

**Solución**:
```bash
# Verificar variable de entorno
cat frontend/.env
# Debe tener: VITE_API_URL=http://localhost:8080/api/v2

# Verificar CORS en backend
docker compose exec app php artisan tinker
# En tinker: config('cors')

# Verificar SANCTUM_STATEFUL_DOMAINS
docker compose exec app env | grep SANCTUM
```

### Build de producción falla

**Síntomas**: Error al ejecutar `npm run build`

**Solución**:
```bash
# Limpiar node_modules
docker compose exec frontend rm -rf node_modules

# Reinstalar
docker compose exec frontend npm install

# Limpiar cache
docker compose exec frontend npm run build -- --force

# Verificar errores TypeScript
docker compose exec frontend npm run build 2>&1 | grep error
```

## Problemas de Autenticación

### No se puede hacer login

**Síntomas**: Error 401 o "Invalid credentials"

**Solución**:
```bash
# Verificar usuario en BD
docker compose exec postgres psql -U jnc_admin -d jnc_adaprinters -c "SELECT email FROM users;"

# Resetear contraseña
docker compose exec app php artisan tinker
# En tinker:
# $user = \App\Models\User::where('email', 'admin@jnc-adaprinters.local')->first();
# $user->password = Hash::make('nueva_password');
# $user->save();

# Verificar token
docker compose exec app php artisan tinker
# En tinker: \Laravel\Sanctum\PersonalAccessToken::count()
```

## Logs Importantes

### Ubicaciones

- `backend/storage/logs/laravel.log` - Log principal
- `backend/storage/logs/scheduler.log` - Log del scheduler
- `backend/storage/logs/snmp_sync.log` - Log de SNMP
- Logs de Docker: `docker compose logs`

### Comandos Útiles

```bash
# Ver últimos 50 logs
docker compose exec app tail -50 storage/logs/laravel.log

# Buscar errores
docker compose exec app grep -i error storage/logs/laravel.log

# Ver logs en tiempo real
docker compose logs -f app
```

## Obtener Ayuda

1. **Revisar logs**: Siempre empezar por los logs
2. **Verificar configuración**: Variables de entorno, permisos
3. **Documentación**: Consultar documentación específica
4. **Issues**: Abrir issue en el repositorio con:
   - Descripción del problema
   - Logs relevantes
   - Pasos para reproducir
   - Versión del sistema

## Referencias

- [Guía de Instalación](../../INSTALLATION.md)
- [Documentación de Monitoreo](MONITORING.md)
- [Documentación de Backup](BACKUP.md)

