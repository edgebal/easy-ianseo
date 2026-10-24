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

    public function test_acople_entre_pares_se_premia_como_la_categoria_del_evento(): void
    {
        $html = $this->subir($this->export('FSJ-1810-FINAL-ORO-PLATA-BRONCE.ianseo'))->assertOk()->getContent();
        $una = substr($html, strpos($html, 'class="modo-una"'), strpos($html, 'class="modo-dos"') - strpos($html, 'class="modo-una"'));

        // BU18E20 se armó para Sub-18 (1 arquero) y recibió a 4 Sub-15: la eliminatoria es el podio de Sub-18,
        // y Sub-15 lleva su podio propio por clasificación
        $this->assertSame(1, substr_count($una, '<h3>JP Raso  Sub-18 E20</h3>'));
        $this->assertStringContainsString('eliminatoria acoplada con Raso JP-Jóvenes Promesas Sub-15 E20', $una);
        $this->assertStringContainsString('<h3>JP Raso Sub-15 E20</h3>', $una);
        $this->assertStringContainsString('categoría propia (eliminó acoplada en JP Raso  Sub-18 E20)', $una);
        $this->assertStringNotContainsString('Raso JP-Jóvenes Promesas Sub-18 E20</h3>', $una);
        $this->assertStringContainsString('id="jp-menos-de-3" checked', $html);
    }

    public function test_avisos_de_cuadro_y_walkover(): void
    {
        $this->subir($this->export('FSJ-1810-FINAL-ORO-PLATA-BRONCE.ianseo'))
            ->assertOk()
            ->assertSee('BE30: la eliminatoria arranca en cuartos con 3 byes')
            ->assertSee('BE20: Muller Marcos perdió sin tirar (semifinal, bronce)');
    }

    public function test_dos_tandas_por_categoria_con_las_acopladas_en_la_segunda(): void
    {
        $respuesta = $this->subir($this->export('FSJ-1810-FINAL-ORO-PLATA-BRONCE.ianseo'))->assertOk();
        $html = $respuesta->getContent();
        $dos = substr($html, strpos($html, 'class="modo-dos"'));
        $primera = substr($dos, 0, strpos($dos, '2ª tanda'));
        $segunda = substr($dos, strpos($dos, '2ª tanda'));

        // sin eliminatorias y sin acople: primera tanda
        $this->assertStringContainsString('Longbow Masculino', $primera);
        $this->assertStringContainsString('Compuesto AA2 Femenino', $primera);
        // con eliminatoria propia o acoplada a una (aunque su podio propio sea por clasificación): segunda
        foreach (['Compuesto Masculino Sub-18', 'Compuesto AA2 Masculino', 'JP Raso Sub-15 E20', 'Raso Escuela E20', 'P.I.2 Recurvo Escuela E40'] as $cat) {
            $this->assertStringNotContainsString($cat, $primera, "$cat no debería estar en la 1ª tanda");
            $this->assertStringContainsString($cat, $segunda);
        }
    }

    public function test_salon_avisa_acople_a_sub21_en_vez_de_mayores(): void
    {
        $this->subir($this->export('Indoor.ianseo'))
            ->assertOk()
            ->assertSee('Salón')
            ->assertSee('acoplado a RU21M en lugar de la clasificación de Mayores R/M')
            ->assertSee('Sub-15 E80 (3): sin eliminatoria')
            ->assertSee('Sub-18 E80 (2): menos de 3 y no se acopló')
            ->assertSee('Compuesto AA2 Femenino (1): sin eliminatoria propia; en salón solo se premia');
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
