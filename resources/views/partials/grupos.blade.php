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
            {{-- opcional: JP con menos de 3 sin eliminatoria; sin pases, el bloque entero depende del selector --}}
            <div @class(['bloque', 'opcional' => $b['opcional'], 'solo-medallas' => $b['opcional'] && !$b['pases']])>
                <h3>{{ $b['nombre'] }}</h3>
                @if ($b['subtitulo'])<div class="sub">{{ $b['subtitulo'] }}</div>@endif
                @if ($b['opcional'])<div class="sub sin-medallas">sin medallas: menos de 3 arqueros y sin eliminatoria (art. 14.4)</div>@endif

                @if ($b['pases'])
                    {{-- con_medalla: en podios opcionales, el medallista se anuncia con la medalla; acá solo si no se entregan --}}
                    <div @class(['pases', 'todos-con-medalla' => !array_filter($b['pases'], fn ($x) => empty($x['con_medalla']))])>
                        <div class="titulo-pases">Pases de categoría ({{ str_starts_with($b['destino'], 'sin ') ? $b['destino'] : 'a ' . $b['destino'] }}):</div>
                        <ul>
                            @foreach ($b['pases'] as $x)
                                <li @class(['con-medalla' => !empty($x['con_medalla'])])>{{ $x['nombre'] }} <span class="club">{{ $x['club'] }}</span>@if (!empty($x['logro'])) <span class="anota">({{ $x['logro'] }})</span>@endif</li>
                            @endforeach
                        </ul>
                    </div>
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
                                        <span @class(['pase', 'solo-sin-opcional' => !empty($a['pase']['solo_sin_opcional'])])>Pase ({{ $a['pase']['puntaje'] }}){{ !empty($a['pase']['logro']) ? ' · ' . $a['pase']['logro'] : '' }}</span>
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
