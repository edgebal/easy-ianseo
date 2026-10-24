<?php

namespace App\Http\Controllers;

use App\Medallero\ArchivoInvalido;
use App\Medallero\Informe;
use Illuminate\Http\Request;

class MedalleroController extends Controller
{
    public function formulario()
    {
        return view('formulario', ['maxMb' => intdiv(config('medallero.max_upload_kb'), 1024)]);
    }

    // El archivo se procesa en el momento y no se guarda: no quedan datos de arqueros en el servidor.
    public function informe(Request $request)
    {
        $request->validate([
            'archivo' => ['required', 'file', 'max:' . config('medallero.max_upload_kb')],
        ], [
            'archivo.required' => 'Elegí el archivo .ianseo del torneo.',
            'archivo.file' => 'No se pudo subir el archivo. Si es muy grande, revisá upload_max_filesize de PHP.',
            'archivo.uploaded' => 'No se pudo subir el archivo. Si es muy grande, revisá upload_max_filesize de PHP.',
            'archivo.max' => 'El archivo supera el máximo de :max KB.',
        ]);

        try {
            $informe = Informe::generar($request->file('archivo')->getRealPath(), config('medallero.normas'));
        } catch (ArchivoInvalido $e) {
            return back()->withErrors(['archivo' => $e->getMessage()]);
        }

        return view('informe', [
            'inf' => $informe,
            'archivo' => $request->file('archivo')->getClientOriginalName(),
            'incluir' => [
                'avisos' => $request->boolean('avisos'),
                'pases' => $request->boolean('pases'),
                'no_pasan' => $request->boolean('no_pasan'),
            ],
        ]);
    }
}
