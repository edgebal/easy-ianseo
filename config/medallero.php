<?php

return [
	// Tabla de puntajes y reglas FATARCO vigente. Para otra temporada, agregar el archivo en
	// resources/normas/ y apuntar acá (o usar MEDALLERO_NORMAS en el .env).
	'normas' => env('MEDALLERO_NORMAS', resource_path('normas/fatarco-2026.php')),

	// tamaño máximo del .ianseo subido, en KB (los exports reales pesan 1-5 MB)
	'max_upload_kb' => 20480,
];
