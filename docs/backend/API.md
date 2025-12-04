# Documentación de API - JNC-AdaPrinters

Documentación completa de los endpoints de la API REST.

## Tabla de Contenidos

1. [Autenticación](#autenticación)
2. [Endpoints Principales](#endpoints-principales)
3. [Códigos de Estado](#códigos-de-estado)
4. [Formato de Respuestas](#formato-de-respuestas)
5. [Paginación](#paginación)
6. [Filtros y Búsqueda](#filtros-y-búsqueda)

## Base URL

```
http://localhost:8080/api/v2
```

## Autenticación

La API utiliza **Laravel Sanctum** para autenticación mediante tokens Bearer.

### Login

```http
POST /api/v2/auth/login
Content-Type: application/json

{
  "email": "admin@jnc-adaprinters.local",
  "password": "admin123"
}
```

**Respuesta exitosa (200)**:
```json
{
  "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
  "user": {
    "id": 1,
    "name": "Administrador",
    "email": "admin@jnc-adaprinters.local"
  }
}
```

### Usar Token

Incluir el token en el header de todas las peticiones autenticadas:

```http
Authorization: Bearer 1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

### Obtener Usuario Actual

```http
GET /api/v2/auth/me
Authorization: Bearer {token}
```

### Logout

```http
POST /api/v2/auth/logout
Authorization: Bearer {token}
```

## Endpoints Principales

### Impresoras

#### Listar Impresoras

```http
GET /api/v2/printers
Authorization: Bearer {token}
```

**Parámetros de consulta**:
- `page` (int): Número de página
- `per_page` (int): Items por página (default: 15)
- `search` (string): Búsqueda en nombre, IP o serial
- `status` (string): Filtrar por estado (online, offline, etc.)
- `province_id` (int): Filtrar por provincia
- `site_id` (int): Filtrar por sede
- `department_id` (int): Filtrar por departamento

**Respuesta**:
```json
{
  "data": [
    {
      "id": 1,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "name": "HP LaserJet Pro",
      "ip_address": "10.64.130.12",
      "status": "online",
      "brand": "HP",
      "model": "LaserJet Pro",
      "is_color": false,
      "is_online": true,
      "last_sync_at": "2025-12-04T10:30:00.000000Z",
      "site": {
        "id": 1,
        "name": "Sede Central"
      },
      "department": {
        "id": 1,
        "name": "IT"
      },
      "latest_snapshot": {
        "consumables": [
          {
            "level": 45,
            "color": "black",
            "slot": "Toner Black"
          }
        ]
      }
    }
  ],
  "current_page": 1,
  "last_page": 5,
  "per_page": 15,
  "total": 72
}
```

#### Obtener Impresora

```http
GET /api/v2/printers/{id}
Authorization: Bearer {token}
```

#### Crear Impresora

```http
POST /api/v2/printers
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "HP LaserJet Pro",
  "ip_address": "10.64.130.12",
  "snmp_profile_id": 1,
  "province_id": 1,
  "site_id": 1,
  "department_id": 1,
  "brand": "HP",
  "model": "LaserJet Pro"
}
```

#### Actualizar Impresora

```http
PUT /api/v2/printers/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "HP LaserJet Pro Updated",
  "notes": "Nuevas notas"
}
```

#### Eliminar Impresora

```http
DELETE /api/v2/printers/{id}
Authorization: Bearer {token}
```

#### Sincronizar Impresora (SNMP)

```http
POST /api/v2/printers/{id}/sync
Authorization: Bearer {token}
```

#### Subir Foto de Impresora

```http
POST /api/v2/printers/{id}/photo
Authorization: Bearer {token}
Content-Type: multipart/form-data

photo: [archivo]
```

#### Obtener Logs de Impresión

```http
GET /api/v2/printers/{id}/logs
Authorization: Bearer {token}
```

**Parámetros**:
- `page`, `per_page`
- `start_date` (date)
- `end_date` (date)

#### Obtener Snapshots

```http
GET /api/v2/printers/{id}/snapshots
Authorization: Bearer {token}
```

#### Descubrir Impresoras

```http
POST /api/v2/printers/discover
Authorization: Bearer {token}
Content-Type: application/json

{
  "ip_range": "10.64.130.0/24",
  "province_id": 1,
  "site_id": 1,
  "department_id": 1
}
```

#### Importar Impresoras Descubiertas

```http
POST /api/v2/printers/import-discovered
Authorization: Bearer {token}
Content-Type: application/json

{
  "printers": [
    {
      "ip_address": "10.64.130.12",
      "name": "HP LaserJet",
      "province_id": 1,
      "site_id": 1
    }
  ]
}
```

### Inventario (Stocks)

#### Listar Stocks

```http
GET /api/v2/stocks
Authorization: Bearer {token}
```

**Parámetros**:
- `page`, `per_page`
- `site_id` (int)
- `low_only` (boolean): Solo stocks bajos

#### Ajustar Stock

```http
POST /api/v2/stocks/{id}/adjust
Authorization: Bearer {token}
Content-Type: application/json

{
  "movement_type": "in",
  "quantity": 10,
  "note": "Entrada de mercancía"
}
```

**movement_type**: `in`, `out`, `adjustment`

#### Regularizar Stock

```http
POST /api/v2/stocks/{id}/regularize
Authorization: Bearer {token}
Content-Type: application/json

{
  "quantity": 50,
  "justification": "Conteo físico"
}
```

#### Actualizar Cantidad Mínima

```http
PUT /api/v2/stocks/{id}/minimum-quantity
Authorization: Bearer {token}
Content-Type: application/json

{
  "minimum_quantity": 20
}
```

#### Crear Movimiento de Stock

```http
POST /api/v2/stocks/{id}/movements
Authorization: Bearer {token}
Content-Type: application/json

{
  "movement_type": "in",
  "quantity": 5,
  "note": "Compra"
}
```

### Alertas

#### Listar Alertas

```http
GET /api/v2/alerts
Authorization: Bearer {token}
```

**Parámetros**:
- `page`, `per_page`
- `status` (string): open, acknowledged, resolved, dismissed
- `severity` (string): critical, high, medium, low

#### Obtener Alerta

```http
GET /api/v2/alerts/{id}
Authorization: Bearer {token}
```

#### Actualizar Alerta

```http
PUT /api/v2/alerts/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "status": "acknowledged"
}
```

#### Reconocer Alerta

```http
POST /api/v2/alerts/{id}/acknowledge
Authorization: Bearer {token}
```

#### Resolver Alerta

```http
POST /api/v2/alerts/{id}/resolve
Authorization: Bearer {token}
```

#### Descartar Alerta

```http
POST /api/v2/alerts/{id}/dismiss
Authorization: Bearer {token}
```

#### Eliminar Alerta

```http
DELETE /api/v2/alerts/{id}
Authorization: Bearer {token}
```

### Pedidos (Orders)

#### Listar Pedidos

```http
GET /api/v2/orders
Authorization: Bearer {token}
```

**Parámetros**:
- `page`, `per_page`
- `status` (string): pending, sent, received, cancelled

#### Crear Pedido

```http
POST /api/v2/orders
Authorization: Bearer {token}
Content-Type: application/json

{
  "printer_id": 1,
  "consumable_id": 1,
  "status": "pending",
  "notes": "Pedido urgente"
}
```

#### Obtener Pedido

```http
GET /api/v2/orders/{id}
Authorization: Bearer {token}
```

#### Actualizar Pedido

```http
PUT /api/v2/orders/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "status": "sent",
  "sent_at": "2025-12-04T10:00:00Z"
}
```

#### Añadir Comentario a Pedido

```http
POST /api/v2/orders/{id}/comments
Authorization: Bearer {token}
Content-Type: application/json

{
  "comment": "Pedido enviado al proveedor"
}
```

#### Obtener Comentarios de Pedido

```http
GET /api/v2/orders/{id}/comments
Authorization: Bearer {token}
```

### Entradas de Pedidos (Order Entries)

#### Listar Entradas

```http
GET /api/v2/order-entries
Authorization: Bearer {token}
```

#### Crear Entrada

```http
POST /api/v2/order-entries
Authorization: Bearer {token}
Content-Type: application/json

{
  "order_id": 1,
  "site_id": 1,
  "department_id": 1,
  "received_at": "2025-12-04T10:00:00Z",
  "items": [
    {
      "consumable_reference_id": 1,
      "quantity": 10
    }
  ]
}
```

### Referencias de Consumibles

#### Listar Referencias

```http
GET /api/v2/references
Authorization: Bearer {token}
```

#### Crear Referencia

```http
POST /api/v2/references
Authorization: Bearer {token}
Content-Type: application/json

{
  "sku": "HP-CF280A",
  "name": "Toner HP Negro",
  "brand": "HP",
  "type": "Toner",
  "color": "Negro"
}
```

#### Obtener Movimientos de Referencia

```http
GET /api/v2/references/{id}/movements
Authorization: Bearer {token}
```

### Registro de Impresiones

#### Listar Logs de Impresión

```http
GET /api/v2/print-logs
Authorization: Bearer {token}
```

**Parámetros**:
- `page`, `per_page`
- `printer_id` (int)
- `start_date` (date)
- `end_date` (date)

#### Exportar Logs

```http
GET /api/v2/print-logs/export
Authorization: Bearer {token}
```

**Parámetros**: Mismos que listar

**Respuesta**: Archivo CSV

### Configuración

Todos los endpoints de configuración están bajo `/api/v2/config/`:

#### SNMP Sync

- `GET /api/v2/config/snmp-sync/config` - Obtener configuración
- `PUT /api/v2/config/snmp-sync/config` - Actualizar configuración
- `POST /api/v2/config/snmp-sync/sync-all` - Sincronizar todas
- `GET /api/v2/config/snmp-sync/history` - Historial

#### Otros

- `GET/POST/PUT/DELETE /api/v2/config/logos` - Logos
- `GET/POST/PUT/DELETE /api/v2/config/custom-fields` - Campos personalizados
- `GET/POST/PUT/DELETE /api/v2/config/snmp-oids` - OIDs SNMP
- `GET/POST/PUT/DELETE /api/v2/config/notification-configs` - Notificaciones
- `GET/POST/PUT/DELETE /api/v2/config/provinces` - Provincias
- `GET/POST/PUT/DELETE /api/v2/config/sites` - Sedes
- `GET/POST/PUT/DELETE /api/v2/config/departments` - Departamentos
- `GET/POST/PUT/DELETE /api/v2/config/users` - Usuarios

### Dashboard

```http
GET /api/v2/dashboard
Authorization: Bearer {token}
```

**Respuesta**:
```json
{
  "stats": {
    "total_printers": 72,
    "online_printers": 65,
    "total_alerts": 12,
    "critical_alerts": 3
  },
  "recent_alerts": [...],
  "low_stocks": [...]
}
```

## Códigos de Estado

| Código | Significado |
|--------|-------------|
| 200 | OK - Petición exitosa |
| 201 | Created - Recurso creado |
| 204 | No Content - Sin contenido (eliminación exitosa) |
| 400 | Bad Request - Error de validación |
| 401 | Unauthorized - No autenticado |
| 403 | Forbidden - Sin permisos |
| 404 | Not Found - Recurso no encontrado |
| 422 | Unprocessable Entity - Error de validación |
| 500 | Internal Server Error - Error del servidor |

## Formato de Respuestas

### Respuesta Exitosa

```json
{
  "data": { ... }
}
```

### Respuesta con Paginación

```json
{
  "data": [ ... ],
  "current_page": 1,
  "last_page": 5,
  "per_page": 15,
  "total": 72,
  "from": 1,
  "to": 15
}
```

### Respuesta de Error

```json
{
  "message": "Error message",
  "errors": {
    "field": ["Error detail"]
  }
}
```

## Paginación

Todos los endpoints de listado soportan paginación mediante parámetros de consulta:

- `page`: Número de página (default: 1)
- `per_page`: Items por página (default: 15, max: 100)

## Filtros y Búsqueda

La mayoría de endpoints de listado soportan:

- **Búsqueda de texto**: Parámetro `search`
- **Filtros específicos**: Dependiendo del endpoint (status, site_id, etc.)
- **Ordenamiento**: Generalmente por `updated_at DESC`

## Referencias

- [Documentación de Controladores](CONTROLLERS.md)
- [Documentación de Modelos](MODELS.md)
- [Guía de Instalación](../../INSTALLATION.md)

