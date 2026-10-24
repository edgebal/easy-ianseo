#!/bin/sh
# Medallero FATARCO: arranca la app con el servidor embebido de PHP.
# Uso: ./iniciar.sh [host:puerto]   (por defecto 127.0.0.1:8090; 0.0.0.0:8090 para abrirlo a la red)
set -e
cd "$(dirname "$0")"

PHP="${MEDALLERO_PHP:-php}"
DIRECCION="${1:-127.0.0.1:8090}"

if [ ! -f .env ]; then
    cp .env.example .env
    "$PHP" artisan key:generate --force
fi

echo "Medallero FATARCO en http://$DIRECCION  (Ctrl+C para apagarlo)"
exec "$PHP" -d upload_max_filesize=20M -d post_max_size=21M -d memory_limit=512M -S "$DIRECCION" -t public server.php
