<x-mobile-operacion :title="'Confirmar visita'" section="Visitas">
    <h1 class="mo-heading">Marcación de visita</h1>
    <p class="mo-subheading">QR escaneado correctamente</p>
    <p class="mo-subheading">{{ $sucursal->nombre }}@if ($puntoVenta) · {{ $puntoVenta->nombre }}@endif</p>

    <div class="mo-callout mo-callout--success">
        <x-heroicon-s-check-circle />
        <span>Elige la acción que vas a registrar.</span>
    </div>

    <div class="mo-actions">
        <form method="POST" action="{{ route('visita-supervisor.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $qrToken->token }}">
            <input type="hidden" name="accion" value="ingreso">
            <button class="mo-action mo-action--success" type="submit" @disabled($accion !== 'ingreso')>
                <x-heroicon-o-arrow-right-on-rectangle /><span class="mo-action__meta">Registrar ingreso de visita</span>
            </button>
            @if ($accion !== 'ingreso')<span class="mo-action__hint">Hay una visita en curso en este local.</span>@endif
        </form>
        <form method="POST" action="{{ route('visita-supervisor.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $qrToken->token }}">
            <input type="hidden" name="accion" value="salida">
            <button class="mo-action mo-action--danger" type="submit" @disabled($accion !== 'salida')>
                <x-heroicon-o-arrow-left-on-rectangle /><span class="mo-action__meta">Registrar salida de visita</span>
            </button>
            @if ($accion !== 'salida')<span class="mo-action__hint">Primero registra el ingreso de la visita.</span>@endif
        </form>
    </div>

    <div class="mo-linkbar">
        <a href="{{ route('visita-supervisor.esperando') }}"><x-heroicon-o-camera />Escanear otro QR</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-heroicon-o-arrow-left-on-rectangle />Cerrar sesión</button></form>
    </div>
</x-mobile-operacion>
