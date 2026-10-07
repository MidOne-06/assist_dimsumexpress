<x-mobile-operacion :title="'QR vencido'" section="Visitas">
    <section class="mo-status">
        <x-heroicon-s-exclamation-triangle class="mo-status__icon" style="color:var(--app-warning)" />
        <h1 class="mo-heading">Código QR vencido</h1>
        <p class="mo-status__detail">Vuelve a escanear el código que muestra la estación.</p>
    </section>
    <div class="mo-actions"><a class="mo-primary-button" href="{{ route('visita-supervisor.esperando') }}"><x-heroicon-o-camera />Abrir escáner</a></div>
</x-mobile-operacion>
