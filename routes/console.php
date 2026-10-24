<?php

use App\Medallero\ArchivoInvalido;
use App\Medallero\Informe;
use App\Medallero\Reporte;
use App\Medallero\TextoDePagina;
use Illuminate\Support\Facades\Artisan;

Artisan::command('medallero {archivo : export .ianseo del torneo} {--json : salida en JSON}', function (string $archivo) {
    try {
        $informe = Informe::generar($archivo, config('medallero.normas'));
    } catch (ArchivoInvalido $e) {
        $this->error($e->getMessage());
        return 1;
    }
    $this->output->write($this->option('json')
        ? json_encode($informe->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
        : Reporte::texto($informe));
    return 0;
})->purpose('Medallero y pases de categoría de un torneo exportado de IANSEO, según normas FATARCO');

// La misma página que ve el navegador (misma vista Blade), en texto o HTML, para revisarla sin abrir la web.
Artisan::command('medallero:pagina {archivo : export .ianseo del torneo} {--tandas : entrega en dos tandas} {--jp-reglas-antiguas : podio propio a JP acopladas y con menos de 3 sin eliminatoria} {--solo-tanda= : con --tandas, solo la 1 o la 2} {--sin-anexo : sin el anexo} {--html : HTML tal cual lo sirve la web}', function (string $archivo) {
    try {
        $informe = Informe::generar($archivo, config('medallero.normas'));
    } catch (ArchivoInvalido $e) {
        $this->error($e->getMessage());
        return 1;
    }
    $html = view('informe', ['inf' => $informe, 'archivo' => basename($archivo)])->render();
    $this->output->write($this->option('html') ? $html : TextoDePagina::convertir($html, $this->option('tandas'), $this->option('jp-reglas-antiguas'),
        $this->option('solo-tanda') ? (int)$this->option('solo-tanda') : null, !$this->option('sin-anexo')));
    return 0;
})->purpose('Guion de entrega tal como se ve en la página (texto, o HTML con --html)');
