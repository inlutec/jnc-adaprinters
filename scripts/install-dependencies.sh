#!/bin/bash
# Script de instalación de dependencias del sistema para JNC-AdaPrinters
# Compatible con Ubuntu/Debian y RHEL/CentOS

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Detectar distribución
if [ -f /etc/os-release ]; then
    . /etc/os-release
    DISTRO=$ID
    VERSION=$VERSION_ID
else
    echo -e "${RED}No se pudo detectar la distribución${NC}"
    exit 1
fi

echo -e "${GREEN}Detectada distribución: $DISTRO $VERSION${NC}"

# Función para Ubuntu/Debian
install_ubuntu_debian() {
    echo -e "${GREEN}Instalando dependencias para Ubuntu/Debian...${NC}"
    
    # Actualizar repositorios
    sudo apt update
    
    # Instalar dependencias básicas
    sudo apt install -y \
        git \
        curl \
        wget \
        unzip \
        software-properties-common \
        build-essential \
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
        cron
    
    echo -e "${GREEN}Dependencias instaladas correctamente${NC}"
}

# Función para RHEL/CentOS
install_rhel_centos() {
    echo -e "${GREEN}Instalando dependencias para RHEL/CentOS...${NC}"
    
    # Instalar EPEL
    sudo dnf install -y epel-release
    
    # Instalar dependencias básicas
    sudo dnf install -y \
        git \
        curl \
        wget \
        unzip \
        gcc \
        gcc-c++ \
        make \
        postgresql16-devel \
        libzip-devel \
        libicu-devel \
        libpng-devel \
        libjpeg-turbo-devel \
        freetype-devel \
        oniguruma-devel \
        net-snmp-devel \
        net-snmp \
        python3 \
        python3-pip \
        python3-psycopg2 \
        cronie
    
    echo -e "${GREEN}Dependencias instaladas correctamente${NC}"
}

# Instalar según la distribución
case $DISTRO in
    ubuntu|debian)
        install_ubuntu_debian
        ;;
    rhel|centos|rocky|almalinux)
        install_rhel_centos
        ;;
    *)
        echo -e "${RED}Distribución no soportada: $DISTRO${NC}"
        echo -e "${YELLOW}Por favor, instala las dependencias manualmente${NC}"
        exit 1
        ;;
esac

echo -e "${GREEN}✓ Instalación de dependencias completada${NC}"

