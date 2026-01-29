#!/bin/bash

# Script para instalar Composer en RHEL/CentOS/Rocky Linux/AlmaLinux
# Uso: sudo ./scripts/install-composer-rhel.sh

set -e

echo "=========================================="
echo "Instalación de Composer para RHEL"
echo "=========================================="

# Verificar que estamos en RHEL/CentOS/Rocky/AlmaLinux
if [ ! -f /etc/redhat-release ]; then
    echo "❌ Error: Este script es solo para RHEL/CentOS/Rocky Linux/AlmaLinux"
    exit 1
fi

# Verificar PHP
if ! command -v php &> /dev/null; then
    echo "❌ Error: PHP no está instalado. Instala PHP primero."
    exit 1
fi

PHP_VERSION=$(php -r 'echo PHP_VERSION;')
echo "✓ PHP encontrado: $PHP_VERSION"

# Verificar que PHP sea 8.0 o superior
PHP_MAJOR=$(php -r 'echo PHP_MAJOR_VERSION;')
if [ "$PHP_MAJOR" -lt 8 ]; then
    echo "⚠️  Advertencia: Se recomienda PHP 8.0 o superior. Actual: PHP $PHP_VERSION"
fi

# Descargar e instalar Composer
echo ""
echo "📥 Descargando Composer..."
EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
    >&2 echo '❌ Error: Checksum de Composer no coincide. Abortando.'
    rm composer-setup.php
    exit 1
fi

echo "✓ Checksum verificado"

# Instalar Composer globalmente
echo ""
echo "📦 Instalando Composer en /usr/local/bin/composer..."
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php

# Verificar instalación
if command -v composer &> /dev/null; then
    COMPOSER_VERSION=$(composer --version | head -n 1)
    echo "✓ Composer instalado correctamente: $COMPOSER_VERSION"
else
    echo "❌ Error: Composer no se instaló correctamente"
    exit 1
fi

# Configurar permisos (opcional, para que cualquier usuario pueda usar composer)
echo ""
echo "🔧 Configurando permisos..."
chmod +x /usr/local/bin/composer

# Verificar que composer funciona
echo ""
echo "🧪 Verificando instalación..."
composer --version

echo ""
echo "=========================================="
echo "✅ Composer instalado correctamente"
echo "=========================================="
echo ""
echo "Uso:"
echo "  composer --version          # Ver versión"
echo "  composer install            # Instalar dependencias"
echo "  composer update             # Actualizar dependencias"
echo ""
