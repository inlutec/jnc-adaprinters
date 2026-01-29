#!/bin/bash
# Script de instalación y configuración de Laravel para JNC-AdaPrinters

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Variables
APP_PATH="${APP_PATH:-/var/www/jnc-adaprinters}"
BACKEND_PATH="$APP_PATH/backend"
WWW_USER="${WWW_USER:-www-data}"

echo -e "${GREEN}Instalando y configurando Laravel...${NC}"

# Verificar que existe el directorio
if [ ! -d "$BACKEND_PATH" ]; then
    echo -e "${RED}Error: No se encontró el directorio $BACKEND_PATH${NC}"
    exit 1
fi

cd "$BACKEND_PATH"

# Verificar que Composer está instalado
if ! command -v composer &> /dev/null; then
    echo -e "${RED}Error: Composer no está instalado${NC}"
    echo -e "${YELLOW}Por favor, instala Composer primero${NC}"
    exit 1
fi

# Instalar dependencias de Composer
echo -e "${GREEN}Instalando dependencias de Composer...${NC}"
sudo -u $WWW_USER composer install --no-dev --optimize-autoloader

# Verificar si existe .env
if [ ! -f .env ]; then
    echo -e "${YELLOW}Archivo .env no encontrado, creando desde .env.example...${NC}"
    if [ -f .env.example ]; then
        sudo -u $WWW_USER cp .env.example .env
    else
        echo -e "${RED}Error: No se encontró .env.example${NC}"
        exit 1
    fi
fi

# Generar clave de aplicación si no existe
if ! grep -q "APP_KEY=base64:" .env 2>/dev/null || grep -q "APP_KEY=$" .env 2>/dev/null; then
    echo -e "${GREEN}Generando clave de aplicación...${NC}"
    sudo -u $WWW_USER php artisan key:generate
fi

# Configurar permisos
echo -e "${GREEN}Configurando permisos...${NC}"
sudo chown -R $WWW_USER:$WWW_USER storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Ejecutar migraciones
echo -e "${GREEN}Ejecutando migraciones...${NC}"
read -p "¿Ejecutar migraciones ahora? (s/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Ss]$ ]]; then
    sudo -u $WWW_USER php artisan migrate --force
    
    read -p "¿Poblar base de datos con datos de prueba? (s/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Ss]$ ]]; then
        sudo -u $WWW_USER php artisan db:seed
        echo -e "${YELLOW}Credenciales por defecto:${NC}"
        echo -e "  Email: admin@jnc-adaprinters.local"
        echo -e "  Password: admin123"
    fi
else
    echo -e "${YELLOW}Migraciones omitidas. Ejecuta manualmente:${NC}"
    echo -e "  cd $BACKEND_PATH"
    echo -e "  sudo -u $WWW_USER php artisan migrate --force"
fi

# Optimizar Laravel
echo -e "${GREEN}Optimizando Laravel...${NC}"
sudo -u $WWW_USER php artisan config:cache
sudo -u $WWW_USER php artisan route:cache
sudo -u $WWW_USER php artisan view:cache

echo -e "${GREEN}✓ Instalación de Laravel completada${NC}"
echo -e "${YELLOW}Recuerda configurar las variables de entorno en $BACKEND_PATH/.env${NC}"

