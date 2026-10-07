<x-mobile-operacion :title="'Visita registrada'" section="Visitas">
    <section class="mo-status">
        <x-heroicon-s-check-circle class="mo-status__icon" style="color:var(--app-success)" />
        <h1 class="mo-heading">{{ $accion === 'salida' ? 'Salida registrada' : 'Ingreso registrado' }}</h1>
        <p class="mo-status__detail">{{ $sucursal->nombre }}@if ($puntoVenta) · {{ $puntoVenta->nombre }}@endif</p>
        <p class="mo-status__time">{{ ($accion === 'salida' ? $visita->salida_en : $visita->ingreso_en)->format('H:i') }}</p>
    </section>
    <div class="mo-linkbar">
        <a href="{{ route('visita-supervisor.esperando') }}"><x-heroicon-o-camera />Escanear otro QR</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-heroicon-o-arrow-left-on-rectangle />Cerrar sesión</button></form>
    </div>
</x-mobile-operacion>
