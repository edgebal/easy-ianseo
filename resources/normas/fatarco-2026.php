<?php
// Normas FATARCO 2026 (rev. 7 de abril de 2026): puntajes de pase de categoría.
//
// Escuela: puntaje de pase (art. 13.9 aire libre, 15.9 salón, 18.6 adaptados).
// Jóvenes Promesas: [Pase de Categoría, Maestro, Excelencia] (art. 14.6).
//   null = esa distancia ya es la categoría oficial WA (no hay pase definido).
// El orden de las claves es la escalera de categorías: el pase lleva a la siguiente.

return [
	'version' => 'Normas FATARCO 2026',

	// divisiones que usan los puntajes de otra (adaptados "igual a convencionales")
	'alias_division' => [
		'RAA2' => 'R', 'CAA2' => 'C', 'BAA2' => 'B',
		'PI2R' => 'R', 'PI2C' => 'C',
	],

	'aire' => [
		'escuela' => [
			'R'   => ['E20' => 550, 'E30' => 550, 'E40' => 550, 'E50' => 550, 'E60' => 550],
			'C'   => ['E30' => 600, 'E40' => 600],
			'B'   => ['E20' => 520, 'E30' => 520, 'E40' => 520],
			'T'   => ['E20' => 500],
			'L'   => ['E20' => 480],
			'PI1' => ['E20' => 450, 'E30' => 450, 'E40' => 450],
			'VI'  => ['E15' => 400, 'E20' => 400],
			'AA1' => ['E15' => 400, 'E20' => 400],
		],
		'jp' => [
			'R' => [
				'U13' => ['I15' => [550, 620, 650], 'I20' => [550, 620, 650]],
				'U15' => ['E20' => [550, 620, 650], 'E30' => [550, 620, 650]],
				'U18' => ['E20' => [550, 620, 650], 'E30' => [550, 620, 650], 'E40' => [550, 620, 650], 'E50' => [550, 620, 650]],
			],
			'C' => [
				'U13' => ['I20' => [600, 640, 670]],
				'U15' => ['E30' => [600, 640, 670]],
				'U18' => ['E30' => [600, 640, 670], 'E40' => [600, 640, 670], 'E50' => null],
			],
			'B' => [
				'U13' => ['I15' => [520, 560, 600], 'I20' => [520, 560, 600]],
				'U15' => ['E20' => [520, 560, 600], 'E30' => null],
				'U18' => ['E20' => [520, 560, 600], 'E30' => [520, 560, 600], 'E40' => [520, 560, 600], 'E50' => null],
			],
		],
	],

	'sala' => [
		'escuela' => [
			'R'   => ['E80' => 450, 'E60' => 450, 'E40' => 450],
			'C'   => ['E60' => 500, 'E40' => 500],
			'B'   => ['E80' => 430, 'E60' => 430],
			'T'   => ['E80' => 420, 'E60' => 420],
			'L'   => ['E80' => 400, 'E60' => 400],
			'PI1' => ['E80' => 400, 'E60' => 400],
			'VI'  => ['E15' => 350, 'E80' => 350], // E15 = "E-80/15" (15 metros)
			'AA1' => ['E15' => 350],
		],
		'jp' => [
			'R' => [
				'U13' => ['I10' => [450, 510, 540], 'I15' => [450, 510, 540]],
				'U15' => ['E80' => [450, 510, 540], 'E60' => [450, 510, 540]],
				'U18' => ['E80' => [450, 510, 540], 'E60' => [450, 510, 540], 'E40' => [450, 510, 540]],
			],
			'C' => [
				'U13' => ['I10' => [500, 530, 560], 'I15' => [500, 530, 560]],
				'U15' => ['E80' => [500, 530, 560]],
				'U18' => ['E80' => [500, 530, 560], 'E40' => [500, 530, 560]],
			],
			'B' => [
				'U13' => ['I10' => [430, 470, 500], 'I15' => [430, 470, 500]],
				'U15' => ['E80' => [430, 470, 500], 'E60' => [430, 470, 500]],
				'U18' => ['E80' => [430, 470, 500], 'E60' => [430, 470, 500], 'E40' => null],
			],
		],
	],

	// a dónde lleva el pase desde el último escalón (null = no hay categoría siguiente)
	'destino_final' => [
		'escuela' => [
			'default' => 'categoría mayor (M/F, Sub-21 o 50+ según edad)',
			'PI1' => 'P.I.1 M/F (o Sub-21 según edad)', // clases adaptadas: art. 18.5.2
			'VI'  => 'VI Única (o Única Sub-21 según edad)',
			'AA1' => 'AA1 Única',
		],
		'U13' => null, // I20 (aire) / I15 (sala) ya es la distancia máxima de Sub-13
		'U15' => 'Sub-15 WA',
		'U18' => 'Sub-18 WA',
	],

	// art. 13.2.2.c (aire libre) y 15.3.2.c (salón): juveniles sin eliminatoria propia
	// se incluyen en la clasificación de Mayores de su división
	'juveniles_a_mayores' => [
		'aire' => ['R' => ['U21'], 'C' => ['U18', 'U21'], 'B' => ['U18', 'U21']],
		'sala' => ['R' => ['U18', 'U21'], 'C' => ['U18', 'U21'], 'B' => ['U18', 'U21']],
	],

	// eliminatorias obligatorias desde semifinales (13.2.2.a / 15.3.2.a)
	'eliminatoria_obligatoria' => ['divisiones' => ['R', 'C', 'B'], 'clases' => ['M', 'W']],

	'minimo_eliminatoria' => 3,
];
