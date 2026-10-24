<?php

namespace App\Medallero;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

// La página del guion (la misma vista que se imprime) pasada a texto, para revisarla desde la consola.
// Aplica a mano lo que en el navegador deciden los selectores: una o dos tandas, qué tanda se imprime,
// el anexo y qué podios opcionales se entregan (Guion::OPCIONALES).

final class TextoDePagina
{
	private const BLOQUES = ['h1', 'h2', 'h3', 'h4', 'p', 'div', 'li', 'tr', 'section', 'header', 'fieldset', 'ul', 'table'];

	// $tanda: en dos tandas, imprimir solo la 1 o la 2 (null = todo). La 1ª tanda nunca lleva anexo.
	// $entregar: tipo de opcional => si se entrega (lo que falte, como viene por defecto el checkbox).
	public static function convertir(string $html, bool $dosTandas = false, ?int $tanda = null, bool $anexo = true, array $entregar = []): string
	{
		$doc = new DOMDocument();
		@$doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR);
		$x = new DOMXPath($doc);
		$clase = fn(string $c) => "contains(concat(' ', normalize-space(@class), ' '), ' $c ')";

		$ocultar = ['//style', '//*[' . $clase($dosTandas ? 'modo-una' : 'modo-dos') . ']', '//*[' . $clase('barra') . ']', '//*[' . $clase('selector') . ']'];
		$entregar += array_map(fn($o) => $o['defecto'], Guion::OPCIONALES);
		foreach ($entregar as $tipo => $seEntrega) {
			$bloque = '//*[' . $clase('opcional-' . $tipo) . ']';
			if ($seEntrega) {
				$ocultar[] = "$bloque//*[" . $clase('sin-medallas') . ']';
				$ocultar[] = "$bloque//*[" . $clase('todos-con-medalla') . ']';
				$ocultar[] = "$bloque//li[" . $clase('con-medalla') . ']';
				$ocultar[] = '//*[' . $clase('solo-sin-' . $tipo) . ']';
			} else {
				$ocultar[] = "$bloque//*[" . $clase('medallas') . ']';
				$ocultar[] = $bloque . '[' . $clase('solo-medallas') . ']';
			}
		}
		// antes de empezar la página no tiene selector: el control del armado se ve siempre
		$previo = $x->query('//*[' . $clase('previo') . ']')->length > 0;
		if ($dosTandas && $tanda) $ocultar[] = '//*[' . $clase('tanda-' . (3 - $tanda)) . ']';
		if (!$previo && (!$anexo || ($dosTandas && $tanda == 1))) $ocultar[] = '//*[' . $clase('anexo') . ']';
		foreach ($ocultar as $q) self::quitar($x->query($q));
		// grupos y tandas que quedaron sin bloques
		foreach (['grupo', 'tanda'] as $c) {
			self::quitar($x->query('//*[' . $clase($c) . '][not(.//*[' . $clase('bloque') . '])]'));
		}

		$hoja = $x->query('//*[' . $clase('hoja') . ']')->item(0) ?? $doc->documentElement;
		$lineas = array_map(fn($l) => preg_match('/^\s*- /', $l) ? '  ' . trim($l) : trim($l), explode("\n", self::texto($hoja)));
		return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lineas))) . "\n";
	}

	private static function quitar(iterable $nodos): void
	{
		foreach (iterator_to_array($nodos) as $n) $n->parentNode?->removeChild($n);
	}

	private static function texto(DOMNode $n): string
	{
		if ($n->nodeType == XML_TEXT_NODE) return preg_replace('/\s+/u', ' ', $n->textContent);
		if (!$n instanceof DOMElement) return '';

		$tag = strtolower($n->tagName);
		$dentro = '';
		foreach ($n->childNodes as $c) $dentro .= self::texto($c);
		if ($tag == 'td' || $tag == 'th') return trim($dentro) . ' | ';
		// lo que en la página resuelve el CSS: el guion antes del club y los badges de medalla y pase
		$clases = ' ' . $n->getAttribute('class') . ' ';
		if (str_contains($clases, ' club ')) return ' — ' . trim($dentro);
		if (str_contains($clases, ' apellido ')) return mb_strtoupper(trim($dentro));
		if (str_contains($clases, ' datos ')) {
			$partes = [];
			foreach ($n->childNodes as $c) if (trim($c->textContent) !== '') $partes[] = trim($c->textContent);
			return "\n" . implode(' · ', $partes) . "\n";
		}
		if (str_contains($clases, ' medalla ') || str_contains($clases, ' pase ')) return ' [' . mb_strtoupper(trim($dentro)) . '] ';
		if (!in_array($tag, self::BLOQUES, true)) return $dentro;

		$linea = trim(preg_replace('/ +/', ' ', $dentro));
		$linea = rtrim($linea, ' |');
		if ($linea === '') return "\n";
		return match ($tag) {
			'h1' => "\n" . mb_strtoupper($linea) . "\n" . str_repeat('=', mb_strlen($linea)) . "\n",
			'h2' => "\n\n## $linea\n",
			'h3' => "\n### $linea\n",
			'h4' => "\n#### $linea\n",
			'li' => "  - $linea\n",
			'tr' => "  $linea\n",
			default => "\n$linea\n",
		};
	}
}
