<?php

// Router para el servidor embebido de PHP, para correr sin Apache ni virtual host:
//   php -S 127.0.0.1:8090 -t public server.php
// Es el de Laravel (Illuminate/Foundation/resources/server.php) con la carpeta public/ fija,
// así funciona desde cualquier directorio. Los archivos estáticos se sirven solo desde public/.

$publicPath = __DIR__ . '/public';

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

if ($uri !== '/' && file_exists($publicPath . $uri)) {
    return false;
}

require_once $publicPath . '/index.php';
