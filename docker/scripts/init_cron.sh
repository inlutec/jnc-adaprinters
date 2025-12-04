#!/bin/bash
# Script de inicialización del cron para sincronización SNMP
# Se ejecuta al iniciar el contenedor scheduler

BACKEND_DIR="/var/www/html"
SCRIPT_PATH="${BACKEND_DIR}/scripts/snmp_sync.py"
LOG_PATH="/var/log/snmp_sync.log"

# Función para leer configuración desde la base de datos
read_config() {
    if [ -f "${BACKEND_DIR}/artisan" ]; then
        cd ${BACKEND_DIR}
        CONFIG_OUTPUT=$(php artisan tinker --execute="
            try {
                \$enabled = \App\Models\SnmpSyncConfig::isEnabled('auto_sync_enabled');
                \$frequency = (int) \App\Models\SnmpSyncConfig::get('auto_sync_frequency', 15);
                echo (\$enabled ? '1' : '0') . '|' . \$frequency;
            } catch (Exception \$e) {
                echo '1|15';
            }
        " 2>&1 | grep -E '^[01]\|[0-9]+$' | head -1)
        
        if [ ! -z "$CONFIG_OUTPUT" ] && echo "$CONFIG_OUTPUT" | grep -qE '^[01]\|[0-9]+$'; then
            ENABLED_VAL=$(echo "$CONFIG_OUTPUT" | cut -d'|' -f1)
            FREQUENCY_VAL=$(echo "$CONFIG_OUTPUT" | cut -d'|' -f2)
            
            if [ "$ENABLED_VAL" = "1" ]; then
                ENABLED=true
            else
                ENABLED=false
            fi
            
            if [ ! -z "$FREQUENCY_VAL" ] && [ "$FREQUENCY_VAL" -gt 0 ] 2>/dev/null; then
                FREQUENCY=$FREQUENCY_VAL
            else
                FREQUENCY=15
            fi
        else
            ENABLED=true
            FREQUENCY=15
        fi
    else
        ENABLED=true
        FREQUENCY=15
    fi
}

# Leer configuración
ENABLED=true
FREQUENCY=15
read_config

echo "Configuración leída: enabled=$ENABLED, frequency=$FREQUENCY"

# Configurar cron si está habilitado
if [ "$ENABLED" = "true" ] && [ "$FREQUENCY" -gt 0 ]; then
    # Crear entrada de cron
    CRON_ENTRY="*/${FREQUENCY} * * * * cd ${BACKEND_DIR} && /usr/bin/python3 ${SCRIPT_PATH} >> ${LOG_PATH} 2>&1"
    
    # Instalar cron job
    echo "$CRON_ENTRY" | crontab -
    
    echo "✅ Cron configurado: ejecutará cada ${FREQUENCY} minutos"
    crontab -l
else
    echo "⚠️ Sincronización automática deshabilitada o frecuencia inválida"
    # Limpiar crontab
    crontab -l 2>/dev/null | grep -v snmp_sync.py | crontab - 2>/dev/null || true
fi

# Iniciar cron en foreground
exec cron -f
