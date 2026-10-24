<?php

use App\Medallero\ArchivoInvalido;
use App\Medallero\Informe;
use App\Medallero\Reporte;
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
