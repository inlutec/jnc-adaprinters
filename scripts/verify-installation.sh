#!/bin/bash
# Script de verificación de instalación de JNC-AdaPrinters

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Variables
APP_PATH="${APP_PATH:-/var/www/jnc-adaprinters}"
BACKEND_PATH="$APP_PATH/backend"

echo -e "${GREEN}Verificando instalación de JNC-AdaPrinters...${NC}"
echo ""

ERRORS=0

# Función para verificar comando
check_command() {
    if command -v $1 &> /dev/null; then
        echo -e "${GREEN}✓ $1 está instalado${NC}"
        return 0
    else
        echo -e "${RED}✗ $1 NO está instalado${NC}"
        ERRORS=$((ERRORS + 1))
        return 1
    fi
}

# Función para verificar servicio
check_service() {
    if sudo systemctl is-active --quiet $1; then
        echo -e "${GREEN}✓ Servicio $1 está activo${NC}"
        return 0
    else
        echo -e "${RED}✗ Servicio $1 NO está activo${NC}"
        ERRORS=$((ERRORS + 1))
        return 1
    fi
}

# Verificar comandos
echo -e "${YELLOW}Verificando comandos instalados...${NC}"
check_command php
check_command composer
check_command node
check_command npm
check_command psql
check_command redis-cli
check_command nginx
echo ""

# Verificar versión de PHP
echo -e "${YELLOW}Verificando versión de PHP...${NC}"
PHP_VERSION=$(php -v | head -n 1 | cut -d ' ' -f 2 | cut -d '.' -f 1,2)
if [ "$(echo "$PHP_VERSION >= 8.3" | bc -l 2>/dev/null || echo 0)" -eq 1 ] || [ "$PHP_VERSION" = "8.3" ]; then
    echo -e "${GREEN}✓ PHP versión $PHP_VERSION (requerido: 8.3+)${NC}"
else
    echo -e "${RED}✗ PHP versión $PHP_VERSION (requerido: 8.3+)${NC}"
    ERRORS=$((ERRORS + 1))
fi
echo ""

# Verificar extensiones PHP
echo -e "${YELLOW}Verificando extensiones PHP...${NC}"
REQUIRED_EXTENSIONS=("pdo_pgsql" "pgsql" "redis" "snmp" "gd" "intl" "zip" "bcmath" "mbstring")
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if php -m | grep -q "^$ext$"; then
        echo -e "${GREEN}✓ Extensión PHP $ext está instalada${NC}"
    else
        echo -e "${RED}✗ Extensión PHP $ext NO está instalada${NC}"
        ERRORS=$((ERRORS + 1))
    fi
done
echo ""

# Verificar servicios
echo -e "${YELLOW}Verificando servicios...${NC}"
check_service php8.3-fpm
check_service postgresql
check_service redis-server
check_service nginx
check_service laravel-horizon
check_service laravel-scheduler.timer
echo ""

# Verificar conexión a PostgreSQL
echo -e "${YELLOW}Verificando conexión a PostgreSQL...${NC}"
if [ -f "$BACKEND_PATH/.env" ]; then
    DB_HOST=$(grep "^DB_HOST=" "$BACKEND_PATH/.env" | cut -d '=' -f 2 | tr -d '"' | tr -d "'")
    DB_PORT=$(grep "^DB_PORT=" "$BACKEND_PATH/.env" | cut -d '=' -f 2 | tr -d '"' | tr -d "'")
    DB_DATABASE=$(grep "^DB_DATABASE=" "$BACKEND_PATH/.env" | cut -d '=' -f 2 | tr -d '"' | tr -d "'")
    DB_USERNAME=$(grep "^DB_USERNAME=" "$BACKEND_PATH/.env" | cut -d '=' -f 2 | tr -d '"' | tr -d "'")
    DB_PASSWORD=$(grep "^DB_PASSWORD=" "$BACKEND_PATH/.env" | cut -d '=' -f 2 | tr -d '"' | tr -d "'")
    
    if PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_DATABASE" -c "SELECT 1;" > /dev/null 2>&1; then
        echo -e "${GREEN}✓ Conexión a PostgreSQL exitosa${NC}"
    else
        echo -e "${RED}✗ No se pudo conectar a PostgreSQL${NC}"
        ERRORS=$((ERRORS + 1))
    fi
else
    echo -e "${YELLOW}⚠ Archivo .env no encontrado, omitiendo verificación de PostgreSQL${NC}"
fi
echo ""

# Verificar conexión a Redis
echo -e "${YELLOW}Verificando conexión a Redis...${NC}"
if redis-cli ping | grep -q "PONG"; then
    echo -e "${GREEN}✓ Conexión a Redis exitosa${NC}"
else
    echo -e "${RED}✗ No se pudo conectar a Redis${NC}"
    ERRORS=$((ERRORS + 1))
fi
echo ""

# Verificar estructura de directorios
echo -e "${YELLOW}Verificando estructura de directorios...${NC}"
if [ -d "$BACKEND_PATH" ]; then
    echo -e "${GREEN}✓ Directorio backend existe${NC}"
    
    if [ -d "$BACKEND_PATH/storage" ]; then
        echo -e "${GREEN}✓ Directorio storage existe${NC}"
    else
        echo -e "${RED}✗ Directorio storage NO existe${NC}"
        ERRORS=$((ERRORS + 1))
    fi
    
    if [ -f "$BACKEND_PATH/.env" ]; then
        echo -e "${GREEN}✓ Archivo .env existe${NC}"
    else
        echo -e "${RED}✗ Archivo .env NO existe${NC}"
        ERRORS=$((ERRORS + 1))
    fi
else
    echo -e "${RED}✗ Directorio backend NO existe${NC}"
    ERRORS=$((ERRORS + 1))
fi
echo ""

# Verificar API
echo -e "${YELLOW}Verificando API...${NC}"
if curl -s http://localhost/api/v2/auth/login > /dev/null 2>&1; then
    echo -e "${GREEN}✓ API responde correctamente${NC}"
else
    echo -e "${YELLOW}⚠ API no responde (puede ser normal si no está configurada)${NC}"
fi
echo ""

# Resumen
echo -e "${YELLOW}════════════════════════════════════════${NC}"
if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}✓ Verificación completada sin errores${NC}"
    exit 0
else
    echo -e "${RED}✗ Verificación completada con $ERRORS error(es)${NC}"
    exit 1
fi

