#!/bin/bash
# Script de configuración de PostgreSQL para JNC-AdaPrinters

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Variables por defecto
DB_NAME="${DB_NAME:-jnc_adaprinters}"
DB_USER="${DB_USER:-jnc_admin}"
DB_PASSWORD="${DB_PASSWORD:-}"

echo -e "${GREEN}Configurando PostgreSQL...${NC}"

# Solicitar contraseña si no está definida
if [ -z "$DB_PASSWORD" ]; then
    echo -e "${YELLOW}Ingresa la contraseña para el usuario de la base de datos:${NC}"
    read -s DB_PASSWORD
    echo ""
    
    if [ -z "$DB_PASSWORD" ]; then
        echo -e "${RED}La contraseña no puede estar vacía${NC}"
        exit 1
    fi
fi

# Detectar distribución
if [ -f /etc/os-release ]; then
    . /etc/os-release
    DISTRO=$ID
else
    echo -e "${RED}No se pudo detectar la distribución${NC}"
    exit 1
fi

# Instalar PostgreSQL según la distribución
case $DISTRO in
    ubuntu|debian)
        if ! command -v psql &> /dev/null; then
            echo -e "${GREEN}Instalando PostgreSQL 16...${NC}"
            sudo apt update
            sudo apt install -y postgresql-16 postgresql-contrib-16
        fi
        ;;
    rhel|centos|rocky|almalinux)
        if ! command -v psql &> /dev/null; then
            echo -e "${GREEN}Instalando PostgreSQL 16...${NC}"
            sudo dnf install -y postgresql16-server postgresql16
            sudo /usr/pgsql-16/bin/postgresql-16-setup initdb
        fi
        ;;
esac

# Iniciar y habilitar PostgreSQL
sudo systemctl start postgresql
sudo systemctl enable postgresql

# Crear base de datos y usuario
echo -e "${GREEN}Creando base de datos y usuario...${NC}"

sudo -u postgres psql << EOF
-- Crear base de datos
CREATE DATABASE $DB_NAME;

-- Crear usuario
CREATE USER $DB_USER WITH PASSWORD '$DB_PASSWORD';

-- Configurar usuario
ALTER ROLE $DB_USER SET client_encoding TO 'utf8';
ALTER ROLE $DB_USER SET default_transaction_isolation TO 'read committed';
ALTER ROLE $DB_USER SET timezone TO 'UTC';

-- Otorgar privilegios
GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;

-- Conectar a la base de datos y otorgar privilegios en el esquema
\c $DB_NAME
GRANT ALL ON SCHEMA public TO $DB_USER;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO $DB_USER;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO $DB_USER;
EOF

echo -e "${GREEN}Base de datos y usuario creados correctamente${NC}"
echo -e "${YELLOW}Información de conexión:${NC}"
echo -e "  Base de datos: $DB_NAME"
echo -e "  Usuario: $DB_USER"
echo -e "  Host: localhost"
echo -e "  Puerto: 5432"

# Verificar conexión
echo -e "${GREEN}Verificando conexión...${NC}"
PGPASSWORD=$DB_PASSWORD psql -h localhost -U $DB_USER -d $DB_NAME -c "SELECT version();" > /dev/null

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Conexión verificada correctamente${NC}"
else
    echo -e "${RED}✗ Error al verificar la conexión${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Configuración de PostgreSQL completada${NC}"

