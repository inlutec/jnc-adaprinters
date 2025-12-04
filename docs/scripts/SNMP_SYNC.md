# Script de Sincronización SNMP - JNC-AdaPrinters

Documentación del script Python de sincronización SNMP automática.

## Ubicación

```
backend/scripts/snmp_sync.py
```

## Descripción

Script Python que lee la configuración de sincronización automática desde la base de datos y ejecuta la sincronización SNMP de todas las impresoras.

## Requisitos

- Python 3
- psycopg2-binary (driver PostgreSQL)
- Acceso a la base de datos PostgreSQL

## Instalación de Dependencias

```bash
pip3 install psycopg2-binary
```

O en el contenedor Docker:

```bash
docker compose exec app pip3 install psycopg2-binary
```

## Configuración

El script lee la configuración desde la base de datos:

- **Tabla**: `snmp_sync_configs`
- **Claves**:
  - `auto_sync_enabled`: "true" o "false"
  - `auto_sync_frequency`: Frecuencia en minutos (ej: "15")

### Variables de Entorno

El script utiliza variables de entorno para la conexión a la base de datos:

```bash
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=jnc_adaprinters
DB_USERNAME=jnc_admin
DB_PASSWORD=secretpassword
```

## Uso

### Ejecución Manual

```bash
python3 backend/scripts/snmp_sync.py
```

O desde el contenedor:

```bash
docker compose exec app python3 scripts/snmp_sync.py
```

### Ejecución Automática (Cron)

El script está diseñado para ejecutarse desde cron. El script `docker/scripts/init_cron.sh` configura el cron automáticamente.

## Funcionamiento

1. **Lee configuración**: Consulta la base de datos para obtener:
   - Si la sincronización automática está habilitada
   - La frecuencia configurada

2. **Verifica frecuencia**: Compara con la última sincronización exitosa:
   - Si no ha pasado el tiempo configurado, no ejecuta
   - Si ha pasado, continúa

3. **Ejecuta sincronización**: Llama al comando Artisan:
   ```bash
   php artisan printers:poll --auto-check
   ```

4. **Registra resultado**: El comando Artisan crea un registro en `snmp_sync_history`

## Integración con Cron

### Script de Inicialización

El script `docker/scripts/init_cron.sh` se ejecuta al iniciar el contenedor `scheduler`:

1. Lee la configuración desde la base de datos
2. Crea la entrada de cron correspondiente
3. Inicia cron en foreground

### Configuración de Cron

El cron se configura dinámicamente según la frecuencia:

```bash
*/15 * * * * cd /var/www/html && /usr/bin/python3 scripts/snmp_sync.py >> /var/log/snmp_sync.log 2>&1
```

## Logs

Los logs se escriben en:
- `/var/log/snmp_sync.log` (en el contenedor)
- `backend/storage/logs/snmp_sync.log` (si se ejecuta desde Laravel)

## Troubleshooting

### Problema: Script no se ejecuta

**Verificar**:
```bash
# Ver logs
docker compose logs scheduler

# Verificar cron
docker compose exec scheduler crontab -l

# Verificar que el script existe
docker compose exec scheduler ls -la /var/www/html/scripts/snmp_sync.py
```

### Problema: Error de conexión a BD

**Verificar variables de entorno**:
```bash
docker compose exec scheduler env | grep DB_
```

### Problema: psycopg2 no instalado

**Instalar**:
```bash
docker compose exec scheduler pip3 install psycopg2-binary
```

### Problema: Permisos

**Ajustar permisos**:
```bash
docker compose exec scheduler chmod +x /var/www/html/scripts/snmp_sync.py
```

## Alternativa: Laravel Scheduler

En lugar de usar cron directamente, el proyecto también utiliza Laravel Scheduler que ejecuta el comando cada minuto y verifica la frecuencia internamente:

```php
// app/Console/Kernel.php
$schedule->command('printers:poll --auto-check')
    ->everyMinute()
    ->withoutOverlapping();
```

Este método es más robusto y recomendado.

## Referencias

- [Documentación de Docker](../docker/DOCKER_SETUP.md)
- [Documentación de Comandos](../backend/COMMANDS.md)
- [Configuración SNMP](../config/SNMP.md)

