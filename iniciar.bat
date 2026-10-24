@echo off
rem Medallero FATARCO: arranca la app con el PHP de IANSEO/XAMPP, sin Apache ni virtual host.
rem Uso: doble clic. Se abre el navegador en http://127.0.0.1:8090 (cerrar esta ventana para apagarla).

cd /d "%~dp0"

set "PHP=php"
if exist "C:\xampp\php\php.exe" set "PHP=C:\xampp\php\php.exe"
if exist "C:\ianseo\php\php.exe" set "PHP=C:\ianseo\php\php.exe"
if not "%MEDALLERO_PHP%"=="" set "PHP=%MEDALLERO_PHP%"

"%PHP%" -v >nul 2>&1
if errorlevel 1 (
    echo No se encontro PHP. Defini MEDALLERO_PHP con la ruta a php.exe, por ejemplo:
    echo   set MEDALLERO_PHP=D:\ianseo\php\php.exe
    pause
    exit /b 1
)

if not exist .env (
    copy .env.example .env >nul
    "%PHP%" artisan key:generate --force
)

set "PUERTO=8090"
echo Medallero FATARCO en http://127.0.0.1:%PUERTO%  (cerrar esta ventana para apagarlo)
start "" "http://127.0.0.1:%PUERTO%"
"%PHP%" -d upload_max_filesize=20M -d post_max_size=21M -d memory_limit=512M -S 127.0.0.1:%PUERTO% -t public server.php
