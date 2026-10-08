<x-mobile-operacion :title="'Marcación de visita'" section="Visitas" layout="operation">
    <h1 class="mo-heading">Marcación de visita</h1>
    <p class="mo-subheading">Escanea el QR del local.</p>

    @include('marcacion.partials.escaner', [
        'rutaQr' => '/visitas-supervisor?token=',
        'textoBoton' => 'Escanear QR de visita',
        'textoContinuar' => 'Ver acciones de visita',
    ])

    <div class="mo-linkbar">
        <span></span>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-heroicon-o-arrow-left-on-rectangle />Cerrar sesión</button></form>
    </div>
</x-mobile-operacion>
