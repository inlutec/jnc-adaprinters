#!/bin/bash
# Script para actualizar el cron job de sincronización SNMP
# Se ejecuta cuando se actualiza la configuración de frecuencia

BACKEND_DIR="/var/www/html"
CRON_FILE="/tmp/snmp_sync_cron"
FREQUENCY=${1:-15}  # Frecuencia en minutos, por defecto 15

# Crear entrada de cron
echo "*/${FREQUENCY} * * * * cd ${BACKEND_DIR} && /usr/bin/python3 scripts/snmp_sync.py >> /var/log/snmp_sync.log 2>&1" > ${CRON_FILE}

# Instalar el cron job
crontab ${CRON_FILE}

echo "Cron job actualizado: ejecutará cada ${FREQUENCY} minutos"


