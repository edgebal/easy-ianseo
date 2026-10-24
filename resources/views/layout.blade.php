<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('titulo', 'Medallero FATARCO')</title>
<style>
:root {
    --tinta: #1b1f24;
    --suave: #5b6470;
    --linea: #d8dce1;
    --fondo: #f4f5f7;
    --papel: #ffffff;
    --acento: #1d4f91;
    --oro: #c99a06;
    --plata: #8d969f;
    --bronce: #a8662c;
    --aviso: #9a5b00;
    --aviso-fondo: #fff6e5;
    --ok: #1d7a3e;
}
* { box-sizing: border-box; }
html { -webkit-text-size-adjust: 100%; }
body {
    margin: 0;
    background: var(--fondo);
    color: var(--tinta);
    font: 15px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}
h1, h2, h3 { line-height: 1.2; margin: 0; }
.contenedor { max-width: 1100px; margin: 0 auto; padding: 24px 16px 48px; }
.boton {
    display: inline-block;
    border: 1px solid var(--acento);
    background: var(--acento);
    color: #fff;
    border-radius: 6px;
    padding: 9px 16px;
    font: inherit;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
}
.boton.secundario { background: transparent; color: var(--acento); }
.boton:focus-visible { outline: 3px solid #9cc0f0; outline-offset: 2px; }
.error {
    background: #fdecec;
    border: 1px solid #f2b8b8;
    color: #8a1c1c;
    border-radius: 6px;
    padding: 10px 14px;
    margin: 0 0 16px;
}
@yield('estilos')
</style>
</head>
<body>
@yield('contenido')
</body>
</html>
