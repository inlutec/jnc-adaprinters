#!/bin/bash
# Script de configuración de servicios systemd para JNC-AdaPrinters

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

echo -e "${GREEN}Configurando servicios systemd...${NC}"

# Verificar que existe el directorio
if [ ! -d "$BACKEND_PATH" ]; then
    echo -e "${RED}Error: No se encontró el directorio $BACKEND_PATH${NC}"
    exit 1
fi

# Verificar archivos de configuración
HORIZON_SERVICE="$(dirname "$0")/../config/systemd/laravel-horizon.service"
SCHEDULER_SERVICE="$(dirname "$0")/../config/systemd/laravel-scheduler.service"
SCHEDULER_TIMER="$(dirname "$0")/../config/systemd/laravel-scheduler.timer"

if [ ! -f "$HORIZON_SERVICE" ] || [ ! -f "$SCHEDULER_SERVICE" ] || [ ! -f "$SCHEDULER_TIMER" ]; then
    echo -e "${RED}Error: No se encontraron los archivos de configuración de systemd${NC}"
    exit 1
fi

# Copiar y configurar servicio de Horizon
echo -e "${GREEN}Configurando servicio de Laravel Horizon...${NC}"
sudo cp "$HORIZON_SERVICE" /etc/systemd/system/laravel-horizon.service

# Reemplazar variables en el servicio
sudo sed -i "s|/var/www/jnc-adaprinters|$APP_PATH|g" /etc/systemd/system/laravel-horizon.service
sudo sed -i "s|User=www-data|User=$WWW_USER|g" /etc/systemd/system/laravel-horizon.service
sudo sed -i "s|Group=www-data|Group=$WWW_USER|g" /etc/systemd/system/laravel-horizon.service

# Copiar y configurar servicio del scheduler
echo -e "${GREEN}Configurando servicio de Laravel Scheduler...${NC}"
sudo cp "$SCHEDULER_SERVICE" /etc/systemd/system/laravel-scheduler.service
sudo cp "$SCHEDULER_TIMER" /etc/systemd/system/laravel-scheduler.timer

# Reemplazar variables en el servicio
sudo sed -i "s|/var/www/jnc-adaprinters|$APP_PATH|g" /etc/systemd/system/laravel-scheduler.service
sudo sed -i "s|User=www-data|User=$WWW_USER|g" /etc/systemd/system/laravel-scheduler.service
sudo sed -i "s|Group=www-data|Group=$WWW_USER|g" /etc/systemd/system/laravel-scheduler.service

# Recargar systemd
sudo systemctl daemon-reload

# Habilitar y iniciar servicios
echo -e "${GREEN}Habilitando servicios...${NC}"
sudo systemctl enable laravel-horizon
sudo systemctl enable laravel-scheduler.timer

echo -e "${GREEN}Iniciando servicios...${NC}"
sudo systemctl start laravel-horizon
sudo systemctl start laravel-scheduler.timer

# Verificar estado
echo -e "${GREEN}Verificando estado de los servicios...${NC}"
echo ""
echo -e "${YELLOW}Estado de Laravel Horizon:${NC}"
sudo systemctl status laravel-horizon --no-pager -l || true
echo ""
echo -e "${YELLOW}Estado de Laravel Scheduler:${NC}"
sudo systemctl status laravel-scheduler.timer --no-pager -l || true

echo -e "${GREEN}✓ Configuración de servicios completada${NC}"
echo -e "${YELLOW}Comandos útiles:${NC}"
echo -e "  Ver logs de Horizon: sudo journalctl -u laravel-horizon -f"
echo -e "  Ver logs del Scheduler: sudo journalctl -u laravel-scheduler -f"
echo -e "  Reiniciar Horizon: sudo systemctl restart laravel-horizon"
echo -e "  Reiniciar Scheduler: sudo systemctl restart laravel-scheduler.timer"

