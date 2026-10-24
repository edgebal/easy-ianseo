{{-- Bloques del guion agrupados (Jóvenes Promesas / Escuela / Sub, 50+ y Mayores). Recibe $grupos y $prefijo (para los id). --}}
@php
    use App\Medallero\Guion;
@endphp
@foreach ($grupos as $g => $bloques)
    <section class="grupo" id="{{ $prefijo }}-g{{ $g }}">
        <h2>{{ Guion::GRUPOS[$g] }}</h2>
        @foreach ($bloques as $b)
            {{-- opcional (Guion::OPCIONALES): sus medallas dependen del selector; sin pases, el bloque entero --}}
            <div @class(['bloque', 'opcional-' . $b['opcional'] => $b['opcional'], 'solo-medallas' => $b['opcional'] && !$b['pases']])>
                <h3>{{ $b['nombre'] }}</h3>
                @if ($b['subtitulo'])<div class="sub">{{ $b['subtitulo'] }}</div>@endif
                @if ($b['opcional'])<div class="sub sin-medallas">{{ $b['opcional'] == 'jp' && $b['acoplada']
                    ? 'sin podio propio: en JP las categorías acopladas se premian solo en la eliminatoria (arts. 14.3 y 14.4)'
                    : Guion::OPCIONALES[$b['opcional']]['nota'] }}</div>@endif

                @if ($b['pases'])
                    {{-- con_medalla: en podios opcionales, el medallista se anuncia con la medalla; acá solo si no se entregan --}}
                    <div @class(['pases', 'todos-con-medalla' => !array_filter($b['pases'], fn ($x) => empty($x['con_medalla']))])>
                        <div class="destino">Pase de categoría {{ str_starts_with($b['destino'], 'sin ') ? '(' . $b['destino'] . ')' : 'a ' . $b['destino'] }}</div>
                        <ul class="filas">
                            @foreach ($b['pases'] as $x)
                                <li @class(['fila', 'con-medalla' => !empty($x['con_medalla'])])>
                                    @include('partials.fila', ['a' => $x, 'medalla' => null, 'pase' => $x, 'de' => null])
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($b['medallas'])
                    <ul class="filas medallas">
                        @foreach ($b['medallas'] as $a)
                            <li class="fila">
                                @include('partials.fila', ['a' => $a, 'medalla' => $a['puesto'], 'pase' => $a['pase'], 'de' => $a['otra_categoria']])
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </section>
@endforeach
