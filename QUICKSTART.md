# Guía Rápida de Implementación - JNC-AdaPrinters

Guía rápida para implementar el proyecto desde cero en un servidor limpio.

## Requisitos Previos

- Servidor Linux (Ubuntu 20.04+ recomendado)
- Docker 24+ y Docker Compose 2.0+
- Git
- Acceso root o sudo

## Pasos Rápidos

### 1. Clonar el Repositorio

```bash
cd /var/www/html
git clone https://github.com/inlutec/jnc-adaprinters.git
cd jnc-adaprinters
```

### 2. Configurar Variables de Entorno

```bash
# Copiar y editar configuración backend
cp docker/backend.env docker/backend.env.local
nano docker/backend.env.local

# Configurar al menos:
# - DB_PASSWORD (cambiar de "secretpassword")
# - APP_KEY (se generará automáticamente)
# - APP_URL (tu dominio o IP)
# - FRONTEND_URL
```

### 3. Configurar Frontend

```bash
# Crear archivo .env para frontend
cd frontend
cat > .env << EOF
VITE_API_URL=http://tu-dominio-o-ip:8080/api/v2
EOF
cd ..
```

### 4. Levantar Servicios

```bash
cd docker
docker compose up -d --build
```

### 5. Configurar Aplicación

```bash
# Generar clave de aplicación
docker compose exec app php artisan key:generate

# Ejecutar migraciones
docker compose exec app php artisan migrate --force

# Poblar datos de prueba (opcional)
docker compose exec app php artisan db:seed

# Configurar permisos
docker compose exec app chmod -R 775 storage bootstrap/cache
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### 6. Verificar Instalación

```bash
# Verificar servicios
docker compose ps

# Todos deben estar "Up"

# Verificar API
curl http://localhost:8080/api/v2/auth/login

# Acceder al frontend
# http://localhost:5173 (desarrollo)
# http://tu-dominio:8080 (producción)
```

## Credenciales por Defecto (Seeder)

Si ejecutaste `php artisan db:seed`:

- **Email**: `admin@jnc-adaprinters.local`
- **Password**: `admin123`

**⚠️ IMPORTANTE**: Cambiar estas credenciales en producción.

## Configuración Inicial

### 1. Configurar Perfil SNMP

1. Acceder a la aplicación
2. Ir a **Configuración > SNMP > Perfiles**
3. Crear perfil con community SNMP de tu red

### 2. Configurar Ubicaciones

1. Ir a **Configuración > Ubicaciones**
2. Crear Provincias, Sedes y Departamentos

### 3. Descubrir Impresoras

```bash
# Descubrimiento automático
docker compose exec app php artisan printers:discover 10.64.130.0/24 --province=1 --site=1

# O añadir manualmente desde la interfaz web
```

### 4. Configurar Sincronización Automática

1. Ir a **Configuración > SNMP > Sincronización**
2. Activar sincronización automática
3. Configurar frecuencia (recomendado: 15 minutos)

## Servicios Expuestos

- **API**: `http://localhost:8080`
- **Frontend Dev**: `http://localhost:5173`
- **Horizon UI**: `http://localhost:8080/horizon` (requiere login)
- **PostgreSQL**: `localhost:5432`
- **Redis**: `localhost:6379`

## Comandos Útiles

```bash
# Ver logs
docker compose logs -f

# Reiniciar servicios
docker compose restart

# Ejecutar comandos Artisan
docker compose exec app php artisan [comando]

# Acceder a base de datos
docker compose exec postgres psql -U jnc_admin -d jnc_adaprinters
```

## Próximos Pasos

1. **Cambiar credenciales por defecto**
2. **Configurar SMTP** (Configuración > Notificaciones)
3. **Configurar backup automático** (ver `docs/operations/BACKUP.md`)
4. **Revisar configuración de seguridad** (ver `DEPLOYMENT.md`)

## Documentación Completa

Para más detalles, consulta:

- [Guía de Instalación Completa](INSTALLATION.md)
- [Guía de Despliegue](DEPLOYMENT.md)
- [Documentación Técnica](docs/README.md)
- [Troubleshooting](docs/operations/TROUBLESHOOTING.md)

## Solución Rápida de Problemas

### Contenedores no inician
```bash
docker compose logs [servicio]
docker compose down && docker compose up -d --build
```

### Error de permisos
```bash
docker compose exec app chmod -R 775 storage bootstrap/cache
```

### Base de datos no conecta
```bash
docker compose ps postgres
docker compose logs postgres
```

## Soporte

- Consulta [Troubleshooting](docs/operations/TROUBLESHOOTING.md)
- Revisa los logs: `docker compose logs`
- Abre un issue en GitHub

