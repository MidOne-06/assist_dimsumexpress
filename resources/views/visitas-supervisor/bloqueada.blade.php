<x-mobile-operacion :title="'Visita pendiente'" section="Visitas" layout="operation">
    <section class="mo-status">
        <x-heroicon-o-exclamation-triangle class="mo-status__icon" style="color:var(--app-warning)" />
        <h1 class="mo-heading">Primero registra tu salida</h1>
        <p class="mo-status__detail">Tienes una visita en curso. Escanea el QR de ese local para cerrarla antes de registrar otra.</p>
    </section>
    <div class="mo-callout mo-callout--warning"><x-heroicon-o-map-pin /><span>{{ $visita->sucursal?->nombre ?? 'Local anterior' }}</span></div>
    <div class="mo-actions"><a class="mo-primary-button" href="{{ route('visita-supervisor.esperando') }}"><x-heroicon-o-camera />Volver a escanear</a></div>
    <div class="mo-linkbar"><span></span><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-heroicon-o-arrow-left-on-rectangle />Cerrar sesión</button></form></div>
</x-mobile-operacion>
