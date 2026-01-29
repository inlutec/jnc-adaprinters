#!/bin/bash
# Script de configuración de Nginx para JNC-AdaPrinters

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Variables
APP_PATH="${APP_PATH:-/var/www/jnc-adaprinters}"
DOMAIN="${DOMAIN:-localhost}"

echo -e "${GREEN}Configurando Nginx...${NC}"

# Detectar distribución
if [ -f /etc/os-release ]; then
    . /etc/os-release
    DISTRO=$ID
else
    echo -e "${RED}No se pudo detectar la distribución${NC}"
    exit 1
fi

# Instalar Nginx según la distribución
case $DISTRO in
    ubuntu|debian)
        if ! command -v nginx &> /dev/null; then
            echo -e "${GREEN}Instalando Nginx...${NC}"
            sudo apt update
            sudo apt install -y nginx
        fi
        ;;
    rhel|centos|rocky|almalinux)
        if ! command -v nginx &> /dev/null; then
            echo -e "${GREEN}Instalando Nginx...${NC}"
            sudo dnf install -y nginx
        fi
        ;;
esac

# Verificar que existe el archivo de configuración
CONFIG_FILE="$(dirname "$0")/../config/nginx/jnc-adaprinters.conf"
if [ ! -f "$CONFIG_FILE" ]; then
    echo -e "${RED}Error: No se encontró el archivo de configuración de Nginx${NC}"
    echo -e "${YELLOW}Por favor, crea el archivo config/nginx/jnc-adaprinters.conf${NC}"
    exit 1
fi

# Copiar configuración
echo -e "${GREEN}Copiando configuración de Nginx...${NC}"
sudo cp "$CONFIG_FILE" /etc/nginx/sites-available/jnc-adaprinters

# Reemplazar variables en la configuración
sudo sed -i "s|/var/www/jnc-adaprinters|$APP_PATH|g" /etc/nginx/sites-available/jnc-adaprinters
sudo sed -i "s|server_name _;|server_name $DOMAIN;|g" /etc/nginx/sites-available/jnc-adaprinters

# Configurar socket PHP-FPM según la distribución
if [ "$DISTRO" = "rhel" ] || [ "$DISTRO" = "centos" ] || [ "$DISTRO" = "rocky" ] || [ "$DISTRO" = "almalinux" ]; then
    # RHEL/CentOS usa /run/php-fpm/www.sock
    sudo sed -i "s|unix:/run/php/php8.3-fpm.sock|unix:/run/php-fpm/www.sock|g" /etc/nginx/sites-available/jnc-adaprinters
    sudo sed -i "s|# fastcgi_pass unix:/run/php-fpm/www.sock;|fastcgi_pass unix:/run/php-fpm/www.sock;|g" /etc/nginx/sites-available/jnc-adaprinters
    sudo sed -i "s|fastcgi_pass unix:/run/php/php8.3-fpm.sock;|# fastcgi_pass unix:/run/php/php8.3-fpm.sock;|g" /etc/nginx/sites-available/jnc-adaprinters
fi

# Crear enlace simbólico
if [ -d /etc/nginx/sites-enabled ]; then
    sudo ln -sf /etc/nginx/sites-available/jnc-adaprinters /etc/nginx/sites-enabled/jnc-adaprinters
    # Eliminar configuración por defecto si existe
    sudo rm -f /etc/nginx/sites-enabled/default
fi

# Verificar configuración
echo -e "${GREEN}Verificando configuración de Nginx...${NC}"
if sudo nginx -t; then
    echo -e "${GREEN}✓ Configuración de Nginx es válida${NC}"
else
    echo -e "${RED}✗ Error en la configuración de Nginx${NC}"
    exit 1
fi

# Iniciar y habilitar Nginx
sudo systemctl start nginx
sudo systemctl enable nginx

echo -e "${GREEN}✓ Configuración de Nginx completada${NC}"
echo -e "${YELLOW}Recuerda configurar el dominio en /etc/nginx/sites-available/jnc-adaprinters${NC}"

