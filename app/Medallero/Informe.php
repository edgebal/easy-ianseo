<?php

namespace App\Medallero;

// Resultado completo de procesar un export: medallero, pendientes, avisos y pases,
// listo para mostrar en consola (Reporte) o en la vista imprimible.

final class Informe
{
	public const MEDALLAS = [1 => 'Oro', 2 => 'Plata', 3 => 'Bronce'];

	public const NOTA_PASES = 'El pase de Escuela se da al alcanzar el puntaje (art. 11.1); en Jóvenes Promesas habilita el pase, '
		. 'que decide el arquero con su entrenador (art. 14.6). La homologación requiere 2 torneos (art. 11.3 / 12.5), '
		. 'cosa que no se puede verificar con un solo archivo.';

	private function __construct(
		public readonly array $torneo,
		public readonly string $normas,
		public readonly array $medallero,
		public readonly array $pendientes,
		public readonly array $avisos,
		public readonly array $pases,
		public readonly bool $previo,  // torneo sin puntajes: solo sirve el control del armado
		public readonly array $armado, // todos los podios (terminados o no), en el orden del guion
	) {}

	public static function generar(string $archivo, string $archivoNormas): self
	{
		ini_set('memory_limit', '512M'); // como IANSEO al importar: los exports grandes se descomprimen enteros
		$normas = require $archivoNormas;
		$t = IanseoExport::leer($archivo);
		$podios = (new Medallero($t, $normas))->calcular();

		$x = $t->torneo;
		$torneo = [
			'codigo' => $x['ToCode'], 'nombre' => trim($x['ToName']), 'lugar' => trim($x['ToWhere']),
			'desde' => $x['ToWhenFrom'], 'hasta' => $x['ToWhenTo'],
			'tipo' => $x['ToTypeName'], 'modalidad' => ['aire' => 'Aire Libre', 'sala' => 'Salón'][$t->modalidad] ?? null,
			'modalidad_codigo' => $t->modalidad,
		];
		$pases = (new Pases($t, $normas))->calcular();
		return new self(
			$torneo,
			$normas['version'],
			array_values(array_filter($podios, fn($p) => $p['estado'] == 'finalizado')),
			self::enOrden(array_filter($podios, fn($p) => $p['estado'] != 'finalizado'), $t->modalidad),
			(new Avisos($t, $normas, $podios, $pases))->calcular(),
			$pases,
			$t->sinPuntajes(),
			self::enOrden($podios, $t->modalidad),
		);
	}

	// Podios en el orden del guion, para el anexo y el control del armado. El medallero queda en el orden de
	// los eventos: Guion::armar asigna cada pase al primer podio de su categoría en ese orden.
	public static function enOrden(array $podios, ?string $modalidad): array
	{
		$orden = fn($p) => Guion::orden($p['propia'] ?? $p['categorias'][0], $modalidad == 'sala', $p['tipo'] == 'categoria', $p['nombre']);
		usort($podios, fn($a, $b) => $orden($a) <=> $orden($b));
		return $podios;
	}

	public function pasan(): array
	{
		return array_values(array_filter($this->pases, fn($x) => $x['pasa']));
	}

	public function noPasan(): array
	{
		return array_values(array_filter($this->pases, fn($x) => !$x['pasa']));
	}

	public function fechas(): string
	{
		return $this->torneo['desde'] . ($this->torneo['hasta'] != $this->torneo['desde'] ? " al {$this->torneo['hasta']}" : '');
	}

	public function toArray(): array
	{
		return [
			'torneo' => $this->torneo, 'normas' => $this->normas,
			'medallero' => $this->medallero, 'pendientes' => $this->pendientes,
			'avisos' => $this->avisos, 'pases' => $this->pases, 'previo' => $this->previo,
		];
	}

	// "5 arq., por eliminatorias, acopla B/U15E20 + B/U18E20"
	public static function detallePodio(array $p): string
	{
		$extra = [$p['participantes'] . ' arq.'];
		$extra[] = $p['eliminatorias'] ? 'por eliminatorias' : 'por clasificación';
		if (count($p['categorias']) > 1) $extra[] = 'acopla ' . implode(' + ', $p['categorias']);
		if ($p['acoplada_en']) $extra[] = 'categoría propia, eliminó acoplada en ' . implode(', ', $p['acoplada_en']);
		if ($p['opcional']) $extra[] = self::OPCIONAL[$p['opcional']];
		return implode(', ', $extra);
	}

	public const OPCIONAL = [
		'jp' => 'solo con reglas antiguas de premiación JP (arts. 14.3 y 14.4)',
		'escuela' => 'Escuela con 2 o menos: podio no obligatorio (art. 11.2)',
		'adaptados' => 'adaptado de salón sin eliminatoria propia: solo con la opción de podio (art. 18.5.1)',
	];

	// fila de la tabla del armado: [evento, categorías, eliminatoria, premiación]
	public static function filaArmado(array $p): array
	{
		$cod = fn($c) => str_replace('/', '', $c);
		$cats = [$cod($p['propia']) . ' (' . ($p['cuenta'][$p['propia']] ?? 0) . ')'];
		foreach ($p['cuenta'] as $c => $n) if ($c != $p['propia']) $cats[] = $cod($c) . " ($n)";
		$elim = !$p['eliminatorias'] ? 'no' : 'desde ' . match (true) {
			$p['fase'] <= 1 => 'la final', $p['fase'] == 2 => 'semifinales', $p['fase'] <= 4 => 'cuartos',
			$p['fase'] <= 8 => 'octavos', default => '1/' . $p['fase'],
		};
		$premio = match (true) {
			(bool)$p['acoplada_en'] => 'podio propio por clasificación (eliminó en ' . implode(', ', $p['acoplada_en']) . ')',
			$p['tipo'] == 'categoria' => 'podio propio por clasificación',
			default => 'podio de ' . $cod($p['propia']) . ($p['eliminatorias'] ? ' por eliminatoria' : ' por clasificación'),
		};
		if ($p['opcional']) $premio .= '; ' . self::OPCIONAL[$p['opcional']];
		$recibe = array_shift($cats);
		$nombre = $p['tipo'] == 'evento' ? "{$p['nombre']} ({$p['codigo']})" : $p['nombre'];
		return [$nombre, $recibe . ($cats ? ' ← ' . implode(' + ', $cats) : ''), $elim, $premio];
	}

	public static function siguiente(array $pase): string
	{
		return $pase['siguiente'] ?? 'sin categoría siguiente (distancia máxima de su clase)';
	}

	// "515 / 520 (clasificación incompleta) — nota"
	public static function detalleNoPasa(array $x): string
	{
		$d = $x['umbral_pase'] !== null ? "{$x['puntaje']} / {$x['umbral_pase']}" : (string)$x['puntaje'];
		if (!$x['clasificacion_completa']) $d .= ' (clasificación incompleta)';
		if ($x['nota']) $d .= " — {$x['nota']}";
		return $d;
	}
}
