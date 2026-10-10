<x-mobile-operacion :title="'Mis marcaciones'" section="Marcación" :appearance="$apariencia" layout="operation">
    <section class="mo-day-marks" aria-labelledby="mis-marcaciones-hoy">
        <header class="mo-day-marks__header">
            <h1 id="mis-marcaciones-hoy" class="mo-heading">Mis marcaciones de hoy</h1>
            <p class="mo-subheading">{{ $fecha }}</p>
        </header>

        @if ($marcaciones === [])
            <div class="mo-day-marks__empty" role="status">
                <x-heroicon-o-clock />
                <span>Aún no registraste marcaciones hoy.</span>
            </div>
        @else
            <ol class="mo-day-marks__timeline" aria-label="Marcaciones de hoy">
                @foreach ($marcaciones as $marcacion)
                    <li class="mo-day-marks__item mo-day-marks__item--{{ $marcacion['color'] }}">
                        <span class="mo-day-marks__icon" aria-hidden="true">
                            <x-dynamic-component :component="$marcacion['icono']" />
                        </span>
                        <span class="mo-day-marks__label">{{ $marcacion['etiqueta'] }}</span>
                        <time class="mo-day-marks__time">{{ $marcacion['hora'] }}</time>
                    </li>
                @endforeach
            </ol>
        @endif

        <div class="mo-actions">
            <a class="mo-primary-button" href="{{ route('marcacion.show') }}">
                <x-heroicon-o-camera />
                <span>Volver a marcar</span>
            </a>
        </div>
    </section>
</x-mobile-operacion>
