<?php

namespace App\Medallero;

// Controles del armado del torneo contra las normas FATARCO. Solo avisan: el medallero
// sigue saliendo de lo que efectivamente se tiró en IANSEO.

final class Avisos
{
	public function __construct(private IanseoExport $t, private array $normas) {}

	public function calcular(): array
	{
		$avisos = [];
		if (!$this->t->modalidad) {
			$avisos[] = "Tipo de torneo {$this->t->torneo['ToType']} ({$this->t->torneo['ToTypeName']}) no es Aire Libre ni Salón: no se controlan normas ni pases.";
			return $avisos;
		}
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
				if ($n < $minimo) $avisos[] = "$codigo: eliminatoria con $n participante(s); el mínimo es $minimo (art. $art.b).";
				array_push($avisos, ...$this->cuadro($codigo, (int)$ev['EvFinalFirstPhase'], $art));
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
				$avisos[] = "$codigo: $n participantes sin eliminatorias; en R/C/B Mayores son obligatorias desde semifinales (art. $art.a).";
			}
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
					$avisos[] = $otros
						? "$nombre ($n): acoplado a " . implode(', ', $otros) . " en lugar de la clasificación de Mayores $mayor (art. $art.c)."
						: "$nombre ($n): sin eliminatoria propia y no fue incluido en la de Mayores $mayor (art. $art.c).";
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
			if ($n >= $minimo) {
				$avisos[] = "$nombre ($n): sin eliminatoria; en Jóvenes Promesas se premia por eliminatorias (art. 14.4). Se muestra el podio por clasificación.";
			} elseif (!empty($jpElim[$k])) {
				$avisos[] = "$nombre ($n): hubo eliminatoria JP de la misma distancia y esta categoría no se acopló (art. 14.3).";
			} elseif (count($jpDistancia[$k]) > 1) {
				$otras = array_map([$this->t, 'nombreCategoria'], array_diff($jpDistancia[$k], [$cat]));
				$avisos[] = "$nombre ($n): menos de $minimo y no se acopló a la otra clase de igual distancia (" . implode(', ', $otras) . ') para eliminar (art. 14.3).';
			}
		}

		// salón: los adaptados P.I.2 / AA2 entran a la convencional y "sólo se premiará a las categorías que eliminen" (18.5.1)
		if ($this->t->modalidad == 'sala') {
			foreach ($porCat as $cat => $ids) {
				[$div] = explode('/', $cat);
				if (!isset($this->normas['alias_division'][$div])) continue;
				if (array_filter($elimDe[$cat] ?? [], fn($cod) => str_replace('/', '', $cat) == $cod)) continue;
				$avisos[] = $this->t->nombreCategoria($cat) . ' (' . count($ids) . '): sin eliminatoria propia; en salón solo se premia a las categorías adaptadas que eliminan (art. 18.5.1). Decide el organizador.';
			}
		}
		return $avisos;
	}

	// Byes de la primera fase y encuentros ganados por ausencia del rival. Se asume torneo regular:
	// desde semifinales, y si se arranca antes, con un máximo de dos byes (art. 13.2.2.a / 15.3.2.a).
	private function cuadro(string $codigo, int $fase, string $art): array
	{
		$avisos = [];
		$f = $this->t->finales[$codigo] ?? [];
		$fase = 2 ** (int)ceil(log(max($fase, 1), 2)); // 24/48 de IANSEO: la llave de 32/64
		$ocupados = 0;
		for ($m = 2 * $fase; $m < 4 * $fase; $m++) if ((int)($f[$m]['FinAthlete'] ?? 0)) $ocupados++;
		$byes = 2 * $fase - $ocupados;
		if ($fase > 2 && $ocupados && $byes > 2) {
			$avisos[] = "$codigo: la eliminatoria arranca en " . self::fase(2 * $fase) . " con $byes byes; en torneos regulares se puede empezar antes de semifinales con un máximo de 2 byes (art. $art.a).";
		}

		// rival presente pero el otro ganó por bye: no se presentó. Sin causa justificada es abandono,
		// con puntaje 0 para cualquier ranking (art. 13.2.2.d / 15.3.2.d): sin medalla ni pase. Lo decide el jurado.
		$ausente = [];
		foreach ($f as $m => $a) {
			if ($m % 2) continue;
			$b = $f[$m + 1] ?? null;
			if (!$b || !(int)$a['FinAthlete'] || !(int)$b['FinAthlete']) continue;
			foreach ([[$a, $b], [$b, $a]] as [$gana, $pierde]) {
				if ((int)$gana['FinTie'] == 2 && (int)$gana['FinWinLose'] == 1) $ausente[(int)$pierde['FinAthlete']][] = self::fase($m);
			}
		}
		foreach ($ausente as $id => $fases) {
			$avisos[] = "$codigo: " . $this->t->arquero($id)['nombre'] . ' perdió sin tirar (' . implode(', ', $fases) . '). Si no se presentó sin causa justificada es abandono: '
				. "puntaje de clasificación 0 para cualquier ranking, sin medalla ni pase de categoría (art. $art.d). Lo decide el jurado.";
		}
		return $avisos;
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
