# Dockerfiles - JNC-AdaPrinters

Documentación de los Dockerfiles del proyecto.

## Tabla de Contenidos

1. [Dockerfile PHP](#dockerfile-php)
2. [Dockerfile Node](#dockerfile-node)
3. [Configuración Nginx](#configuración-nginx)

## Dockerfile PHP

**Ubicación**: `docker/php/Dockerfile`

### Imagen Base

```dockerfile
FROM php:8.3-fpm
```

### Dependencias del Sistema

```dockerfile
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libsnmp-dev \
    snmp \
    python3 \
    python3-pip \
    python3-psycopg2 \
    cron \
```

### Extensiones PHP

```dockerfile
docker-php-ext-configure gd --with-freetype --with-jpeg \
docker-php-ext-install -j$(nproc) \
    bcmath \
    intl \
    pcntl \
    pdo_pgsql \
    pgsql \
    zip \
    gd \
    snmp \
pecl install redis \
docker-php-ext-enable redis
```

**Extensiones instaladas**:
- `bcmath` - Cálculos matemáticos de precisión arbitraria
- `intl` - Internacionalización
- `pcntl` - Control de procesos
- `pdo_pgsql` - Driver PDO para PostgreSQL
- `pgsql` - Extensión PostgreSQL
- `zip` - Manejo de archivos ZIP
- `gd` - Procesamiento de imágenes
- `snmp` - Protocolo SNMP
- `redis` - Cliente Redis

### Composer

```dockerfile
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer
```

### Configuración PHP

```dockerfile
RUN echo "upload_max_filesize = 20M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size = 25M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini
```

**Límites configurados**:
- Subida de archivos: 20M
- POST máximo: 25M
- Tiempo de ejecución: 300 segundos
- Tiempo de entrada: 300 segundos

### Working Directory

```dockerfile
WORKDIR /var/www/html
```

### Comando por Defecto

```dockerfile
CMD ["php-fpm"]
```

## Dockerfile Node

**Ubicación**: `docker/node/Dockerfile`

### Imagen Base

```dockerfile
FROM node:20
```

### Working Directory

```dockerfile
WORKDIR /app
```

### Corepack

```dockerfile
RUN corepack enable
```

Habilita Corepack para gestionar versiones de pnpm/yarn.

### Comando por Defecto

```dockerfile
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "5173"]
```

Inicia el servidor de desarrollo Vite.

## Configuración Nginx

**Ubicación**: `docker/nginx/conf.d/default.conf`

### Configuración Principal

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.html index.php;
    
    client_max_body_size 20M;
    
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
}
```

### Rutas API

```nginx
location ^~ /api {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME /var/www/html/public/index.php;
    fastcgi_param PATH_INFO $fastcgi_path_info;
    fastcgi_param QUERY_STRING $query_string;
    fastcgi_pass app:9000;
}
```

### Horizon UI

```nginx
location ^~ /horizon {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME /var/www/html/public/index.php;
    fastcgi_pass app:9000;
}
```

### Storage

```nginx
location /storage {
    try_files $uri $uri/ =404;
}
```

Sirve archivos del storage de Laravel.

### SPA (Vue Router)

```nginx
location / {
    try_files $uri $uri/ /index.html;
}
```

Sirve `index.html` para todas las rutas (SPA).

### PHP Files

```nginx
location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    fastcgi_param PATH_INFO $fastcgi_path_info;
    fastcgi_index index.php;
    fastcgi_pass app:9000;
}
```

### Seguridad

```nginx
location ~ /\.ht {
    deny all;
}
```

Bloquea acceso a archivos `.htaccess`.

## Optimizaciones para Producción

### PHP-FPM

Añadir a configuración PHP-FPM:

```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
```

### Nginx

Añadir compresión y cache:

```nginx
gzip on;
gzip_vary on;
gzip_proxied any;
gzip_comp_level 6;
gzip_types text/plain text/css text/xml text/javascript application/json application/javascript;

location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

## Referencias

- [Docker Setup](DOCKER_SETUP.md)
- [Variables de Entorno](ENVIRONMENT.md)
- [Guía de Despliegue](../../DEPLOYMENT.md)

