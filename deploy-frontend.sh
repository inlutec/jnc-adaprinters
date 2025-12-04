#!/bin/bash
# Script para compilar y desplegar el frontend en producción

set -e

echo "🔨 Compilando frontend..."
cd frontend
npm run build

echo "📦 Copiando archivos compilados a backend/public..."
cd ..
rm -rf backend/public/assets/*
cp -r frontend/dist/* backend/public/

echo "✅ Frontend desplegado correctamente"
echo ""
echo "📋 Archivos desplegados:"
ls -lh backend/public/assets/*.js backend/public/assets/*.css 2>/dev/null | awk '{print "  -", $9, "("$5")"}'

echo ""
echo "🔄 Reiniciando Nginx..."
cd docker
docker compose restart nginx

echo ""
echo "✅ ¡Despliegue completado!"
echo "💡 Recuerda hacer Ctrl+Shift+R en el navegador para ver los cambios"

