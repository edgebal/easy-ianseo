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
		return new self(
			$torneo,
			$normas['version'],
			array_values(array_filter($podios, fn($p) => $p['estado'] == 'finalizado')),
			array_values(array_filter($podios, fn($p) => $p['estado'] != 'finalizado')),
			(new Avisos($t, $normas, $podios))->calcular(),
			(new Pases($t, $normas))->calcular(),
		);
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
			'avisos' => $this->avisos, 'pases' => $this->pases,
		];
	}

	// "5 arq., por eliminatorias, acopla B/U15E20 + B/U18E20"
	public static function detallePodio(array $p): string
	{
		$extra = [$p['participantes'] . ' arq.'];
		$extra[] = $p['eliminatorias'] ? 'por eliminatorias' : 'por clasificación';
		if (count($p['categorias']) > 1) $extra[] = 'acopla ' . implode(' + ', $p['categorias']);
		if ($p['acoplada_en']) $extra[] = 'categoría propia, eliminó acoplada en ' . implode(', ', $p['acoplada_en']);
		if ($p['opcional'] == 'jp') $extra[] = 'solo con reglas antiguas de premiación JP (arts. 14.3 y 14.4)';
		if ($p['opcional'] == 'escuela') $extra[] = 'Escuela con 2 o menos: podio no obligatorio (art. 11.2)';
		return implode(', ', $extra);
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
