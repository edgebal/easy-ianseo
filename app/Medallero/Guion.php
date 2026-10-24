<?php

namespace App\Medallero;

// Guion para leer en la entrega de premios: un bloque por podio, en el orden de la ceremonia,
// con los pases de categoría primero y después Bronce, Plata, Oro.
//
// Orden: Jóvenes Promesas → Escuela → Sub / 50+ / Mayores. Dentro de cada grupo por arco
// (Longbow, Tradicional, Raso, Compuesto, Recurvo; los adaptados al lado de su arco) y de lo más
// fácil a lo más difícil (edad y distancia; en salón el blanco más grande primero).
//
// Los pases de una categoría van en el bloque de su podio propio; si el arquero además tiene
// medalla en ese podio, no se lo repite en los pases sino que se anota en su línea.

final class Guion
{
	public const GRUPOS = [1 => 'Jóvenes Promesas', 2 => 'Escuela', 3 => 'Sub, 50+ y Mayores'];

	private const ARCOS = ['L' => 1, 'T' => 2, 'B' => 3, 'C' => 4, 'R' => 5, 'Z' => 6, 'PI1' => 7, 'VI' => 8, 'AA1' => 9];

	// divisiones adaptadas que van junto a su arco convencional
	private const ARCO_ADAPTADO = ['BAA2' => 'B', 'CAA2' => 'C', 'RAA2' => 'R', 'PI2R' => 'R', 'PI2C' => 'C'];

	private const CLASES_FINALES = ['U15' => 1, 'U18' => 2, 'U21' => 3, '50' => 4];

	public static function armar(Informe $inf): array
	{
		$sala = $inf->torneo['modalidad_codigo'] == 'sala';

		$pasesPorCat = [];
		foreach ($inf->pasan() as $p) $pasesPorCat[$p['categoria']][] = $p;

		$bloques = [];
		foreach ($inf->medallero as $p) {
			$cat = $p['propia'];
			$enPodio = array_column($p['podio'], 'id');
			$pases = $cat ? ($pasesPorCat[$cat] ?? []) : [];
			$idsPase = array_column($pases, 'id');
			unset($pasesPorCat[$cat]);

			$medallas = [];
			foreach (array_reverse($p['podio']) as $a) { // Bronce → Plata → Oro
				$pase = $cat && in_array($a['id'], $idsPase, true) ? $pases[array_search($a['id'], $idsPase)] : null;
				// en eventos acoplados se aclara la categoría de quien no es de la anfitriona
				$otraCat = $a['categoria'] != $p['anfitriona'] ? $p['nombres_categorias'][$a['categoria']] : null;
				$medallas[] = $a + ['pase' => $pase, 'otra_categoria' => $otraCat];
			}
			$bloques[] = self::bloque($p['anfitriona'], $p['nombre'], self::subtitulo($p), $medallas,
				array_values(array_filter($pases, fn($x) => !in_array($x['id'], $enPodio, true))), $p['tipo'] == 'categoria', $sala);
		}

		// categorías con pases pero sin podio terminado (p. ej. eliminatoria pendiente)
		foreach ($pasesPorCat as $cat => $pases) {
			$bloques[] = self::bloque($cat, $pases[0]['nombre_categoria'], 'podio pendiente', [], $pases, true, $sala);
		}

		usort($bloques, fn($a, $b) => $a['orden'] <=> $b['orden']);

		$grupos = [];
		foreach ($bloques as $b) $grupos[$b['grupo']][] = $b;
		return $grupos;
	}

	private static function bloque(string $cat, string $nombre, ?string $subtitulo, array $medallas, array $pases, bool $propio, bool $sala): array
	{
		[$div, $clase] = explode('/', $cat, 2);
		$adaptado = isset(self::ARCO_ADAPTADO[$div]) ? 1 : 0;
		$arco = self::ARCOS[self::ARCO_ADAPTADO[$div] ?? $div] ?? 99;

		if (preg_match('/^U(1[358])([EI])(\d+)$/', $clase, $m)) {
			$grupo = 1;
			$nivel = [(int)$m[1], self::dificultad($m[2], (int)$m[3], $sala)];
		} elseif (preg_match('/^E(\d+)$/', $clase, $m)) {
			$grupo = 2;
			$nivel = [0, self::dificultad('E', (int)$m[1], $sala)];
		} else {
			$grupo = 3;
			$edad = preg_match('/^(U15|U18|U21|50)/', $clase, $m) ? self::CLASES_FINALES[$m[1]] : 5;
			$nivel = [$edad, str_ends_with($clase, 'W') ? 1 : 0];
		}

		// el acople entre pares va antes que el podio propio de la misma categoría
		return [
			'grupo' => $grupo,
			'orden' => [$grupo, $arco, ...$nivel, $adaptado, $propio ? 1 : 0, $nombre],
			'nombre' => $nombre,
			'subtitulo' => $subtitulo,
			'pases' => $pases,
			'destino' => $pases ? Informe::siguiente($pases[0]) : null,
			'medallas' => $medallas,
		];
	}

	// aire libre: más cerca es más fácil (I15 < I20 < E20 < E30...); salón: blanco más grande es más fácil
	// (E80 < E60 < E40); la Escuela adaptada E15 de salón (15 m) va primero
	private static function dificultad(string $letra, int $n, bool $sala): int
	{
		if (!$sala) return $n;
		if ($letra == 'I') return $n - 1000;
		return $n == 15 ? -999 : -$n;
	}

	private static function subtitulo(array $p): ?string
	{
		if ($p['tipo'] == 'categoria') return 'categoría propia';
		if ($p['propia'] === null) {
			$otras = array_diff_key($p['nombres_categorias'], [$p['anfitriona'] => 1]);
			return 'eliminatoria acoplada con ' . implode(', ', $otras);
		}
		return null;
	}
}
