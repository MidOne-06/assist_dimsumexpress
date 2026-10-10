@php
    $nombre = trim((string) $colaborador->nombre_completo);
    $primerNombre = explode(' ', preg_replace('/\s+/', ' ', $nombre))[0] ?: $nombre;
@endphp

<x-mobile-operacion :title="'Marcación'" section="Marcación" :appearance="$apariencia" layout="marking-home">
    <section class="mo-marking-welcome" aria-labelledby="marcacion-saludo">
        <p class="mo-marking-greeting">Hola,</p>
        <h1 id="marcacion-saludo" class="mo-marking-name">{{ $primerNombre }}</h1>

        @include('marcacion.partials.escaner', [
            'mostrarBoton' => true,
            'validarAntesDeContinuar' => true,
            'textoBoton' => 'Escanear QR',
            'textoContinuar' => 'Elegir marcación',
        ])
    </section>

    <nav class="mo-marking-quick-actions" aria-label="Acciones de marcación">
        <a class="mo-marking-quick-action" href="{{ route('marcaciones-hoy.show') }}">
            <span class="mo-marking-quick-action__icon"><x-heroicon-o-clock /></span>
            <span>Mi marcación</span>
            <x-heroicon-o-chevron-right />
        </a>
        <form class="mo-marking-quick-action mo-marking-quick-action--logout" method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">
                <span class="mo-marking-quick-action__icon"><x-heroicon-o-arrow-left-on-rectangle /></span>
                <span>Cerrar sesión</span>
                <x-heroicon-o-chevron-right />
            </button>
        </form>
    </nav>
</x-mobile-operacion>
