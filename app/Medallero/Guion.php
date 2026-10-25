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
// medalla en ese podio, no se lo repite en los pases sino que se anota en su línea. En los podios
// opcionales (JP sin eliminatoria, Escuela con 2 o menos: se entregan o no según el selector) el medallista queda también en la lista de
// pases, marcado con_medalla: la página lo muestra ahí solo cuando esas medallas no se entregan.

final class Guion
{
	public const GRUPOS = [1 => 'Jóvenes Promesas', 2 => 'Escuela', 3 => 'Sub, 50+ y Mayores'];

	// podios opcionales (ver Medallero): checkbox del selector, si se entregan por defecto, texto del
	// selector y nota del bloque cuando no se entregan
	public const OPCIONALES = [
		'jp' => [
			'id' => 'jp-reglas-antiguas', 'defecto' => false,
			'texto' => 'Reglas antiguas de premiación JP: podio propio por clasificación también a las categorías que eliminaron acopladas, como en Mayores (en JP no hay doble medalla: se premia solo la eliminatoria, arts. 14.3 y 14.4)',
			'nota' => 'sin podio propio: en JP las categorías acopladas se premian solo en la eliminatoria (arts. 14.3 y 14.4)',
		],
		'escuela' => [
			'id' => 'escuela-hasta-2', 'defecto' => true,
			'texto' => 'Podio a Escuela con 2 o menos arqueros sin eliminatoria (no obligatorio, art. 11.2)',
			'nota' => 'sin podio: 2 o menos arqueros (art. 11.2)',
		],
		'adaptados' => [
			'id' => 'adaptados-sala', 'defecto' => false,
			'texto' => 'Podio por clasificación a P.I.2 y AA2 de salón sin eliminatoria propia (las normas premian solo a las que eliminan, art. 18.5.1)',
			'nota' => 'sin medallas: en salón solo se premia a las categorías adaptadas que eliminan (art. 18.5.1)',
		],
	];

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
				$pase = in_array($a['id'], $idsPase, true) ? $pases[array_search($a['id'], $idsPase)] : null;
				// en eventos acoplados se aclara (con el código) la categoría de quien no es de la que recibió
				$otraCat = $a['categoria'] != $cat ? str_replace('/', '', $a['categoria']) : null;
				$medallas[] = $a + ['pase' => $pase, 'otra_categoria' => $otraCat];
			}
			$conMedalla = fn($x) => in_array($x['id'], $enPodio, true);
			$sinRepetir = $p['opcional']
				? array_map(fn($x) => $x + ['con_medalla' => $conMedalla($x)], $pases)
				: array_values(array_filter($pases, fn($x) => !$conMedalla($x)));
			$bloques[] = self::bloque($cat, $p['nombre'], self::subtitulo($p), $medallas, $sinRepetir, $p['tipo'] == 'categoria', $sala)
				+ ['eliminatorias' => $p['eliminatorias'], 'categorias' => $p['categorias'], 'opcional' => $p['opcional'], 'acoplada' => (bool)$p['acoplada_en'], 'propia' => $cat];
		}

		// categorías con pases pero sin podio terminado (p. ej. eliminatoria pendiente)
		foreach ($pasesPorCat as $cat => $pases) {
			$bloques[] = self::bloque($cat, $pases[0]['nombre_categoria'], 'podio pendiente', [], $pases, true, $sala)
				+ ['eliminatorias' => false, 'categorias' => [$cat], 'opcional' => null, 'acoplada' => false, 'propia' => $cat];
		}

		usort($bloques, fn($a, $b) => $a['orden'] <=> $b['orden']);
		$bloques = self::acopladasAntesDeSuEliminatoria($bloques);
		self::paseConMedallaDeOtroPodio($bloques);

		$grupos = [];
		foreach ($bloques as $b) $grupos[$b['grupo']][] = $b;
		return $grupos;
	}

	// Tipos de opcional de los que depende que se vea un conjunto de bloques: si todos son opcionales sin
	// pases (solo medallas), el conjunto desaparece cuando ninguno de esos tipos se entrega.
	public static function soloOpcionales(array $bloques): array
	{
		foreach ($bloques as $b) if (!$b['opcional'] || $b['pases']) return [];
		return array_values(array_unique(array_column($bloques, 'opcional')));
	}

	// Entrega en dos tandas: [1 => grupos sin eliminatorias, 2 => grupos con eliminatorias].
	// Se decide por categoría: si una categoría forma parte de alguna eliminatoria (propia o acoplada,
	// terminada o pendiente), todos sus bloques van a la 2ª tanda, para que reciba todo junto.
	public static function tandas(Informe $inf): array
	{
		$eliminan = [];
		foreach (array_merge($inf->medallero, $inf->pendientes) as $p) {
			if ($p['eliminatorias']) foreach ($p['categorias'] as $c) $eliminan[$c] = true;
		}

		$tandas = [1 => [], 2 => []];
		foreach (self::armar($inf) as $g => $bloques) {
			foreach ($bloques as $b) {
				$segunda = $b['eliminatorias'] || array_filter($b['categorias'], fn($c) => isset($eliminan[$c]));
				$tandas[$segunda ? 2 : 1][$g][] = $b;
			}
		}
		return array_filter($tandas);
	}

	// Los podios propios de las categorías que se acoplaron se entregan justo antes de la eliminatoria
	// que las recibió (para cerrar con la eliminatoria), aunque por edad o arco irían en otro lugar.
	private static function acopladasAntesDeSuEliminatoria(array $bloques): array
	{
		$host = []; // categoría acoplada => índice del bloque de la eliminatoria que la recibió
		foreach ($bloques as $i => $b) {
			if (!$b['eliminatorias']) continue;
			foreach ($b['categorias'] as $c) if ($c != $b['propia']) $host[$c] ??= $i;
		}
		$antes = [];
		foreach ($bloques as $i => $b) {
			if (!$b['eliminatorias'] && isset($host[$b['propia']])) $antes[$host[$b['propia']]][] = $i;
		}
		$movidos = array_merge([], ...array_values($antes));
		$out = [];
		foreach ($bloques as $i => $b) {
			if (in_array($i, $movidos, true)) continue;
			foreach ($antes[$i] ?? [] as $j) $out[] = ['grupo' => $b['grupo']] + $bloques[$j];
			$out[] = $b;
		}
		return $out;
	}

	// Un acoplado que tiene medalla en la eliminatoria de otra categoría recibe el pase con esa medalla,
	// salvo que ya lo reciba con la de su podio propio. Si ese podio propio es opcional, en la otra medalla
	// el pase se anuncia solo cuando el opcional no se entrega (solo_sin_opcional: el tipo de opcional).
	private static function paseConMedallaDeOtroPodio(array &$bloques): void
	{
		$medallaDe = []; // id => [bloque, medalla] en podios que siempre se entregan, el primero de la ceremonia
		foreach ($bloques as $i => $b) {
			if ($b['opcional']) continue;
			foreach ($b['medallas'] as $j => $a) $medallaDe[$a['id']] ??= [$i, $j];
		}
		foreach ($bloques as $i => &$b) {
			$enPropio = array_column(array_filter($b['medallas'], fn($a) => $a['pase']), 'id');
			foreach ($b['pases'] as $k => $x) {
				[$oi, $oj] = $medallaDe[$x['id']] ?? [null, null];
				if ($oi === null || $oi == $i) continue;
				$propia = in_array($x['id'], $enPropio, true);
				if ($propia && !$b['opcional']) continue;
				$bloques[$oi]['medallas'][$oj]['pase'] = $x + ['solo_sin_opcional' => $propia ? $b['opcional'] : null];
				unset($b['pases'][$k]);
			}
			$b['pases'] = array_values($b['pases']);
		}
		unset($b);
	}

	private static function bloque(string $cat, string $nombre, ?string $subtitulo, array $medallas, array $pases, bool $propio, bool $sala): array
	{
		$orden = self::orden($cat, $sala, $propio, $nombre);
		return [
			'grupo' => $orden[0],
			'orden' => $orden,
			'nombre' => $nombre,
			'subtitulo' => $subtitulo,
			'pases' => $pases,
			'destino' => $pases ? Informe::siguiente($pases[0]) : null,
			'medallas' => $medallas,
		];
	}

	// Clave de orden de la ceremonia para una categoría (la usan también el anexo y el control del armado):
	// [grupo, arco, edad, dificultad, adaptado, propio, nombre]
	public static function orden(string $cat, bool $sala, bool $propio = false, string $nombre = ''): array
	{
		[$div, $clase] = explode('/', $cat, 2) + [1 => ''];
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
		return [$grupo, $arco, ...$nivel, $adaptado, $propio ? 1 : 0, $nombre];
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
		if ($p['acoplada_en']) return 'categoría propia (eliminó acoplada en ' . implode(', ', $p['acoplada_en']) . ')';
		if ($p['tipo'] == 'categoria') return 'categoría propia';
		if ($p['acople']) {
			$otras = array_diff_key($p['nombres_categorias'], [$p['propia'] => 1]);
			return 'eliminatoria acoplada con ' . implode(', ', $otras);
		}
		return null;
	}
}
