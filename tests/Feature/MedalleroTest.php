<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
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
            ->assertSeeInOrder(['Raso Escuela E20', 'Pase de categoría a E30', 'MULLER', 'Marcos',
                'Bronce', 'Pase (595)', 'SALVA', 'Diego Zenon', 'Plata', 'FERRECCIO', 'Oro', 'GARCÍA', 'Alejandra S.'])
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
        $this->assertStringContainsString('<input type="checkbox" id="jp-reglas-antiguas">', $html); // por defecto, las normas
        $this->assertStringContainsString('<input type="checkbox" id="escuela-hasta-2" checked>', $html);
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

    public function test_comando_pagina_muestra_lo_mismo_que_la_web(): void
    {
        $this->assertSame(0, Artisan::call('medallero:pagina', ['archivo' => $this->export('FSJ-1810-FINAL-ORO-PLATA-BRONCE.ianseo')]));
        $texto = Artisan::output();
        $this->assertStringContainsString('[BRONCE] [PASE (595)] SALVA Diego Zenon — Tira Palitos', $texto);
        // JP con menos de 3 sin eliminatoria: por las normas no hay medallas y el pase se anuncia solo
        $this->assertStringContainsString("Pase de categoría a I20\n\n  - [PASE (634) - MAESTRO] CASTILLO Josu — Cef 4", $texto);
        // con las reglas antiguas recibe el oro y el pase se anuncia con él
        Artisan::call('medallero:pagina', ['archivo' => $this->export('FSJ-1810-FINAL-ORO-PLATA-BRONCE.ianseo'), '--jp-reglas-antiguas' => true]);
        $antiguas = Artisan::output();
        $this->assertStringContainsString('[ORO] [PASE (634) - MAESTRO] CASTILLO Josu — Cef 4', $antiguas);
        $this->assertStringNotContainsString('Pase de categoría a I20', $antiguas);
    }

    public function test_el_pase_se_anuncia_una_sola_vez_y_con_la_medalla(): void
    {
        $ruta = $this->export('ADASJ263_FATARCO.ianseo');
        $pagina = function (array $opciones) use ($ruta) {
            Artisan::call('medallero:pagina', ['archivo' => $ruta] + $opciones);
            return explode('## Anexo', Artisan::output())[0];
        };
        // acoplado Sub-15 con plata en la eliminatoria de Sub-18: por las normas solo se premia la eliminatoria,
        // así que el pase va con la plata y no se lo anuncia aparte
        $normas = $pagina([]);
        $this->assertSame(1, substr_count($normas, 'SUAREZ Pablo León'));
        $this->assertStringContainsString('[PLATA] [PASE (603)] SUAREZ Pablo León — C. A. Tiro Federal San Justo - de RU15E20', $normas);
        // con las reglas antiguas también tiene oro en su podio propio, y el pase va con ese
        $antiguas = $pagina(['--jp-reglas-antiguas' => true]);
        $this->assertStringContainsString('[ORO] [PASE (603)] SUAREZ Pablo León — C. A. Tiro Federal San Justo', $antiguas);
        $this->assertSame(1, substr_count($antiguas, '[PASE (603)]'));
    }

    public function test_jp_sin_eliminatoria_no_lleva_medallas_y_escuela_con_2_o_menos_si(): void
    {
        $ruta = $this->export('ADASJ263_FATARCO.ianseo');
        $pagina = function (array $opciones) use ($ruta) {
            Artisan::call('medallero:pagina', ['archivo' => $ruta] + $opciones);
            return explode('## Anexo', Artisan::output())[0];
        };
        // por defecto: JP sin eliminatoria (aunque sean 3 o más) solo anuncia pases; Escuela con 2 o menos tiene podio
        $defecto = $pagina([]);
        $this->assertStringContainsString("Pase de categoría a I20\n\n  - [PASE (545)] CRISTALDO Sebastián", $defecto);
        $this->assertStringNotContainsString('SANCHEZ Angel', $defecto);
        $this->assertStringContainsString('[ORO] [PASE (624)] PATANE Maximiliano Jesús', $defecto);
        // invertidas las dos opciones
        $invertido = $pagina(['--jp-reglas-antiguas' => true, '--sin-escuela-hasta-2' => true]);
        $this->assertStringContainsString('[BRONCE] SANCHEZ Angel', $invertido);
        $this->assertStringContainsString('[ORO] [PASE (545)] CRISTALDO Sebastián', $invertido);
        $this->assertStringContainsString("sin podio: 2 o menos arqueros (art. 11.2)\n\nPase de categoría a E30\n\n  - [PASE (624)] PATANE Maximiliano Jesús", $invertido);
    }

    public function test_las_acopladas_se_premian_justo_antes_de_su_eliminatoria(): void
    {
        // con las reglas antiguas, para que estén también los podios propios de las JP acopladas
        Artisan::call('medallero:pagina', ['archivo' => $this->export('FSJ-1810-FINAL-ORO-PLATA-BRONCE.ianseo'), '--jp-reglas-antiguas' => true]);
        $titulos = array_values(array_filter(explode("\n", Artisan::output()), fn ($l) => str_starts_with($l, '### ')));
        $despues = fn ($t) => $titulos[array_search("### $t", $titulos) + 1];
        $this->assertSame('### Raso Escuela E20', $despues('Raso AA2 Escuela E20'));
        $this->assertSame('### Compuesto AA2 Masculino', $despues('Compuesto Masculino Sub-18'));
        $this->assertSame('### Compuesto Masculino', $despues('Compuesto AA2 Masculino'));
        $this->assertSame('### JP Recurvo Sub-18 E20', $despues('JP Recurvo Sub-15 E20'));
    }

    public function test_imprimir_una_sola_tanda(): void
    {
        $ruta = $this->export('FSJ-1810-FINAL-ORO-PLATA-BRONCE.ianseo');
        Artisan::call('medallero:pagina', ['archivo' => $ruta, '--tandas' => true, '--solo-tanda' => 1]);
        $uno = Artisan::output();
        $this->assertStringContainsString('1ª tanda', $uno);
        $this->assertStringNotContainsString('2ª tanda', $uno);
        $this->assertStringNotContainsString('## Anexo', $uno); // la 1ª tanda nunca lleva anexo
        Artisan::call('medallero:pagina', ['archivo' => $ruta, '--tandas' => true, '--solo-tanda' => 2, '--sin-anexo' => true]);
        $dos = Artisan::output();
        $this->assertStringNotContainsString('1ª tanda', $dos);
        $this->assertStringNotContainsString('## Anexo', $dos);
    }

    public function test_adaptados_de_salon_sin_eliminatoria_sin_medallas_salvo_con_la_opcion(): void
    {
        $ruta = $this->export('Indoor.ianseo');
        Artisan::call('medallero:pagina', ['archivo' => $ruta]);
        $normas = explode('## Anexo', Artisan::output())[0];
        $this->assertStringNotContainsString('TORALES FOCHESATTO Sofia', $normas);
        Artisan::call('medallero:pagina', ['archivo' => $ruta, '--adaptados-sala' => true]);
        $this->assertStringContainsString("### Compuesto AA2 Femenino\n\n  - [ORO] TORALES FOCHESATTO Sofia", Artisan::output());
    }

    public function test_antes_de_empezar_muestra_el_control_del_armado(): void
    {
        // el export con los puntajes y el cuadro vacíos, como antes de arrancar el torneo
        $g = unserialize(gzuncompress(file_get_contents($this->export('FSJ-1810-FINAL-ORO-PLATA-BRONCE.ianseo'))), ['allowed_classes' => false]);
        foreach ($g['Qualifications'] as &$q) {
            foreach ($q as $k => &$v) if (preg_match('/^Qu(Score|Gold|Xnine|Hits|D\dScore|D\dHits|D\dArrowstring|Rank|ClRank)$/', $k)) $v = is_numeric($v) ? 0 : '';
        }
        unset($q, $v);
        foreach ($g['Finals'] as &$f) $f = ['FinAthlete' => 0, 'FinScore' => 0, 'FinSetScore' => 0, 'FinWinLose' => 0, 'FinTie' => 0, 'FinArrowstring' => ''] + $f;
        unset($f);
        $ruta = tempnam(sys_get_temp_dir(), 'previo');
        file_put_contents($ruta, gzcompress(serialize($g)));

        Artisan::call('medallero:pagina', ['archivo' => $ruta]);
        $texto = Artisan::output();
        // las opciones de impresión no aplican: la página previa no tiene selector
        Artisan::call('medallero:pagina', ['archivo' => $ruta, '--sin-anexo' => true, '--tandas' => true, '--solo-tanda' => 1]);
        $this->assertSame($texto, Artisan::output());
        unlink($ruta);
        $this->assertStringContainsString('Control previo al torneo', $texto);
        $this->assertStringContainsString('## Control del armado', $texto);
        $this->assertStringNotContainsString("\n## Jóvenes Promesas", $texto); // sin guion
        $this->assertStringNotContainsString('clasificación incompleta', $texto);
        $this->assertStringNotContainsString('No alcanzan el pase', $texto);
        // byes y cortes calculados con los inscriptos, y el armado de cada evento
        $this->assertStringContainsString('BE30: la eliminatoria arranca en cuartos con 5 clasificados (3 byes)', $texto);
        $this->assertStringContainsString('BM: 5 inscriptos para un cuadro de 4 (desde semifinal): entran los 4 primeros', $texto);
        $this->assertStringContainsString('JP Raso Sub-18 E20 (BU18E20) | BU18E20 (1) ← BU15E20 (4) | desde semifinales | podio de BU18E20 por eliminatoria', $texto);
        $this->assertStringContainsString("#### Eliminatorias", $texto); // avisos agrupados por tema
        // el AA2 se avisa aunque la convencional no tenga arqueros
        $this->assertStringContainsString('Compuesto AA2 Femenino (1): no está incluido en la clasificación de Compuesto Femenino, sin inscriptos (CW)', $texto);
    }

    public function test_acoples_mal_hechos(): void
    {
        // el torneo armado con el Sub-21 masculino acoplado a Recurvo Femenino
        Artisan::call('medallero', ['archivo' => $this->export('ADASJ26X_TORNEO_MAL.ianseo')]);
        $texto = Artisan::output();
        $this->assertStringContainsString('Recurvo Masculino Sub-21 (2): está en más de una eliminatoria (RM, RW)', $texto);
        $this->assertStringContainsString('RW: acopla categorías de otro sexo (RU21M (2)) a RW', $texto);
        $this->assertStringContainsString('Recurvo Femenino Sub-21 (1): sin eliminatoria propia y no fue incluido en la de Mayores R/W', $texto);

        // y otros acoples agregados a mano al torneo bien armado
        $g = unserialize(gzuncompress(file_get_contents($this->export('ADASJ26X_TORNEO_4.ianseo'))), ['allowed_classes' => false]);
        $acoplar = function (string $evento, string $div, string $clase) use (&$g) {
            $g['EventClass'][] = ['EcCode' => $evento, 'EcClass' => $clase, 'EcDivision' => $div] + $g['EventClass'][0];
            $ind = $g['Individuals'][0];
            foreach ($g['Entries'] as $e) {
                if ($e['EnDivision'] == $div && $e['EnClass'] == $clase) $g['Individuals'][] = ['IndId' => $e['EnId'], 'IndEvent' => $evento] + $ind;
            }
        };
        $acoplar('RM', 'R', '50M');      // 50+ a Mayores: no previsto
        $acoplar('RU18M', 'R', 'M');     // Mayores a Sub-18: al revés
        $acoplar('RU18E30', 'R', 'U18E50'); // JP de otra distancia
        $acoplar('PI2RU21M', 'R', 'U21M');  // convencional al adaptado: al revés de 18.4.1
        $correr = function () use (&$g) {
            $ruta = tempnam(sys_get_temp_dir(), 'acoples');
            file_put_contents($ruta, gzcompress(serialize($g)));
            Artisan::call('medallero', ['archivo' => $ruta]);
            unlink($ruta);
            return Artisan::output();
        };
        $texto = $correr();
        $this->assertStringContainsString('PI2RU21M: acopla otra división (RU21M (2)) a PI2RU21M', $texto);
        $this->assertStringContainsString('RM: acople no previsto en las normas (R50M (5) a RM). Solo se incluyen en Mayores los Sub-21 de Recurvo', $texto);
        $this->assertStringContainsString('RU18M: acopla Mayores (RM (9)) a RU18M; es al revés', $texto);
        $this->assertStringContainsString('RU18E30: acopla categorías de otra distancia (RU18E50 (1)) a RU18E30; en JP', $texto);
        // los avisos y el armado van en el orden del guion: JP, Escuela y después el resto
        $this->assertLessThan(strpos($texto, 'RU18M: acopla Mayores'), strpos($texto, 'RU18E30: acopla'));
        $this->assertLessThan(strpos($texto, '  Recurvo Masculino (RM) |'), strpos($texto, '  Recurvo Escuela E60 (RE60) |'));

        // la que recibe sin inscriptos también se controla
        $g['Individuals'] = array_values(array_filter($g['Individuals'], fn($i) => $i['IndEvent'] != 'RM' || !in_array($i['IndId'], array_column(array_filter($g['Entries'], fn($e) => $e['EnDivision'] == 'R' && $e['EnClass'] == 'M'), 'EnId'))));
        $this->assertStringContainsString('RM: acople no previsto en las normas (R50M (5) a RM)', $correr());
    }

    public function test_comando_de_consola(): void
    {
        $this->artisan('medallero', ['archivo' => $this->export('FSJ-1810-FINAL-ORO-PLATA-BRONCE.ianseo')])
            ->expectsOutputToContain('MEDALLERO')
            ->assertExitCode(0);
    }
}
