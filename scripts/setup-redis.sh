#!/bin/bash
# Script de configuración de Redis para JNC-AdaPrinters

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}Configurando Redis...${NC}"

# Detectar distribución
if [ -f /etc/os-release ]; then
    . /etc/os-release
    DISTRO=$ID
else
    echo -e "${RED}No se pudo detectar la distribución${NC}"
    exit 1
fi

# Instalar Redis según la distribución
case $DISTRO in
    ubuntu|debian)
        if ! command -v redis-server &> /dev/null; then
            echo -e "${GREEN}Instalando Redis...${NC}"
            sudo apt update
            sudo apt install -y redis-server
        fi
        ;;
    rhel|centos|rocky|almalinux)
        if ! command -v redis-server &> /dev/null; then
            echo -e "${GREEN}Instalando Redis...${NC}"
            sudo dnf install -y redis
        fi
        ;;
esac

# Configurar Redis
REDIS_CONF="/etc/redis/redis.conf"
if [ -f "$REDIS_CONF" ]; then
    echo -e "${GREEN}Configurando Redis...${NC}"
    
    # Hacer backup de la configuración
    sudo cp $REDIS_CONF ${REDIS_CONF}.backup
    
    # Configurar bind
    sudo sed -i 's/^bind .*/bind 127.0.0.1/' $REDIS_CONF
    
    # Configurar protected mode
    sudo sed -i 's/^protected-mode .*/protected-mode yes/' $REDIS_CONF
    
    # Configurar save
    if ! grep -q "^save 60 1" $REDIS_CONF; then
        sudo sed -i '/^save /d' $REDIS_CONF
        echo "save 60 1" | sudo tee -a $REDIS_CONF > /dev/null
    fi
    
    # Configurar loglevel
    sudo sed -i 's/^loglevel .*/loglevel warning/' $REDIS_CONF
fi

# Iniciar y habilitar Redis
sudo systemctl start redis-server || sudo systemctl start redis
sudo systemctl enable redis-server || sudo systemctl enable redis

# Verificar instalación
echo -e "${GREEN}Verificando Redis...${NC}"
if redis-cli ping | grep -q "PONG"; then
    echo -e "${GREEN}✓ Redis está funcionando correctamente${NC}"
else
    echo -e "${RED}✗ Error: Redis no responde${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Configuración de Redis completada${NC}"

