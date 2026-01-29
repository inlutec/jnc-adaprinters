# Instalador Local de Composer

Este directorio contiene una copia local del instalador de Composer para permitir la instalación sin conexión a internet.

## Archivos

- **`composer-setup.php`**: Instalador de Composer (59 KB)
- **`composer-installer.sig`**: Checksum SHA384 del instalador para verificación
- **`EXPECTED_CHECKSUM.txt`**: Copia del checksum esperado

## Uso

El script `install-composer-rhel.sh` detecta automáticamente si existe el instalador local y lo usa en lugar de descargarlo desde internet.

### Instalación con instalador local (recomendado)

```bash
cd /var/www/jnc-adaprinters
sudo ./scripts/install-composer-rhel.sh
```

El script:
1. Busca el instalador local en `scripts/composer-installer/composer-setup.php`
2. Si existe, lo usa y verifica su checksum
3. Si no existe o el checksum falla, descarga desde internet automáticamente

### Ventajas

- ✅ Instalación más rápida (no necesita descargar)
- ✅ Funciona sin conexión a internet
- ✅ Verificación de integridad con checksum
- ✅ Fallback automático a descarga si el instalador local no está disponible

## Actualización del Instalador

Si necesitas actualizar el instalador local:

```bash
cd /var/www/jnc-adaprinters/scripts/composer-installer
curl -o composer-setup.php https://getcomposer.org/installer
curl -o composer-installer.sig https://composer.github.io/installer.sig
echo "$(cat composer-installer.sig)" > EXPECTED_CHECKSUM.txt
```

Luego sube los cambios a Git:

```bash
cd /var/www/jnc-adaprinters
git add scripts/composer-installer/
git commit -m "Actualizar instalador de Composer"
git push
```

## Verificación

Para verificar que el instalador local es válido:

```bash
cd /var/www/jnc-adaprinters/scripts/composer-installer
EXPECTED=$(cat composer-installer.sig)
ACTUAL=$(php -r "echo hash_file('sha384', 'composer-setup.php');")
if [ "$EXPECTED" == "$ACTUAL" ]; then
    echo "✓ Checksum válido"
else
    echo "❌ Checksum no coincide - el instalador puede estar corrupto"
fi
```
