@extends('layout')

@section('estilos')
.tarjeta {
    max-width: 560px;
    margin: 8vh auto 0;
    background: var(--papel);
    border: 1px solid var(--linea);
    border-radius: 10px;
    padding: 28px;
}
.tarjeta h1 { font-size: 24px; margin-bottom: 6px; }
.tarjeta p { color: var(--suave); margin: 0 0 22px; }
.campo { margin-bottom: 18px; }
.campo label.titulo { display: block; font-weight: 600; margin-bottom: 6px; }
input[type=file] {
    width: 100%;
    padding: 14px;
    border: 2px dashed var(--linea);
    border-radius: 8px;
    background: var(--fondo);
    font: inherit;
}
.pie { color: var(--suave); font-size: 13px; margin-top: 18px; }
@endsection

@section('contenido')
<main class="contenedor">
    <form class="tarjeta" method="POST" action="{{ route('informe') }}" enctype="multipart/form-data">
        @csrf
        <h1>Medallero FATARCO</h1>
        <p>Subí el export del torneo (<b>Torneo → Exportar</b> en IANSEO) y se arma el guion de la entrega de premios, listo para imprimir.</p>

        @error('archivo')
            <div class="error" role="alert">{{ $message }}</div>
        @enderror

        <div class="campo">
            <label class="titulo" for="archivo">Archivo .ianseo</label>
            <input id="archivo" name="archivo" type="file" accept=".ianseo" required>
        </div>

        <button class="boton" type="submit">Generar imprimible</button>

        <div class="pie">El archivo no se guarda: se procesa y se descarta. Máximo {{ $maxMb }} MB.</div>
    </form>
</main>
@endsection
