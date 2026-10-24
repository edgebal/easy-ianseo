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

    public function test_aire_libre_con_acoplamientos_y_pases(): void
    {
        $this->subir($this->export('FSJ-1810-FINAL-ORO-PLATA-BRONCE.ianseo'), ['avisos' => 1, 'pases' => 1])
            ->assertOk()
            ->assertSee('Final Provincial FETARCO')
            ->assertSee('acopla C/M + C/U18M + CAA2/M')
            ->assertSeeInOrder(['Compuesto Masculino', 'Gabas Sebastian', 'Della Penna Carlos', 'Amarillo Matías'])
            ->assertSee('falta definir la final')                  // RE40 pendiente
            ->assertSee('eliminatoria con 2 participante(s)')      // aviso 13.2.2.b
            ->assertSeeInOrder(['Pases de categoría', 'Salva Diego Zenon', 'E30']);
    }

    public function test_salon_avisa_acople_a_sub21_en_vez_de_mayores(): void
    {
        $this->subir($this->export('Indoor.ianseo'), ['avisos' => 1])
            ->assertOk()
            ->assertSee('Salón')
            ->assertSee('acoplado a RU21M en lugar de la clasificación de Mayores R/M');
    }

    public function test_secciones_opcionales_se_pueden_sacar(): void
    {
        $this->subir($this->export('ADASJ263_FATARCO.ianseo'))
            ->assertOk()
            ->assertSee('Medallero')
            ->assertDontSee('Pases de categoría')
            ->assertDontSee('Avisos de normas');
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
