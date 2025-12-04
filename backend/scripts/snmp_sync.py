#!/usr/bin/env python3
"""
Script de sincronización SNMP automática
Lee la configuración de la base de datos y ejecuta la sincronización
"""

#!/usr/bin/env python3
"""
Script de sincronización SNMP automática
Lee la configuración de la base de datos y ejecuta la sincronización
"""

import os
import sys
import subprocess
from datetime import datetime, timedelta

try:
    import psycopg2
    from psycopg2.extras import RealDictCursor
except ImportError:
    print("Error: psycopg2 no está instalado. Ejecuta: pip3 install psycopg2-binary", file=sys.stderr)
    sys.exit(1)

# Configuración de la base de datos desde variables de entorno
DB_CONFIG = {
    'host': os.getenv('DB_HOST', 'postgres'),
    'port': os.getenv('DB_PORT', '5432'),
    'database': os.getenv('DB_DATABASE', 'jnc_adaprinters'),
    'user': os.getenv('DB_USERNAME', 'jnc_admin'),
    'password': os.getenv('DB_PASSWORD', 'secretpassword'),
}

def get_sync_config():
    """Obtiene la configuración de sincronización desde la base de datos"""
    try:
        conn = psycopg2.connect(**DB_CONFIG)
        cursor = conn.cursor(cursor_factory=RealDictCursor)
        
        # Obtener configuración
        cursor.execute("""
            SELECT key, value 
            FROM snmp_sync_configs 
            WHERE key IN ('auto_sync_enabled', 'auto_sync_frequency')
        """)
        
        config = {row['key']: row['value'] for row in cursor.fetchall()}
        
        cursor.close()
        conn.close()
        
        enabled = config.get('auto_sync_enabled', 'false') == 'true'
        frequency = int(config.get('auto_sync_frequency', '15'))
        
        return enabled, frequency
    except Exception as e:
        print(f"Error al obtener configuración: {e}", file=sys.stderr)
        return False, 15

def should_run_sync(frequency):
    """Verifica si debe ejecutarse la sincronización según la frecuencia"""
    try:
        conn = psycopg2.connect(**DB_CONFIG)
        cursor = conn.cursor(cursor_factory=RealDictCursor)
        
        # Obtener última sincronización completada
        cursor.execute("""
            SELECT completed_at 
            FROM snmp_sync_history 
            WHERE type = 'automatic' 
            AND status = 'completed' 
            ORDER BY completed_at DESC 
            LIMIT 1
        """)
        
        result = cursor.fetchone()
        cursor.close()
        conn.close()
        
        if not result or not result['completed_at']:
            return True  # No hay sincronizaciones previas, ejecutar
        
        last_sync = result['completed_at']
        next_run = last_sync.replace(second=0, microsecond=0)
        
        # Añadir minutos de frecuencia
        from datetime import timedelta
        next_run = next_run + timedelta(minutes=frequency)
        
        now = datetime.now().replace(second=0, microsecond=0)
        return now >= next_run
        
    except Exception as e:
        print(f"Error al verificar última sincronización: {e}", file=sys.stderr)
        return True  # En caso de error, ejecutar

def run_sync():
    """Ejecuta la sincronización SNMP usando Artisan"""
    try:
        # Cambiar al directorio del backend
        backend_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        os.chdir(backend_dir)
        
        # Ejecutar comando Artisan
        result = subprocess.run(
            ['php', 'artisan', 'printers:poll', '--auto-check'],
            capture_output=True,
            text=True,
            timeout=300  # 5 minutos máximo
        )
        
        if result.returncode == 0:
            print(f"[{datetime.now()}] Sincronización ejecutada correctamente")
            if result.stdout:
                print(result.stdout)
            return True
        else:
            print(f"[{datetime.now()}] Error en sincronización:", file=sys.stderr)
            if result.stderr:
                print(result.stderr, file=sys.stderr)
            return False
            
    except subprocess.TimeoutExpired:
        print(f"[{datetime.now()}] La sincronización excedió el tiempo límite", file=sys.stderr)
        return False
    except Exception as e:
        print(f"[{datetime.now()}] Error al ejecutar sincronización: {e}", file=sys.stderr)
        return False

def main():
    """Función principal"""
    enabled, frequency = get_sync_config()
    
    if not enabled:
        print(f"[{datetime.now()}] Sincronización automática deshabilitada")
        sys.exit(0)
    
    if not should_run_sync(frequency):
        print(f"[{datetime.now()}] Aún no es momento de sincronizar (frecuencia: {frequency} minutos)")
        sys.exit(0)
    
    print(f"[{datetime.now()}] Iniciando sincronización automática (frecuencia: {frequency} minutos)")
    success = run_sync()
    sys.exit(0 if success else 1)

if __name__ == '__main__':
    main()

