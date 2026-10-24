{{-- Una persona del guion: badges (medalla y/o pase), APELLIDO Nombre, club y categoría si viene acoplada.
     Recibe $a (arquero), $medalla (puesto o null), $pase (fila de pase o null) y $de (código de categoría o null). --}}
@php $clases = [1 => 'oro', 2 => 'plata', 3 => 'bronce']; @endphp
<span class="badges">
    @if ($medalla)<span class="medalla {{ $clases[$medalla] ?? '' }}">{{ App\Medallero\Informe::MEDALLAS[$medalla] ?? $medalla . 'º' }}</span>@endif
    @if ($pase)<span @class(['pase', 'solo-sin-' . ($pase['solo_sin_opcional'] ?? '') => !empty($pase['solo_sin_opcional'])])>Pase ({{ $pase['puntaje'] }}){{ !empty($pase['logro']) ? ' - ' . $pase['logro'] : '' }}</span>@endif
</span>
<span class="nombre"><span class="apellido">{{ mb_strtoupper($a['apellido']) }}</span> {{ $a['nombres'] }}</span>
<span class="club">{{ $a['club'] }}{{ $de ? ' - de ' . $de : '' }}</span>
