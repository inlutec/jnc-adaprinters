#!/bin/bash
# Script de configuración de PHP 8.3 para JNC-AdaPrinters
# Compatible con Ubuntu/Debian y RHEL/CentOS

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Detectar distribución
if [ -f /etc/os-release ]; then
    . /etc/os-release
    DISTRO=$ID
else
    echo -e "${RED}No se pudo detectar la distribución${NC}"
    exit 1
fi

echo -e "${GREEN}Configurando PHP 8.3...${NC}"

# Función para Ubuntu/Debian
setup_php_ubuntu_debian() {
    echo -e "${GREEN}Instalando PHP 8.3 para Ubuntu/Debian...${NC}"
    
    # Añadir repositorio de PHP
    sudo add-apt-repository ppa:ondrej/php -y
    sudo apt update
    
    # Instalar PHP y extensiones
    sudo apt install -y \
        php8.3 \
        php8.3-fpm \
        php8.3-cli \
        php8.3-common \
        php8.3-pgsql \
        php8.3-redis \
        php8.3-snmp \
        php8.3-gd \
        php8.3-intl \
        php8.3-zip \
        php8.3-bcmath \
        php8.3-mbstring \
        php8.3-xml \
        php8.3-curl \
        php8.3-openssl
    
    # Copiar configuración personalizada si existe
    if [ -f "$(dirname "$0")/../config/php/jnc-adaprinters.ini" ]; then
        sudo cp "$(dirname "$0")/../config/php/jnc-adaprinters.ini" /etc/php/8.3/fpm/conf.d/99-jnc-adaprinters.ini
        sudo cp "$(dirname "$0")/../config/php/jnc-adaprinters.ini" /etc/php/8.3/cli/conf.d/99-jnc-adaprinters.ini
        echo -e "${GREEN}Configuración personalizada aplicada${NC}"
    else
        # Configurar manualmente
        echo -e "${YELLOW}Configurando PHP manualmente...${NC}"
        sudo sed -i 's/upload_max_filesize = .*/upload_max_filesize = 20M/' /etc/php/8.3/fpm/php.ini
        sudo sed -i 's/post_max_size = .*/post_max_size = 25M/' /etc/php/8.3/fpm/php.ini
        sudo sed -i 's/max_execution_time = .*/max_execution_time = 300/' /etc/php/8.3/fpm/php.ini
        sudo sed -i 's/max_input_time = .*/max_input_time = 300/' /etc/php/8.3/fpm/php.ini
        
        sudo sed -i 's/upload_max_filesize = .*/upload_max_filesize = 20M/' /etc/php/8.3/cli/php.ini
        sudo sed -i 's/post_max_size = .*/post_max_size = 25M/' /etc/php/8.3/cli/php.ini
        sudo sed -i 's/max_execution_time = .*/max_execution_time = 300/' /etc/php/8.3/cli/php.ini
        sudo sed -i 's/max_input_time = .*/max_input_time = 300/' /etc/php/8.3/cli/php.ini
    fi
    
    # Reiniciar PHP-FPM
    sudo systemctl restart php8.3-fpm
    sudo systemctl enable php8.3-fpm
    
    echo -e "${GREEN}PHP 8.3 configurado correctamente${NC}"
}

# Función para RHEL/CentOS
setup_php_rhel_centos() {
    echo -e "${YELLOW}Para RHEL/CentOS, por favor sigue las instrucciones en INSTALLATION_RHEL.md${NC}"
    echo -e "${YELLOW}Se requiere configurar repositorios Remi para PHP 8.3${NC}"
    exit 1
}

# Configurar según la distribución
case $DISTRO in
    ubuntu|debian)
        setup_php_ubuntu_debian
        ;;
    rhel|centos|rocky|almalinux)
        setup_php_rhel_centos
        ;;
    *)
        echo -e "${RED}Distribución no soportada: $DISTRO${NC}"
        exit 1
        ;;
esac

# Verificar instalación
echo -e "${GREEN}Verificando instalación de PHP...${NC}"
php -v
echo ""
echo -e "${GREEN}Extensiones instaladas:${NC}"
php -m | grep -E 'pdo_pgsql|pgsql|redis|snmp|gd|intl|zip|bcmath|mbstring' || echo -e "${YELLOW}Algunas extensiones pueden no estar instaladas${NC}"

echo -e "${GREEN}✓ Configuración de PHP completada${NC}"

