<x-filament-panels::page>
    <form wire:submit="asignar">
        {{ $this->form }}

        <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
            <x-filament::button type="submit" icon="heroicon-o-calendar-days">
                Asignar turno
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
