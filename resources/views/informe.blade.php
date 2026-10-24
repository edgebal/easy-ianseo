@extends('layout')

@php
    use App\Medallero\Informe;
    $fecha = fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d/m/Y');
    $t = $inf->torneo;
    $fechas = $fecha($t['desde']) . ($t['hasta'] != $t['desde'] ? ' al ' . $fecha($t['hasta']) : '');
    $medallas = ['1' => 'oro', '2' => 'plata', '3' => 'bronce'];
@endphp

@section('titulo', 'Medallero — ' . $t['nombre'])

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
}
.encabezado { border-bottom: 2px solid var(--tinta); padding-bottom: 12px; margin-bottom: 18px; }
.encabezado h1 { font-size: 22px; }
.encabezado .datos { color: var(--suave); margin-top: 4px; }
.encabezado .datos span + span::before { content: " · "; }

.seccion { margin-top: 26px; }
.seccion > h2 {
    font-size: 15px; text-transform: uppercase; letter-spacing: .06em;
    border-bottom: 1px solid var(--linea); padding-bottom: 6px; margin-bottom: 12px;
}
.seccion.nueva-pagina { break-before: page; }

.podios { columns: 2 420px; column-gap: 22px; }
.podio { break-inside: avoid; margin: 0 0 16px; }
.podio h3 { font-size: 15px; }
.podio .detalle { color: var(--suave); font-size: 12px; margin: 2px 0 6px; }
.podio table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.podio td { padding: 3px 4px; border-top: 1px solid var(--linea); vertical-align: top; }
.podio td.puntos { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.podio td.club { color: var(--suave); }
.podio .cat { color: var(--suave); font-size: 11.5px; }
/* la medalla se lee por el texto; el color es solo para pantalla (la impresión es en blanco y negro) */
.medalla {
    display: inline-block; min-width: 56px; padding: 1px 6px; border-radius: 4px;
    border: 1.5px solid transparent;
    color: #fff; font-weight: 700; font-size: 11.5px; line-height: 18px; text-align: center;
    text-transform: uppercase; letter-spacing: .04em;
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
.medalla.oro { background: var(--oro); }
.medalla.plata { background: var(--plata); }
.medalla.bronce { background: var(--bronce); }
.nota-podio { color: var(--aviso); font-size: 12px; margin-top: 4px; }

ul.lista { margin: 0; padding-left: 18px; }
ul.lista li { margin: 3px 0; }
.avisos li { color: var(--aviso); }

table.pases { width: 100%; border-collapse: collapse; font-size: 13px; }
table.pases th { text-align: left; font-size: 12px; color: var(--suave); border-bottom: 1px solid var(--tinta); padding: 4px; }
table.pases td { padding: 4px; border-bottom: 1px solid var(--linea); vertical-align: top; }
table.pases td.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
table.pases tr { break-inside: avoid; }
.logro { color: var(--ok); font-weight: 600; }
.notas { color: var(--suave); font-size: 12px; margin-top: 10px; }
.tabla-scroll { overflow-x: auto; }

@media (max-width: 640px) {
    .hoja { padding: 16px; }
    .podios { columns: 1; }
}

@media print {
    @page { size: A4; margin: 12mm; }
    body { background: #fff; font-size: 11pt; }
    .contenedor { max-width: none; padding: 0; }
    .barra { display: none; }
    .hoja { border: 0; padding: 0; border-radius: 0; }
    .podios { columns: 2; }
    .tabla-scroll { overflow: visible; }

    /* impresora láser: todo en negro, la medalla se distingue por relleno y borde además del texto */
    .medalla.oro { background: #000; color: #fff; border-color: #000; }
    .medalla.plata { background: #fff; color: #000; border: 1.5px solid #000; }
    .medalla.bronce { background: #fff; color: #000; border: 1.5px dashed #000; }
    .podio .detalle, .podio td.club, .podio .cat, .encabezado .datos, .notas, table.pases th { color: #333; }
    .nota-podio, .avisos li { color: #000; font-style: italic; }
    .logro { color: #000; }
}
@endsection

@section('contenido')
<main class="contenedor">
    <div class="barra">
        <a class="boton secundario" href="{{ route('formulario') }}">← Otro torneo</a>
        <span class="archivo">{{ $archivo }}</span>
        <button class="boton" type="button" onclick="window.print()">Imprimir</button>
    </div>

    <article class="hoja">
        <header class="encabezado">
            <h1>{{ $t['nombre'] }}</h1>
            <div class="datos">
                <span>{{ $t['lugar'] }}</span>
                <span>{{ $fechas }}</span>
                <span>{{ $t['modalidad'] ?? $t['tipo'] }}</span>
                <span>{{ $t['codigo'] }}</span>
                <span>{{ $inf->normas }}</span>
            </div>
        </header>

        <section class="seccion">
            <h2>Medallero</h2>
            @if (!$inf->medallero)
                <p>No hay eventos finalizados.</p>
            @endif
            <div class="podios">
                @foreach ($inf->medallero as $p)
                    <div class="podio">
                        <h3>{{ $p['nombre'] }}</h3>
                        <div class="detalle">{{ Informe::detallePodio($p) }}</div>
                        <table>
                            @foreach ($p['podio'] as $a)
                                <tr>
                                    <td style="width:66px"><span class="medalla {{ $medallas[$a['puesto']] ?? '' }}">{{ Informe::MEDALLAS[$a['puesto']] ?? $a['puesto'] . 'º' }}</span></td>
                                    <td>
                                        {{ $a['nombre'] }}
                                        @if (count($p['categorias']) > 1)<span class="cat">({{ $a['categoria'] }})</span>@endif
                                    </td>
                                    <td class="club">{{ $a['club'] }}</td>
                                    <td class="puntos">{{ $a['puntaje'] }}</td>
                                </tr>
                            @endforeach
                        </table>
                        @foreach ($p['avisos'] as $av)
                            <div class="nota-podio">⚠ {{ $av }}</div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </section>

        @if ($inf->pendientes)
            <section class="seccion">
                <h2>Pendientes (no entran en el medallero)</h2>
                <ul class="lista">
                    @foreach ($inf->pendientes as $p)
                        <li><b>{{ $p['nombre'] }}</b> — {{ $p['motivo'] }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($incluir['avisos'] && $inf->avisos)
            <section class="seccion">
                <h2>Avisos de normas</h2>
                <ul class="lista avisos">
                    @foreach ($inf->avisos as $av)
                        <li>{{ $av }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($incluir['pases'] && $inf->pases)
            <section class="seccion nueva-pagina">
                <h2>Pases de categoría — Escuela y Jóvenes Promesas</h2>
                @if (!$inf->pasan())
                    <p>Nadie alcanzó el puntaje de pase.</p>
                @else
                    <div class="tabla-scroll">
                        <table class="pases">
                            <thead>
                                <tr><th>Arquero</th><th>Club</th><th>Categoría</th><th>Puntaje</th><th>Pasa a</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($inf->pasan() as $x)
                                    <tr>
                                        <td>{{ $x['nombre'] }}</td>
                                        <td>{{ $x['club'] }}</td>
                                        <td>{{ $x['nombre_categoria'] }}</td>
                                        <td class="num">{{ $x['puntaje'] }} ≥ {{ $x['umbral_pase'] }}</td>
                                        <td>
                                            {{ Informe::siguiente($x) }}
                                            @if (!empty($x['logro']))<div class="logro">{{ $x['logro'] }}</div>@endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($incluir['no_pasan'] && $inf->noPasan())
                    <h3 style="font-size:14px;margin:18px 0 8px">No alcanzan el pase</h3>
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

                <p class="notas">{{ Informe::NOTA_PASES }}</p>
            </section>
        @endif
    </article>
</main>
@endsection
