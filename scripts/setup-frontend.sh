#!/bin/bash
# Script de compilación del frontend para JNC-AdaPrinters

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Variables
APP_PATH="${APP_PATH:-/var/www/jnc-adaprinters}"
FRONTEND_PATH="$APP_PATH/frontend"
BACKEND_PATH="$APP_PATH/backend"
WWW_USER="${WWW_USER:-www-data}"

echo -e "${GREEN}Compilando frontend...${NC}"

# Verificar que existe el directorio
if [ ! -d "$FRONTEND_PATH" ]; then
    echo -e "${RED}Error: No se encontró el directorio $FRONTEND_PATH${NC}"
    exit 1
fi

cd "$FRONTEND_PATH"

# Verificar que Node.js está instalado
if ! command -v node &> /dev/null; then
    echo -e "${RED}Error: Node.js no está instalado${NC}"
    echo -e "${YELLOW}Por favor, instala Node.js 20 o superior${NC}"
    exit 1
fi

# Verificar que npm está instalado
if ! command -v npm &> /dev/null; then
    echo -e "${RED}Error: npm no está instalado${NC}"
    exit 1
fi

# Instalar dependencias
echo -e "${GREEN}Instalando dependencias de npm...${NC}"
npm install

# Verificar archivo .env
if [ ! -f .env ]; then
    echo -e "${YELLOW}Archivo .env no encontrado en frontend${NC}"
    read -p "¿Crear archivo .env? (s/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Ss]$ ]]; then
        read -p "Ingresa la URL de la API (ej: http://localhost/api/v2): " API_URL
        echo "VITE_API_URL=$API_URL" > .env
        echo -e "${GREEN}Archivo .env creado${NC}"
    else
        echo -e "${YELLOW}Compilando sin .env. Asegúrate de configurar VITE_API_URL${NC}"
    fi
fi

# Compilar para producción
echo -e "${GREEN}Compilando frontend para producción...${NC}"
npm run build

# Copiar archivos al directorio público
echo -e "${GREEN}Copiando archivos al directorio público...${NC}"
if [ -d "$BACKEND_PATH/public" ]; then
    # Limpiar assets antiguos (opcional, comentado para seguridad)
    # sudo rm -rf $BACKEND_PATH/public/assets/*
    
    # Copiar archivos compilados
    sudo -u $WWW_USER cp -r dist/* $BACKEND_PATH/public/
    echo -e "${GREEN}✓ Archivos copiados correctamente${NC}"
else
    echo -e "${RED}Error: No se encontró el directorio $BACKEND_PATH/public${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Compilación del frontend completada${NC}"

