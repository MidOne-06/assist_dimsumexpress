@php($apariencia = app(\App\Services\AparienciaSistemaService::class))

<x-filament::section class="access-card" compact>
    <div class="access-header">
        <div class="access-brand">
            <img class="access-logo access-logo-light" src="{{ $apariencia->logoUrl() }}" alt="{{ $apariencia->nombre() }}">
            <img class="access-logo access-logo-dark" src="{{ $apariencia->logoOscuroUrl() }}" alt="{{ $apariencia->nombre() }}">
        </div>

    </div>

    @if ($disponible)
        <div class="access-summary">
            <div class="access-summary-icon"><x-heroicon-o-key /></div>
            <div>
                <h1 class="access-title">Establece tu contraseña</h1>
                <p class="access-subtitle">{{ $nombreColaborador }}</p>
            </div>
        </div>

        <form wire:submit="activar" class="access-form">
            {{ $this->form }}

            <ul class="access-password-policy" data-password-policy aria-live="polite">
                @foreach (['length' => '8 caracteres', 'upper' => 'Mayúscula', 'lower' => 'Minúscula', 'number' => 'Número', 'match' => 'Coinciden'] as $rule => $label)
                    <li data-rule="{{ $rule }}">
                        <span class="access-rule-icon" aria-hidden="true">
                            <x-heroicon-o-minus-circle class="access-rule-pending" />
                            <x-heroicon-s-check-circle class="access-rule-valid" />
                        </span>
                        {{ $label }}
                    </li>
                @endforeach
            </ul>

            <x-filament::button type="submit" class="access-submit" wire:loading.attr="disabled" wire:target="activar">
                Continuar
            </x-filament::button>
        </form>

        <p class="access-expiry">Válido hasta {{ $venceA }}</p>
    @else
        <div class="access-unavailable">
            <div class="access-summary-icon"><x-heroicon-o-exclamation-triangle /></div>
            <div>
                <h1 class="access-title">Enlace no disponible</h1>
                <p class="access-subtitle">Solicita un nuevo enlace de acceso.</p>
            </div>
        </div>
    @endif
</x-filament::section>
