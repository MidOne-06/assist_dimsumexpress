<x-filament-panels::page>
    <form wire:submit="asignar">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-calendar-days">
                Asignar turno
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
