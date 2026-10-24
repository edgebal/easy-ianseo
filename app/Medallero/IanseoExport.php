<?php

namespace App\Medallero;

// Lectura de un archivo .ianseo (export de torneo de IANSEO).
// Formato: gzcompress(serialize($Gara)) — ver Tournament/TournamentExport.php en IANSEO.

final class IanseoExport
{
	public const MODALIDADES = [3 => 'aire', 6 => 'sala', 22 => 'sala']; // TourTypes: 70m Round, Indoor 18

	// IRM sin ranking (IrmShowRank=0 en IANSEO): DNF sin ranking, DNS, DSQ, DQB. El DNF 5 sí rankea.
	public const IRM_FUERA = [7, 10, 15, 20];

	public array $torneo;
	public ?string $modalidad;
	public array $entries = [];      // EnId => row
	public array $clubes = [];       // CoId => row
	public array $quali = [];        // EnId => row
	public array $eventos = [];      // EvCode => row (solo individuales)
	public array $eventoClases = []; // EvCode => ["DIV/CLASE", ...]
	public array $individuales = []; // EvCode => [EnId => row]
	public array $finales = [];      // EvCode => [MatchNo => row]
	public array $clases = [];       // ClId => row
	public array $divisiones = [];   // DivId => row
	private array $flechas = [];     // QuSession => [distancia => flechas esperadas]

	// tope de descompresión: un export real pesa pocos MB, esto evita que un archivo armado llene la memoria
	private const MAX_DESCOMPRIMIDO = 256 * 1024 * 1024;

	public static function leer(string $archivo): self
	{
		$raw = @file_get_contents($archivo);
		if ($raw === false) throw new ArchivoInvalido('No se pudo leer el archivo.');
		$data = @gzuncompress($raw, self::MAX_DESCOMPRIMIDO);
		if ($data === false) throw new ArchivoInvalido('El archivo no es un export .ianseo válido.');
		$gara = @unserialize($data, ['allowed_classes' => false]);
		if (!is_array($gara) || empty($gara['Tournament'])) throw new ArchivoInvalido('El archivo no contiene un torneo de IANSEO.');
		return new self($gara);
	}

	private function __construct(array $g)
	{
		$this->torneo = $g['Tournament'];
		$this->modalidad = self::MODALIDADES[(int)$this->torneo['ToType']] ?? null;

		foreach ($g['Entries'] ?? [] as $r) $this->entries[$r['EnId']] = $r;
		foreach ($g['Countries'] ?? [] as $r) $this->clubes[$r['CoId']] = $r;
		foreach ($g['Qualifications'] ?? [] as $r) $this->quali[$r['QuId']] = $r;
		foreach ($g['Classes'] ?? [] as $r) $this->clases[$r['ClId']] = $r;
		foreach ($g['Divisions'] ?? [] as $r) $this->divisiones[$r['DivId']] = $r;
		foreach ($g['DistanceInformation'] ?? [] as $r) {
			if ($r['DiType'] == 'Q') $this->flechas[(int)$r['DiSession']][(int)$r['DiDistance']] = (int)$r['DiEnds'] * (int)$r['DiArrows'];
		}

		foreach ($g['Events'] ?? [] as $r) if (!$r['EvTeamEvent']) $this->eventos[$r['EvCode']] = $r;
		uasort($this->eventos, fn($a, $b) => $a['EvProgr'] <=> $b['EvProgr'] ?: strcmp($a['EvCode'], $b['EvCode']));

		foreach ($g['EventClass'] ?? [] as $r) {
			if (!$r['EcTeamEvent']) $this->eventoClases[$r['EcCode']][] = $r['EcDivision'] . '/' . $r['EcClass'];
		}
		foreach ($g['Individuals'] ?? [] as $r) {
			if (isset($this->eventos[$r['IndEvent']]) && $this->compite($r['IndId']) && !self::fueraDeRanking($r['IndIrmType'])) {
				$this->individuales[$r['IndEvent']][$r['IndId']] = $r;
			}
		}
		foreach ($g['Finals'] ?? [] as $r) $this->finales[$r['FinEvent']][(int)$r['FinMatchNo']] = $r;
	}

	// participante válido: atleta, inscripción activa y no DNS/DSQ
	public function compite(int|string $enId): bool
	{
		$e = $this->entries[$enId] ?? null;
		$q = $this->quali[$enId] ?? null;
		return $e && $q && $e['EnAthlete'] && $e['EnStatus'] <= 1 && !self::fueraDeRanking($q['QuIrmType']);
	}

	// clasificación completa: en cada distancia se tiraron todas las flechas de la sesión,
	// o se cargó solo el total (puntaje sin flechas, como pasa en algunos torneos de Escuela)
	public function clasificacionCompleta(int|string $enId): bool
	{
		$q = $this->quali[$enId];
		$esperadas = $this->flechas[(int)$q['QuSession']] ?? [];
		for ($d = 1; $d <= (int)$this->torneo['ToNumDist']; $d++) {
			$score = (int)$q["QuD{$d}Score"];
			$hits = (int)$q["QuD{$d}Hits"];
			if ($hits == 0) {
				if ($score <= 0) return false;
			} elseif (isset($esperadas[$d]) ? $hits < $esperadas[$d] : $score <= 0) {
				return false;
			}
		}
		return true;
	}

	public static function fueraDeRanking(int|string $irm): bool
	{
		return in_array((int)$irm, self::IRM_FUERA, true);
	}

	public function categoria(int|string $enId): string
	{
		return $this->entries[$enId]['EnDivision'] . '/' . $this->entries[$enId]['EnClass'];
	}

	public function nombreCategoria(string $categoria): string
	{
		[$div, $cl] = explode('/', $categoria, 2);
		return trim(($this->divisiones[$div]['DivDescription'] ?? $div) . ' ' . ($this->clases[$cl]['ClDescription'] ?? $cl));
	}

	public function arquero(int|string $enId): array
	{
		$e = $this->entries[$enId];
		$q = $this->quali[$enId];
		$club = $this->clubes[$e['EnCountry']] ?? null;
		return [
			'id' => (int)$enId,
			'nombre' => trim($e['EnFirstName'] . ' ' . $e['EnName']),
			'club' => $club ? trim($club['CoName']) : '',
			'categoria' => $this->categoria($enId),
			'puntaje' => (int)$q['QuScore'],
			'oros' => (int)$q['QuGold'],
			'xs' => (int)$q['QuXnine'],
		];
	}

	// entries válidos agrupados por "DIV/CLASE"
	public function porCategoria(): array
	{
		$out = [];
		foreach ($this->entries as $id => $e) if ($this->compite($id)) $out[$this->categoria($id)][] = $id;
		ksort($out);
		return $out;
	}
}
