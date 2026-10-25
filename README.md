# Medallero FATARCO

Subís el export de un torneo de IANSEO (`.ianseo`) y te arma una página lista para imprimir (Ctrl+P) con:

- **Medallero** de los eventos finalizados: por eliminatorias o por clasificación, según cómo se armó el torneo en IANSEO, incluyendo los acoplamientos: la eliminatoria es el podio de la categoría que recibió a las otras (la de 3 o más) y cada acoplado tiene también podio en su categoría propia. En JP no hay doble medalla: las categorías que eliminaron acopladas se premian solo en la eliminatoria de la que las recibió (arts. 14.3 y 14.4), salvo que en el guion se elijan las *reglas antiguas de premiación JP*; las JP que no eliminan (por falta de arqueros o porque no se eliminó) se premian por clasificación. En salón, P.I.2 y AA2 sin eliminatoria propia tampoco (art. 18.5.1), salvo con la opción de podio para ellos. En Escuela (también la adaptada) con 2 o menos sin eliminatoria el podio no es obligatorio (art. 11.2): se entrega por defecto y se puede sacar desde el guion.
- **Pendientes**: eventos que todavía no terminaron (no entran al medallero).
- **Avisos de normas** (en el anexo, agrupados por tema; las decisiones son del jurado u organizador): eliminatorias con menos de 3, más de 2 byes (asumiendo torneo regular), encuentros perdidos sin tirar (posible abandono), juveniles no incluidos en Mayores, JP que podían eliminar o acoplarse, acoples mal hechos según las normas (otra distancia, división o sexo, Mayores acoplados a juveniles, categorías que no se incluyen en Mayores, categorías que podían eliminar solas, una categoría en dos eliminatorias), categorías sin evento, P.I.2/AA2 fuera de la convencional, adaptados de salón sin eliminatoria, categorías sin puntaje de pase, etc. Además, una tabla con el armado de cada evento (quién recibe, acopladas, eliminatoria y cómo se premia). Todo el anexo va en el mismo orden que el guion.
- **Control previo**: si el export todavía no tiene puntajes (antes de arrancar el torneo), la página muestra solo los avisos y el armado, con los byes y cortes calculados con los inscriptos.
- **Pases de categoría** de Escuela y Jóvenes Promesas (aire libre y salón), con Maestro/Excelencia en JP.

Los puntajes y reglas están en `resources/normas/fatarco-2026.php` (Normas FATARCO 2026). Para otra temporada se agrega otro archivo y se cambia `config/medallero.php` o `MEDALLERO_NORMAS` en el `.env`.

El archivo subido no se guarda: se procesa y se descarta. No usa base de datos.

## En la máquina de IANSEO (XAMPP, Windows)

No hace falta tocar Apache ni agregar un virtual host: usa el servidor embebido de PHP.

1. Copiar `medallero-fatarco.zip` (ver *Empaquetar*) y descomprimirlo en cualquier carpeta.
2. Doble clic en `iniciar.bat`. Busca `C:\ianseo\php\php.exe` o `C:\xampp\php\php.exe`; si PHP está en otro lado, antes de ejecutarlo: `set MEDALLERO_PHP=D:\ruta\php.exe`.
3. Se abre el navegador en <http://127.0.0.1:8090>. Para apagarlo, cerrar la ventana negra.

Requiere PHP 8.2 o superior (el de IANSEO/XAMPP 8.2 sirve).

## En un servidor (Linux)

```sh
composer install --no-dev --optimize-autoloader
./iniciar.sh 0.0.0.0:8090        # crea .env y APP_KEY la primera vez
```

`iniciar.sh` corre `php -S` con `upload_max_filesize=20M` y `post_max_size=21M`: los exports pesan entre 1 y 5 MB y el límite por defecto de PHP es 2 MB. Detrás de nginx/Apache, apuntar el sitio a `public/` y subir esos mismos límites en `php.ini`. `storage/` y `bootstrap/cache/` tienen que poder escribirse.

## Consola

```sh
php artisan medallero torneo.ianseo          # reporte en texto
php artisan medallero torneo.ianseo --json   # todo en JSON
php artisan medallero:pagina torneo.ianseo  # el guion tal como lo muestra la página (--tandas, --solo-tanda=1|2, --sin-anexo, --jp-reglas-antiguas, --sin-escuela-hasta-2, --adaptados-sala, --html)
```

## Empaquetar

```sh
./scripts/empaquetar.sh    # genera dist/medallero-fatarco.zip con vendor/ incluido (necesita Docker)
```

## Desarrollo

```sh
php artisan test
```

Los tests de punta a punta usan exports reales en `ianseo/exports/`; esa carpeta no va al repositorio (tiene datos de personas), así que sin ella esos tests se saltean.

`scripts/fetch-ianseo-update.php` baja la última versión de IANSEO (que no publica zip de las actualizaciones) para leer su código como referencia.
