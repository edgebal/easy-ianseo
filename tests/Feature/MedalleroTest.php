<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MedalleroTest extends TestCase
{
    // exports reales de torneos (ianseo/ está en .gitignore: tienen datos de personas)
    private const EXPORTS = __DIR__ . '/../../ianseo/exports/';

    private function export(string $nombre): string
    {
        $ruta = self::EXPORTS . $nombre;
        if (!is_file($ruta)) {
            $this->markTestSkipped("Falta el export de prueba $nombre en ianseo/exports/");
        }
        return $ruta;
    }

    private function subir(string $ruta, array $opciones = [])
    {
        $archivo = new UploadedFile($ruta, basename($ruta), 'application/octet-stream', null, true);
        return $this->post('/informe', ['archivo' => $archivo] + $opciones);
    }

    public function test_muestra_el_formulario(): void
    {
        $this->get('/')->assertOk()->assertSee('Archivo .ianseo')->assertSee('Generar imprimible');
    }

    public function test_guion_de_entrega_aire_libre(): void
    {
        $this->subir($this->export('FSJ-1810-FINAL-ORO-PLATA-BRONCE.ianseo'))
            ->assertOk()
            ->assertSee('Final Provincial FETARCO')
            // grupos en el orden de la ceremonia
            ->assertSeeInOrder(['Jóvenes Promesas', 'Escuela', 'Sub, 50+ y Mayores', 'Anexo'])
            // bloque: pases primero, después Bronce, Plata, Oro; el medallista que pasa se anota
            ->assertSeeInOrder(['Raso Escuela E20', 'Pases de categoría (a E30)', 'Muller Marcos',
                'Bronce', 'Salva Diego Zenon', 'y pase de categoría', 'Plata', 'Ferreccio', 'Oro', 'García Alejandra'])
            // por arco dentro de Mayores: Longbow, Raso, Compuesto, Recurvo
            ->assertSeeInOrder(['Sub, 50+ y Mayores', 'Longbow Masculino', 'Raso Masculino', 'Compuesto Masculino', 'Recurvo Masculino'])
            // anexo
            ->assertSeeInOrder(['Anexo', 'falta definir la final', 'eliminatoria con 2 participante(s)', 'No alcanzan el pase']);
    }

    public function test_acople_entre_pares_tiene_bloque_propio_para_cada_categoria(): void
    {
        $this->subir($this->export('FSJ-1810-FINAL-ORO-PLATA-BRONCE.ianseo'))
            ->assertOk()
            ->assertSeeInOrder(['JP Raso Sub-15 E20', 'JP Raso  Sub-18 E20', 'eliminatoria acoplada con', 'Raso JP-Jóvenes Promesas Sub-18 E20', 'categoría propia']);
    }

    public function test_salon_avisa_acople_a_sub21_en_vez_de_mayores(): void
    {
        $this->subir($this->export('Indoor.ianseo'))
            ->assertOk()
            ->assertSee('Salón')
            ->assertSee('acoplado a RU21M en lugar de la clasificación de Mayores R/M');
    }

    public function test_archivo_que_no_es_ianseo_vuelve_con_error(): void
    {
        $this->from('/')
            ->post('/informe', ['archivo' => UploadedFile::fake()->createWithContent('torneo.ianseo', 'esto no es un export')])
            ->assertRedirect('/')
            ->assertSessionHasErrors(['archivo' => 'El archivo no es un export .ianseo válido.']);
    }

    public function test_sin_archivo_vuelve_con_error(): void
    {
        $this->from('/')->post('/informe')->assertRedirect('/')->assertSessionHasErrors('archivo');
    }

    public function test_comando_de_consola(): void
    {
        $this->artisan('medallero', ['archivo' => $this->export('FSJ-1810-FINAL-ORO-PLATA-BRONCE.ianseo')])
            ->expectsOutputToContain('MEDALLERO')
            ->assertExitCode(0);
    }
}
