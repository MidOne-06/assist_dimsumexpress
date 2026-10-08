<x-mobile-operacion :title="'No se pudo marcar'" section="Marcación" layout="operation">
    <section class="mo-status">
        <x-heroicon-s-exclamation-triangle class="mo-status__icon" style="color:var(--app-danger)" />
        <h1 class="mo-heading">No se pudo registrar</h1>
        <p class="mo-status__detail">{{ $mensaje }}</p>
    </section>
    <div class="mo-linkbar">
        <a href="{{ route('marcacion.show') }}"><x-heroicon-o-camera />Volver a escanear</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-heroicon-o-arrow-left-on-rectangle />Cerrar sesión</button></form>
    </div>
</x-mobile-operacion>
