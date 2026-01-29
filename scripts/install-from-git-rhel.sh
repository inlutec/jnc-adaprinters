#!/bin/bash

# Script para instalar JNC-AdaPrinters desde Git en servidor RHEL
# Uso: sudo ./scripts/install-from-git-rhel.sh [GIT_REPO_URL] [INSTALL_DIR]

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuración por defecto
GIT_REPO_URL="${1:-https://github.com/inlutec/jnc-adaprinters.git}"
INSTALL_DIR="${2:-/var/www/jnc-adaprinters}"
BRANCH="${3:-main}"

echo "=========================================="
echo "Instalación de JNC-AdaPrinters desde Git"
echo "=========================================="
echo ""
echo "Repositorio: $GIT_REPO_URL"
echo "Directorio: $INSTALL_DIR"
echo "Rama: $BRANCH"
echo ""

# Verificar que estamos en RHEL
if [ ! -f /etc/redhat-release ]; then
    echo -e "${RED}❌ Error: Este script es solo para RHEL/CentOS/Rocky Linux/AlmaLinux${NC}"
    exit 1
fi

# Verificar requisitos
echo "🔍 Verificando requisitos..."

REQUIRED_COMMANDS=("git" "php" "composer" "node" "npm" "psql")
MISSING_COMMANDS=()

for cmd in "${REQUIRED_COMMANDS[@]}"; do
    if ! command -v "$cmd" &> /dev/null; then
        MISSING_COMMANDS+=("$cmd")
    else
        VERSION=$($cmd --version 2>/dev/null | head -n 1 || echo "instalado")
        echo "  ✓ $cmd: $VERSION"
    fi
done

if [ ${#MISSING_COMMANDS[@]} -ne 0 ]; then
    echo -e "${RED}❌ Faltan los siguientes comandos: ${MISSING_COMMANDS[*]}${NC}"
    echo ""
    echo "Instala los requisitos faltantes:"
    echo "  - Composer: sudo ./scripts/install-composer-rhel.sh"
    echo "  - PHP 8.0+: Ver INSTALLATION_RHEL.md"
    echo "  - Node.js 20+: Ver INSTALLATION_RHEL.md"
    echo "  - PostgreSQL 16+: Ver INSTALLATION_RHEL.md"
    exit 1
fi

# Crear directorio de instalación
echo ""
echo "📁 Creando directorio de instalación..."
sudo mkdir -p "$INSTALL_DIR"
sudo chown -R $USER:$USER "$INSTALL_DIR" || true

# Clonar o actualizar repositorio
if [ -d "$INSTALL_DIR/.git" ]; then
    echo ""
    echo "🔄 Actualizando repositorio existente..."
    cd "$INSTALL_DIR"
    git fetch origin
    git checkout "$BRANCH"
    git pull origin "$BRANCH"
else
    echo ""
    echo "📥 Clonando repositorio..."
    if [ -d "$INSTALL_DIR" ] && [ "$(ls -A $INSTALL_DIR)" ]; then
        echo -e "${YELLOW}⚠️  El directorio $INSTALL_DIR ya existe y no está vacío${NC}"
        read -p "¿Deseas continuar? (s/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Ss]$ ]]; then
            exit 1
        fi
    fi
    git clone -b "$BRANCH" "$GIT_REPO_URL" "$INSTALL_DIR"
    cd "$INSTALL_DIR"
fi

# Instalar dependencias del backend
echo ""
echo "📦 Instalando dependencias del backend (Composer)..."
cd "$INSTALL_DIR/backend"
composer install --no-dev --optimize-autoloader

# Instalar dependencias del frontend
echo ""
echo "📦 Instalando dependencias del frontend (npm)..."
cd "$INSTALL_DIR/frontend"
npm install

# Compilar frontend
echo ""
echo "🔨 Compilando frontend..."
npm run build

# Copiar frontend compilado al backend
echo ""
echo "📋 Copiando frontend compilado al backend..."
cd "$INSTALL_DIR"
cp -r frontend/dist/* backend/public/

# Configurar permisos
echo ""
echo "🔧 Configurando permisos..."
sudo chown -R nginx:nginx "$INSTALL_DIR/backend/storage" "$INSTALL_DIR/backend/bootstrap/cache" || true
sudo chmod -R 775 "$INSTALL_DIR/backend/storage" "$INSTALL_DIR/backend/bootstrap/cache" || true

# Crear archivo .env si no existe
if [ ! -f "$INSTALL_DIR/backend/.env" ]; then
    echo ""
    echo "📝 Creando archivo .env desde .env.example..."
    if [ -f "$INSTALL_DIR/backend/.env.example" ]; then
        cp "$INSTALL_DIR/backend/.env.example" "$INSTALL_DIR/backend/.env"
        echo -e "${YELLOW}⚠️  IMPORTANTE: Edita $INSTALL_DIR/backend/.env con tus configuraciones${NC}"
    else
        echo -e "${YELLOW}⚠️  No se encontró .env.example. Crea manualmente $INSTALL_DIR/backend/.env${NC}"
    fi
fi

# Crear .env del frontend si no existe
if [ ! -f "$INSTALL_DIR/frontend/.env" ]; then
    echo ""
    echo "📝 Creando archivo .env del frontend..."
    read -p "¿Cuál es la URL del servidor? (ej: http://10.47.12.13): " SERVER_URL
    echo "VITE_API_URL=${SERVER_URL}/api/v2" > "$INSTALL_DIR/frontend/.env"
    echo "✓ Frontend .env creado con VITE_API_URL=${SERVER_URL}/api/v2"
fi

echo ""
echo "=========================================="
echo -e "${GREEN}✅ Instalación desde Git completada${NC}"
echo "=========================================="
echo ""
echo "Próximos pasos:"
echo ""
echo "1. Configura el archivo .env del backend:"
echo "   sudo nano $INSTALL_DIR/backend/.env"
echo ""
echo "2. Genera la clave de aplicación:"
echo "   cd $INSTALL_DIR/backend"
echo "   php artisan key:generate"
echo ""
echo "3. Ejecuta las migraciones:"
echo "   cd $INSTALL_DIR/backend"
echo "   php artisan migrate --seed"
echo ""
echo "4. Configura Nginx (ver INSTALLATION_RHEL.md)"
echo ""
echo "5. Configura permisos y servicios (ver INSTALLATION_RHEL.md)"
echo ""
