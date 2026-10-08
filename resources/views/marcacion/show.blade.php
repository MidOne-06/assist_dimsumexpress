<x-mobile-operacion :title="'Confirmar marcación'" section="Marcación" :appearance="$apariencia" layout="operation">
    <h1 class="mo-heading">QR escaneado correctamente</h1>
    <p class="mo-subheading">{{ $colaborador->nombre_completo }}</p>

    <div class="mo-callout mo-callout--success">
        <x-heroicon-s-check-circle />
        <span>Selecciona una acción.</span>
    </div>

    @if (isset($errors) && $errors->any())
        <div class="mo-callout mo-callout--danger" role="alert">
            <x-heroicon-s-exclamation-triangle />
            <span>@foreach ($errors->all() as $error){{ $error }}@if (! $loop->last)<br>@endif @endforeach</span>
        </div>
    @endif

    <form class="mo-actions" method="POST" action="{{ route('marcacion.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        @foreach ($acciones as $accion)
            <div>
                <button class="mo-action mo-action--{{ $accion['color'] }}" type="submit" name="accion" value="{{ $accion['tipo'] }}" @disabled(! $accion['habilitada'])>
                    <x-dynamic-component :component="$accion['icono']" />
                    <span class="mo-action__meta">{{ $accion['etiqueta'] }}</span>
                </button>
                @if (! $accion['habilitada'] && $accion['motivo'])
                    <span class="mo-action__hint">{{ $accion['motivo'] }}</span>
                @endif
            </div>
        @endforeach
    </form>

    <div class="mo-linkbar">
        <a href="{{ route('marcacion.show') }}"><x-heroicon-o-camera />Escanear otro QR</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-heroicon-o-arrow-left-on-rectangle />Cerrar sesión</button></form>
    </div>
</x-mobile-operacion>
