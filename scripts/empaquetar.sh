#!/bin/sh
# Arma dist/medallero-fatarco.zip listo para copiar a una máquina sin composer (p. ej. la de IANSEO con XAMPP):
# descomprimir y doble clic en iniciar.bat. Necesita Docker; las dependencias se resuelven para PHP 8.2.
set -e
cd "$(dirname "$0")/.."

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
APP="$TMP/medallero-fatarco"
mkdir -p "$APP" dist

# solo lo que la app necesita para correr (sin tests, sin exports de prueba, sin .env)
cp -a app bootstrap config public resources routes artisan composer.json composer.lock server.php \
      iniciar.bat iniciar.sh .env.example "$APP"/
mkdir -p "$APP/storage/app" "$APP/storage/framework/cache/data" "$APP/storage/framework/sessions" \
         "$APP/storage/framework/views" "$APP/storage/logs"
rm -f "$APP"/bootstrap/cache/*.php

docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/c -v "$APP:/app" -w /app composer:latest \
    install --no-dev --optimize-autoloader --no-interaction --no-scripts --quiet

rm -f dist/medallero-fatarco.zip
docker run --rm -e DUENO="$(id -u):$(id -g)" -v "$TMP:/src" -v "$PWD/dist:/dist" -w /src alpine \
    sh -c 'apk add -q zip >/dev/null && zip -qr /dist/medallero-fatarco.zip medallero-fatarco && chown "$DUENO" /dist/medallero-fatarco.zip'
echo "Listo: dist/medallero-fatarco.zip ($(du -h dist/medallero-fatarco.zip | cut -f1))"
