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

		// Jóvenes Promesas con menos de 3: se acoplan a otra clase etaria de igual distancia (art. 14.3)
		$jpElim = [];
		foreach ($porCat as $cat => $ids) {
			if (!empty($elimDe[$cat]) && ($k = $this->claveJP($cat))) $jpElim[$k] = true;
		}
		foreach ($porCat as $cat => $ids) {
			$k = $this->claveJP($cat);
			if (!$k || count($ids) >= $minimo || !empty($elimDe[$cat]) || empty($jpElim[$k])) continue;
			$avisos[] = $this->t->nombreCategoria($cat) . ' (' . count($ids) . '): hubo eliminatoria JP de la misma distancia y esta categoría no se acopló (art. 14.3).';
		}
		return $avisos;
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
