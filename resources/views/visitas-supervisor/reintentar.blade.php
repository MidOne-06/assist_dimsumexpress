<x-mobile-operacion :title="'Escanea nuevamente'" section="Visitas">
    <section class="mo-status">
        <x-heroicon-o-arrow-path class="mo-status__icon" style="color:var(--app-warning)" />
        <h1 class="mo-heading">Escanea nuevamente</h1>
        <p class="mo-status__detail">{{ $motivo }}</p>
    </section>
    <div class="mo-actions"><a class="mo-primary-button" href="{{ route('visita-supervisor.esperando') }}"><x-heroicon-o-camera />Abrir escáner</a></div>
</x-mobile-operacion>
