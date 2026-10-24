@extends('layout')

@php
    use App\Medallero\Guion;
    use App\Medallero\Informe;
    $fecha = fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d/m/Y');
    $t = $inf->torneo;
    $fechas = $fecha($t['desde']) . ($t['hasta'] != $t['desde'] ? ' al ' . $fecha($t['hasta']) : '');
    $grupos = Guion::armar($inf);
    $tandas = Guion::tandas($inf);
    // podios opcionales => checkbox que los entrega (sin marcar: no se entregan)
    $opcionales = array_intersect_key(['jp' => 'jp-reglas-antiguas', 'escuela' => 'escuela-hasta-2'],
        array_flip(array_filter(array_column($inf->medallero, 'opcional'))));
    $tituloTanda = [1 => '1ª tanda — categorías sin eliminatorias', 2 => '2ª tanda — categorías con eliminatorias (propias o acopladas)'];
@endphp

@section('titulo', 'Entrega de premios — ' . $t['nombre'])

@section('estilos')
.barra {
    display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between;
    margin-bottom: 16px;
}
.barra .archivo { color: var(--suave); font-size: 13px; }
.hoja {
    background: var(--papel);
    border: 1px solid var(--linea);
    border-radius: 10px;
    padding: 28px;
    max-width: 820px;
    margin: 0 auto;
}
.encabezado { border-bottom: 2px solid var(--tinta); padding-bottom: 12px; margin-bottom: 8px; }
.encabezado h1 { font-size: 22px; }
.encabezado .datos { color: var(--suave); margin-top: 4px; }
.encabezado .datos span + span::before { content: " · "; }

.grupo > h2 {
    font-size: 15px; text-transform: uppercase; letter-spacing: .08em;
    border-bottom: 2px solid var(--tinta); padding: 22px 0 6px; margin-bottom: 4px;
}

.bloque { break-inside: avoid; padding: 14px 0 12px; border-bottom: 1px solid var(--linea); }
.bloque h3 { font-size: 18px; }
.bloque .sub { color: var(--suave); font-size: 13px; margin-top: 1px; }
.bloque .destino { color: var(--suave); font-size: 13px; margin-top: 10px; }
.bloque ul { list-style: none; margin: 0; padding: 0; }
.anota { font-style: italic; }

/* una persona por cuadrito: badges, APELLIDO Nombre y club, con aire para leer de corrido */
.filas { margin-top: 8px; }
.medallas { margin-top: 10px; }
.fila {
    display: block; margin: 6px 0; padding: 8px 12px;
    border: 1px solid var(--linea); border-radius: 6px; break-inside: avoid;
}
.fila .badges { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 4px; }
.fila .nombre { display: block; font-size: 17px; font-weight: 600; }
.fila .apellido { letter-spacing: .02em; }
.fila .club { display: block; color: var(--suave); font-size: 14px; }
.medalla, .pase {
    display: inline-block; padding: 0 8px; border-radius: 4px; border: 1.5px solid transparent;
    font-weight: 700; font-size: 12px; line-height: 19px; text-align: center;
    text-transform: uppercase; letter-spacing: .05em; white-space: nowrap;
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
.medalla { min-width: 76px; color: #fff; }
.medalla.oro { background: var(--oro); }
.medalla.plata { background: var(--plata); }
.medalla.bronce { background: var(--bronce); }
.pase { border-color: currentColor; }

.selector {
    border: 1px solid var(--linea); border-radius: 8px; background: var(--papel);
    max-width: 820px; margin: 0 auto 16px; padding: 10px 16px;
    display: grid; gap: 8px;
}
.selector .oculto { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
.selector .opcion { display: flex; flex-wrap: wrap; gap: 6px 22px; align-items: center; }
.selector .rotulo { font-weight: 600; min-width: 64px; }
.selector label { display: inline-flex; gap: 6px; align-items: center; cursor: pointer; }
/* el selector elige qué versión del guion se ve (y se imprime) */
.modo-dos { display: none; }
body:has(#modo-dos:checked) .modo-una { display: none; }
body:has(#modo-dos:checked) .modo-dos { display: block; }
/* en dos tandas se puede imprimir una sola; la 1ª tanda nunca lleva anexo */
.solo-dos { display: none !important; }
body:has(#modo-dos:checked) .solo-dos { display: flex !important; }
body:has(#modo-dos:checked):has(#imp-1:checked) .tanda-2,
body:has(#modo-dos:checked):has(#imp-2:checked) .tanda-1,
body:has(#con-anexo:not(:checked)) .anexo,
body:has(#modo-dos:checked):has(#imp-1:checked) .anexo,
body:has(#modo-dos:checked):has(#imp-1:checked) .op-anexo { display: none; }
/* podios opcionales (JP sin eliminatoria, Escuela con 2 o menos): con su checkbox sin marcar no se entregan */
.bloque .sin-medallas, .pases.todos-con-medalla, .pases li.con-medalla, .pase.solo-sin-jp, .pase.solo-sin-escuela { display: none; }
@foreach (['jp' => 'jp-reglas-antiguas', 'escuela' => 'escuela-hasta-2'] as $tipo => $id)
body:has(#{{ $id }}:not(:checked)) .pase.solo-sin-{{ $tipo }} { display: inline-block; }
body:has(#{{ $id }}:not(:checked)) .opcional-{{ $tipo }} .pases.todos-con-medalla { display: block; }
body:has(#{{ $id }}:not(:checked)) .opcional-{{ $tipo }} .pases li.con-medalla { display: block; }
body:has(#{{ $id }}:not(:checked)) .opcional-{{ $tipo }} .medallas { display: none; }
body:has(#{{ $id }}:not(:checked)) .opcional-{{ $tipo }} .sin-medallas { display: block; }
body:has(#{{ $id }}:not(:checked)) .bloque.solo-medallas.opcional-{{ $tipo }},
body:has(#{{ $id }}:not(:checked)) .grupo:not(:has(.bloque:not(.solo-medallas.opcional-{{ $tipo }}))),
body:has(#{{ $id }}:not(:checked)) .tanda:not(:has(.bloque:not(.solo-medallas.opcional-{{ $tipo }}))) { display: none; }
@endforeach
/* con los dos sin marcar, grupos y tandas que solo tienen opcionales de uno y otro tipo */
body:has(#jp-reglas-antiguas:not(:checked)):has(#escuela-hasta-2:not(:checked)) .grupo:not(:has(.bloque:not(.solo-medallas))),
body:has(#jp-reglas-antiguas:not(:checked)):has(#escuela-hasta-2:not(:checked)) .tanda:not(:has(.bloque:not(.solo-medallas))) { display: none; }
.titulo-tanda {
    font-size: 19px; margin: 26px 0 0; padding: 8px 12px;
    border: 2px solid var(--tinta); border-radius: 6px;
}
/* salto después de la 1ª tanda y no antes de la 2ª: si una de las dos no se muestra, no deja hoja en blanco */
.tanda-1 { break-after: page; }
body:has(#imp-1:checked) .tanda-1 { break-after: auto; }

.anexo { break-before: page; }
.anexo > h2 {
    font-size: 15px; text-transform: uppercase; letter-spacing: .08em;
    border-bottom: 2px solid var(--tinta); padding: 22px 0 6px; margin-bottom: 10px;
}
.anexo h3 { font-size: 14px; margin: 16px 0 6px; }
ul.lista { margin: 0; padding-left: 18px; }
ul.lista li { margin: 3px 0; }
table.pases { width: 100%; border-collapse: collapse; font-size: 13px; }
table.pases th { text-align: left; font-size: 12px; color: var(--suave); border-bottom: 1px solid var(--tinta); padding: 4px; }
table.pases td { padding: 4px; border-bottom: 1px solid var(--linea); vertical-align: top; }
table.pases tr { break-inside: avoid; }
.notas { color: var(--suave); font-size: 12px; margin-top: 10px; }
.tabla-scroll { overflow-x: auto; }

@media (max-width: 640px) {
    .hoja { padding: 16px; }
}

@media print {
    @page { size: A4; margin: 14mm; }
    body { background: #fff; font-size: 12pt; }
    .contenedor { max-width: none; padding: 0; }
    .barra, .selector { display: none; }
    .hoja { border: 0; padding: 0; border-radius: 0; max-width: none; }
    .tabla-scroll { overflow: visible; }
    .grupo > h2 { break-after: avoid; }

    /* impresora láser: todo en negro; la medalla se distingue por texto, relleno y borde */
    .medalla.oro { background: #000; color: #fff; border-color: #000; }
    .medalla.plata { background: #fff; color: #000; border: 1.5px solid #000; }
    .medalla.bronce { background: #fff; color: #000; border: 1.5px dashed #000; }
    .fila { border-color: #bbb; }
    .club, .bloque .sub, .bloque .destino, .encabezado .datos, .notas, table.pases th { color: #333; }
}
@endsection

@section('contenido')
<main class="contenedor">
    <div class="barra">
        <a class="boton secundario" href="{{ route('formulario') }}">← Otro torneo</a>
        <span class="archivo">{{ $archivo }}</span>
        <button class="boton" type="button" onclick="window.print()">Imprimir</button>
    </div>
    {{-- opciones de la entrega: deciden qué se ve y, por lo tanto, qué se imprime --}}
    <fieldset class="selector">
        <legend class="oculto">Opciones de la entrega</legend>
        <div class="opcion">
            <span class="rotulo">Entrega</span>
            <label><input type="radio" name="modo" id="modo-una" checked> Una tanda</label>
            <label><input type="radio" name="modo" id="modo-dos"> Dos tandas (primero sin eliminatorias, después con eliminatorias)</label>
        </div>
        <div class="opcion solo-dos">
            <span class="rotulo">Imprimir</span>
            <label><input type="radio" name="imprimir" id="imp-todo" checked> Todo</label>
            @foreach ($tandas as $n => $_)
                <label><input type="radio" name="imprimir" id="imp-{{ $n }}"> Tanda {{ $n }}</label>
            @endforeach
        </div>
        <div class="opcion op-anexo">
            <label><input type="checkbox" id="con-anexo" checked> Incluir el anexo (pendientes, avisos y quienes no pasan)</label>
        </div>
        @isset($opcionales['jp'])
            <div class="opcion">
                <label><input type="checkbox" id="jp-reglas-antiguas"> Reglas antiguas de premiación JP: podio por clasificación a las categorías sin eliminatoria, como en Mayores (las normas premian solo las eliminatorias, arts. 12.4, 14.3 y 14.4)</label>
            </div>
        @endisset
        @isset($opcionales['escuela'])
            <div class="opcion">
                <label><input type="checkbox" id="escuela-hasta-2" checked> Podio a Escuela con 2 o menos arqueros sin eliminatoria (no obligatorio, art. 11.2)</label>
            </div>
        @endisset
    </fieldset>

    <article class="hoja">
        <header class="encabezado">
            <h1>{{ $t['nombre'] }}</h1>
            <div class="datos">
                <span>Entrega de premios</span>
                <span>{{ $t['lugar'] }}</span>
                <span>{{ $fechas }}</span>
                <span>{{ $t['modalidad'] ?? $t['tipo'] }}</span>
            </div>
        </header>

        @if (!$grupos)
            <p>No hay podios finalizados ni pases de categoría.</p>
        @endif

        <div class="modo-una">
            @include('partials.grupos', ['grupos' => $grupos])
        </div>

        <div class="modo-dos">
            @foreach ($tandas as $n => $gruposTanda)
                <section class="tanda tanda-{{ $n }}">
                    <h2 class="titulo-tanda">{{ $tituloTanda[$n] }}</h2>
                    @include('partials.grupos', ['grupos' => $gruposTanda])
                </section>
            @endforeach
        </div>

        <section class="anexo">
            <h2>Anexo</h2>

            <h3>Pendientes (no entran en la entrega)</h3>
            @if ($inf->pendientes)
                <ul class="lista">
                    @foreach ($inf->pendientes as $p)
                        <li><b>{{ $p['nombre'] }}</b> — {{ $p['motivo'] }}</li>
                    @endforeach
                </ul>
            @else
                <p>No hay eventos pendientes.</p>
            @endif

            @php $avisosPodio = collect($inf->medallero)->flatMap(fn ($p) => array_map(fn ($a) => $p['nombre'] . ': ' . $a, $p['avisos'])); @endphp
            <h3>Avisos de normas y de datos</h3>
            @if ($inf->avisos || $avisosPodio->isNotEmpty())
                <ul class="lista">
                    @foreach ($inf->avisos as $av)<li>{{ $av }}</li>@endforeach
                    @foreach ($avisosPodio as $av)<li>{{ $av }}</li>@endforeach
                </ul>
            @else
                <p>Sin avisos.</p>
            @endif

            @if ($inf->noPasan())
                <h3>No alcanzan el pase de categoría</h3>
                <div class="tabla-scroll">
                    <table class="pases">
                        <thead>
                            <tr><th>Arquero</th><th>Club</th><th>Categoría</th><th>Puntaje / pase</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($inf->noPasan() as $x)
                                <tr>
                                    <td>{{ $x['nombre'] }}</td>
                                    <td>{{ $x['club'] }}</td>
                                    <td>{{ $x['nombre_categoria'] }}</td>
                                    <td>{{ Informe::detalleNoPasa($x) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($inf->pases)
                <p class="notas">{{ Informe::NOTA_PASES }}</p>
            @endif
            <p class="notas">{{ $t['codigo'] }} · {{ $inf->normas }}</p>
        </section>
    </article>
</main>
@endsection
