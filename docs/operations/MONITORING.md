# Monitoreo - JNC-AdaPrinters

Guía de monitoreo y observabilidad del sistema.

## Tabla de Contenidos

1. [Horizon UI](#horizon-ui)
2. [Logs](#logs)
3. [Métricas](#métricas)
4. [Alertas del Sistema](#alertas-del-sistema)

## Horizon UI

Laravel Horizon proporciona una interfaz web para monitorear las colas de Redis.

### Acceso

URL: `http://localhost:8080/horizon` (o dominio de producción)

**Requisito**: Autenticación con usuario del sistema.

### Funcionalidades

- **Dashboard**: Vista general de jobs procesados
- **Jobs**: Lista de jobs por estado (pending, processing, completed, failed)
- **Workers**: Estado de los workers
- **Metrics**: Métricas de throughput y tiempo de procesamiento
- **Recent Jobs**: Jobs recientes
- **Failed Jobs**: Jobs fallidos con detalles de error

### Métricas Disponibles

- **Throughput**: Jobs procesados por minuto
- **Wait Time**: Tiempo promedio de espera
- **Process Time**: Tiempo promedio de procesamiento
- **Jobs per Minute**: Gráfico de jobs por minuto

### Comandos Útiles

```bash
# Ver estado
docker compose exec horizon php artisan horizon:status

# Terminar workers (reinicio suave)
docker compose exec horizon php artisan horizon:terminate

# Pausar procesamiento
docker compose exec horizon php artisan horizon:pause

# Continuar procesamiento
docker compose exec horizon php artisan horizon:continue
```

## Logs

### Ubicación de Logs

Los logs se almacenan en `backend/storage/logs/`:

- **laravel.log**: Log principal de Laravel
- **scheduler.log**: Log del scheduler
- **snmp_sync.log**: Log de sincronización SNMP

### Ver Logs

```bash
# Logs en tiempo real
docker compose logs -f app

# Logs de un servicio específico
docker compose logs -f scheduler
docker compose logs -f horizon

# Logs de Laravel
docker compose exec app tail -f storage/logs/laravel.log

# Logs del scheduler
docker compose exec app tail -f storage/logs/scheduler.log
```

### Niveles de Log

Configurados en `docker/backend.env`:

```env
LOG_LEVEL=debug  # debug, info, warning, error
```

**Producción**: Usar `error` o `warning`

### Rotación de Logs

Laravel rota logs automáticamente. Para limpiar logs antiguos:

```bash
# Eliminar logs de más de 30 días
docker compose exec app find storage/logs -name "*.log" -mtime +30 -delete
```

## Métricas

### Métricas de Aplicación

#### Dashboard API

El endpoint `/api/v2/dashboard` proporciona métricas:

```json
{
  "stats": {
    "total_printers": 72,
    "online_printers": 65,
    "total_alerts": 12,
    "critical_alerts": 3
  }
}
```

#### Métricas de Base de Datos

```bash
# Conectar a PostgreSQL
docker compose exec postgres psql -U jnc_admin -d jnc_adaprinters

# Ver tamaño de tablas
SELECT 
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size
FROM pg_tables
WHERE schemaname = 'public'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;

# Ver conexiones activas
SELECT count(*) FROM pg_stat_activity;
```

#### Métricas de Redis

```bash
# Conectar a Redis
docker compose exec redis redis-cli

# Ver información
INFO

# Ver memoria usada
INFO memory

# Ver número de claves
DBSIZE
```

### Métricas del Sistema

#### Uso de Recursos

```bash
# Ver uso de recursos de contenedores
docker stats

# Ver uso de CPU y memoria del host
top
htop

# Ver espacio en disco
df -h
docker system df
```

## Alertas del Sistema

### Alertas de Aplicación

El sistema genera alertas automáticamente:

- **PRINTER_OFFLINE**: Impresora offline durante 3 sincronizaciones consecutivas
- **LOW_CONSUMABLE**: Consumible por debajo del umbral (5% critical, 15% medium)
- **STOCK_LOW**: Stock por debajo de la cantidad mínima

Ver alertas en: `/alerts` o API `/api/v2/alerts`

### Monitoreo de Servicios

#### Verificar Estado de Servicios

```bash
# Ver estado de todos los servicios
docker compose ps

# Verificar que todos están "Up"
docker compose ps | grep -v "Up"
```

#### Health Checks

```bash
# Verificar API
curl http://localhost:8080/api/v2/auth/login

# Verificar PostgreSQL
docker compose exec postgres pg_isready

# Verificar Redis
docker compose exec redis redis-cli ping
```

### Alertas del Sistema Operativo

Configurar alertas para:

- **CPU > 80%**: Alto uso de CPU
- **RAM > 80%**: Alto uso de memoria
- **Disco < 20%**: Poco espacio en disco
- **Servicios caídos**: Contenedores no corriendo

#### Ejemplo con Monit

```conf
check system localhost
    if cpu usage > 80% then alert
    if memory usage > 80% then alert
    if disk usage > 80% then alert
```

## Monitoreo Recomendado

### Producción

1. **Horizon UI**: Monitorear jobs y throughput
2. **Logs**: Revisar logs diariamente
3. **Métricas de BD**: Verificar tamaño y conexiones
4. **Alertas**: Configurar notificaciones para alertas críticas
5. **Backups**: Verificar que los backups se ejecutan correctamente

### Herramientas Externas (Opcional)

- **Sentry**: Monitoreo de errores
- **Prometheus + Grafana**: Métricas y dashboards
- **ELK Stack**: Análisis de logs
- **New Relic / Datadog**: APM completo

## Referencias

- [Documentación de Horizon](https://laravel.com/docs/horizon)
- [Documentación de Backup](BACKUP.md)
- [Troubleshooting](TROUBLESHOOTING.md)

