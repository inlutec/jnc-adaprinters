# Estrategia de Backup - JNC-AdaPrinters

Guía completa de backup y recuperación del sistema.

## Tabla de Contenidos

1. [Estrategia de Backup](#estrategia-de-backup)
2. [Backup de Base de Datos](#backup-de-base-de-datos)
3. [Backup de Storage](#backup-de-storage)
4. [Automatización](#automatización)
5. [Restauración](#restauración)

## Estrategia de Backup

### Elementos a Respaldar

1. **Base de Datos PostgreSQL**: Datos críticos
2. **Storage Laravel**: Archivos subidos (fotos, documentos)
3. **Configuración**: Variables de entorno, configuraciones

### Frecuencia Recomendada

- **Base de datos**: Diario (retener 30 días)
- **Storage**: Diario (retener 30 días)
- **Configuración**: Semanal (retener 12 semanas)

## Backup de Base de Datos

### Backup Manual

```bash
# Backup completo
docker compose exec postgres pg_dump -U jnc_admin jnc_adaprinters > backup_$(date +%Y%m%d_%H%M%S).sql

# Backup comprimido
docker compose exec postgres pg_dump -U jnc_admin jnc_adaprinters | gzip > backup_$(date +%Y%m%d_%H%M%S).sql.gz

# Backup con formato custom (recomendado)
docker compose exec postgres pg_dump -U jnc_admin -Fc jnc_adaprinters > backup_$(date +%Y%m%d_%H%M%S).dump
```

### Script de Backup Automático

Crear `scripts/backup-db.sh`:

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/jnc-adaprinters"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR

# Backup de base de datos
docker compose exec -T postgres pg_dump -U jnc_admin jnc_adaprinters | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Eliminar backups antiguos (mantener últimos 30 días)
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +30 -delete

echo "Backup completado: db_$DATE.sql.gz"
```

**Hacer ejecutable**:
```bash
chmod +x scripts/backup-db.sh
```

## Backup de Storage

### Backup Manual

```bash
# Backup de storage
tar -czf storage_backup_$(date +%Y%m%d_%H%M%S).tar.gz -C backend storage/

# Backup desde contenedor
docker compose exec app tar -czf /tmp/storage_backup.tar.gz -C /var/www/html storage/
docker compose cp app:/tmp/storage_backup.tar.gz ./
```

### Script de Backup Automático

Crear `scripts/backup-storage.sh`:

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/jnc-adaprinters"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR

# Backup de storage
tar -czf $BACKUP_DIR/storage_$DATE.tar.gz -C /var/www/html/jnc-adaprinters/backend storage/

# Eliminar backups antiguos
find $BACKUP_DIR -name "storage_*.tar.gz" -mtime +30 -delete

echo "Backup completado: storage_$DATE.tar.gz"
```

## Automatización

### Cron

Añadir a crontab del servidor:

```bash
# Backup diario de BD a las 2 AM
0 2 * * * /var/www/html/jnc-adaprinters/scripts/backup-db.sh

# Backup diario de storage a las 3 AM
0 3 * * * /var/www/html/jnc-adaprinters/scripts/backup-storage.sh
```

### Verificar Backups

```bash
# Listar backups
ls -lh /var/backups/jnc-adaprinters/

# Verificar integridad
gunzip -t /var/backups/jnc-adaprinters/db_20251204_020000.sql.gz
```

## Restauración

### Restaurar Base de Datos

#### Desde SQL plano

```bash
# Descomprimir si es necesario
gunzip backup.sql.gz

# Restaurar
docker compose exec -T postgres psql -U jnc_admin jnc_adaprinters < backup.sql
```

#### Desde SQL comprimido

```bash
gunzip < backup.sql.gz | docker compose exec -T postgres psql -U jnc_admin jnc_adaprinters
```

#### Desde formato custom

```bash
docker compose exec -T postgres pg_restore -U jnc_admin -d jnc_adaprinters -c backup.dump
```

### Restaurar Storage

```bash
# Detener aplicación (opcional pero recomendado)
docker compose stop app

# Restaurar
tar -xzf storage_backup.tar.gz -C /var/www/html/jnc-adaprinters/backend/

# Ajustar permisos
docker compose exec app chmod -R 775 storage
docker compose exec app chown -R www-data:www-data storage

# Reiniciar aplicación
docker compose start app
```

### Restauración Completa

1. **Restaurar base de datos**
2. **Restaurar storage**
3. **Verificar configuración** (`docker/backend.env`)
4. **Reiniciar servicios**
5. **Verificar funcionamiento**

## Backup Remoto (Recomendado)

### S3 / Object Storage

Usar herramientas como:
- `aws s3 cp` para AWS S3
- `rclone` para múltiples proveedores
- `duplicity` para backups encriptados

### Ejemplo con rclone

```bash
# Configurar rclone
rclone config

# Subir backup
rclone copy /var/backups/jnc-adaprinters/ remote:backups/jnc-adaprinters/

# Automatizar
0 4 * * * rclone sync /var/backups/jnc-adaprinters/ remote:backups/jnc-adaprinters/
```

## Verificación de Backups

### Checklist

- [ ] Backups se ejecutan automáticamente
- [ ] Backups se almacenan en ubicación segura
- [ ] Backups antiguos se eliminan correctamente
- [ ] Se prueba restauración periódicamente
- [ ] Backups remotos configurados (producción)

## Referencias

- [Documentación de PostgreSQL Backup](https://www.postgresql.org/docs/current/backup.html)
- [Guía de Despliegue](../../DEPLOYMENT.md)

