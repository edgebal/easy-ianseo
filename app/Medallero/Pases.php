<?php

namespace App\Medallero;

// Pases de categoría de Escuela (art. 11, 13.9, 15.9, 18.6) y Jóvenes Promesas (art. 12, 14.6).
// Se lista a todos los arqueros de esas categorías con su puntaje y el umbral, marcando quién pasa.

final class Pases
{
	public function __construct(private IanseoExport $t, private array $normas) {}

	public function calcular(): array
	{
		$out = [];
		if (!$this->t->modalidad) return $out;
		$tablas = $this->normas[$this->t->modalidad];

		foreach ($this->t->porCategoria() as $cat => $ids) {
			[$div, $clase] = explode('/', $cat, 2);
			$divNorma = $this->normas['alias_division'][$div] ?? $div;

			if (preg_match('/^E\d+$/', $clase)) {
				$programa = 'Escuela';
				$escalera = $tablas['escuela'][$divNorma] ?? null;
				$umbral = $escalera[$clase] ?? null;
				$umbrales = $umbral === null ? null : [$umbral];
				$destinos = $this->normas['destino_final']['escuela'];
				$destinoFinal = $destinos[$divNorma] ?? $destinos['default'];
			} elseif (preg_match('/^(U1[358])([EI]\d+)$/', $clase, $m)) {
				$programa = 'Jóvenes Promesas';
				$escalera = $tablas['jp'][$divNorma][$m[1]] ?? null;
				$umbrales = $escalera[$m[2]] ?? null;
				$destinoFinal = $this->normas['destino_final'][$m[1]];
				$clase = $m[2];
			} else {
				continue;
			}

			$siguiente = null;
			if ($umbrales) {
				$pasos = array_keys($escalera);
				$i = array_search($clase, $pasos, true);
				$siguiente = $pasos[$i + 1] ?? $destinoFinal;
				if ($programa == 'Jóvenes Promesas' && isset($pasos[$i + 1]) && $escalera[$pasos[$i + 1]] === null) {
					$siguiente = $destinoFinal;
				}
			}

			foreach ($ids as $id) {
				$a = $this->t->arquero($id);
				$completo = $this->t->clasificacionCompleta($id);
				$fila = $a + [
					'programa' => $programa,
					'nombre_categoria' => $this->t->nombreCategoria($cat),
					'umbral_pase' => $umbrales[0] ?? null,
					'clasificacion_completa' => $completo,
					'pasa' => $completo && $umbrales && $a['puntaje'] >= $umbrales[0],
					'siguiente' => $siguiente,
					'nota' => null,
				];
				if ($umbrales && $siguiente === null) {
					$fila['nota'] = 'ya está en la distancia máxima de su clase: no hay categoría siguiente';
				}
				if (!$umbrales) {
					$fila['nota'] = $escalera !== null && array_key_exists($clase, $escalera)
						? 'distancia oficial WA: no tiene pase'
						: 'sin puntaje de pase en las normas para esta división/categoría';
				}
				if ($programa == 'Jóvenes Promesas' && $umbrales) {
					$fila['umbral_maestro'] = $umbrales[1];
					$fila['umbral_excelencia'] = $umbrales[2];
					$fila['logro'] = !$completo ? null
						: ($a['puntaje'] >= $umbrales[2] ? 'Excelencia' : ($a['puntaje'] >= $umbrales[1] ? 'Maestro' : null));
				}
				$out[] = $fila;
			}
		}
		// categorías en el orden del guion
		$sala = $this->t->modalidad == 'sala';
		usort($out, fn($a, $b) => [$b['pasa'], Guion::orden($a['categoria'], $sala), $b['puntaje']] <=> [$a['pasa'], Guion::orden($b['categoria'], $sala), $a['puntaje']]);
		return $out;
	}
}
