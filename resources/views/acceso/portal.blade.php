<x-mobile-operacion :title="'Elige una operación'" section="Acceso" :appearance="$apariencia" layout="operation">
    <h1 class="mo-heading">¿Qué deseas hacer?</h1>
    <p class="mo-subheading">{{ $usuario->name }}</p>

    @if (count($accesos))
        <nav class="mo-actions mo-portal-actions" aria-label="Operaciones disponibles">
            @foreach ($accesos as $acceso)
                <a class="mo-action mo-action--{{ $acceso['color'] }} mo-portal-action" href="{{ $acceso['ruta'] }}">
                    <x-dynamic-component :component="$acceso['icono']" />
                    <span class="mo-action__meta">
                        <span>{{ $acceso['titulo'] }}</span>
                        <small>{{ $acceso['descripcion'] }}</small>
                    </span>
                    <x-heroicon-o-chevron-right class="mo-portal-action__arrow" />
                </a>
            @endforeach
        </nav>
    @else
        <div class="mo-callout mo-callout--warning" role="status">
            <x-heroicon-s-exclamation-triangle />
            <span>Tu cuenta no tiene una operación habilitada. Consulta con administración.</span>
        </div>
    @endif

    <div class="mo-linkbar">
        <span></span>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><x-heroicon-o-arrow-left-on-rectangle />Cerrar sesión</button></form>
    </div>

    <style>
        .mo-portal-action { min-height: 4.4rem; text-decoration: none; }
        .mo-portal-action .mo-action__meta { gap: .2rem; }
        .mo-portal-action small { color: var(--mo-marking-muted); font-size: .75rem; font-weight: 500; line-height: 1.3; }
        .mo-portal-action__arrow { margin-left: auto; color: var(--mo-marking-muted); }
    </style>
</x-mobile-operacion>
