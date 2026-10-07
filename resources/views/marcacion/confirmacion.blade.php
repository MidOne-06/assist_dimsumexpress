<x-mobile-operacion :title="'Registrado correctamente'" section="Marcación">
    <section class="mo-status">
        <x-heroicon-s-check-circle class="mo-status__icon" style="color:var(--app-success)" />
        <h1 class="mo-heading">Registrado correctamente</h1>
        <div class="mo-status__time">{{ $marcacion->fecha_hora->format('H:i:s') }}</div>
        <p class="mo-status__detail">{{ $marcacion->fecha_hora->translatedFormat('l d \d\e F') }}</p>
    </section>
    <div class="mo-linkbar">
        <a href="{{ route('marcacion.show') }}"><x-heroicon-o-camera />Volver a escanear</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-heroicon-o-arrow-left-on-rectangle />Cerrar sesión</button></form>
    </div>
</x-mobile-operacion>
