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
    <button
        type="button"
        class="mo-share-button"
        data-marcacion-share
        data-marcacion-name="{{ $confirmacion['nombre'] }}"
        data-marcacion-event="{{ $confirmacion['evento'] }}"
        data-marcacion-time="{{ $marcacion->fecha_hora->format('H:i:s') }}"
        data-marcacion-date="{{ $marcacion->fecha_hora->translatedFormat('l d \d\e F') }}"
        data-marcacion-color="{{ $confirmacion['color'] }}"
        data-marcacion-logo="{{ app(\App\Services\AparienciaSistemaService::class)->logoUrl() }}"
        data-marcacion-background="{{ asset('images/marcacion-dimsum-vertical.png') }}"
    >
        <x-heroicon-o-share />
        <span>Compartir</span>
    </button>
    <p class="mo-share-feedback" data-marcacion-share-feedback hidden aria-live="polite"></p>
    <div class="mo-linkbar">
        <a href="{{ route('marcacion.show') }}"><x-heroicon-o-camera />Volver a escanear</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-heroicon-o-arrow-left-on-rectangle />Cerrar sesión</button></form>
    </div>
    @vite('resources/js/marcacion-compartir.js')
</x-mobile-operacion>
