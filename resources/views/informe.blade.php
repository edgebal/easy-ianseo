@extends('layout')

@php
    use App\Medallero\Guion;
    use App\Medallero\Informe;
    $fecha = fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d/m/Y');
    $t = $inf->torneo;
    $fechas = $fecha($t['desde']) . ($t['hasta'] != $t['desde'] ? ' al ' . $fecha($t['hasta']) : '');
    $clases = [1 => 'oro', 2 => 'plata', 3 => 'bronce'];
    $grupos = Guion::armar($inf);
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
.bloque .titulo-pases { font-weight: 700; margin-top: 10px; }
.bloque ul { list-style: none; margin: 2px 0 0; padding: 0; }
.bloque li { margin: 3px 0; }
.club { color: var(--suave); }
.club::before { content: "— "; }
.anota { font-style: italic; }

.medallas { margin-top: 10px; }
.medallas li { display: grid; grid-template-columns: 76px 1fr; gap: 10px; align-items: baseline; margin: 5px 0; }
.medallas .nombre { font-size: 16.5px; font-weight: 600; }
.medalla {
    display: inline-block; padding: 1px 0; border-radius: 4px; border: 1.5px solid transparent;
    color: #fff; font-weight: 700; font-size: 12px; line-height: 19px; text-align: center;
    text-transform: uppercase; letter-spacing: .05em;
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
}
.medalla.oro { background: var(--oro); }
.medalla.plata { background: var(--plata); }
.medalla.bronce { background: var(--bronce); }

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
    .medallas li { grid-template-columns: 1fr; gap: 2px; }
    .medalla { width: 76px; }
}

@media print {
    @page { size: A4; margin: 14mm; }
    body { background: #fff; font-size: 12pt; }
    .contenedor { max-width: none; padding: 0; }
    .barra { display: none; }
    .hoja { border: 0; padding: 0; border-radius: 0; max-width: none; }
    .tabla-scroll { overflow: visible; }
    .grupo > h2 { break-after: avoid; }

    /* impresora láser: todo en negro; la medalla se distingue por texto, relleno y borde */
    .medalla.oro { background: #000; color: #fff; border-color: #000; }
    .medalla.plata { background: #fff; color: #000; border: 1.5px solid #000; }
    .medalla.bronce { background: #fff; color: #000; border: 1.5px dashed #000; }
    .club, .bloque .sub, .encabezado .datos, .notas, table.pases th { color: #333; }
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
                <span>Entrega de premios</span>
                <span>{{ $t['lugar'] }}</span>
                <span>{{ $fechas }}</span>
                <span>{{ $t['modalidad'] ?? $t['tipo'] }}</span>
            </div>
        </header>

        @if (!$grupos)
            <p>No hay podios finalizados ni pases de categoría.</p>
        @endif

        @foreach ($grupos as $g => $bloques)
            <section class="grupo">
                <h2>{{ Guion::GRUPOS[$g] }}</h2>
                @foreach ($bloques as $b)
                    <div class="bloque">
                        <h3>{{ $b['nombre'] }}</h3>
                        @if ($b['subtitulo'])<div class="sub">{{ $b['subtitulo'] }}</div>@endif

                        @if ($b['pases'])
                            <div class="titulo-pases">Pases de categoría ({{ str_starts_with($b['destino'], 'sin ') ? $b['destino'] : 'a ' . $b['destino'] }}):</div>
                            <ul>
                                @foreach ($b['pases'] as $x)
                                    <li>{{ $x['nombre'] }} <span class="club">{{ $x['club'] }}</span>@if (!empty($x['logro'])) <span class="anota">({{ $x['logro'] }})</span>@endif</li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($b['medallas'])
                            <ul class="medallas">
                                @foreach ($b['medallas'] as $a)
                                    <li>
                                        <span class="medalla {{ $clases[$a['puesto']] ?? '' }}">{{ Informe::MEDALLAS[$a['puesto']] ?? $a['puesto'] . 'º' }}</span>
                                        <span>
                                            <span class="nombre">{{ $a['nombre'] }}</span>
                                            @if ($a['otra_categoria'])<span class="anota">({{ $a['otra_categoria'] }})</span>@endif
                                            <span class="club">{{ $a['club'] }}</span>
                                            @if ($a['pase'])
                                                <span class="anota">(y pase de categoría{{ !empty($a['pase']['logro']) ? ' — ' . $a['pase']['logro'] : '' }})</span>
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </section>
        @endforeach

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
