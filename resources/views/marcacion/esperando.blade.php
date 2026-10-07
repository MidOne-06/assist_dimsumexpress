<x-mobile-operacion :title="'Marcación'" section="Marcación" :appearance="$apariencia">
    <h1 class="mo-heading">Hola, {{ $colaborador->nombre_completo }}</h1>
    <p class="mo-subheading">Escanea el QR</p>

    @include('marcacion.partials.escaner', [
        'mostrarBoton' => true,
        'validarAntesDeContinuar' => true,
        'textoBoton' => 'Escanear QR',
        'textoContinuar' => 'Elegir marcación',
    ])

    <div class="mo-linkbar">
        <a href="{{ route('horario.show') }}"><x-heroicon-o-calendar-days />Mi horario</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-heroicon-o-arrow-left-on-rectangle />Cerrar sesión</button></form>
    </div>
</x-mobile-operacion>
