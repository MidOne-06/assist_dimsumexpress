<x-mobile-operacion :title="'Registrado correctamente'" section="Marcación" layout="operation">
    <section class="mo-status mo-status--{{ $confirmacion['color'] }}">
        <x-dynamic-component :component="$confirmacion['icono']" class="mo-status__icon" />
        <p class="mo-status__eyebrow">{{ $confirmacion['saludo'] }}</p>
        <h1 class="mo-heading">{{ $confirmacion['nombre'] }}</h1>
        <p class="mo-status__event">
            <x-heroicon-s-check-circle />
            <span>{{ $confirmacion['evento'] }}</span>
        </p>
        <div class="mo-status__time">{{ $marcacion->fecha_hora->format('H:i:s') }}</div>
        <p class="mo-status__detail">{{ $marcacion->fecha_hora->translatedFormat('l d \d\e F') }}</p>
    </section>
    <div class="mo-linkbar">
        <a href="{{ route('marcacion.show') }}"><x-heroicon-o-camera />Volver a escanear</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-heroicon-o-arrow-left-on-rectangle />Cerrar sesión</button></form>
    </div>
</x-mobile-operacion>
