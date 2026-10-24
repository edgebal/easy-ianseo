{{-- Bloques del guion agrupados (Jóvenes Promesas / Escuela / Sub, 50+ y Mayores). Recibe $grupos. --}}
@php
    use App\Medallero\Guion;
    use App\Medallero\Informe;
    $clases = [1 => 'oro', 2 => 'plata', 3 => 'bronce'];
@endphp
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
