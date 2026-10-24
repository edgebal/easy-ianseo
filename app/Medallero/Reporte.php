<?php

namespace App\Medallero;

// Salida del informe en texto plano para la consola (php artisan medallero).

final class Reporte
{
	public static function texto(Informe $inf): string
	{
		$tor = $inf->torneo;
		$o = "{$tor['nombre']}\n{$tor['lugar']} · {$inf->fechas()} · {$tor['codigo']} · " . ($tor['modalidad'] ?? $tor['tipo']) . " · {$inf->normas}\n";

		$o .= self::titulo('MEDALLERO');
		foreach ($inf->medallero as $p) {
			$o .= "\n{$p['nombre']} ({$p['codigo']}) — " . Informe::detallePodio($p) . "\n";
			foreach ($p['podio'] as $a) {
				$o .= '  ' . self::col(Informe::MEDALLAS[$a['puesto']] ?? $a['puesto'], 7) . self::col($a['nombre'], 32) . self::col($a['club'], 34)
					. sprintf('%4d', $a['puntaje']) . (count($p['categorias']) > 1 ? "  [{$a['categoria']}]" : '') . "\n";
			}
			foreach ($p['avisos'] as $av) $o .= "  ! $av\n";
		}

		if ($inf->pendientes) {
			$o .= self::titulo('PENDIENTES (no se incluyen en el medallero)');
			foreach ($inf->pendientes as $p) $o .= "  {$p['nombre']} ({$p['codigo']}) — " . Informe::detallePodio($p) . ": {$p['motivo']}\n";
		}

		if ($inf->avisos) {
			$o .= self::titulo('AVISOS DE NORMAS');
			foreach ($inf->avisos as $a) $o .= "  - $a\n";
		}

		$o .= self::titulo('PASES DE CATEGORÍA (Escuela y Jóvenes Promesas)');
		if (!$inf->pases) return $o . "  No hay arqueros de Escuela ni Jóvenes Promesas.\n";

		$o .= "\n  Alcanzan el puntaje de pase: " . count($inf->pasan()) . "\n";
		foreach ($inf->pasan() as $x) {
			$o .= '  * ' . self::col($x['nombre'], 30) . self::col($x['club'], 31) . self::col($x['nombre_categoria'], 48)
				. "{$x['puntaje']} ≥ {$x['umbral_pase']}  → " . Informe::siguiente($x) . (!empty($x['logro']) ? "  ({$x['logro']})" : '') . "\n";
		}
		if ($inf->noPasan()) {
			$o .= "\n  No alcanzan el pase:\n";
			foreach ($inf->noPasan() as $x) {
				$o .= '    ' . self::col($x['nombre'], 30) . self::col($x['club'], 31) . self::col($x['nombre_categoria'], 48) . Informe::detalleNoPasa($x) . "\n";
			}
		}
		return $o . "\n  Notas: " . wordwrap(Informe::NOTA_PASES, 100, "\n  ") . "\n";
	}

	// str_pad que cuenta caracteres y no bytes (nombres con tildes)
	private static function col(string $s, int $ancho): string
	{
		$s = trim(preg_replace('/\s+/u', ' ', $s));
		if (mb_strlen($s) >= $ancho) $s = mb_substr($s, 0, $ancho - 2) . '…';
		return $s . str_repeat(' ', $ancho - mb_strlen($s));
	}

	private static function titulo(string $s): string
	{
		return "\n" . str_repeat('=', 78) . "\n$s\n" . str_repeat('=', 78) . "\n";
	}
}
