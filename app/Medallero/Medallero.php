<?php

namespace App\Medallero;

// Podios individuales a partir de cómo se armó el torneo en IANSEO:
//  - un podio por evento con participantes (con eliminatorias: por los encuentros; sin ellas: por clasificación)
//  - un podio extra por clasificación para las categorías acopladas que no tienen evento propio
//    (art. 13.2.2.c / 15.3.2.c: "podrán obtener medallas en ambas categorías")
//
// Cada evento es el podio de la categoría que recibió a las demás, que es la que se eligió al armar el
// torneo en IANSEO: la anfitriona (EvCode == División.Clase). Solo si ninguna coincide con el código se
// toma la de 3 o más (o la de más arqueros). Un acople entre pares es uno en el que alguna invitada no es una
// inclusión reglamentaria (juveniles en Mayores, 13.2.2.c / 15.3.2.c; adaptados en la convencional, 18.5.1).
// Las categorías acopladas llevan además su podio propio por clasificación. En JP ese podio (y el de una
// categoría con menos de 3 sin eliminatoria) queda marcado como opcional: los arts. 14.3 y 14.4 solo premian
// las eliminatorias; se entrega únicamente con las reglas antiguas de premiación JP.

final class Medallero
{
	public function __construct(private IanseoExport $t, private array $normas) {}

	public function calcular(): array
	{
		$minimo = $this->normas['minimo_eliminatoria'];
		$podios = [];
		foreach ($this->t->eventos as $codigo => $ev) {
			$ids = array_keys($this->t->individuales[$codigo] ?? []);
			if (!$ids) continue;
			$cuenta = array_count_values(array_map([$this->t, 'categoria'], $ids));
			ksort($cuenta);
			$categorias = array_keys($cuenta);
			$anfitriona = null; // categoría cuyo código coincide con el del evento
			foreach ($this->t->eventoClases[$codigo] ?? $categorias as $c) {
				if (str_replace('/', '', $c) == $codigo) $anfitriona = $c;
			}
			[$propia, $acople] = $this->receptora($cuenta, $anfitriona, $minimo);
			$eliminatorias = (int)$ev['EvFinalFirstPhase'] > 0;
			$podio = $eliminatorias
				? $this->porEliminatoria($codigo, $ids)
				: $this->porClasificacion($ids, fn($id) => (int)$this->t->individuales[$codigo][$id]['IndRank']);
			$nombre = trim($ev['EvEventName']);
			$podios[] = [
				'tipo' => 'evento',
				'codigo' => $codigo,
				'nombre' => $nombre,
				'categorias' => $categorias,
				'nombres_categorias' => array_combine($categorias, array_map([$this->t, 'nombreCategoria'], $categorias)),
				'propia' => $propia,
				'acople' => $acople,
				'participantes' => count($ids),
				'eliminatorias' => $eliminatorias,
			] + $podio;
		}

		// la categoría que recibió a otras en una eliminatoria se premia por esa eliminatoria:
		// si además tiene un evento propio sin eliminatorias, ese podio no se entrega
		$conElim = [];
		$huesped = []; // categoría => nombres de las eliminatorias a las que se acopló
		foreach ($podios as $p) {
			if (!$p['eliminatorias']) continue;
			$conElim[$p['propia']] = true;
			foreach ($p['categorias'] as $c) if ($c != $p['propia']) $huesped[$c][] = $p['nombre'];
		}
		$podios = array_values(array_filter($podios, fn($p) => $p['eliminatorias'] || !isset($conElim[$p['propia']])));
		$cubiertas = array_flip(array_column($podios, 'propia'));

		$porCategoria = $this->t->porCategoria();
		foreach ($porCategoria as $cat => $ids) {
			if (isset($cubiertas[$cat])) continue;
			$podios[] = [
				'tipo' => 'categoria',
				'codigo' => str_replace('/', '', $cat),
				'nombre' => $this->t->nombreCategoria($cat),
				'categorias' => [$cat],
				'nombres_categorias' => [$cat => $this->t->nombreCategoria($cat)],
				'propia' => $cat,
				'acople' => false,
				'participantes' => count($ids),
				'eliminatorias' => false,
			] + $this->porClasificacion($ids, fn($id) => (int)$this->t->quali[$id]['QuClRank']);
		}

		foreach ($podios as &$p) {
			$p['acoplada_en'] = $p['eliminatorias'] ? [] : array_values(array_unique($huesped[$p['propia']] ?? []));
			// JP acoplada o con menos de 3 que no eliminó: los arts. 14.3 y 14.4 no la premian por separado
			$p['opcional'] = !$p['eliminatorias'] && self::esJP($p['propia'])
				&& ($p['acoplada_en'] || count($porCategoria[$p['propia']] ?? []) < $minimo);
		}
		unset($p);
		return $podios;
	}

	public static function esJP(string $cat): bool
	{
		return (bool)preg_match('/^U1[358][EI]\d+$/', explode('/', $cat, 2)[1] ?? '');
	}

	// [categoría que recibió a las otras, si es un acople entre pares]
	private function receptora(array $cuenta, ?string $anfitriona, int $minimo): array
	{
		if ($anfitriona) {
			$invitadas = array_diff(array_keys($cuenta), [$anfitriona]);
			return [$anfitriona, (bool)array_filter($invitadas, fn($c) => !$this->esInclusion($c, $anfitriona))];
		}
		if (count($cuenta) == 1) return [array_key_first($cuenta), false];
		$grandes = array_keys(array_filter($cuenta, fn($n) => $n >= $minimo));
		if (count($grandes) == 1) return [$grandes[0], true];
		return [array_search(max($cuenta), $cuenta), true];
	}

	// la categoría invitada entra al evento de la anfitriona por norma, no por falta de cupo
	private function esInclusion(string $invitada, string $host): bool
	{
		[$divI, $clI] = explode('/', $invitada);
		[$divH, $clH] = explode('/', $host);
		$alias = $this->normas['alias_division'];
		if ($divI != $divH) return $clI == $clH && ($alias[$divI] ?? null) == $divH;
		return in_array($clH, ['M', 'W'], true) && preg_match('/^U(18|21)' . $clH . '$/', $clI);
	}

	private function porClasificacion(array $ids, callable $rankIanseo): array
	{
		$incompletos = array_filter($ids, fn($id) => !$this->t->clasificacionCompleta($id));
		if ($incompletos) {
			return $this->pendiente('clasificación incompleta: ' . implode(', ', array_map(fn($id) => $this->t->arquero($id)['nombre'], $incompletos)));
		}

		$avisos = [];
		$filas = array_map(fn($id) => ['rank' => $rankIanseo($id)] + $this->t->arquero($id), $ids);
		$porPuntaje = fn($a, $b) => [$b['puntaje'], $b['oros'], $b['xs']] <=> [$a['puntaje'], $a['oros'], $a['xs']];

		if (in_array(0, array_column($filas, 'rank'), true)) {
			// IANSEO no calculó el ranking: lo armamos por puntaje, oros y X
			usort($filas, $porPuntaje);
			$prev = null;
			foreach ($filas as $i => &$f) {
				$f['rank'] = ($prev && $porPuntaje($prev, $f) == 0) ? $prev['rank'] : $i + 1;
				$prev = $f;
			}
			unset($f);
			$avisos[] = 'IANSEO no tenía el ranking calculado; se ordenó por puntaje/oros/X';
		} else {
			usort($filas, fn($a, $b) => $a['rank'] <=> $b['rank'] ?: $porPuntaje($a, $b));
			for ($i = 1; $i < count($filas); $i++) {
				if ($filas[$i]['puntaje'] > $filas[$i - 1]['puntaje']) {
					$avisos[] = "ranking de IANSEO no coincide con los puntajes ({$filas[$i]['nombre']})";
					break;
				}
			}
		}

		$podio = [];
		foreach ($filas as $f) {
			if ($f['rank'] > 3) break;
			$podio[] = ['puesto' => $f['rank']] + array_diff_key($f, ['rank' => 1]);
		}
		$ranks = array_column($podio, 'puesto');
		if (count($ranks) != count(array_unique($ranks))) $avisos[] = 'empate sin resolver en el podio';
		return ['estado' => 'finalizado', 'podio' => $podio, 'avisos' => $avisos];
	}

	// oro/plata del encuentro 0-1, bronce del 2-3 (FinMatchNo de IANSEO)
	private function porEliminatoria(string $codigo, array $ids): array
	{
		$f = $this->t->finales[$codigo] ?? [];
		if (!isset($f[0], $f[1])) return $this->pendiente('eliminatorias sin generar');

		$final = $this->resultado($f[0], $f[1]);
		if (!$final || !$final[0]) return $this->pendiente('falta definir la final');
		$bronce = isset($f[2], $f[3]) ? $this->resultado($f[2], $f[3]) : [null, null];
		if (!$bronce) return $this->pendiente('falta definir el bronce');

		$podio = [];
		$avisos = [];
		$partido = [1 => [$f[0], $f[1]], 2 => [$f[0], $f[1]], 3 => [$f[2] ?? [], $f[3] ?? []]];
		foreach ([1 => $final[0], 2 => $final[1], 3 => $bronce[0]] as $puesto => $id) {
			if (!$id) continue;
			// descalificado/no presentado en la fase final: pierde la medalla (no se corre el podio)
			$irm = max((int)($this->t->individuales[$codigo][$id]['IndIrmTypeFinal'] ?? 0),
				...array_map(fn($m) => ($m['FinAthlete'] ?? 0) == $id ? (int)$m['FinIrmType'] : 0, $partido[$puesto]));
			if (IanseoExport::fueraDeRanking($irm)) {
				$avisos[] = $this->t->arquero($id)['nombre'] . " sin medalla de puesto $puesto (IRM $irm en eliminatorias)";
				continue;
			}
			$podio[] = ['puesto' => $puesto] + $this->t->arquero($id);
		}

		foreach ($podio as $p) {
			$rf = (int)($this->t->individuales[$codigo][$p['id']]['IndRankFinal'] ?? 0);
			if ($rf != $p['puesto']) $avisos[] = "IANSEO tiene a {$p['nombre']} en el puesto final $rf, los encuentros dicen {$p['puesto']}";
		}
		return ['estado' => 'finalizado', 'podio' => $podio, 'avisos' => $avisos];
	}

	// [ganador, perdedor]; [único, null] si fue bye; [null, null] si no hay nadie; null si no está definido.
	// Un arquero solo es bye únicamente si IANSEO le dio el encuentro ganado (FinWinLose=1, FinTie=2):
	// si no, falta la otra llave (p. ej. una semifinal todavía sin terminar).
	private function resultado(array $a, array $b): ?array
	{
		$ia = (int)$a['FinAthlete'];
		$ib = (int)$b['FinAthlete'];
		if ($ia && $ib) {
			if ((int)$a['FinWinLose'] == 1) return [$ia, $ib];
			if ((int)$b['FinWinLose'] == 1) return [$ib, $ia];
			return null;
		}
		if ($ia) return (int)$a['FinWinLose'] == 1 ? [$ia, null] : null;
		if ($ib) return (int)$b['FinWinLose'] == 1 ? [$ib, null] : null;
		return [null, null];
	}

	private function pendiente(string $motivo): array
	{
		return ['estado' => 'pendiente', 'motivo' => $motivo, 'podio' => [], 'avisos' => []];
	}
}
