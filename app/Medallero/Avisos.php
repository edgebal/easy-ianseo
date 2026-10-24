<?php

namespace App\Medallero;

// Controles del armado del torneo contra las normas FATARCO. Solo avisan: el medallero
// sigue saliendo de lo que efectivamente se tiró en IANSEO. Sirven también antes de empezar
// (con los inscriptos ya cargados), para corregir el armado a tiempo.

final class Avisos
{
	// los avisos salen agrupados por tema, en este orden
	public const TEMAS = ['Armado de eventos', 'Eliminatorias', 'Mayores y juveniles', 'Jóvenes Promesas', 'Adaptados', 'Pases de categoría'];

	private array $avisos = [];

	// $podios: los de Medallero, para saber de qué categoría es cada eliminatoria; $pases: los de Pases
	public function __construct(private IanseoExport $t, private array $normas, private array $podios = [], private array $pases = []) {}

	private function avisar(string $tema, string $texto): void
	{
		$this->avisos[$tema][] = $texto;
	}

	// [tema => [avisos]]
	public function calcular(): array
	{
		if (!$this->t->modalidad) {
			return ['Armado de eventos' => ["Tipo de torneo {$this->t->torneo['ToType']} ({$this->t->torneo['ToTypeName']}) no es Aire Libre ni Salón: no se controlan normas ni pases."]];
		}
		$this->controlar();
		return array_filter(array_merge(array_fill_keys(self::TEMAS, []), $this->avisos));
	}

	private function controlar(): void
	{
		$art = $this->t->modalidad == 'aire' ? '13.2.2' : '15.3.2';
		$minimo = $this->normas['minimo_eliminatoria'];
		$porCat = $this->t->porCategoria();

		// eventos con eliminatorias de cada categoría, y su "host" (categoría que coincide con el código)
		$elimDe = [];
		foreach ($this->t->eventos as $codigo => $ev) {
			$ids = array_keys($this->t->individuales[$codigo] ?? []);
			if (!$ids) continue;
			$n = count($ids);
			$conElim = (int)$ev['EvFinalFirstPhase'] > 0;
			$cats = array_unique(array_map([$this->t, 'categoria'], $ids));

			if ($conElim) {
				foreach ($cats as $c) $elimDe[$c][] = $codigo;
				if ($n < $minimo) $this->avisar('Eliminatorias', "$codigo: eliminatoria con $n participante(s); el mínimo es $minimo (art. $art.b).");
				$this->cuadro($codigo, $ev, $n, $art);
			}

			// eliminatoria obligatoria desde semis en R/C/B Mayores. El evento es "de Mayores" por su
			// categoría anfitriona (EvCode == Div.Clase); los juveniles o adaptados incluidos no cambian eso.
			$ob = $this->normas['eliminatoria_obligatoria'];
			$host = null;
			foreach ($this->t->eventoClases[$codigo] ?? [] as $c) if (str_replace('/', '', $c) == $codigo) $host = $c;
			$adulta = function ($c) use ($ob) {
				[$d, $cl] = explode('/', $c);
				return in_array($d, $ob['divisiones'], true) && in_array($cl, $ob['clases'], true);
			};
			$esMayor = $host ? $adulta($host) : !array_filter($cats, fn($c) => !$adulta($c));
			if ($esMayor && !$conElim && $n >= $minimo) {
				$this->avisar('Eliminatorias', "$codigo: $n participantes sin eliminatorias; en R/C/B Mayores son obligatorias desde semifinales (art. $art.a).");
			}
			$this->acoples($codigo, $host, array_count_values(array_map([$this->t, 'categoria'], $ids)));
		}

		// categorías con arqueros que no están en ningún evento individual: el podio sale de la clasificación
		// de la categoría, sin eliminatorias
		$enEventos = array_flip(array_merge([], ...array_values($this->t->eventoClases)));
		foreach ($porCat as $cat => $ids) {
			if (isset($enEventos[$cat])) continue;
			$this->avisar('Armado de eventos', $this->t->nombreCategoria($cat) . ' (' . count($ids) . '): no está en ningún evento individual de IANSEO; el podio sale de la clasificación de la categoría.');
		}

		// juveniles con menos de 3 que deberían ir a la clasificación de Mayores
		foreach ($this->normas['juveniles_a_mayores'][$this->t->modalidad] as $div => $edades) {
			foreach ($edades as $edad) {
				foreach (['M', 'W'] as $sexo) {
					$cat = "$div/$edad$sexo";
					$n = count($porCat[$cat] ?? []);
					if (!$n || $n >= $minimo) continue;
					// la norma pide incluirlos en la clasificación de Mayores: vale cualquier evento
					// (con o sin eliminatorias) que tenga a la categoría Mayores en su EventClass
					$mayor = "$div/$sexo";
					$enMayores = false;
					$otros = [];
					foreach ($this->t->eventoClases as $codigo => $clasesEv) {
						if (!isset($this->t->individuales[$codigo]) || !in_array($cat, $clasesEv, true)) continue;
						if (in_array($mayor, $clasesEv, true)) $enMayores = true;
						elseif (in_array($codigo, $elimDe[$cat] ?? [], true)) $otros[] = $codigo;
					}
					if ($enMayores) continue;
					$nombre = $this->t->nombreCategoria($cat);
					$this->avisar('Mayores y juveniles', $otros
						? "$nombre ($n): acoplado a " . implode(', ', $otros) . " en lugar de la clasificación de Mayores $mayor (art. $art.c)."
						: "$nombre ($n): sin eliminatoria propia y no fue incluido en la de Mayores $mayor (art. $art.c).");
				}
			}
		}

		// Jóvenes Promesas: se premia por eliminatorias (art. 14.4); con menos de 3 se acoplan a otra clase
		// etaria de igual distancia (art. 14.3). A veces no se elimina a propósito por falta de espacio: solo avisos.
		$jpElim = [];
		$jpDistancia = [];
		foreach ($porCat as $cat => $ids) {
			if (!($k = $this->claveJP($cat))) continue;
			$jpDistancia[$k][] = $cat;
			if (!empty($elimDe[$cat])) $jpElim[$k] = true;
		}
		foreach ($porCat as $cat => $ids) {
			$k = $this->claveJP($cat);
			if (!$k || !empty($elimDe[$cat])) continue;
			$n = count($ids);
			$nombre = $this->t->nombreCategoria($cat);
			$sin = 'sin medallas, salvo con las reglas antiguas de premiación JP';
			if ($n >= $minimo) {
				$this->avisar('Jóvenes Promesas', "$nombre ($n): sin eliminatoria; en Jóvenes Promesas se premia solo por eliminatorias (art. 14.4): $sin.");
			} elseif (!empty($jpElim[$k])) {
				$this->avisar('Jóvenes Promesas', "$nombre ($n): hubo eliminatoria JP de la misma distancia y esta categoría no se acopló (art. 14.3): $sin.");
			} elseif (count($jpDistancia[$k]) > 1) {
				$otras = array_map([$this->t, 'nombreCategoria'], array_diff($jpDistancia[$k], [$cat]));
				$this->avisar('Jóvenes Promesas', "$nombre ($n): menos de $minimo y no se acopló a la otra clase de igual distancia (" . implode(', ', $otras) . ") para eliminar (art. 14.3): $sin.");
			} else {
				$this->avisar('Jóvenes Promesas', "$nombre ($n): menos de $minimo y no hay otra clase JP de igual distancia con quien acoplar: sin eliminatoria, $sin (art. 14.4).");
			}
		}

		// P.I.2 / AA2 (fuera de JP y Escuela): además de su categoría, van a la clasificación de la convencional
		// (18.4.1 / 18.5.1); en salón "sólo se premiará a las categorías que eliminen" (18.5.1)
		$artAd = $this->t->modalidad == 'aire' ? '18.4.1' : '18.5.1';
		foreach ($porCat as $cat => $ids) {
			[$div, $cl] = explode('/', $cat);
			$conv = $this->normas['alias_division'][$div] ?? null;
			if (!$conv || Medallero::esJP($cat) || Medallero::esEscuela($cat)) continue;
			$nombre = $this->t->nombreCategoria($cat) . ' (' . count($ids) . ')';
			// también cuando la convencional no tiene arqueros (o ni siquiera evento)
			$nombreConv = $this->t->nombreCategoria("$conv/$cl") . (isset($porCat["$conv/$cl"]) ? '' : ', sin inscriptos');
			$eventosConv = array_keys(array_filter($this->t->eventoClases, fn($c) => in_array("$conv/$cl", $c, true)));
			if (!$eventosConv) {
				$this->avisar('Adaptados', "$nombre: no hay evento de $nombreConv para incluirlo; los P.I.2 y AA2 tiran también en la convencional (art. $artAd).");
			} elseif (!array_filter($eventosConv, fn($e) => in_array($cat, $this->t->eventoClases[$e], true))) {
				$this->avisar('Adaptados', "$nombre: no está incluido en la clasificación de $nombreConv"
					. ' (' . implode(', ', $eventosConv) . "); los P.I.2 y AA2 tiran también en la convencional (art. $artAd).");
			}
			if ($this->t->modalidad == 'sala' && !array_filter($this->podios, fn($p) => $p['eliminatorias'] && $p['propia'] == $cat)) {
				$this->avisar('Adaptados', "$nombre: sin eliminatoria propia; en salón solo se premia a las categorías adaptadas que eliminan (art. 18.5.1): sin medallas, salvo con la opción de podio a P.I.2 y AA2 de salón.");
			}
		}

		// Escuela y JP sin puntaje de pase en las normas: no se puede calcular el pase
		$sinUmbral = [];
		foreach ($this->pases as $x) {
			if ($x['umbral_pase'] === null && str_starts_with($x['nota'] ?? '', 'sin puntaje')) $sinUmbral[$x['nombre_categoria']] = ($sinUmbral[$x['nombre_categoria']] ?? 0) + 1;
		}
		foreach ($sinUmbral as $nombre => $n) {
			$this->avisar('Pases de categoría', "$nombre ($n): las normas no tienen puntaje de pase para esta división y distancia; no se calcula el pase.");
		}
	}

	// Lo que no se ve en el medallero de un acople: si se eligió a mano la categoría que recibe (ninguna coincide
	// con el código del evento), si junta distancias distintas (en JP el acople es "de igual distancia", 14.3)
	// o divisiones distintas (fuera de los adaptados que tiran en la convencional).
	private function acoples(string $codigo, ?string $host, array $cuenta): void
	{
		$cats = array_keys($cuenta);
		if (!$host && count($cats) == 1) {
			$this->avisar('Armado de eventos', "$codigo: el código del evento no es el de su categoría (" . str_replace('/', '', $cats[0]) . '); conviene usar el código de la categoría.');
		}
		if (count($cats) < 2) return;
		$lista = fn(array $cs) => implode(', ', array_map(fn($c) => str_replace('/', '', $c) . " ({$cuenta[$c]})", $cs));
		if (!$host) {
			$receptora = current(array_filter($this->podios, fn($p) => $p['tipo'] == 'evento' && $p['codigo'] == $codigo))['propia'] ?? $cats[0];
			$this->avisar('Armado de eventos', "$codigo: ninguna categoría coincide con el código del evento; se toma " . str_replace('/', '', $receptora)
				. ' como la que recibió a las demás (' . $lista(array_diff($cats, [$receptora])) . '). Conviene que el código sea el de la categoría que recibe.');
			$host = $receptora;
		}
		[$divH] = explode('/', $host);
		$distH = $this->distancia($host);
		$alias = $this->normas['alias_division'];
		$otraDistancia = $otraDivision = [];
		foreach (array_diff($cats, [$host]) as $c) {
			[$div] = explode('/', $c);
			if ($div != $divH && ($alias[$div] ?? null) != $divH) $otraDivision[] = $c;
			$d = $this->distancia($c);
			if ($distH !== null && $d !== null && $d != $distH) $otraDistancia[] = $c;
		}
		if ($otraDistancia) {
			$this->avisar('Armado de eventos', "$codigo: acopla categorías de otra distancia (" . $lista($otraDistancia) . ') a ' . str_replace('/', '', $host)
				. (Medallero::esJP($host) ? '; en JP el acople es a la categoría de igual distancia (art. 14.3).' : '.'));
		}
		if ($otraDivision) {
			$this->avisar('Armado de eventos', "$codigo: acopla otra división (" . $lista($otraDivision) . ') a ' . str_replace('/', '', $host) . '.');
		}
	}

	// distancia de una categoría de Escuela o JP según su código (en salón, con el blanco), o null
	private function distancia(string $cat): ?string
	{
		$cl = explode('/', $cat)[1] ?? '';
		if (!preg_match('/^(?:U1[358])?([EI])(\d+)$/', $cl, $m)) return null;
		return $this->t->modalidad == 'aire' ? $m[2] : $m[1] . $m[2];
	}

	// Byes de la primera fase y encuentros ganados por ausencia del rival. Se asume torneo regular:
	// desde semifinales, y si se arranca antes, con un máximo de dos byes (art. 13.2.2.a / 15.3.2.a).
	private function cuadro(string $codigo, array $ev, int $inscriptos, string $art): void
	{
		$f = $this->t->finales[$codigo] ?? [];
		$fase = 2 ** (int)ceil(log(max((int)$ev['EvFinalFirstPhase'], 1), 2)); // 24/48 de IANSEO: la llave de 32/64
		$lugares = 2 * $fase;
		// bye: encuentro de la primera fase con un solo arquero (los vacíos de los dos lados no cuentan)
		$byes = 0;
		$armado = false;
		for ($m = $lugares; $m < 2 * $lugares; $m += 2) {
			$a = (bool)(int)($f[$m]['FinAthlete'] ?? 0);
			$b = (bool)(int)($f[$m + 1]['FinAthlete'] ?? 0);
			$armado = $armado || $a || $b;
			if ($a != $b) $byes++;
		}
		// cuadro todavía sin armar (antes de terminar la clasificación): lo que va a pasar con los inscriptos. Entran los
		// que fija el evento (EvNumQualified desde el puesto EvFirstQualified) o los lugares del cuadro; con la siembra
		// 1 contra el último, 2 contra el anteúltimo..., los byes son los cruces con un solo arquero
		$desde = max(1, (int)$ev['EvFirstQualified']);
		$disponibles = max(0, $inscriptos - $desde + 1);
		$entran = min((int)$ev['EvNumQualified'] ?: $lugares, $lugares, $disponibles);
		if (!$armado) $byes = min($entran, $lugares - $entran);
		if ($fase > 2 && $byes > 2) {
			$this->avisar('Eliminatorias', "$codigo: la eliminatoria arranca en " . self::fase($lugares) . ' con ' . ($armado ? "$byes byes" : "$entran clasificados ($byes byes)")
				. "; en torneos regulares se puede empezar antes de semifinales con un máximo de 2 byes (art. $art.a).");
		}
		if (!$armado && $disponibles > $entran) {
			$this->avisar('Eliminatorias', "$codigo: $inscriptos inscriptos para un cuadro de $lugares (desde " . self::fase($lugares) . '): entran '
				. ($desde > 1 ? "$entran desde el puesto $desde" : "los $entran primeros") . ' de la clasificación.');
		}

		// rival presente pero el otro ganó por bye, sin IRM ni flechas cargadas: no se presentó. Sin causa justificada
		// es abandono, con puntaje 0 para cualquier ranking (art. 13.2.2.d / 15.3.2.d): sin medalla ni pase. Lo decide el jurado.
		// (IANSEO también da el bye cuando el rival tiene DNF/DSQ: eso ya lo marca el IRM, no se avisa acá.)
		$ausente = [];
		foreach ($f as $m => $a) {
			if ($m % 2) continue;
			$b = $f[$m + 1] ?? null;
			if (!$b || !(int)$a['FinAthlete'] || !(int)$b['FinAthlete']) continue;
			foreach ([[$a, $b], [$b, $a]] as [$gana, $pierde]) {
				if ((int)$gana['FinTie'] != 2 || (int)$gana['FinWinLose'] != 1 || (int)$pierde['FinIrmType']) continue;
				if ((int)$pierde['FinScore'] || (int)$pierde['FinSetScore'] || trim($pierde['FinArrowstring'] ?? '') !== '') continue;
				$ausente[(int)$pierde['FinAthlete']][] = self::fase($m);
			}
		}
		foreach ($ausente as $id => $fases) {
			$this->avisar('Eliminatorias', "$codigo: " . $this->t->arquero($id)['nombre'] . ' perdió sin tirar (' . implode(', ', $fases) . '). Si no se presentó sin causa justificada es abandono: '
				. "puntaje de clasificación 0 para cualquier ranking, sin medalla ni pase de categoría (art. $art.d). Lo decide el jurado.");
		}
	}

	private static function fase(int $matchNo): string
	{
		return match (true) {
			$matchNo < 2 => 'final', $matchNo < 4 => 'bronce', $matchNo < 8 => 'semifinal',
			$matchNo < 16 => 'cuartos', $matchNo < 32 => 'octavos', default => 'ronda previa',
		};
	}

	// división + distancia de una categoría JP, para encontrar las de "igual distancia" de otra edad
	private function claveJP(string $cat): ?string
	{
		[$div, $cl] = explode('/', $cat);
		if (!preg_match('/^U1[358]([EI])(\d+)$/', $cl, $m)) return null;
		// en aire libre el número es la distancia (I20 = E20 = 20 m); en sala E = tamaño de blanco a 18 m, I = distancia
		return $div . '|' . ($this->t->modalidad == 'aire' ? $m[2] : $m[1] . $m[2]);
	}
}
